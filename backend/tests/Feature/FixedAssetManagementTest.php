<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Models\Asset;
use App\Models\AssetDepreciationSchedule;
use App\Models\Booking;
use App\Models\JournalEntry;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Asset\AssetDepreciationService;
use App\Services\Asset\AssetService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FixedAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $tenant;
    protected Property $propertyA;
    protected Property $propertyB;
    protected Room $roomA1;
    protected Room $roomA2;
    protected Room $roomB1;
    protected AssetService $assetService;
    protected AssetDepreciationService $depreciationService;
    protected ChartOfAccountService $coaService;
    protected JournalPostingService $journalPostingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assetService = app(AssetService::class);
        $this->depreciationService = app(AssetDepreciationService::class);
        $this->coaService = app(ChartOfAccountService::class);
        $this->journalPostingService = app(JournalPostingService::class);

        // Users
        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'is_active' => true]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'is_active' => true]);
        $this->tenant = User::factory()->create(['role' => Role::TENANT, 'is_active' => true]);

        // Properties
        $this->propertyA = Property::create([
            'code' => 'PROP-AST-A',
            'name' => 'Kos Permata Hijau',
            'type' => 'kos',
            'address' => 'Jl. Permata Hijau No. 1',
            'city' => 'Jakarta Selatan',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-AST-B',
            'name' => 'Kos Dago Asri',
            'type' => 'kos',
            'address' => 'Jl. Dago No. 15',
            'city' => 'Bandung',
            'is_active' => true,
        ]);

        $this->manager->properties()->attach($this->propertyA->id, ['role' => 'manager']);

        // Inisialisasi Akun Standar COA untuk kedua properti
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyB->id);

        $roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Standard Room',
            'base_price_monthly' => 1500000,
        ]);

        $roomTypeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Deluxe Room',
            'base_price_monthly' => 2000000,
        ]);

        $this->roomA1 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        $this->roomA2 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $roomTypeA->id,
            'room_number' => '102',
            'floor' => 1,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        $this->roomB1 = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $roomTypeB->id,
            'room_number' => '201',
            'floor' => 2,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);
    }

    public function test_asset_registration_and_code_generation(): void
    {
        // Manager mendaftarkan AC Daikin di kamar 101 Properti A
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'name' => 'AC Daikin 1 PK Inverter',
            'category' => 'electronics',
            'classification' => 'fixed_asset',
            'purchase_cost' => 4500000,
            'salvage_value' => 500000,
            'useful_life_months' => 48,
            'acquisition_date' => '2026-01-10',
            'serial_number' => 'DAIKIN-998822',
            'vendor_name' => 'Toko Sejuk Abadi',
        ]);

        $this->assertNotNull($asset->id);
        $this->assertStringStartsWith("AST-P{$this->propertyA->id}-", $asset->asset_code);
        $this->assertEquals(4500000, $asset->purchase_cost);
        $this->assertEquals(4500000, $asset->current_book_value);
        $this->assertEquals(0, $asset->accumulated_depreciation);

        // Pastikan histori lokasi awal tercatat
        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->id,
            'to_room_id' => $this->roomA1->id,
            'transferred_by' => $this->manager->id,
        ]);

        // Coba daftarkan di Properti B oleh manager yang tidak punya akses -> Ditolak
        $this->expectException(DomainException::class);
        $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyB->id,
            'name' => 'Kulkas Sharp',
            'category' => 'electronics',
            'purchase_cost' => 2500000,
        ]);
    }

    public function test_asset_location_transfer_records_complete_history(): void
    {
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'name' => 'Meja Belajar Kayu Jati',
            'category' => 'furniture',
            'purchase_cost' => 1200000,
        ]);

        $this->assertEquals($this->roomA1->id, $asset->room_id);

        // 1. Pindahkan dari Kamar 101 ke Kamar 102
        $transferredAsset = $this->assetService->transferLocation($asset, $this->manager, [
            'to_location_type' => 'room',
            'to_room_id' => $this->roomA2->id,
            'reason' => 'Permintaan penggantian meja oleh penyewa Kamar 102.',
        ]);

        $this->assertEquals($this->roomA2->id, $transferredAsset->room_id);

        $this->assertDatabaseHas('asset_location_histories', [
            'asset_id' => $asset->id,
            'from_room_id' => $this->roomA1->id,
            'to_room_id' => $this->roomA2->id,
            'reason' => 'Permintaan penggantian meja oleh penyewa Kamar 102.',
        ]);

        // 2. Coba pindahkan ke kamar Properti B -> Harus ditolak
        try {
            $this->assetService->transferLocation($asset, $this->manager, [
                'to_location_type' => 'room',
                'to_room_id' => $this->roomB1->id,
                'reason' => 'Pindah cabang',
            ]);
            $this->fail('Seharusnya gagal memindahkan aset ke kamar cabang berbeda.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('cabang yang sama', $e->getMessage());
        }
    }

    public function test_straight_line_depreciation_preview_and_monthly_calculation(): void
    {
        // Aset: Biaya 12.000.000, Residu 0, Masa 12 Bulan -> Depresiasi 1.000.000/bln
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'name' => 'Mesin Cuci Laundry Koin',
            'category' => 'electronics',
            'classification' => 'fixed_asset',
            'purchase_cost' => 12000000,
            'salvage_value' => 0,
            'useful_life_months' => 12,
            'depreciation_method' => 'straight_line',
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
        ]);

        $this->assertEquals(1000000.00, $asset->calculateMonthlyDepreciation());

        $preview = $this->depreciationService->generateDepreciationPreview($asset);
        $this->assertCount(12, $preview);

        // Periksa periode pertama
        $first = $preview[0];
        $this->assertEquals(1000000.00, $first['depreciation_amount']);
        $this->assertEquals(0.00, $first['accumulated_before']);
        $this->assertEquals(1000000.00, $first['accumulated_after']);
        $this->assertEquals(12000000.00, $first['book_value_beginning']);
        $this->assertEquals(11000000.00, $first['book_value_ending']);

        // Periksa periode terakhir
        $last = $preview[11];
        $this->assertEquals(1000000.00, $last['depreciation_amount']);
        $this->assertEquals(12000000.00, $last['accumulated_after']);
        $this->assertEquals(0.00, $last['book_value_ending']);
    }

    public function test_depreciation_respects_salvage_value_and_stops_when_fully_depreciated(): void
    {
        // Biaya 10.000.000, Residu 2.000.000, Masa 4 Bulan -> Depr: (10M - 2M) / 4 = 2.000.000/bln
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'name' => 'Pompa Air Booster Grundfos',
            'category' => 'building_infrastructure',
            'classification' => 'fixed_asset',
            'purchase_cost' => 10000000,
            'salvage_value' => 2000000,
            'useful_life_months' => 4,
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
        ]);

        $this->assertEquals(2000000.00, $asset->calculateMonthlyDepreciation());

        $preview = $this->depreciationService->generateDepreciationPreview($asset);
        $this->assertCount(4, $preview);
        $last = end($preview);
        $this->assertEquals(2000000.00, $last['book_value_ending']); // Sama dengan nilai residu!
    }

    public function test_single_depreciation_posting_creates_balanced_journal_and_is_idempotent(): void
    {
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'name' => 'Smart TV Samsung 43 Inch',
            'category' => 'electronics',
            'classification' => 'fixed_asset',
            'purchase_cost' => 6000000,
            'salvage_value' => 0,
            'useful_life_months' => 12,
            'acquisition_date' => '2026-01-01',
            'in_service_date' => '2026-01-01',
        ]);

        // 1. Post depresiasi periode 2026 Bulan 1 (Januari)
        $schedule = $this->depreciationService->postSingleDepreciation($asset, 2026, 1, $this->manager);

        $this->assertEquals('posted', $schedule->status);
        $this->assertEquals(500000.00, $schedule->depreciation_amount);
        $this->assertNotNull($schedule->journal_entry_id);

        // Verifikasi Jurnal Umum
        $journal = JournalEntry::with('lines.account')->findOrFail($schedule->journal_entry_id);
        $this->assertEquals('posted', $journal->status->value);
        $this->assertEquals(500000.00, (float) $journal->total_amount);

        // Verifikasi Baris Jurnal Seimbang (Debit 5201 = Kredit 1391)
        $lines = $journal->lines;
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit', '>', 0);
        $creditLine = $lines->firstWhere('credit', '>', 0);

        $this->assertEquals('5201', $debitLine->account->code); // Beban Penyusutan
        $this->assertEquals(500000.00, (float) $debitLine->debit);

        $this->assertEquals('1391', $creditLine->account->code); // Akumulasi Penyusutan
        $this->assertEquals(500000.00, (float) $creditLine->credit);

        // Verifikasi Nilai Buku Aset Terupdate
        $this->assertEquals(500000.00, $asset->fresh()->accumulated_depreciation);
        $this->assertEquals(5500000.00, $asset->fresh()->current_book_value);

        // 2. IDEMPOTENCY TEST: Panggil kembali untuk periode yang sama
        $secondCallSchedule = $this->depreciationService->postSingleDepreciation($asset, 2026, 1, $this->manager);

        $this->assertEquals($schedule->id, $secondCallSchedule->id);
        $this->assertEquals($schedule->journal_entry_id, $secondCallSchedule->journal_entry_id);

        // Pastikan tidak ada jurnal ganda yang tercipta
        $this->assertEquals(1, JournalEntry::where('source_type', 'asset_depreciation')->where('source_id', $asset->id)->count());
        $this->assertEquals(500000.00, $asset->fresh()->accumulated_depreciation);
    }

    public function test_batch_depreciation_posting_and_dry_run(): void
    {
        // Daftarkan 2 aset pada Properti A
        $asset1 = $this->assetService->createAsset($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'name' => 'Router Mikrotik Cloud Router',
            'category' => 'electronics',
            'classification' => 'fixed_asset',
            'purchase_cost' => 3600000,
            'useful_life_months' => 12, // 300.000 / bln
        ]);

        $asset2 = $this->assetService->createAsset($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'name' => 'Set Sofa Lobi Tamu',
            'category' => 'furniture',
            'classification' => 'fixed_asset',
            'purchase_cost' => 4800000,
            'useful_life_months' => 12, // 400.000 / bln
        ]);

        // 1. Dry Run (Simulasi)
        $dryResult = $this->depreciationService->batchPostDepreciation($this->propertyA->id, 2026, 10, $this->superAdmin, true);
        $this->assertTrue($dryResult['dry_run']);
        $this->assertEquals(2, $dryResult['eligible']);
        $this->assertEquals(0, $dryResult['posted']);
        $this->assertEquals(700000.00, $dryResult['total_amount']);
        $this->assertEquals(0, AssetDepreciationSchedule::count());

        // 2. Real Batch Posting
        $realResult = $this->depreciationService->batchPostDepreciation($this->propertyA->id, 2026, 10, $this->superAdmin, false);
        $this->assertFalse($realResult['dry_run']);
        $this->assertEquals(2, $realResult['posted']);
        $this->assertEquals(700000.00, $realResult['total_amount']);
        $this->assertEquals(2, AssetDepreciationSchedule::where('status', 'posted')->count());
    }

    public function test_asset_disposal_calculates_gain_and_posts_balanced_journal(): void
    {
        // Beli motor operasional seharga 15.000.000
        $motor = $this->assetService->createAsset($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'name' => 'Honda Beat Operasional',
            'category' => 'vehicles',
            'classification' => 'fixed_asset',
            'purchase_cost' => 15000000,
            'useful_life_months' => 30, // 500.000 / bln
        ]);

        // Simulasikan sudah disusutkan 10 bulan (Akumulasi: 5.000.000, Nilai Buku: 10.000.000)
        $motor->accumulated_depreciation = 5000000;
        $motor->current_book_value = 10000000;
        $motor->save();

        // Jual motor seharga 12.000.000 (Ada Laba Pelepasan sebesar 2.000.000)
        $disposed = $this->assetService->disposeAsset($motor, $this->superAdmin, [
            'disposal_date' => '2026-10-18',
            'disposal_price' => 12000000,
            'disposal_cost' => 0,
            'disposal_notes' => 'Dijual karena peremajaan unit operasional.',
            'post_journal' => true,
        ]);

        $this->assertEquals('disposed', $disposed->lifecycle_status);
        $this->assertEquals(0.00, $disposed->current_book_value);
        $this->assertNotNull($disposed->disposal_journal_id);

        // Verifikasi Jurnal Pelepasan Berpasangan
        $journal = JournalEntry::with('lines.account')->findOrFail($disposed->disposal_journal_id);
        $this->assertEquals('posted', $journal->status->value);

        // Debit: Kas 12M + Akumulasi Penyusutan 5M = 17M
        // Kredit: Harga Perolehan Motor 15M + Laba Pelepasan 2M = 17M
        $totalDebit = round((float) $journal->lines->sum('debit'), 2);
        $totalCredit = round((float) $journal->lines->sum('credit'), 2);

        $this->assertEquals(17000000.00, $totalDebit);
        $this->assertEquals(17000000.00, $totalCredit);
        $this->assertEquals($totalDebit, $totalCredit);

        // Verifikasi Akun Laba Pelepasan 4201 terisi
        $gainLine = $journal->lines->firstWhere('account.code', '4201');
        $this->assertNotNull($gainLine);
        $this->assertEquals(2000000.00, (float) $gainLine->credit);
    }

    public function test_maintenance_request_linked_to_asset_and_capitalization(): void
    {
        $asset = $this->assetService->createAsset($this->manager, [
            'property_id' => $this->propertyA->id,
            'name' => 'Genset Silent 5000 Watt',
            'category' => 'building_infrastructure',
            'purchase_cost' => 18000000,
        ]);

        $workOrder = MaintenanceRequest::create([
            'ticket_number' => 'MNT-GENSET-01',
            'property_id' => $this->propertyA->id,
            'asset_id' => $asset->id,
            'reporter_id' => $this->manager->id,
            'title' => 'Penggantian Dinamo Genset & Overhaul',
            'description' => 'Overhaul berkala genset silent gedung utama.',
            'category' => 'other',
            'labor_cost' => 500000,
            'material_cost' => 2500000,
            'total_cost' => 3000000,
            'is_capitalized' => true,
            'capitalized_amount' => 3000000,
            'capitalized_at' => now(),
            'capitalized_by' => $this->manager->id,
            'status' => 'completed',
        ]);

        $this->assertTrue($workOrder->is_capitalized);
        $this->assertEquals(3000000.00, (float) $workOrder->capitalized_amount);
        $this->assertEquals($asset->id, $workOrder->asset->id);
    }

    public function test_asset_api_security_and_tenant_isolation(): void
    {
        $asset = $this->assetService->createAsset($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'name' => 'Kasur Springbed Queen Size',
            'category' => 'furniture',
            'purchase_cost' => 3500000,
            'salvage_value' => 500000,
        ]);

        // 1. Tenant belum punya booking aktif di roomA1 -> Mengakses API aset kamar menghasilkan respon kosong / 403
        $response = $this->actingAs($this->tenant, 'sanctum')->getJson('/api/v1/assets/' . $asset->id);
        $response->assertStatus(403);

        // 2. Tenant menyewa RoomA1 dengan booking aktif
        Booking::create([
            'booking_code' => 'BKG-TNT-A1',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'status' => 'active',
        ]);

        // Sekarang tenant dapat melihat aset kamar tapi data finansial (purchase_cost, book_value) disanitasi
        $response = $this->actingAs($this->tenant, 'sanctum')->getJson('/api/v1/assets/' . $asset->id);
        $response->assertStatus(200);
        $response->assertJsonPath('data.name', 'Kasur Springbed Queen Size');
        $response->assertJsonMissing(['purchase_cost', 'salvage_value', 'current_book_value']);

        // 3. Tenant dapat melaporkan keluhan/kerusakan aset langsung
        $reportResponse = $this->actingAs($this->tenant, 'sanctum')->postJson("/api/v1/assets/{$asset->id}/report-issue", [
            'title' => 'Pegas Kasur Bunyi & Cekung',
            'description' => 'Pegas bagian tengah berbunyi derit saat ditiduri.',
            'priority' => 'normal',
        ]);

        $reportResponse->assertStatus(200);
        $this->assertDatabaseHas('maintenance_requests', [
            'asset_id' => $asset->id,
            'reporter_id' => $this->tenant->id,
            'title' => 'Pegas Kasur Bunyi & Cekung',
        ]);

        // Status aset berubah menjadi in_maintenance
        $this->assertEquals('in_maintenance', $asset->fresh()->lifecycle_status);
    }

    public function test_asset_web_dashboard_index_and_show_renders_properly_for_manager(): void
    {
        // 1. Web Index
        $response = $this->actingAs($this->manager)->get(route('dashboard.assets.index'));
        $response->assertStatus(200);
        $response->assertSee('Manajemen Aset Tetap');

        // 2. Web Store
        $storeResponse = $this->actingAs($this->manager)->post(route('dashboard.assets.store'), [
            'property_id' => $this->propertyA->id,
            'name' => 'Smart TV 43 Inch LG',
            'category' => 'electrical_electronic',
            'classification' => 'fixed_asset',
            'location_type' => 'room',
            'room_id' => $this->roomA1->id,
            'acquisition_date' => '2026-01-10',
            'purchase_cost' => 4500000,
            'salvage_value' => 500000,
            'useful_life_months' => 36,
            'condition' => 'good',
        ]);

        $storeResponse->assertRedirect();
        $storeResponse->assertSessionHas('success');

        $asset = Asset::where('name', 'Smart TV 43 Inch LG')->firstOrFail();

        // 3. Web Show
        $showResponse = $this->actingAs($this->manager)->get(route('dashboard.assets.show', $asset->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee($asset->asset_code);
        $showResponse->assertSee('Smart TV 43 Inch LG');
    }
}

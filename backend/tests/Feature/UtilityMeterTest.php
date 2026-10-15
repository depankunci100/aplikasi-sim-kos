<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Enums\UtilityReadingStatus;
use App\Enums\UtilityType;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Models\UtilityMeter;
use App\Models\UtilityReading;
use App\Models\UtilityTariff;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UtilityMeterTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected User $staff;
    protected User $tenant;
    protected User $otherTenant;
    protected Property $propertyA;
    protected Property $propertyB;
    protected Room $room101;
    protected Room $room201;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'name' => 'Super Admin']);
        $this->owner = User::factory()->create(['role' => Role::OWNER, 'name' => 'Owner Kos']);
        $this->staff = User::factory()->create(['role' => Role::STAFF, 'name' => 'Staff Kos']);
        $this->tenant = User::factory()->create(['role' => Role::TENANT, 'name' => 'Penyewa Kamar 101']);
        $this->otherTenant = User::factory()->create(['role' => Role::TENANT, 'name' => 'Penyewa Lain']);

        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Permata A',
            'type' => 'kos',
            'address' => 'Jl. Mawar No. 10',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Kos Permata B',
            'type' => 'kos',
            'address' => 'Jl. Melati No. 20',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        // Hubungkan owner dan staff ke Property A
        DB::table('property_users')->insert([
            ['property_id' => $this->propertyA->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->staff->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $typeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe',
            'base_price_monthly' => 1500000,
        ]);

        $this->room101 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $typeA->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->room201 = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $typeA->id,
            'room_number' => '201',
            'floor' => 2,
            'status' => 'available',
            'is_active' => true,
        ]);

        // Aktifkan booking untuk tenant di room 101
        Booking::create([
            'booking_code' => 'BK-101-UTL',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'rate_amount' => 1500000,
            'total_amount' => 1500000,
            'status' => 'confirmed',
        ]);
    }

    public function test_owner_can_create_utility_meter_for_property(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'PLN-101-A',
            'meter_type' => 'individual',
            'unit' => 'kWh',
            'initial_reading' => 1250.00,
            'installed_at' => now()->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meter_number', 'PLN-101-A')
            ->assertJsonPath('data.current_reading', '1250.00');

        $this->assertDatabaseHas('utility_meters', [
            'meter_number' => 'PLN-101-A',
            'initial_reading' => '1250.00',
            'current_reading' => '1250.00',
        ]);
    }

    public function test_tenant_cannot_create_utility_meter(): void
    {
        Sanctum::actingAs($this->tenant);

        $response = $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'PLN-ILLEGAL',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cross_property_isolation_on_meter_creation(): void
    {
        Sanctum::actingAs($this->owner);

        // Owner Property A mencoba membuat meter di Property B
        $response = $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyB->id,
            'room_id' => $this->room201->id,
            'utility_type' => 'water',
            'meter_number' => 'PDAM-CROSS-B',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_active_meter_for_same_room_and_utility_is_rejected(): void
    {
        Sanctum::actingAs($this->owner);

        // Meter pertama
        $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'PLN-FIRST',
            'initial_reading' => 100,
        ])->assertStatus(201);

        // Meter kedua di kamar yang sama untuk listrik aktif
        $duplicate = $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'PLN-SECOND',
            'initial_reading' => 200,
        ]);

        $duplicate->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_tariff_creation_and_effective_date_lookup(): void
    {
        Sanctum::actingAs($this->owner);

        // Pasang tarif lama
        UtilityTariff::create([
            'property_id' => $this->propertyA->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'rate_per_unit' => 1500.00,
            'unit' => 'kWh',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-06-30',
            'is_active' => true,
        ]);

        // Pasang tarif baru
        UtilityTariff::create([
            'property_id' => $this->propertyA->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'rate_per_unit' => 1750.00,
            'unit' => 'kWh',
            'effective_from' => '2026-07-01',
            'effective_to' => null,
            'is_active' => true,
        ]);

        // Cek tarif untuk tanggal Mei 2026
        $resPast = $this->getJson("/api/v1/utilities/tariffs/effective?property_id={$this->propertyA->id}&utility_type=electricity&date=2026-05-15");
        $resPast->assertStatus(200)
            ->assertJsonPath('data.rate_per_unit', '1500.00');

        // Cek tarif untuk tanggal Agustus 2026
        $resNow = $this->getJson("/api/v1/utilities/tariffs/effective?property_id={$this->propertyA->id}&utility_type=electricity&date=2026-08-01");
        $resNow->assertStatus(200)
            ->assertJsonPath('data.rate_per_unit', '1750.00');
    }

    public function test_first_reading_uses_meter_initial_reading_as_baseline(): void
    {
        Sanctum::actingAs($this->owner);

        // Buat meter dengan baseline awal 500 kWh (bawaan pabrik/sebelum disewa)
        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-BASELINE-01',
            'unit' => 'kWh',
            'initial_reading' => 500.00,
            'current_reading' => 500.00,
            'is_active' => true,
        ]);

        // Pasang tarif
        UtilityTariff::create([
            'property_id' => $this->propertyA->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'rate_per_unit' => 2000.00,
            'unit' => 'kWh',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        // Staf mencatat pembacaan pertama bulan ini sebesar 580 kWh
        Sanctum::actingAs($this->staff);

        $response = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 580.00,
            'reading_date' => now()->toDateString(),
            'notes' => 'Pembacaan meter pertama',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.previous_meter', '500.00')
            ->assertJsonPath('data.current_meter', '580.00')
            ->assertJsonPath('data.usage', '80.00') // 580 - 500 = 80 kWh, BUKAN 580 kWh!
            ->assertJsonPath('data.total_cost', '160000.00'); // 80 * 2000 = 160.000
    }

    public function test_reading_lower_than_previous_is_rejected(): void
    {
        Sanctum::actingAs($this->staff);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-LOWER-TEST',
            'unit' => 'kWh',
            'initial_reading' => 300.00,
            'current_reading' => 300.00,
            'is_active' => true,
        ]);

        // Coba input angka lebih kecil dari 300.00
        $response = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 250.00, // Error: mundurnya pembacaan
            'reading_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_reading_review_approval_and_rejection_with_reason(): void
    {
        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::WATER,
            'meter_number' => 'PDAM-REV-01',
            'unit' => 'm3',
            'initial_reading' => 10.00,
            'current_reading' => 10.00,
            'is_active' => true,
        ]);

        // Staf mencatat reading pending review
        Sanctum::actingAs($this->staff);
        $res = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 25.00,
            'reading_date' => now()->toDateString(),
            'status' => 'pending_review',
        ]);
        $readingId = $res->json('data.id');

        // Owner menolak tanpa alasan -> harus error 422
        Sanctum::actingAs($this->owner);
        $rejectNoReason = $this->postJson("/api/v1/utilities/readings/{$readingId}/review", [
            'status' => 'rejected',
        ]);
        $rejectNoReason->assertStatus(422);

        // Owner menolak dengan alasan sah
        $rejectWithReason = $this->postJson("/api/v1/utilities/readings/{$readingId}/review", [
            'status' => 'rejected',
            'rejection_reason' => 'Foto bukti tidak terbaca jelas.',
        ]);
        $rejectWithReason->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Foto bukti tidak terbaca jelas.');

        // Sekarang setujui
        $approve = $this->postJson("/api/v1/utilities/readings/{$readingId}/review", [
            'status' => 'approved',
        ]);
        $approve->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Pastikan current_reading di meter ter-update
        $meter->refresh();
        $this->assertEquals('25.00', $meter->current_reading);
    }

    public function test_reading_correction_stores_strict_audit_trail(): void
    {
        Sanctum::actingAs($this->owner);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-CORR-01',
            'unit' => 'kWh',
            'initial_reading' => 100.00,
            'current_reading' => 100.00,
            'is_active' => true,
        ]);

        UtilityTariff::create([
            'property_id' => $this->propertyA->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'rate_per_unit' => 1000.00,
            'unit' => 'kWh',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $reading = UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100.00,
            'current_meter' => 150.00,
            'usage' => 50.00,
            'rate_per_unit' => 1000.00,
            'total_cost' => 50000.00,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
            'recorded_by' => $this->staff->id,
        ]);

        // Lakukan koreksi nilai ke 160.00
        $corrResponse = $this->postJson("/api/v1/utilities/readings/{$reading->id}/correct", [
            'current_meter' => 160.00,
            'reason' => 'Koreksi salah baca angka meteran display analog.',
        ]);

        $corrResponse->assertStatus(200)
            ->assertJsonPath('data.current_meter', '160.00')
            ->assertJsonPath('data.usage', '60.00')
            ->assertJsonPath('data.total_cost', '60000.00');

        $this->assertDatabaseHas('utility_reading_logs', [
            'utility_reading_id' => $reading->id,
            'action' => 'corrected',
        ]);
    }

    public function test_private_photo_upload_and_authorized_streaming(): void
    {
        Sanctum::actingAs($this->staff);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-PHOTO-01',
            'unit' => 'kWh',
            'initial_reading' => 100.00,
            'current_reading' => 100.00,
            'is_active' => true,
        ]);

        $fakePhoto = UploadedFile::fake()->image('meter_proof.jpg', 640, 480);

        $response = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 120.00,
            'reading_date' => now()->toDateString(),
            'proof_photo' => $fakePhoto,
        ]);

        $response->assertStatus(201);
        $readingId = $response->json('data.id');
        $this->assertNotNull($response->json('data.proof_photo'));

        // 1. Staff Property A dapat mengakses foto bukti
        $streamStaff = $this->get("/api/v1/utilities/readings/{$readingId}/proof-photo");
        $streamStaff->assertStatus(200);

        // 2. Tenant pemilik kamar 101 dapat mengakses foto bukti
        Sanctum::actingAs($this->tenant);
        $streamTenant = $this->get("/api/v1/utilities/readings/{$readingId}/proof-photo");
        $streamTenant->assertStatus(200);

        // 3. Tenant kamar lain TIDAK dapat mengakses foto bukti (403)
        Sanctum::actingAs($this->otherTenant);
        $streamOther = $this->get("/api/v1/utilities/readings/{$readingId}/proof-photo");
        $streamOther->assertStatus(403);
    }

    public function test_utility_invoice_generation_is_idempotent_and_prevents_duplicate_billing(): void
    {
        Sanctum::actingAs($this->owner);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-INV-01',
            'unit' => 'kWh',
            'initial_reading' => 100.00,
            'current_reading' => 100.00,
            'is_active' => true,
        ]);

        $reading = UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'booking_id' => Booking::first()->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100.00,
            'current_meter' => 200.00,
            'usage' => 100.00,
            'rate_per_unit' => 1500.00,
            'total_cost' => 150000.00,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED, // Disetujui
            'recorded_by' => $this->staff->id,
            'is_billed' => false,
        ]);

        // Penerbitan Pertama
        $firstGen = $this->postJson("/api/v1/utilities/readings/{$reading->id}/generate-invoice");
        $firstGen->assertStatus(201)
            ->assertJsonPath('data.total_amount', '150000.00')
            ->assertJsonPath('data.status', 'unpaid');

        $reading->refresh();
        $this->assertTrue($reading->is_billed);
        $this->assertNotNull($reading->invoice_id);

        // Cek Invoice Item snapshot
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $reading->invoice_id,
            'item_type' => 'electricity',
            'amount' => '150000.00',
            'quantity' => '100.00',
            'rate' => '1500.00',
        ]);

        // Penerbitan Kedua (Percobaan duplikat) -> HARUS DITOLAK
        $secondGen = $this->postJson("/api/v1/utilities/readings/{$reading->id}/generate-invoice");
        $secondGen->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_tariff_change_does_not_alter_historical_readings_or_invoices(): void
    {
        Sanctum::actingAs($this->owner);

        // Pasang tarif Rp 1.500
        $tariff = UtilityTariff::create([
            'property_id' => $this->propertyA->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'rate_per_unit' => 1500.00,
            'unit' => 'kWh',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-HIST-01',
            'unit' => 'kWh',
            'initial_reading' => 0.00,
            'current_reading' => 0.00,
            'is_active' => true,
        ]);

        // Catat reading & generate invoice saat tarif Rp 1.500
        $resReading = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 100.00,
            'reading_date' => '2026-02-01',
            'status' => 'approved',
        ]);
        $readingId = $resReading->json('data.id');

        $resInv = $this->postJson("/api/v1/utilities/readings/{$readingId}/generate-invoice");
        $invoiceId = $resInv->json('data.id');

        // Ubah tarif di masa depan menjadi Rp 3.000
        $this->postJson('/api/v1/utilities/tariffs', [
            'property_id' => $this->propertyA->id,
            'utility_type' => 'electricity',
            'rate_per_unit' => 3000.00,
            'unit' => 'kWh',
            'effective_from' => '2026-03-01',
        ])->assertStatus(201);

        // Verifikasi invoice historis dan snapshot pembacaan TIDAK berubah
        $oldReading = UtilityReading::find($readingId);
        $this->assertEquals('1500.00', $oldReading->rate_per_unit);
        $this->assertEquals('150000.00', $oldReading->total_cost);

        $oldInvoice = \App\Models\Invoice::with('items')->find($invoiceId);
        $this->assertEquals('150000.00', $oldInvoice->total_amount);
        $this->assertEquals('1500.00', $oldInvoice->items->first()->rate);
    }

    public function test_tenant_can_only_view_their_own_room_readings(): void
    {
        // Buat reading untuk room 101 (milik $this->tenant)
        $meter101 = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'MTR-101-T',
            'unit' => 'kWh',
            'initial_reading' => 100,
            'current_reading' => 150,
            'is_active' => true,
        ]);

        UtilityReading::create([
            'meter_id' => $meter101->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'booking_id' => Booking::first()->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100,
            'current_meter' => 150,
            'usage' => 50,
            'rate_per_unit' => 1000,
            'total_cost' => 50000,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
        ]);

        // Login sebagai tenant pemilik kamar 101
        Sanctum::actingAs($this->tenant);
        $resTenant = $this->getJson('/api/v1/utilities/tenant/consumption');
        $resTenant->assertStatus(200)
            ->assertJsonPath('data.total', 1);

        // Login sebagai tenant lain yang tidak menyewa kamar 101
        Sanctum::actingAs($this->otherTenant);
        $resOther = $this->getJson('/api/v1/utilities/tenant/consumption');
        $resOther->assertStatus(200)
            ->assertJsonPath('data.total', 0);
    }

    public function test_web_dashboard_meters_readings_and_tariffs_access(): void
    {
        // Akses oleh Owner
        $this->actingAs($this->owner)
            ->get('/dashboard/utilities/meters')
            ->assertStatus(200)
            ->assertSee('Master Meter Utilitas');

        $this->actingAs($this->owner)
            ->get('/dashboard/utilities/tariffs')
            ->assertStatus(200)
            ->assertSee('Konfigurasi Tarif Utilitas');

        $this->actingAs($this->owner)
            ->get('/dashboard/utilities/readings')
            ->assertStatus(200)
            ->assertSee('Review Konsumsi Meter');
    }

    public function test_cashier_role_boundary_can_generate_invoice_but_cannot_alter_or_create_meters(): void
    {
        $cashier = User::factory()->create(['role' => Role::RECEPTIONIST, 'name' => 'Kasir Kantor']);
        DB::table('property_users')->insert([
            'property_id' => $this->propertyA->id,
            'user_id' => $cashier->id,
            'role' => 'receptionist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 1. Kasir DILARANG membuat master meter
        Sanctum::actingAs($cashier);
        $resCreateMeter = $this->postJson('/api/v1/utilities/meters', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'EL-CASHIER-DENIED',
        ]);
        $resCreateMeter->assertStatus(422)
            ->assertJsonPath('message', 'Hanya Pemilik, Manajer, atau Staf berwenang yang dapat membuat meter utilitas.');

        // 2. Kasir DILARANG mencatat pembacaan meter fisik
        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'EL-CASHIER-TEST',
            'unit' => 'kWh',
            'initial_reading' => 100,
            'current_reading' => 100,
            'is_active' => true,
        ]);

        $resRecordReading = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 120,
            'reading_date' => now()->toDateString(),
        ]);
        $resRecordReading->assertStatus(422)
            ->assertJsonPath('message', 'Penyewa dan Kasir tidak diizinkan mencatat pembacaan meter fisik.');

        // 3. Kasir DIIZINKAN menerbitkan invoice dari pembacaan yang sudah disetujui
        $reading = UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100,
            'current_meter' => 150,
            'usage' => 50,
            'rate_per_unit' => 1500,
            'total_cost' => 75000,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
            'is_billed' => false,
        ]);

        $resGenerate = $this->postJson("/api/v1/utilities/readings/{$reading->id}/generate-invoice");
        $resGenerate->assertStatus(201);
        $this->assertTrue((bool) $reading->fresh()->is_billed);
    }

    public function test_id_consistency_validation_rejects_mismatched_room_and_booking(): void
    {
        Sanctum::actingAs($this->staff);

        $meterA = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'EL-CONSISTENCY-A',
            'unit' => 'kWh',
            'initial_reading' => 0,
            'current_reading' => 0,
            'is_active' => true,
        ]);

        // Mismatched room_id (kamar 201 berada pada property B)
        $resMismatchRoom = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meterA->id,
            'room_id' => $this->room201->id,
            'current_meter' => 50,
            'reading_date' => now()->toDateString(),
        ]);
        $resMismatchRoom->assertStatus(422)
            ->assertJsonPath('message', "Kamar yang dipilih (ID: {$this->room201->id}) tidak sesuai dengan kamar yang terhubung ke meter ini (ID: {$this->room101->id}).");

        // Mismatched booking_id (booking di property B)
        $bookingB = Booking::create([
            'booking_code' => 'BOOK-PROP-B',
            'user_id' => $this->otherTenant->id,
            'property_id' => $this->propertyB->id,
            'room_id' => $this->room201->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_amount' => 1500000,
            'status' => 'confirmed',
        ]);

        $resMismatchBooking = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meterA->id,
            'booking_id' => $bookingB->id,
            'current_meter' => 50,
            'reading_date' => now()->toDateString(),
        ]);
        $resMismatchBooking->assertStatus(422);
    }

    public function test_chronological_reading_order_and_period_overlap_are_strictly_rejected(): void
    {
        Sanctum::actingAs($this->staff);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'EL-CHRONO-TEST',
            'unit' => 'kWh',
            'initial_reading' => 100,
            'current_reading' => 100,
            'is_active' => true,
        ]);

        // Catat dan setujui pembacaan periode 2026-10-01 s/d 2026-10-15
        UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100,
            'current_meter' => 150,
            'usage' => 50,
            'rate_per_unit' => 1500,
            'total_cost' => 75000,
            'reading_date' => '2026-10-15',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-15',
            'status' => UtilityReadingStatus::APPROVED,
        ]);

        // Mencoba mencatat pembacaan dengan tanggal mundur (2026-10-10) harus ditolak
        $resBackwardDate = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 160,
            'reading_date' => '2026-10-10',
        ]);
        $resBackwardDate->assertStatus(422)
            ->assertJsonPath('message', 'Sudah ada pembacaan yang disetujui setelah tanggal 2026-10-10. Tanggal pembacaan harus berurutan secara kronologis.');

        // Mencoba mencatat pembacaan dengan periode yang tumpang tindih harus ditolak
        $resOverlap = $this->postJson('/api/v1/utilities/readings', [
            'meter_id' => $meter->id,
            'current_meter' => 180,
            'reading_date' => '2026-10-25',
            'period_start' => '2026-10-10', // Tumpang tindih dengan 2026-10-01 .. 2026-10-15
            'period_end' => '2026-10-25',
        ]);
        $resOverlap->assertStatus(422)
            ->assertJsonPath('message', 'Terdapat periode pembacaan yang tumpang tindih dengan pembacaan yang sudah disetujui untuk meter ini.');
    }

    public function test_correction_on_already_billed_reading_is_rejected(): void
    {
        Sanctum::actingAs($this->owner);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => 'electricity',
            'meter_number' => 'EL-CORRECT-BILLED',
            'unit' => 'kWh',
            'initial_reading' => 100,
            'current_reading' => 200,
            'is_active' => true,
        ]);

        $booking = Booking::first();
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => 'INV-TEST-BILLED',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'total_amount' => 150000,
            'subtotal' => 150000,
            'status' => \App\Enums\InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $reading = UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100,
            'current_meter' => 200,
            'usage' => 100,
            'rate_per_unit' => 1500,
            'total_cost' => 150000,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
            'is_billed' => true,
            'invoice_id' => $invoice->id,
        ]);

        $resCorrect = $this->postJson("/api/v1/utilities/readings/{$reading->id}/correct", [
            'current_meter' => 210,
            'reason' => 'Koreksi salah input angka',
        ]);
        $resCorrect->assertStatus(422)
            ->assertJsonPath('message', 'Pembacaan yang telah ditagihkan ke invoice tidak boleh dikoreksi langsung. Hubungi kasir untuk penyesuaian invoice.');
    }

    public function test_unauthenticated_and_unauthorized_proof_photo_access_are_blocked(): void
    {
        $file = UploadedFile::fake()->image('proof.jpg', 640, 480);
        $path = $file->store('utility_proofs/test', 'local');

        $reading = UtilityReading::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 100,
            'current_meter' => 150,
            'usage' => 50,
            'rate_per_unit' => 1500,
            'total_cost' => 75000,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
            'proof_photo' => $path,
        ]);

        // 1. Tanpa autentikasi (unauthenticated) -> 401
        $resGuest = $this->getJson("/api/v1/utilities/readings/{$reading->id}/proof-photo");
        $resGuest->assertStatus(401);

        // 2. Tenant lain yang tidak menyewa kamar ini -> 403
        Sanctum::actingAs($this->otherTenant);
        $resOtherTenant = $this->getJson("/api/v1/utilities/readings/{$reading->id}/proof-photo");
        $resOtherTenant->assertStatus(403);

        // 3. Tenant sah yang menyewa kamar 101 -> 200 File Response
        Sanctum::actingAs($this->tenant);
        $resTenant = $this->getJson("/api/v1/utilities/readings/{$reading->id}/proof-photo");
        $resTenant->assertStatus(200);
    }

    public function test_concurrent_lock_for_update_prevents_simultaneous_double_billing(): void
    {
        $utilityService = app(\App\Services\Utility\UtilityService::class);

        $meter = UtilityMeter::create([
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'meter_number' => 'EL-CONCURRENT-01',
            'unit' => 'kWh',
            'initial_reading' => 0,
            'current_reading' => 100,
            'is_active' => true,
        ]);

        $reading = UtilityReading::create([
            'meter_id' => $meter->id,
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'booking_id' => Booking::first()->id,
            'utility_type' => UtilityType::ELECTRICITY,
            'previous_meter' => 0,
            'current_meter' => 100,
            'usage' => 100,
            'rate_per_unit' => 1500,
            'total_cost' => 150000,
            'reading_date' => now()->toDateString(),
            'status' => UtilityReadingStatus::APPROVED,
            'is_billed' => false,
        ]);

        // Request 1 berhasil
        $invoice1 = $utilityService->generateUtilityInvoice($reading->id, $this->owner);
        $this->assertNotNull($invoice1->id);

        // Verifikasi count sebelum call kedua
        $this->assertEquals(1, \App\Models\Invoice::where('property_id', $this->propertyA->id)->where('invoice_type', 'electricity')->count());
        $this->assertEquals(1, \App\Models\InvoiceItem::where('item_type', 'electricity')->count());

        // Request 2 (mensimulasikan race condition bersamaan) wajib melempar DomainException
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Tagihan untuk pembacaan meter ini sudah pernah diterbitkan sebelumnya');

        $utilityService->generateUtilityInvoice($reading->id, $this->owner);
    }
}


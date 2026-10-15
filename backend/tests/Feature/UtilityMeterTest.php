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
}

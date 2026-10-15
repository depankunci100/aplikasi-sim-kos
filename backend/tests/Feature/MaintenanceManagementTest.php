<?php

namespace Tests\Feature;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $managerA;
    protected User $staffA;
    protected User $technicianA;
    protected User $tenantA;
    protected User $tenantB;
    protected User $staffB;

    protected Property $propertyA;
    protected Property $propertyB;
    protected Room $roomA1;
    protected Room $roomB1;
    protected Booking $bookingA1;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->managerA = User::factory()->create(['role' => Role::MANAGER]);
        $this->staffA = User::factory()->create(['role' => Role::STAFF]);
        $this->technicianA = User::factory()->create(['role' => Role::STAFF, 'name' => 'Teknisi Budi']);
        $this->tenantA = User::factory()->create(['role' => Role::TENANT, 'name' => 'Tenant Siti']);
        $this->tenantB = User::factory()->create(['role' => Role::TENANT, 'name' => 'Tenant Doni']);
        $this->staffB = User::factory()->create(['role' => Role::STAFF]);

        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Melati',
            'type' => 'kos',
            'address' => 'Jl. Melati 1',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Kos Mawar',
            'type' => 'kos',
            'address' => 'Jl. Mawar 2',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        $this->propertyA->users()->attach($this->managerA->id, ['role' => 'manager']);
        $this->propertyA->users()->attach($this->staffA->id, ['role' => 'staff']);
        $this->propertyA->users()->attach($this->technicianA->id, ['role' => 'staff']);
        $this->propertyB->users()->attach($this->staffB->id, ['role' => 'staff']);

        $typeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe',
            'base_price_monthly' => 1500000,
        ]);

        $this->roomA1 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $typeA->id,
            'room_number' => '101',
            'status' => 'occupied',
        ]);

        $typeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Standard',
            'base_price_monthly' => 1000000,
        ]);

        $this->roomB1 = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $typeB->id,
            'room_number' => '201',
            'status' => 'occupied',
        ]);

        $this->bookingA1 = Booking::create([
            'booking_code' => 'BK-101',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'rate_amount' => 1500000,
            'deposit_amount' => 500000,
            'total_amount' => 2000000,
            'status' => 'confirmed',
        ]);
    }

    public function test_tenant_can_submit_maintenance_request_with_photo_for_rented_room(): void
    {
        $photo = UploadedFile::fake()->image('kerusakan_ac.jpg');

        $response = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson('/api/v1/maintenance', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'category' => MaintenanceCategory::AC->value,
                'priority' => MaintenancePriority::HIGH->value,
                'title' => 'AC tidak dingin dan bocor air',
                'description' => 'Air menetes membasahi kasur sejak kemarin sore.',
                'photos_before' => [$photo],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'AC tidak dingin dan bocor air')
            ->assertJsonPath('data.status', MaintenanceStatus::REPORTED->value)
            ->assertJsonPath('data.category', MaintenanceCategory::AC->value)
            ->assertJsonPath('data.priority', MaintenancePriority::HIGH->value);

        $this->assertDatabaseHas('maintenance_requests', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'reporter_id' => $this->tenantA->id,
            'status' => MaintenanceStatus::REPORTED->value,
        ]);

        // Pastikan SLA otomatis terhitung (High = 24 jam)
        $maint = MaintenanceRequest::where('room_id', $this->roomA1->id)->first();
        $this->assertNotNull($maint->sla_due_at);
        $this->assertTrue($maint->sla_due_at->isAfter(now()));
        $this->assertNotEmpty($maint->photos_before);
    }

    public function test_tenant_cannot_submit_maintenance_for_unrented_room(): void
    {
        $response = $this->actingAs($this->tenantB, 'sanctum')
            ->postJson('/api/v1/maintenance', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'category' => MaintenanceCategory::PLUMBING->value,
                'title' => 'Pipa bocor',
                'description' => 'Mencoba submit untuk kamar orang lain',
            ]);

        $response->assertStatus(403);
    }

    public function test_full_maintenance_lifecycle_state_transitions(): void
    {
        // 1. Create maintenance ticket
        $maint = MaintenanceRequest::create([
            'ticket_number' => 'MNT-TEST-001',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'reporter_id' => $this->tenantA->id,
            'category' => MaintenanceCategory::ELECTRICITY,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
            'title' => 'Saklar lampu konslet',
            'description' => 'Bau hangus saat dinyalakan.',
            'sla_hours' => 48,
            'sla_due_at' => now()->addHours(48),
        ]);

        // 2. Staff triage: ubah prioritas & update SLA
        $triageResponse = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/triage", [
                'priority' => MaintenancePriority::URGENT->value,
                'notes' => 'Bahaya kebakaran, dinaikkan ke Urgent.',
            ]);

        $triageResponse->assertStatus(200)
            ->assertJsonPath('data.status', MaintenanceStatus::TRIAGED->value)
            ->assertJsonPath('data.priority', MaintenancePriority::URGENT->value);

        // 3. Manager assign technician
        $assignResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/assign", [
                'assigned_to' => $this->technicianA->id,
                'scheduled_date' => now()->addHours(2)->toDateTimeString(),
                'notes' => 'Tolong segera dicek siang ini.',
            ]);

        $assignResponse->assertStatus(200)
            ->assertJsonPath('data.status', MaintenanceStatus::ASSIGNED->value)
            ->assertJsonPath('data.assigned_to', $this->technicianA->id);

        // 4. Technician starts work
        $startResponse = $this->actingAs($this->technicianA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/start");

        $startResponse->assertStatus(200)
            ->assertJsonPath('data.status', MaintenanceStatus::IN_PROGRESS->value);

        // 5. Technician submits verification with costs & after photo
        $afterPhoto = UploadedFile::fake()->image('saklar_baru.jpg');
        $submitResponse = $this->actingAs($this->technicianA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/submit-verification", [
                'action_taken' => 'Ganti saklar Panasonic baru dan isolasi ulang kabel.',
                'diagnosis_notes' => 'Kabel saklar longgar dan meleleh.',
                'spare_parts' => ['Saklar Panasonic 1 gang'],
                'labor_cost' => 50000,
                'material_cost' => 35000,
                'notes' => 'Sudah dicoba dan aman beroperasi.',
                'photos_after' => [$afterPhoto],
            ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('data.status', MaintenanceStatus::PENDING_VERIFICATION->value)
            ->assertJsonPath('data.labor_cost', '50000.00')
            ->assertJsonPath('data.material_cost', '35000.00')
            ->assertJsonPath('data.total_cost', '85000.00');

        // 6. Supervisor / Manager verifies work
        $verifyResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/verify", [
                'verification_notes' => 'Hasil pengecekan fisik sudah rapi dan berfungsi normal.',
            ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('data.status', MaintenanceStatus::COMPLETED->value);

        $maint->refresh();
        $this->assertEquals(MaintenanceStatus::COMPLETED, $maint->status);
        $this->assertNotNull($maint->verified_at);
        $this->assertEquals($this->managerA->id, $maint->verified_by);

        // Pastikan tidak merusak status kamar
        $this->roomA1->refresh();
        $this->assertEquals('occupied', $this->roomA1->status->value);
    }

    public function test_invalid_state_transition_is_rejected(): void
    {
        $maint = MaintenanceRequest::create([
            'ticket_number' => 'MNT-TEST-002',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'reporter_id' => $this->tenantA->id,
            'category' => MaintenanceCategory::DOOR_LOCK,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
            'title' => 'Kunci pintu macet',
            'description' => 'Kunci tidak bisa diputar dari luar.',
        ]);

        // Melompat langsung dari reported ke completed harus ditolak (422)
        $response = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/verify", [
                'verification_notes' => 'Langsung selesaikan tanpa proses.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_cross_property_staff_isolation(): void
    {
        $maint = MaintenanceRequest::create([
            'ticket_number' => 'MNT-TEST-003',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'reporter_id' => $this->tenantA->id,
            'category' => MaintenanceCategory::WATER,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
            'title' => 'Keran wastafel patah',
            'description' => 'Keran patah saat diputar.',
        ]);

        // Staf B dari Properti B mencoba akses atau triage tiket Properti A
        $response = $this->actingAs($this->staffB, 'sanctum')
            ->postJson("/api/v1/maintenance/{$maint->id}/triage", [
                'priority' => MaintenancePriority::HIGH->value,
                'notes' => 'Mencoba triage properti lain',
            ]);

        $response->assertStatus(403);
    }

    public function test_private_photo_streaming_authorization(): void
    {
        $photo = UploadedFile::fake()->image('foto_privat.jpg');
        $storedPath = $photo->store('maintenance_before', 'local');

        $maint = MaintenanceRequest::create([
            'ticket_number' => 'MNT-TEST-004',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'reporter_id' => $this->tenantA->id,
            'category' => MaintenanceCategory::FURNITURE,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
            'title' => 'Lemari rusak',
            'description' => 'Pintu lemari lepas dari engsel.',
            'photos_before' => [$storedPath],
        ]);

        // Tenant A yang sah dapat mengakses foto
        $validResponse = $this->actingAs($this->tenantA, 'sanctum')
            ->getJson("/api/v1/maintenance/{$maint->id}/photo?path={$storedPath}");
        $validResponse->assertStatus(200);

        // Tenant B tidak berhak mengakses foto dan mendapatkan 403
        $forbiddenResponse = $this->actingAs($this->tenantB, 'sanctum')
            ->getJson("/api/v1/maintenance/{$maint->id}/photo?path={$storedPath}");
        $forbiddenResponse->assertStatus(403);
    }
}

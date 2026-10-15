<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingType;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HousekeepingManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $managerA;
    protected User $staffA;
    protected User $cashierA;
    protected User $tenantA;
    protected User $staffB;

    protected Property $propertyA;
    protected Property $propertyB;
    protected Room $roomA1;
    protected Booking $bookingA1;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->managerA = User::factory()->create(['role' => Role::MANAGER]);
        $this->staffA = User::factory()->create(['role' => Role::STAFF, 'name' => 'Staf Rini']);
        $this->cashierA = User::factory()->create(['role' => Role::RECEPTIONIST, 'name' => 'Kasir Bayu']);
        $this->tenantA = User::factory()->create(['role' => Role::TENANT, 'name' => 'Penyewa Tina']);
        $this->staffB = User::factory()->create(['role' => Role::STAFF]);

        $this->propertyA = Property::create([
            'code' => 'PROP-HK-A',
            'name' => 'Kos Cempaka',
            'type' => 'kos',
            'address' => 'Jl. Cempaka Putih 12',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-HK-B',
            'name' => 'Kos Kenanga',
            'type' => 'kos',
            'address' => 'Jl. Kenanga 3',
            'city' => 'Bandung',
            'is_active' => true,
        ]);

        $this->propertyA->users()->attach($this->managerA->id, ['role' => 'manager']);
        $this->propertyA->users()->attach($this->staffA->id, ['role' => 'staff']);
        $this->propertyA->users()->attach($this->cashierA->id, ['role' => 'receptionist']);
        $this->propertyB->users()->attach($this->staffB->id, ['role' => 'staff']);

        $typeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Suite',
            'base_price_monthly' => 2000000,
        ]);

        $this->roomA1 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $typeA->id,
            'room_number' => '401',
            'status' => 'occupied',
        ]);

        $this->bookingA1 = Booking::create([
            'booking_code' => 'BK-HK-401',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'rate_amount' => 2000000,
            'deposit_amount' => 1000000,
            'total_amount' => 3000000,
            'status' => 'confirmed',
        ]);
    }

    public function test_schedule_housekeeping_task_with_default_checklist(): void
    {
        $response = $this->actingAs($this->managerA, 'sanctum')
            ->postJson('/api/v1/housekeeping', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'type' => HousekeepingType::ROOM_CLEANING->value,
                'priority' => 'normal',
                'scheduled_date' => now()->addDay()->toDateString(),
                'scheduled_slot' => 'Pagi (08:00 - 11:00)',
                'notes' => 'Pembersihan rutin mingguan.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', HousekeepingStatus::SCHEDULED->value)
            ->assertJsonPath('data.type', HousekeepingType::ROOM_CLEANING->value);

        $task = HousekeepingTask::where('room_id', $this->roomA1->id)->first();
        $this->assertNotNull($task);
        $this->assertStringStartsWith('HSK-', $task->task_number);
        $this->assertNotEmpty($task->checklist);
        $this->assertGreaterThanOrEqual(4, count($task->checklist));
    }

    public function test_anti_duplicate_active_housekeeping_task_prevention(): void
    {
        $scheduleDate = now()->addDays(2)->toDateString();

        // 1. Buat tugas pertama
        $this->actingAs($this->managerA, 'sanctum')
            ->postJson('/api/v1/housekeeping', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'type' => HousekeepingType::ROOM_CLEANING->value,
                'scheduled_date' => $scheduleDate,
            ])
            ->assertStatus(201);

        // 2. Coba buat tugas kedua untuk kamar dan tanggal yang sama persis -> harus ditolak (422)
        $duplicateResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson('/api/v1/housekeeping', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'type' => HousekeepingType::DEEP_CLEANING->value,
                'scheduled_date' => $scheduleDate,
            ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_full_housekeeping_lifecycle_and_verification(): void
    {
        // 1. Buat tugas langsung ditugaskan ke Staff Rini
        $task = HousekeepingTask::create([
            'task_number' => 'HK-TEST-0001',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'type' => HousekeepingType::ROOM_CLEANING,
            'priority' => \App\Enums\MaintenancePriority::NORMAL,
            'status' => HousekeepingStatus::ASSIGNED,
            'assigned_to' => $this->staffA->id,
            'assigned_by' => $this->managerA->id,
            'scheduled_date' => now()->toDateString(),
            'checklist' => [
                ['id' => 'item_1', 'task' => 'Sapu dan pel lantai', 'is_done' => false],
                ['id' => 'item_2', 'task' => 'Ganti sprei dan sarung bantal', 'is_done' => false],
                ['id' => 'item_3', 'task' => 'Bersihkan kamar mandi', 'is_done' => false],
            ],
        ]);

        // 2. Staff mulai bersihkan dengan upload foto before
        $beforePhoto = UploadedFile::fake()->image('kamar_kotor.jpg');
        $startResponse = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$task->id}/start", [
                'photos_before' => [$beforePhoto],
            ]);

        $startResponse->assertStatus(200)
            ->assertJsonPath('data.status', HousekeepingStatus::IN_PROGRESS->value);

        // 3. Staff checklist pekerjaan & submit untuk verifikasi
        $afterPhoto = UploadedFile::fake()->image('kamar_bersih.jpg');
        $submitChecklist = [
            ['id' => 'item_1', 'is_done' => true],
            ['id' => 'item_2', 'is_done' => true],
            ['id' => 'item_3', 'is_done' => true],
        ];

        $submitResponse = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$task->id}/submit-verification", [
                'checklist' => $submitChecklist,
                'damaged_or_missing_items' => 'Gelas mug pecah 1 pcs.',
                'notes' => 'Semua sudah disemprot pewangi lavender.',
                'photos_after' => [$afterPhoto],
            ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('data.status', HousekeepingStatus::PENDING_VERIFICATION->value)
            ->assertJsonPath('data.damaged_or_missing_items', 'Gelas mug pecah 1 pcs.');

        // 4. Manajer/Supervisor verifikasi
        $verifyResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$task->id}/verify", [
                'verification_notes' => 'Kamar sudah diperiksa, rapi dan harum sesuai standar SOP.',
            ]);

        $verifyResponse->assertStatus(200)
            ->assertJsonPath('data.status', HousekeepingStatus::COMPLETED->value);

        $task->refresh();
        $this->assertEquals(HousekeepingStatus::COMPLETED, $task->status);
        $this->assertNotNull($task->verified_at);
        $this->assertEquals($this->managerA->id, $task->verified_by);

        // CRITICAL BUSINESS RULE: Housekeeping TIDAK boleh mengubah status kamar atau booking
        $this->roomA1->refresh();
        $this->assertEquals('occupied', $this->roomA1->status->value);
        $this->bookingA1->refresh();
        $this->assertEquals('confirmed', $this->bookingA1->status->value);
    }

    public function test_cross_property_staff_isolation(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HK-TEST-0002',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'type' => HousekeepingType::ROOM_CLEANING,
            'priority' => \App\Enums\MaintenancePriority::NORMAL,
            'status' => HousekeepingStatus::SCHEDULED,
            'scheduled_date' => now()->toDateString(),
            'checklist' => [],
        ]);

        // Staf B dari Properti B mencoba akses atau assign tugas Properti A -> 403 Forbidden
        $response = $this->actingAs($this->staffB, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$task->id}/assign", [
                'assigned_to' => $this->staffB->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_cancel_housekeeping_task_with_reason(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HK-TEST-0003',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'type' => HousekeepingType::ON_DEMAND,
            'priority' => \App\Enums\MaintenancePriority::NORMAL,
            'status' => HousekeepingStatus::SCHEDULED,
            'scheduled_date' => now()->toDateString(),
            'checklist' => [],
        ]);

        $cancelResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$task->id}/cancel", [
                'reason' => 'Penyewa meminta pembersihan diundur ke hari Sabtu.',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('data.status', HousekeepingStatus::CANCELLED->value);

        $task->refresh();
        $this->assertEquals(HousekeepingStatus::CANCELLED, $task->status);
        $this->assertEquals('Penyewa meminta pembersihan diundur ke hari Sabtu.', $task->cancellation_reason);
    }
}

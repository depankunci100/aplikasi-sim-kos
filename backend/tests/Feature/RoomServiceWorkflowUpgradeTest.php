<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\InspectionStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoomServiceWorkflowUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected Property $property;
    protected Room $room1;
    protected Room $room2;
    protected User $manager;
    protected User $staff;
    protected User $tenant;
    protected User $otherTenant;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'code' => 'PROP-RS-01',
            'name' => 'Kos Melati Harmoni',
            'type' => 'boarding_house',
            'address' => 'Jl. Harmoni No. 12',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'name' => 'Budi Manager',
            'email' => 'manager@test.com',
            'role' => Role::MANAGER,
        ]);

        $this->staff = User::factory()->create([
            'name' => 'Agus Staf',
            'email' => 'staff@test.com',
            'role' => Role::STAFF,
        ]);

        $this->tenant = User::factory()->create([
            'name' => 'Rizky Tenant',
            'email' => 'tenant@test.com',
            'role' => Role::TENANT,
        ]);

        $this->otherTenant = User::factory()->create([
            'name' => 'Siti Other Tenant',
            'email' => 'other_tenant@test.com',
            'role' => Role::TENANT,
        ]);

        DB::table('property_users')->insert([
            ['property_id' => $this->property->id, 'user_id' => $this->manager->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property->id, 'user_id' => $this->staff->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Deluxe Room',
            'base_price' => 1500000,
        ]);

        $this->room1 = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => 'occupied',
        ]);

        $this->room2 = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '102',
            'floor' => 1,
            'status' => 'available',
        ]);

        $this->booking = Booking::create([
            'booking_code' => 'BK-RS-1001',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'total_amount' => 1500000,
            'paid_amount' => 1500000,
            'status' => 'active',
        ]);
    }

    public function test_tenant_can_request_room_service_only_for_active_booking(): void
    {
        Sanctum::actingAs($this->tenant);

        // 1. Sukses ajukan housekeeping untuk kamar aktif
        $response = $this->postJson('/api/v1/housekeeping/request', [
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_slot' => 'Pagi (08:00 - 11:00)',
            'priority' => 'normal',
            'notes' => 'Tolong sapu dan ganti sprei.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_id', $this->room1->id);

        // 2. Sukses ajukan inspeksi untuk kamar aktif
        $inspResponse = $this->postJson('/api/v1/inspections/request', [
            'type' => 'periodic',
            'reason' => 'Pemeriksaan Rutin Kondisi Kamar',
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'description' => 'Cek AC dan kunci jendela.',
        ]);

        $inspResponse->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_id', $this->room1->id);

        // 3. Penyewa lain yang tidak memiliki booking kamar aktif ditolak
        Sanctum::actingAs($this->otherTenant);

        $failResponse = $this->postJson('/api/v1/housekeeping/request', [
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_slot' => 'Siang (13:00 - 15:00)',
            'notes' => 'Kamar saya kotor',
        ]);

        $failResponse->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_manager_can_assign_inspector_and_housekeeping_staff(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HSK-TEST-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'area_name' => 'Kamar 101',
            'type' => 'room_cleaning',
            'status' => HousekeepingStatus::SCHEDULED,
            'priority' => 'normal',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_slot' => 'Pagi (08:00 - 11:00)',
            'created_by' => $this->tenant->id,
        ]);

        $inspection = Inspection::create([
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'inspection_code' => 'INSP-TEST-001',
            'type' => 'periodic',
            'status' => InspectionStatus::SCHEDULED,
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->manager);

        // 1. Assign Housekeeping Staff
        $hskAssign = $this->postJson("/api/v1/housekeeping/{$task->id}/assign", [
            'staff_id' => $this->staff->id,
        ]);

        $hskAssign->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($this->staff->id, $task->fresh()->assigned_to);
        $this->assertEquals(HousekeepingStatus::ASSIGNED, $task->fresh()->status);

        // 2. Assign Inspection Staff
        $inspAssign = $this->postJson("/api/v1/inspections/{$inspection->id}/assign", [
            'inspector_id' => $this->staff->id,
        ]);

        $inspAssign->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($this->staff->id, $inspection->fresh()->inspector_id);
    }

    public function test_manager_can_reschedule_inspection_and_housekeeping(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HSK-TEST-002',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'area_name' => 'Kamar 101',
            'type' => 'room_cleaning',
            'status' => HousekeepingStatus::ASSIGNED,
            'priority' => 'normal',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_slot' => 'Pagi (08:00 - 11:00)',
            'assigned_to' => $this->staff->id,
            'created_by' => $this->tenant->id,
        ]);

        $inspection = Inspection::create([
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'inspection_code' => 'INSP-TEST-002',
            'type' => 'periodic',
            'status' => InspectionStatus::SCHEDULED,
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'tenant_id' => $this->tenant->id,
            'inspector_id' => $this->staff->id,
        ]);

        Sanctum::actingAs($this->manager);

        // 1. Reschedule Housekeeping
        $newHskDate = now()->addDays(3)->toDateString();
        $resHsk = $this->postJson("/api/v1/housekeeping/{$task->id}/reschedule", [
            'scheduled_date' => $newHskDate,
            'scheduled_slot' => 'Sore (15:00 - 17:00)',
            'reason' => 'Staf sedang bertugas di unit lain.',
        ]);

        $resHsk->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($newHskDate, $task->fresh()->scheduled_date->toDateString());
        $this->assertEquals('Sore (15:00 - 17:00)', $task->fresh()->scheduled_slot);

        // 2. Reschedule Inspection
        $newInspDate = now()->addDays(4)->toDateString();
        $resInsp = $this->postJson("/api/v1/inspections/{$inspection->id}/reschedule", [
            'scheduled_date' => $newInspDate,
            'reschedule_reason' => 'Penyesuaian jadwal peninjauan fasilitas.',
        ]);

        $resInsp->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals($newInspDate, $inspection->fresh()->scheduled_date->toDateString());
    }

    public function test_staff_submits_housekeeping_and_tenant_approves(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HSK-TEST-003',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'area_name' => 'Kamar 101',
            'type' => 'room_cleaning',
            'status' => HousekeepingStatus::IN_PROGRESS,
            'priority' => 'normal',
            'scheduled_date' => now()->toDateString(),
            'scheduled_slot' => 'Pagi (08:00 - 11:00)',
            'assigned_to' => $this->staff->id,
            'created_by' => $this->tenant->id,
            'checklist' => [
                ['id' => 1, 'task' => 'Sapu dan pel lantai', 'is_done' => false],
                ['id' => 2, 'task' => 'Ganti sprei kasur', 'is_done' => false],
            ],
        ]);

        // Staf menyelesaikan pekerjaan
        Sanctum::actingAs($this->staff);
        $submitRes = $this->postJson("/api/v1/housekeeping/{$task->id}/submit-verification", [
            'checklist' => [
                ['id' => 1, 'task' => 'Sapu dan pel lantai', 'is_done' => true],
                ['id' => 2, 'task' => 'Ganti sprei kasur', 'is_done' => true],
            ],
            'notes' => 'Kamar telah dibersihkan dan disemprot pewangi.',
        ]);

        $submitRes->assertStatus(200);
        $this->assertEquals(HousekeepingStatus::PENDING_VERIFICATION, $task->fresh()->status);

        // Penyewa kamar mengonfirmasi selesai ("Sesuai — Konfirmasi Selesai")
        Sanctum::actingAs($this->tenant);
        $approveRes = $this->postJson("/api/v1/housekeeping/{$task->id}/approve", [
            'feedback' => 'Hasil pembersihan sangat bersih dan wangi. Terima kasih.',
        ]);

        $approveRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $updatedTask = $task->fresh();
        $this->assertEquals(HousekeepingStatus::COMPLETED, $updatedTask->status);
        $this->assertEquals($this->tenant->id, $updatedTask->verified_by);
        $this->assertNotNull($updatedTask->verified_at);
        $this->assertStringContainsString('Hasil pembersihan sangat bersih', $updatedTask->verification_notes);

        // Penyewa lain yang mencoba approve ditolak (403 Forbidden)
        Sanctum::actingAs($this->otherTenant);
        $otherTask = HousekeepingTask::create([
            'task_number' => 'HSK-TEST-004',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'area_name' => 'Kamar 101',
            'type' => 'room_cleaning',
            'status' => HousekeepingStatus::PENDING_VERIFICATION,
            'priority' => 'normal',
            'scheduled_date' => now()->toDateString(),
            'created_by' => $this->tenant->id,
        ]);

        $rejectRes = $this->postJson("/api/v1/housekeeping/{$otherTask->id}/approve", [
            'feedback' => 'Bukan kamar saya',
        ]);

        $rejectRes->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_can_request_housekeeping_revision_with_problematic_items(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HSK-TEST-005',
            'property_id' => $this->property->id,
            'room_id' => $this->room1->id,
            'area_name' => 'Kamar 101',
            'type' => 'room_cleaning',
            'status' => HousekeepingStatus::PENDING_VERIFICATION,
            'priority' => 'normal',
            'scheduled_date' => now()->toDateString(),
            'scheduled_slot' => 'Pagi (08:00 - 11:00)',
            'assigned_to' => $this->staff->id,
            'created_by' => $this->tenant->id,
            'photos_after' => ['housekeeping_photos/HSK-TEST-005/after/1.jpg'],
            'checklist' => [
                ['id' => 1, 'task' => 'Sapu dan pel lantai', 'is_done' => true],
                ['id' => 2, 'task' => 'Bersihkan kamar mandi', 'is_done' => true],
            ],
        ]);

        Sanctum::actingAs($this->tenant);

        $revisionRes = $this->postJson("/api/v1/housekeeping/{$task->id}/request-revision", [
            'reason' => 'Lantai di bawah ranjang masih berdebu dan kloset belum dibersihkan.',
            'problematic_items' => ['Sapu dan pel lantai', 'Bersihkan kamar mandi'],
        ]);

        $revisionRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Foto tidak boleh dihapus
        $this->assertEquals(['housekeeping_photos/HSK-TEST-005/after/1.jpg'], $task->fresh()->photos_after);

        // Periksa log riwayat
        $log = DB::table('housekeeping_logs')
            ->where('housekeeping_task_id', $task->id)
            ->where('action', 'revision_requested')
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('Lantai di bawah ranjang masih berdebu', $log->notes);
        $this->assertStringContainsString('Item bermasalah:', $log->notes);
    }

    public function test_tenant_identity_update_blind_index_and_no_raw_nik_exposure(): void
    {
        Sanctum::actingAs($this->tenant);

        $rawNik = '3507123456780004';

        $response = $this->putJson('/api/v1/tenant/identity', [
            'name' => 'Rizky Pratama',
            'nik' => $rawNik,
            'emergency_name' => 'Bapak Pratama',
            'emergency_phone' => '081234567890',
            'occupation' => 'Software Engineer',
            'institution_name' => 'PT Teknologi Unggul',
            'vehicle_type' => 'motor',
            'vehicle_plate' => 'N 1234 ABC',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.masked_nik', '3507********0004')
            ->assertJsonPath('data.name', 'Rizky Pratama')
            ->assertJsonPath('data.is_nik_verified', true)
            ->assertJsonMissing(['nik' => $rawNik]); // Tidak boleh membocorkan plaintext NIK

        // Verifikasi di DB: NIK tersimpan masked dan hash blind index
        $profile = TenantProfile::where('user_id', $this->tenant->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals('3507********0004', $profile->masked_nik);
        $this->assertNotNull($profile->nik_hash);
        $this->assertNotEquals($rawNik, $profile->nik_hash);

        // Duplikasi NIK dari penyewa lain wajib ditolak
        Sanctum::actingAs($this->otherTenant);
        $dupRes = $this->putJson('/api/v1/tenant/identity', [
            'nik' => $rawNik,
        ]);

        $dupRes->assertStatus(422)
            ->assertJsonPath('success', false);
        $this->assertStringContainsString('NIK KTP ini sudah terdaftar', (string) $dupRes->json('message'));
    }
}

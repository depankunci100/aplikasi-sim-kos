<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Enums\HousekeepingStatus;
use App\Enums\HousekeepingType;
use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\HousekeepingTask;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected User $manager;
    protected User $staff;
    protected User $cashier;
    protected User $tenant1;
    protected User $tenant2;
    protected Property $propertyA;
    protected Property $propertyB;
    protected Room $room101;
    protected Room $room102;
    protected Room $room201;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Setup Properti A
        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Permata A',
            'type' => 'boarding_house',
            'address' => 'Jl. Mawar No. 1',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        // Setup Properti B
        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Kos Permata B',
            'type' => 'boarding_house',
            'address' => 'Jl. Melati No. 2',
            'city' => 'Batu',
            'is_active' => true,
        ]);

        $roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe',
            'base_price_monthly' => 1500000,
        ]);

        $roomTypeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Standard',
            'base_price_monthly' => 1000000,
        ]);

        $this->room101 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $roomTypeA->id,
            'room_number' => '101',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        $this->room102 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $roomTypeA->id,
            'room_number' => '102',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->room201 = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $roomTypeB->id,
            'room_number' => '201',
            'status' => 'occupied',
            'is_active' => true,
        ]);

        // Users
        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN,
            'name' => 'Super Administrator',
            'email' => 'admin@kos.test',
        ]);

        $this->owner = User::factory()->create([
            'role' => Role::OWNER,
            'name' => 'Pak Owner A',
            'email' => 'owner@kos.test',
        ]);

        $this->manager = User::factory()->create([
            'role' => Role::MANAGER,
            'name' => 'Ibu Manager A',
            'email' => 'manager@kos.test',
        ]);

        $this->staff = User::factory()->create([
            'role' => Role::STAFF,
            'name' => 'Teknisi Rudi',
            'email' => 'staff@kos.test',
        ]);

        $this->cashier = User::factory()->create([
            'role' => Role::RECEPTIONIST,
            'name' => 'Kasir Maya',
            'email' => 'kasir@kos.test',
        ]);

        $this->tenant1 = User::factory()->create([
            'role' => Role::TENANT,
            'name' => 'Anisa Penyewa 101',
            'email' => 'anisa@tenant.test',
        ]);

        $this->tenant2 = User::factory()->create([
            'role' => Role::TENANT,
            'name' => 'Budi Penyewa 201',
            'email' => 'budi@tenant.test',
        ]);

        // Property Users relations
        DB::table('property_users')->insert([
            ['property_id' => $this->propertyA->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->manager->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->staff->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->cashier->id, 'role' => 'receptionist', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Tenant 1 Bookings (Room 101 Property A)
        Booking::create([
            'booking_code' => 'BK-101-ANISA',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'user_id' => $this->tenant1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
            'rate_amount' => 1500000,
            'deposit_amount' => 500000,
            'total_amount' => 2000000,
            'status' => BookingStatus::ACTIVE,
        ]);

        // Tenant 2 Bookings (Room 201 Property B)
        Booking::create([
            'booking_code' => 'BK-201-BUDI',
            'property_id' => $this->propertyB->id,
            'room_id' => $this->room201->id,
            'user_id' => $this->tenant2->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonths(5)->toDateString(),
            'rate_amount' => 1000000,
            'deposit_amount' => 500000,
            'total_amount' => 1500000,
            'status' => BookingStatus::ACTIVE,
        ]);
    }

    public function test_owner_can_view_maintenance_index_and_create_pages(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard.maintenance.index'));
        $response->assertStatus(200);
        $response->assertSee('Tiket Perbaikan');

        $responseCreate = $this->actingAs($this->owner)->get(route('dashboard.maintenance.create'));
        $responseCreate->assertStatus(200);
        $responseCreate->assertSee('Ajukan Tiket Perbaikan');
    }

    public function test_user_can_create_maintenance_request_with_photo_via_web(): void
    {
        $file = UploadedFile::fake()->image('kerusakan_lampu.jpg', 600, 400);

        $response = $this->actingAs($this->manager)->post(route('dashboard.maintenance.store'), [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'title' => 'Saklar dan Lampu Kamar 101 Mati',
            'description' => 'Saklar berderik dan lampu tidak menyala saat dinyalakan.',
            'category' => 'electricity',
            'priority' => 'high',
            'photos_before' => [$file],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('maintenance_requests', [
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'title' => 'Saklar dan Lampu Kamar 101 Mati',
            'category' => 'electricity',
            'priority' => 'high',
            'status' => 'reported',
        ]);
    }

    public function test_maintenance_lifecycle_end_to_end_via_web(): void
    {
        $fileBefore = UploadedFile::fake()->image('before.jpg');
        $maintenance = MaintenanceRequest::create([
            'ticket_number' => 'MNT-20261015-TEST1',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'reporter_id' => $this->manager->id,
            'title' => 'Kran Wastafel Bocor',
            'description' => 'Air terus mengalir di wastafel kamar mandi.',
            'category' => MaintenanceCategory::PLUMBING,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
            'sla_due_at' => now()->addHours(48),
        ]);

        // 1. Triase
        $triageResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.maintenance.triage', $maintenance->id), [
                'category' => 'plumbing',
                'priority' => 'high',
                'notes' => 'Kerusakan butuh penanganan segera agar air tidak terbuang',
            ]);
        $triageResponse->assertRedirect();
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::TRIAGED, $maintenance->status);
        $this->assertEquals(MaintenancePriority::HIGH, $maintenance->priority);

        // 2. Tugaskan Teknisi
        $assignResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.maintenance.assign', $maintenance->id), [
                'assigned_to' => $this->staff->id,
                'scheduled_date' => now()->toDateString(),
            ]);
        $assignResponse->assertRedirect();
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::ASSIGNED, $maintenance->status);
        $this->assertEquals($this->staff->id, $maintenance->assigned_to);

        // 3. Mulai Kerja oleh Teknisi
        $startResponse = $this->actingAs($this->staff)
            ->post(route('dashboard.maintenance.start', $maintenance->id));
        $startResponse->assertRedirect();
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $maintenance->status);

        // 4. Serahkan Hasil Perbaikan dengan Foto & Biaya
        $fileAfter = UploadedFile::fake()->image('after.jpg');
        $submitResponse = $this->actingAs($this->staff)
            ->post(route('dashboard.maintenance.submit-verification', $maintenance->id), [
                'action_taken' => 'Mengganti seal karet kran dan memasang isolasi pipa baru.',
                'diagnosis_notes' => 'Seal karet lama sudah getas dan aus.',
                'spare_parts' => '1x Seal Karet Onda 1/2 inch',
                'labor_cost' => 50000,
                'material_cost' => 25000,
                'photos_after' => [$fileAfter],
            ]);
        $submitResponse->assertRedirect();
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::PENDING_VERIFICATION, $maintenance->status);
        $this->assertEquals(75000.00, $maintenance->total_cost);

        // 5. Verifikasi & Selesai oleh Supervisor/Manager
        $verifyResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.maintenance.verify', $maintenance->id), [
                'verification_notes' => 'Kran sudah dites tidak ada kebocoran sama sekali. Kerja rapi.',
            ]);
        $verifyResponse->assertRedirect();
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::COMPLETED, $maintenance->status);
        $this->assertEquals($this->manager->id, $maintenance->verified_by);
        $this->assertNotNull($maintenance->completed_at);
    }

    public function test_tenant_complaint_web_lifecycle_and_linking_to_maintenance(): void
    {
        // 1. Tenant mengajukan keluhan
        $fileComplaint = UploadedFile::fake()->image('ac_rusak.jpg');
        $createResponse = $this->actingAs($this->tenant1)
            ->post(route('dashboard.complaints.store'), [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->room101->id,
                'title' => 'AC Kamar Tidak Dingin',
                'description' => 'Hanya angin saja yang keluar dan terdengar bunyi mendesis.',
                'category' => 'room',
                'priority' => 'high',
                'photos' => [$fileComplaint],
            ]);

        $createResponse->assertRedirect();
        $complaint = Complaint::where('user_id', $this->tenant1->id)->first();
        $this->assertNotNull($complaint);
        $this->assertStringStartsWith('CMP-', $complaint->ticket_number);

        // 2. Petugas meninjau / acknowledge
        $ackResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.complaints.acknowledge', $complaint->id));
        $ackResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::ACKNOWLEDGED, $complaint->status);

        // 3. Petugas menugaskan staf
        $assignResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.complaints.assign', $complaint->id), [
                'assigned_to' => $this->staff->id,
            ]);
        $assignResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals($this->staff->id, $complaint->assigned_to);

        // 4. Thread tanggapan (komentar)
        $commentResponse = $this->actingAs($this->staff)
            ->post(route('dashboard.complaints.comments', $complaint->id), [
                'comment' => 'Baik kak Anisa, teknisi akan datang memeriksa freon pukul 14:00.',
            ]);
        $commentResponse->assertRedirect();
        $this->assertDatabaseHas('complaint_comments', [
            'complaint_id' => $complaint->id,
            'user_id' => $this->staff->id,
            'is_internal' => false,
        ]);

        // 5. Tautkan ke Tiket Maintenance secara Idempoten
        $linkResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.complaints.link-maintenance', $complaint->id), [
                'category' => 'ac',
                'priority' => 'high',
            ]);
        $linkResponse->assertRedirect();
        $complaint->refresh();
        $this->assertNotNull($complaint->maintenance_request_id);

        $linkedMaintenance = MaintenanceRequest::find($complaint->maintenance_request_id);
        $this->assertNotNull($linkedMaintenance);
        $this->assertEquals(MaintenanceCategory::AC, $linkedMaintenance->category);

        // Memanggil link-maintenance lagi tidak boleh menduplikasi tiket
        $linkAgainResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.complaints.link-maintenance', $complaint->id), [
                'category' => 'ac',
                'priority' => 'high',
            ]);
        $linkAgainResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals($linkedMaintenance->id, $complaint->maintenance_request_id);

        // 6. Petugas menyelesaikan keluhan
        $resolveResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.complaints.resolve', $complaint->id), [
                'resolution_notes' => 'Freon AC R32 telah diisi ulang dan suhu sudah kembali sejuk 20 derajat.',
            ]);
        $resolveResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::RESOLVED, $complaint->status);

        // 7. Tenant mengonfirmasi kepuasan & memberi rating
        $confirmResponse = $this->actingAs($this->tenant1)
            ->post(route('dashboard.complaints.confirm-reopen', $complaint->id), [
                'action' => 'confirm',
                'rating' => 5,
                'feedback' => 'Terima kasih, perbaikan sangat cepat dan AC dingin kembali!',
            ]);
        $confirmResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::CLOSED, $complaint->status);
        $this->assertEquals(5, $complaint->rating);
    }

    public function test_tenant_cannot_view_or_modify_other_tenant_complaint(): void
    {
        $complaintTenant1 = Complaint::create([
            'ticket_number' => 'CMP-ANISA-PRIVATE',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'user_id' => $this->tenant1->id,
            'title' => 'Keluhan Pribadi Kamar 101',
            'description' => 'Rahasia kamar',
            'category' => ComplaintCategory::ROOM,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::SUBMITTED,
        ]);

        // Tenant 2 mencoba melihat keluhan Tenant 1 (harus 403)
        $response = $this->actingAs($this->tenant2)->get(route('dashboard.complaints.show', $complaintTenant1->id));
        $response->assertStatus(403);

        // Tenant 2 mencoba menambah komentar di keluhan Tenant 1 (harus 403)
        $commentResponse = $this->actingAs($this->tenant2)
            ->post(route('dashboard.complaints.comments', $complaintTenant1->id), [
                'comment' => 'Penyusup mencoba berkomentar',
            ]);
        $commentResponse->assertStatus(403);
    }

    public function test_housekeeping_web_lifecycle_and_anti_duplicate_task(): void
    {
        // 1. Buat jadwal kebersihan kamar
        $createResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.housekeeping.store'), [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->room102->id,
                'type' => 'turnover_cleaning',
                'priority' => 'normal',
                'scheduled_date' => now()->toDateString(),
                'scheduled_slot' => 'Pagi (08:00 - 11:00)',
                'assigned_to' => $this->staff->id,
                'notes' => 'Pembersihan turnover tamu baru',
            ]);

        $createResponse->assertRedirect();
        $task = HousekeepingTask::where('room_id', $this->room102->id)->first();
        $this->assertNotNull($task);
        $this->assertEquals(HousekeepingStatus::ASSIGNED, $task->status);

        // 2. Pencegahan Duplikasi: Mencoba menjadwalkan tugas aktif untuk kamar & tanggal yang sama
        $duplicateResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.housekeeping.store'), [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->room102->id,
                'type' => 'room_cleaning',
                'scheduled_date' => now()->toDateString(),
            ]);

        $duplicateResponse->assertRedirect();
        $duplicateResponse->assertSessionHas('error');
        $this->assertEquals(1, HousekeepingTask::where('room_id', $this->room102->id)->count());

        // 3. Staf Memulai Tugas dengan Foto Before
        $fileBefore = UploadedFile::fake()->image('kamar_kotor.jpg');
        $startResponse = $this->actingAs($this->staff)
            ->post(route('dashboard.housekeeping.start', $task->id), [
                'photos_before' => [$fileBefore],
            ]);
        $startResponse->assertRedirect();
        $task->refresh();
        $this->assertEquals(HousekeepingStatus::IN_PROGRESS, $task->status);

        // 4. Staf Menyerahkan Hasil Pembersihan dengan Checklist & Bukti After
        $fileAfter = UploadedFile::fake()->image('kamar_bersih.jpg');
        $checklistIds = array_column($task->checklist, 'id');

        $submitResponse = $this->actingAs($this->staff)
            ->post(route('dashboard.housekeeping.submit-verification', $task->id), [
                'checklist_done' => $checklistIds,
                'damaged_or_missing_items' => 'Gantungan baju patah 1 buah.',
                'notes' => 'Sudah dipel bersih dan disemprot pewangi ruangan.',
                'photos_after' => [$fileAfter],
            ]);
        $submitResponse->assertRedirect();
        $task->refresh();
        $this->assertEquals(HousekeepingStatus::PENDING_VERIFICATION, $task->status);
        $this->assertEquals('Gantungan baju patah 1 buah.', $task->damaged_or_missing_items);

        // 5. Supervisor/Manager memverifikasi hasil kebersihan
        $verifyResponse = $this->actingAs($this->manager)
            ->post(route('dashboard.housekeeping.verify', $task->id), [
                'verification_notes' => 'Kamar sudah diperiksa, rapi dan harum sesuai SOP.',
            ]);
        $verifyResponse->assertRedirect();
        $task->refresh();
        $this->assertEquals(HousekeepingStatus::COMPLETED, $task->status);
        $this->assertEquals($this->manager->id, $task->verified_by);
    }

    public function test_cashier_cannot_perform_technical_maintenance_actions(): void
    {
        $maintenance = MaintenanceRequest::create([
            'ticket_number' => 'MNT-CASHIER-TEST',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'reporter_id' => $this->manager->id,
            'title' => 'Kerusakan Teknis',
            'description' => 'Test deskripsi',
            'category' => MaintenanceCategory::ELECTRICITY,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'sla_due_at' => now()->addHours(48),
        ]);

        // Kasir mencoba serahkan hasil (harus error)
        $response = $this->actingAs($this->cashier)
            ->post(route('dashboard.maintenance.submit-verification', $maintenance->id), [
                'action_taken' => 'Kasir mencoba mengubah data teknis',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $maintenance->status);

        // Kasir mencoba verify
        $maintenance->update(['status' => MaintenanceStatus::PENDING_VERIFICATION]);
        $verifyResponse = $this->actingAs($this->cashier)
            ->post(route('dashboard.maintenance.verify', $maintenance->id), [
                'verification_notes' => 'Kasir memverifikasi',
            ]);
        $verifyResponse->assertRedirect();
        $verifyResponse->assertSessionHas('error');
        $maintenance->refresh();
        $this->assertEquals(MaintenanceStatus::PENDING_VERIFICATION, $maintenance->status);
    }

    public function test_cashier_cannot_perform_housekeeping_technical_actions(): void
    {
        $task = HousekeepingTask::create([
            'task_number' => 'HSK-CASHIER-TEST',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room102->id,
            'type' => HousekeepingType::ROOM_CLEANING,
            'scheduled_date' => now()->toDateString(),
            'status' => HousekeepingStatus::IN_PROGRESS,
            'checklist' => [['id' => 1, 'task' => 'Sapu lantai', 'is_done' => false]],
        ]);

        // Kasir mencoba serahkan hasil verifikasi
        $response = $this->actingAs($this->cashier)
            ->post(route('dashboard.housekeeping.submit-verification', $task->id), [
                'checklist_done' => [1],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $task->refresh();
        $this->assertEquals(HousekeepingStatus::IN_PROGRESS, $task->status);
    }

    public function test_cross_property_access_is_forbidden(): void
    {
        // Staf Properti A mencoba mengakses tugas housekeeping di Properti B (harus 403)
        $taskPropB = HousekeepingTask::create([
            'task_number' => 'HSK-PROPB-TEST',
            'property_id' => $this->propertyB->id,
            'room_id' => $this->room201->id,
            'type' => HousekeepingType::ROOM_CLEANING,
            'scheduled_date' => now()->toDateString(),
            'status' => HousekeepingStatus::SCHEDULED,
        ]);

        $response = $this->actingAs($this->staff)->get(route('dashboard.housekeeping.show', $taskPropB->id));
        $response->assertStatus(403);

        // Staf Properti A mencoba mengakses maintenance di Properti B (harus 403)
        $mntPropB = MaintenanceRequest::create([
            'ticket_number' => 'MNT-PROPB-TEST',
            'property_id' => $this->propertyB->id,
            'room_id' => $this->room201->id,
            'reporter_id' => $this->superAdmin->id,
            'title' => 'MNT di Properti B',
            'description' => 'Kerusakan',
            'category' => MaintenanceCategory::OTHER,
            'priority' => MaintenancePriority::NORMAL,
            'status' => MaintenanceStatus::REPORTED,
        ]);

        $mntResponse = $this->actingAs($this->staff)->get(route('dashboard.maintenance.show', $mntPropB->id));
        $mntResponse->assertStatus(403);
    }

    public function test_tenant_can_reopen_complaint_with_mandatory_reason(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'CMP-REOPEN-TEST',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->room101->id,
            'user_id' => $this->tenant1->id,
            'title' => 'Wastafel Mampet',
            'description' => 'Saluran air lambat',
            'category' => ComplaintCategory::ROOM,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::RESOLVED,
            'resolved_at' => now(),
        ]);

        // Reopen tanpa alasan wajib gagal validasi
        $invalidResponse = $this->actingAs($this->tenant1)
            ->post(route('dashboard.complaints.confirm-reopen', $complaint->id), [
                'action' => 'reopen',
                'reason' => '',
            ]);
        $invalidResponse->assertSessionHasErrors('reason');

        // Reopen dengan alasan berhasil
        $validResponse = $this->actingAs($this->tenant1)
            ->post(route('dashboard.complaints.confirm-reopen', $complaint->id), [
                'action' => 'reopen',
                'reason' => 'Air masih tergenang dan bau belum hilang.',
            ]);
        $validResponse->assertRedirect();
        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::REOPENED, $complaint->status);
        $this->assertEquals('Air masih tergenang dan bau belum hilang.', $complaint->reopen_reason);
    }
}

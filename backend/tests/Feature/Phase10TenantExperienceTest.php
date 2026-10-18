<?php

namespace Tests\Feature;

use App\Enums\HousekeepingStatus;
use App\Enums\InspectionStatus;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Booking;
use App\Models\HousekeepingTask;
use App\Models\Inspection;
use App\Models\Property;
use App\Models\Room;
use App\Models\TenantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase10TenantExperienceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $manager;
    protected User $staff;
    protected User $tenant;
    protected User $otherTenant;
    protected Property $property;
    protected Room $room;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => Role::SUPER_ADMIN]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER]);
        $this->staff = User::factory()->create(['role' => Role::STAFF]);
        $this->tenant = User::factory()->create(['role' => Role::TENANT]);
        $this->otherTenant = User::factory()->create(['role' => Role::TENANT]);

        $this->property = Property::create([
            'code' => 'WCI',
            'name' => 'Wisma Cempaka Indah',
            'slug' => 'wisma-cempaka-indah',
            'address' => 'Jl. Cempaka No. 45',
            'city' => 'Malang',
            'type' => 'boarding_house',
        ]);

        DB::table('property_users')->insert([
            ['user_id' => $this->manager->id, 'property_id' => $this->property->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $this->staff->id, 'property_id' => $this->property->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roomType = \App\Models\RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Deluxe AC',
            'base_price' => 1200000,
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => 'occupied',
        ]);

        $this->booking = Booking::create([
            'booking_code' => 'BK-TEST-1001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(1)->toDateString(),
            'total_amount' => 1200000,
            'status' => 'active',
        ]);
    }

    // ==========================================
    // 1. FITUR A: PENGUMUMAN PENYEWA
    // ==========================================
    public function test_tenant_can_list_announcements_targeted_to_them_and_cannot_read_internal_staff_announcements(): void
    {
        // 1. Pengumuman untuk Tenant di properti ini
        $announcementTenant = Announcement::create([
            'property_id' => $this->property->id,
            'author_id' => $this->manager->id,
            'title' => 'Kerja Bakti Kost',
            'content' => 'Akan diadakan pembersihan lingkungan bersama.',
            'target_role' => 'tenant',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        // 2. Pengumuman internal hanya untuk Staf
        $announcementStaff = Announcement::create([
            'property_id' => $this->property->id,
            'author_id' => $this->manager->id,
            'title' => 'SOP Shift Internal Staf',
            'content' => 'Jadwal pergantian jaga malam.',
            'target_role' => 'staff',
            'is_published' => true,
            'published_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/announcements');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $titles = collect($response->json('data.announcements.data'))->pluck('title');
        $this->assertTrue($titles->contains('Kerja Bakti Kost'));
        $this->assertFalse($titles->contains('SOP Shift Internal Staf'));
    }

    public function test_tenant_can_mark_announcement_as_read_idempotently(): void
    {
        $announcement = Announcement::create([
            'property_id' => $this->property->id,
            'author_id' => $this->manager->id,
            'title' => 'Pemberitahuan Air',
            'content' => 'Tandon air akan dibersihkan.',
            'target_role' => 'all',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Mark as read pertama kali
        $res1 = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/announcements/{$announcement->id}/read");
        $res1->assertStatus(200);

        // Mark as read kedua kali (idempoten)
        $res2 = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/announcements/{$announcement->id}/read");
        $res2->assertStatus(200);

        // Pastikan hanya 1 record di announcement_reads
        $count = DB::table('announcement_reads')
            ->where('announcement_id', $announcement->id)
            ->where('user_id', $this->tenant->id)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ==========================================
    // 2. FITUR B: PENGAJUAN PEMBERSIHAN KAMAR
    // ==========================================
    public function test_tenant_can_request_room_cleaning_for_active_room(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/housekeeping/request', [
                'scheduled_date' => now()->addDay()->toDateString(),
                'scheduled_slot' => 'Pagi (08:00 - 11:00)',
                'priority' => 'normal',
                'notes' => 'Tolong bersihkan kaca jendela kamar.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.room_id', $this->room->id);

        $this->assertDatabaseHas('housekeeping_tasks', [
            'room_id' => $this->room->id,
            'property_id' => $this->property->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_tenant_cannot_request_duplicate_cleaning_on_same_date(): void
    {
        $date = now()->addDays(2)->toDateString();

        // Pengajuan pertama
        $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/housekeeping/request', [
                'scheduled_date' => $date,
                'notes' => 'Pembersihan 1',
            ])->assertStatus(201);

        // Pengajuan kedua di tanggal sama harus ditolak (mencegah spam)
        $res2 = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/housekeeping/request', [
                'scheduled_date' => $date,
                'notes' => 'Pembersihan 2',
            ]);

        $res2->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_tenant_can_cancel_scheduled_cleaning_request(): void
    {
        $createRes = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/housekeeping/request', [
                'scheduled_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Mau dibatalkan',
            ]);
        $taskId = $createRes->json('data.id');

        $cancelRes = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/housekeeping/{$taskId}/tenant-cancel", [
                'reason' => 'Ada jadwal keluar kota mendadak.',
            ]);

        $cancelRes->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('housekeeping_tasks', [
            'id' => $taskId,
            'status' => 'cancelled',
        ]);
    }

    public function test_tenant_can_get_active_room_info(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/tenant/active-room');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_number', '101')
            ->assertJsonPath('data.booking_code', 'BK-TEST-1001')
            ->assertJsonPath('data.property_name', 'Wisma Cempaka Indah');
    }

    public function test_tenant_without_active_room_receives_ok_with_null_data(): void
    {
        $response = $this->actingAs($this->otherTenant, 'sanctum')
            ->getJson('/api/v1/tenant/active-room');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null);
    }

    public function test_tenant_with_booking_matched_by_email_can_get_active_room(): void
    {
        $unlinkedTenant = User::factory()->create([
            'role' => Role::TENANT,
            'email' => 'guest.stay@example.com',
        ]);

        Booking::create([
            'booking_code' => 'BK-TEST-GUEST-01',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'user_id' => null,
            'guest_email' => 'guest.stay@example.com',
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(1)->toDateString(),
            'total_amount' => 1200000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($unlinkedTenant, 'sanctum')
            ->getJson('/api/v1/tenant/active-room');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_code', 'BK-TEST-GUEST-01')
            ->assertJsonPath('data.room_number', '101');
    }

    // ==========================================
    // 3. FITUR C: PENGAJUAN INSPEKSI KAMAR
    // ==========================================
    public function test_tenant_can_request_room_inspection_for_active_room(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/inspections/request', [
                'type' => 'periodic',
                'reason' => 'Pemeriksaan Rutin 6 Bulanan',
                'description' => 'Mohon dicek kondisi remote AC yang baterainya longgar.',
                'scheduled_date' => now()->addDays(4)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_id', $this->room->id)
            ->assertJsonPath('data.tenant_id', $this->tenant->id)
            ->assertJsonPath('data.status', 'scheduled');

        $this->assertDatabaseHas('inspections', [
            'room_id' => $this->room->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'scheduled',
        ]);
    }

    // ==========================================
    // 4. FITUR E: VERIFIKASI NIK & KEUNIKAN GLOBAL
    // ==========================================
    public function test_tenant_identity_verification_with_valid_16_digit_nik(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/tenant/identity/verify', [
                'nik' => '3507123456789012',
                'emergency_name' => 'Bapak Budi',
                'emergency_phone' => '08123456789',
                'occupation' => 'Mahasiswa',
                'vehicle_type' => 'motor',
                'vehicle_plate' => 'N 1234 AB',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_nik_verified', true)
            ->assertJsonPath('data.masked_nik', '3507********9012');

        $profile = \App\Models\TenantProfile::where('user_id', $this->tenant->id)->first();
        $this->assertNotNull($profile);
        $this->assertTrue($profile->is_nik_verified);
        $this->assertEquals('3507123456789012', $profile->ktp_number); // Decrypted via cast
        $this->assertEquals('3507********9012', $profile->masked_nik);

        // Verifikasi fisik di database: tersimpan terenkripsi, BUKAN plaintext
        $rawRow = DB::table('tenant_profiles')->where('user_id', $this->tenant->id)->first();
        $this->assertNotEquals('3507123456789012', $rawRow->ktp_number);
        $this->assertNotEmpty($rawRow->nik_hash);
        $this->assertEquals(hash_hmac('sha256', '3507123456789012', config('app.key')), $rawRow->nik_hash);
    }

    public function test_tenant_identity_verification_rejects_invalid_nik_format(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/tenant/identity/verify', [
                'nik' => '12345', // Kurang dari 16 digit
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nik']);
    }

    public function test_unique_nik_prevents_duplicate_registration_across_accounts(): void
    {
        // Tenant 1 mendaftar NIK
        $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/tenant/identity/verify', [
                'nik' => '3507999988887777',
            ])->assertStatus(200);

        // Tenant 2 mencoba mendaftar dengan NIK yang sama
        $response = $this->actingAs($this->otherTenant, 'sanctum')
            ->postJson('/api/v1/tenant/identity/verify', [
                'nik' => '3507999988887777',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ==========================================
    // 5. FITUR F: RESET PASSWORD PENYEWA OLEH PENGELOLA
    // ==========================================
    public function test_authorized_staff_can_initiate_tenant_password_reset(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/auth/tenant/password-reset/initiate', [
                'tenant_id' => $this->tenant->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $resetToken = $response->json('data.reset_token');
        $this->assertNotEmpty($resetToken);

        // Pastikan token yang tersimpan di DB ter-hash secara aman
        $record = DB::table('password_reset_tokens')->where('email', $this->tenant->email)->first();
        $this->assertNotNull($record);
        $this->assertEquals(hash('sha256', $resetToken), $record->token);
    }

    public function test_user_can_complete_password_reset_with_valid_token(): void
    {
        $initRes = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/auth/tenant/password-reset/initiate', [
                'tenant_id' => $this->tenant->id,
            ]);

        $token = $initRes->json('data.reset_token');

        // Selesaikan reset password
        $completeRes = $this->postJson('/api/v1/auth/password-reset/complete', [
            'email' => $this->tenant->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $completeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Verifikasi password baru dapat digunakan untuk login
        $this->tenant->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $this->tenant->password));
    }

    public function test_announcement_route_unread_count_is_not_captured_by_id_parameter(): void
    {
        // Panggil endpoint unread-count
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/announcements/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['unread_count']]);

        // Panggil endpoint {id} dengan string non-numerik harus 404 (karena whereNumber constraint)
        $invalidIdResponse = $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/announcements/non-numeric-id');

        $invalidIdResponse->assertStatus(404);
    }

    public function test_password_reset_revokes_active_sanctum_tokens(): void
    {
        // Berikan personal access token aktif ke tenant
        $plainSanctumToken = $this->tenant->createToken('test-mobile-token')->plainTextToken;
        $this->assertCount(1, $this->tenant->tokens);

        // Verifikasi token aktif bisa mengakses profile
        $validAuthResponse = $this->withHeader('Authorization', 'Bearer ' . $plainSanctumToken)
            ->getJson('/api/v1/auth/profile');
        $validAuthResponse->assertStatus(200);

        // Manajer menginisiasi reset password
        $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/auth/tenant/password-reset/initiate', [
                'tenant_id' => $this->tenant->id,
            ])->assertStatus(200);

        // Pastikan token Sanctum di database telah dihapus
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->tenant->id,
        ]);

        // Bersihkan guard auth test runner agar Sanctum memvalidasi Bearer header aktual
        \Illuminate\Support\Facades\Auth::forgetGuards();

        // Request dengan token Sanctum lama HARUS ditolak 401 Unauthorized
        $revokedAuthResponse = $this->withHeader('Authorization', 'Bearer ' . $plainSanctumToken)
            ->getJson('/api/v1/auth/profile');
        $revokedAuthResponse->assertStatus(401);
    }

    public function test_password_reset_rejects_expired_or_invalid_tokens(): void
    {
        $initRes = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/auth/tenant/password-reset/initiate', [
                'tenant_id' => $this->tenant->id,
            ]);
        $token = $initRes->json('data.reset_token');

        // Uji coba token yang salah
        $wrongTokenRes = $this->postJson('/api/v1/auth/password-reset/complete', [
            'email' => $this->tenant->email,
            'token' => 'invalid-token-string-xyz',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $wrongTokenRes->assertStatus(422)
            ->assertJsonPath('success', false);

        // Uji coba token kedaluwarsa (> 60 menit)
        DB::table('password_reset_tokens')
            ->where('email', $this->tenant->email)
            ->update(['created_at' => now()->subMinutes(65)]);

        $expiredRes = $this->postJson('/api/v1/auth/password-reset/complete', [
            'email' => $this->tenant->email,
            'token' => $token,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);
        $expiredRes->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_database_unique_constraint_enforces_nik_uniqueness_at_schema_level(): void
    {
        $nikHash = hash_hmac('sha256', '3507111122223333', config('app.key'));

        // Simpan row pertama secara langsung
        DB::table('tenant_profiles')->insert([
            'user_id' => $this->tenant->id,
            'ktp_number' => 'encrypted-sample-1',
            'nik_hash' => $nikHash,
            'is_nik_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Coba bypass lock aplikasi dan insert langsung duplicate nik_hash
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('tenant_profiles')->insert([
            'user_id' => $this->otherTenant->id,
            'ktp_number' => 'encrypted-sample-2',
            'nik_hash' => $nikHash, // Duplikat hash NIK
            'is_nik_verified' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

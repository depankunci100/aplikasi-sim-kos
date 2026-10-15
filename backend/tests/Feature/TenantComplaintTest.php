<?php

namespace Tests\Feature;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Complaint;
use App\Models\Invoice;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantComplaintTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $managerA;
    protected User $staffA;
    protected User $tenantA;
    protected User $tenantB;
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
        $this->staffA = User::factory()->create(['role' => Role::STAFF]);
        $this->tenantA = User::factory()->create(['role' => Role::TENANT, 'name' => 'Penyewa Siti']);
        $this->tenantB = User::factory()->create(['role' => Role::TENANT, 'name' => 'Penyewa Doni']);
        $this->staffB = User::factory()->create(['role' => Role::STAFF]);

        $this->propertyA = Property::create([
            'code' => 'PROP-CMP-A',
            'name' => 'Kos Alamanda',
            'type' => 'kos',
            'address' => 'Jl. Alamanda 5',
            'city' => 'Yogyakarta',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-CMP-B',
            'name' => 'Kos Bougenville',
            'type' => 'kos',
            'address' => 'Jl. Bougenville 10',
            'city' => 'Solo',
            'is_active' => true,
        ]);

        $this->propertyA->users()->attach($this->managerA->id, ['role' => 'manager']);
        $this->propertyA->users()->attach($this->staffA->id, ['role' => 'staff']);
        $this->propertyB->users()->attach($this->staffB->id, ['role' => 'staff']);

        $typeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Superior',
            'base_price_monthly' => 1200000,
        ]);

        $this->roomA1 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $typeA->id,
            'room_number' => '301',
            'status' => 'occupied',
        ]);

        $this->bookingA1 = Booking::create([
            'booking_code' => 'BK-CMP-301',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'rate_amount' => 1200000,
            'deposit_amount' => 500000,
            'total_amount' => 1700000,
            'status' => 'confirmed',
        ]);
    }

    public function test_tenant_can_submit_complaint_with_photo(): void
    {
        $photo = UploadedFile::fake()->image('bukti_keluhan.jpg');

        $response = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson('/api/v1/complaints', [
                'property_id' => $this->propertyA->id,
                'room_id' => $this->roomA1->id,
                'category' => ComplaintCategory::ROOM->value,
                'priority' => ComplaintPriority::NORMAL->value,
                'title' => 'Plafon bocor saat hujan deras',
                'description' => 'Ada tetesan air dari atap di pojok kamar dekat jendela.',
                'photos' => [$photo],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Plafon bocor saat hujan deras')
            ->assertJsonPath('data.status', ComplaintStatus::SUBMITTED->value);

        $complaint = Complaint::where('title', 'Plafon bocor saat hujan deras')->first();
        $this->assertNotNull($complaint);
        $this->assertStringStartsWith('CMP-', $complaint->ticket_number);
        $this->assertEquals($this->tenantA->id, $complaint->user_id);
        $this->assertNotEmpty($complaint->photos);
    }

    public function test_tenant_isolation_cannot_view_or_access_other_tenant_complaint(): void
    {
        $complaintA = Complaint::create([
            'ticket_number' => 'CMP-20261015-00001',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'category' => ComplaintCategory::FACILITY,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::SUBMITTED,
            'title' => 'WiFi mati di lantai 3',
            'description' => 'Sinyal tidak terdeteksi sejak pagi.',
        ]);

        // Tenant B mengakses list: tidak boleh melihat komplain milik Tenant A
        $listResponse = $this->actingAs($this->tenantB, 'sanctum')
            ->getJson('/api/v1/complaints');
        $listResponse->assertStatus(200);
        $this->assertEmpty($listResponse->json('data.data'));

        // Tenant B mencoba mengakses detail komplain Tenant A secara langsung -> 403 Forbidden
        $detailResponse = $this->actingAs($this->tenantB, 'sanctum')
            ->getJson("/api/v1/complaints/{$complaintA->id}");
        $detailResponse->assertStatus(403);
    }

    public function test_complaint_acknowledgement_and_assignment(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'CMP-20261015-00002',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'category' => ComplaintCategory::SERVICE,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::SUBMITTED,
            'title' => 'Sampah depan kamar belum diangkat',
            'description' => 'Sudah 2 hari belum diambil petugas.',
        ]);

        // 1. Staff acknowledge
        $ackResponse = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/acknowledge");

        $ackResponse->assertStatus(200)
            ->assertJsonPath('data.status', ComplaintStatus::ACKNOWLEDGED->value);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::ACKNOWLEDGED, $complaint->status);
        $this->assertNotNull($complaint->first_responded_at);

        // 2. Staff assign penanggung jawab
        $assignResponse = $this->actingAs($this->managerA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/assign", [
                'assigned_to' => $this->staffA->id,
            ]);

        $assignResponse->assertStatus(200)
            ->assertJsonPath('data.status', ComplaintStatus::IN_PROGRESS->value)
            ->assertJsonPath('data.assigned_to', $this->staffA->id);
    }

    public function test_threaded_comments_and_internal_notes_isolation(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'CMP-20261015-00003',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'category' => ComplaintCategory::ROOM,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::IN_PROGRESS,
            'title' => 'Kran wastafel dol',
            'description' => 'Air terus menetes.',
        ]);

        // Staff kirim pesan internal (hanya untuk staff/manajer)
        $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/comments", [
                'comment' => 'Catatan internal: Teknisi butuh sparepart seal karet ukuran 1/2.',
                'is_internal' => true,
            ])
            ->assertStatus(201);

        // Staff kirim tanggapan publik untuk tenant
        $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/comments", [
                'comment' => 'Halo Kak Siti, teknisi kami sedang menuju kamar.',
                'is_internal' => false,
            ])
            ->assertStatus(201);

        // Tenant melihat detail: komentar internal TIDAK boleh muncul
        $tenantDetail = $this->actingAs($this->tenantA, 'sanctum')
            ->getJson("/api/v1/complaints/{$complaint->id}");

        $tenantComments = $tenantDetail->json('data.comments');
        $this->assertCount(1, $tenantComments);
        $this->assertEquals('Halo Kak Siti, teknisi kami sedang menuju kamar.', $tenantComments[0]['comment']);

        // Staff melihat detail: seluruh komentar internal & publik terlihat
        $staffDetail = $this->actingAs($this->staffA, 'sanctum')
            ->getJson("/api/v1/complaints/{$complaint->id}");

        $this->assertCount(2, $staffDetail->json('data.comments'));
    }

    public function test_link_complaint_to_maintenance_is_idempotent(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'CMP-20261015-00004',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'category' => ComplaintCategory::ROOM,
            'priority' => ComplaintPriority::HIGH,
            'status' => ComplaintStatus::IN_PROGRESS,
            'title' => 'Pintu balkon tidak bisa dikunci',
            'description' => 'Slot gerendel terlepas.',
        ]);

        // Tautkan pertama kali
        $linkResponse1 = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/link-maintenance", [
                'category' => 'door_lock',
                'priority' => 'high',
                'notes' => 'Segera perbaiki slot kunci.',
            ]);

        $linkResponse1->assertStatus(201);
        $maintId = $linkResponse1->json('data.id');
        $this->assertNotNull($maintId);

        $complaint->refresh();
        $this->assertEquals($maintId, $complaint->maintenance_request_id);

        // Tautkan kedua kali (harus idempoten: mengembalikan maintenance yang sama tanpa tiket baru)
        $linkResponse2 = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/link-maintenance", [
                'category' => 'door_lock',
            ]);

        $linkResponse2->assertStatus(201);
        $this->assertEquals($maintId, $linkResponse2->json('data.id'));
    }

    public function test_complaint_resolution_tenant_confirmation_and_reopen(): void
    {
        $complaint = Complaint::create([
            'ticket_number' => 'CMP-20261015-00005',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA1->id,
            'user_id' => $this->tenantA->id,
            'category' => ComplaintCategory::ROOM,
            'priority' => ComplaintPriority::NORMAL,
            'status' => ComplaintStatus::IN_PROGRESS,
            'title' => 'Lampu belajar mati',
            'description' => 'Bohlam putus.',
        ]);

        // 1. Petugas menyelesaikan keluhan
        $resolveResponse = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/resolve", [
                'resolution_notes' => 'Bohlam LED Philips 9W baru telah dipasang dan dites menyala terang.',
            ]);

        $resolveResponse->assertStatus(200)
            ->assertJsonPath('data.status', ComplaintStatus::RESOLVED->value);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::RESOLVED, $complaint->status);
        $this->assertNotNull($complaint->resolved_at);

        // 2. Tenant membuka kembali karena lampu berkedip
        $reopenResponse = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/confirm-reopen", [
                'action' => 'reopen',
                'reason' => 'Lampu kadang berkedip-kedip saat dinyalakan.',
            ]);

        $reopenResponse->assertStatus(200)
            ->assertJsonPath('data.status', ComplaintStatus::REOPENED->value);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::REOPENED, $complaint->status);

        // 3. Petugas selesaikan lagi setelah ganti fitting
        $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/resolve", [
                'resolution_notes' => 'Fitting lampu diganti baru, sekarang stabil.',
            ])->assertStatus(200);

        // 4. Tenant konfirmasi selesai dengan rating 5 bintang
        $confirmResponse = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson("/api/v1/complaints/{$complaint->id}/confirm-reopen", [
                'action' => 'confirm',
                'rating' => 5,
                'feedback' => 'Terima kasih respon cepatnya!',
            ]);

        $confirmResponse->assertStatus(200)
            ->assertJsonPath('data.status', ComplaintStatus::CLOSED->value);

        $complaint->refresh();
        $this->assertEquals(ComplaintStatus::CLOSED, $complaint->status);
        $this->assertEquals(5, $complaint->rating);
        $this->assertEquals('Terima kasih respon cepatnya!', $complaint->tenant_feedback);
    }

    public function test_billing_complaint_does_not_modify_invoices_or_payments(): void
    {
        $invoice = Invoice::create([
            'booking_id' => $this->bookingA1->id,
            'user_id' => $this->tenantA->id,
            'invoice_number' => 'INV-202610-001',
            'period_month' => 10,
            'period_year' => 2026,
            'subtotal' => 1200000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total_amount' => 1200000,
            'status' => 'unpaid',
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson('/api/v1/complaints', [
                'property_id' => $this->propertyA->id,
                'category' => ComplaintCategory::BILLING->value,
                'priority' => ComplaintPriority::HIGH->value,
                'title' => 'Ada ketidaksesuaian jumlah tagihan invoice',
                'description' => 'Mohon dicek kembali perhitungan diskon awal.',
            ]);

        $response->assertStatus(201);

        // Pastikan invoice tidak berubah sedikitpun
        $invoice->refresh();
        $this->assertEquals(1200000, $invoice->total_amount);
        $this->assertEquals('unpaid', $invoice->status->value);
    }
}

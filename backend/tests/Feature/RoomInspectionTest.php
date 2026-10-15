<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\InspectionItemCondition;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoomInspectionTest extends TestCase
{
    use RefreshDatabase;

    protected Property $property;
    protected Room $room;
    protected User $owner;
    protected User $manager;
    protected User $staff;
    protected User $tenant;
    protected User $otherTenant;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->property = Property::create([
            'code' => 'PROP-INSP-01',
            'name' => 'Wisma Asri Harmoni',
            'type' => 'kos',
            'address' => 'Jl. Asri No. 10',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create(['name' => 'Haji Huda', 'role' => Role::OWNER]);
        $this->manager = User::factory()->create(['name' => 'Bambang Manager', 'role' => Role::MANAGER]);
        $this->staff = User::factory()->create(['name' => 'Joko Staff', 'role' => Role::STAFF]);
        $this->tenant = User::factory()->create(['name' => 'Rizky Tenant', 'role' => Role::TENANT]);
        $this->otherTenant = User::factory()->create(['name' => 'Dewi Other Tenant', 'role' => Role::TENANT]);

        DB::table('property_users')->insert([
            ['property_id' => $this->property->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property->id, 'user_id' => $this->manager->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property->id, 'user_id' => $this->staff->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Deluxe AC',
            'base_price_monthly' => 1500000.00,
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'available',
            'is_active' => true,
        ]);

        // Aset kamar terdaftar
        RoomAsset::create([
            'room_id' => $this->room->id,
            'item_name' => 'Kasur Springbed Queen',
            'quantity' => 1,
            'condition' => 'good',
        ]);
        RoomAsset::create([
            'room_id' => $this->room->id,
            'item_name' => 'Unit AC Daikin 1 PK',
            'quantity' => 1,
            'condition' => 'good',
        ]);

        $this->booking = Booking::create([
            'booking_code' => 'BK-INSP-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rate_amount' => 1500000.00,
            'total_amount' => 1500000.00,
            'status' => BookingStatus::CONFIRMED,
        ]);
    }

    public function test_manager_can_schedule_inspection_and_populates_checklist_from_assets(): void
    {
        $payload = [
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'type' => 'check_in',
            'scheduled_date' => now()->toDateString(),
            'inspector_notes' => 'Pemeriksaan awal sebelum serah terima kunci',
        ];

        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/v1/inspections', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.room_id', $this->room->id)
            ->assertJsonPath('data.tenant_id', $this->tenant->id);

        $inspectionId = $response->json('data.id');
        $inspection = Inspection::with('items')->find($inspectionId);

        $this->assertNotNull($inspection);
        $this->assertEquals(InspectionStatus::SCHEDULED, $inspection->status);
        $this->assertCount(2, $inspection->items);
        $this->assertEquals('Kasur Springbed Queen', $inspection->items->first()->item_name);

        // Audit trail tercatat
        $this->assertDatabaseHas('inspection_logs', [
            'inspection_id' => $inspectionId,
            'action' => 'created',
            'user_id' => $this->manager->id,
        ]);
    }

    public function test_tenant_cannot_create_inspection(): void
    {
        $payload = [
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'type' => 'check_in',
            'scheduled_date' => now()->toDateString(),
        ];

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/inspections', $payload);

        $response->assertStatus(403);
    }

    public function test_staff_can_start_inspection(): void
    {
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-TEST-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'tenant_id' => $this->tenant->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::SCHEDULED,
            'scheduled_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/start");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'in_progress');

        $inspection->refresh();
        $this->assertEquals(InspectionStatus::IN_PROGRESS, $inspection->status);
        $this->assertNotNull($inspection->started_at);
    }

    public function test_staff_can_update_checklist_items_and_upload_photo(): void
    {
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-TEST-002',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'tenant_id' => $this->tenant->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::IN_PROGRESS,
            'scheduled_date' => now()->toDateString(),
            'started_at' => now(),
        ]);

        $item = InspectionItem::create([
            'inspection_id' => $inspection->id,
            'item_name' => 'Kran Kamar Mandi',
            'category' => 'bathroom',
            'condition' => InspectionItemCondition::GOOD,
        ]);

        // Update item condition ke DAMAGED
        $updateResp = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspections/{$inspection->id}/items/{$item->id}", [
                'condition' => 'damaged',
                'notes' => 'Kran air bocor sedikit di bagian tuas',
                'repair_required' => true,
                'estimated_cost' => 75000.00,
            ]);

        $updateResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.condition', 'damaged');

        $item->refresh();
        $this->assertEquals(InspectionItemCondition::DAMAGED, $item->condition);
        $this->assertTrue($item->repair_required);
        $this->assertEquals(75000.00, (float)$item->estimated_cost);

        // Upload foto bukti
        $file = UploadedFile::fake()->image('kran_bocor.jpg', 600, 400);

        $uploadResp = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/media", [
                'photo' => $file,
                'inspection_item_id' => $item->id,
                'caption' => 'Foto kran air menetes',
            ]);

        $uploadResp->assertStatus(201)
            ->assertJsonPath('success', true);

        $mediaId = $uploadResp->json('data.id');

        // Streaming foto oleh staf yang berwenang -> 200
        $streamResp = $this->actingAs($this->staff, 'sanctum')
            ->get("/api/v1/inspections/{$inspection->id}/media/{$mediaId}");
        $streamResp->assertStatus(200);

        // Streaming foto oleh tenant lain -> 403
        $this->actingAs($this->otherTenant, 'sanctum')
            ->get("/api/v1/inspections/{$inspection->id}/media/{$mediaId}")
            ->assertStatus(403);
    }

    public function test_staff_completes_inspection_and_tenant_approves(): void
    {
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-TEST-003',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'tenant_id' => $this->tenant->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::IN_PROGRESS,
            'scheduled_date' => now()->toDateString(),
            'started_at' => now(),
        ]);

        // Selesaikan inspeksi oleh staf
        $completeResp = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/complete", [
                'overall_condition' => 'good',
                'inspector_notes' => 'Kamar bersih dan fasilitas lengkap berfungsi',
            ]);

        $completeResp->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        // Tenant menyetujui hasil inspeksi
        $approveResp = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/approve", [
                'feedback' => 'Semua sudah sesuai dan siap ditempati.',
            ]);

        $approveResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved_by_tenant');

        $inspection->refresh();
        $this->assertEquals(InspectionStatus::APPROVED_BY_TENANT, $inspection->status);
        $this->assertNotNull($inspection->tenant_decision_at);
        $this->assertEquals('Semua sudah sesuai dan siap ditempati.', $inspection->tenant_feedback);
    }

    public function test_tenant_can_object_to_inspection_with_reason(): void
    {
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-TEST-004',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'tenant_id' => $this->tenant->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::COMPLETED,
            'scheduled_date' => now()->toDateString(),
            'completed_at' => now(),
        ]);

        $objResp = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/inspections/{$inspection->id}/object", [
                'notes' => 'AC kamar mengeluarkan bunyi bising dan tidak dingin saat dinyalakan.',
            ]);

        $objResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'objected_by_tenant');

        $inspection->refresh();
        $this->assertEquals(InspectionStatus::OBJECTED_BY_TENANT, $inspection->status);
        $this->assertEquals('AC kamar mengeluarkan bunyi bising dan tidak dingin saat dinyalakan.', $inspection->tenant_feedback);
    }

    public function test_inspection_comparison_identifies_degraded_items_and_calculates_damages(): void
    {
        // 1. Baseline Check-In
        $baseline = Inspection::create([
            'inspection_code' => 'INSP-BASE-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::APPROVED_BY_TENANT,
            'scheduled_date' => now()->subMonth()->toDateString(),
        ]);

        InspectionItem::create([
            'inspection_id' => $baseline->id,
            'item_name' => 'Meja Belajar Kayu',
            'category' => 'furniture',
            'condition' => InspectionItemCondition::GOOD,
        ]);
        InspectionItem::create([
            'inspection_id' => $baseline->id,
            'item_name' => 'Remote AC',
            'category' => 'electronics',
            'condition' => InspectionItemCondition::GOOD,
        ]);

        // 2. Current Check-Out
        $current = Inspection::create([
            'inspection_code' => 'INSP-OUT-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'baseline_inspection_id' => $baseline->id,
            'type' => InspectionType::CHECK_OUT,
            'status' => InspectionStatus::COMPLETED,
            'scheduled_date' => now()->toDateString(),
        ]);

        InspectionItem::create([
            'inspection_id' => $current->id,
            'item_name' => 'Meja Belajar Kayu',
            'category' => 'furniture',
            'condition' => InspectionItemCondition::DAMAGED,
            'repair_required' => true,
            'estimated_cost' => 150000.00,
            'notes' => 'Kaki meja patah sebagian',
        ]);
        InspectionItem::create([
            'inspection_id' => $current->id,
            'item_name' => 'Remote AC',
            'category' => 'electronics',
            'condition' => InspectionItemCondition::MISSING,
            'repair_required' => true,
            'estimated_cost' => 100000.00,
            'notes' => 'Remote AC tidak ditemukan saat serah terima',
        ]);

        $compareResp = $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/v1/inspections/{$current->id}/compare/{$baseline->id}");

        $compareResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_degradation', true)
            ->assertJsonCount(2, 'data.differences');
        $this->assertEquals(250000, $compareResp->json('data.total_damage_cost'));
    }

    public function test_web_dashboard_inspections_pages_accessible_by_staff(): void
    {
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-WEB-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'booking_id' => $this->booking->id,
            'inspector_id' => $this->staff->id,
            'tenant_id' => $this->tenant->id,
            'type' => InspectionType::CHECK_IN,
            'status' => InspectionStatus::SCHEDULED,
            'scheduled_date' => now()->toDateString(),
        ]);

        // 1. Index page
        $this->actingAs($this->staff)
            ->get('/dashboard/inspections')
            ->assertStatus(200)
            ->assertSee('Inspeksi Kamar')
            ->assertSee('INSP-WEB-001');

        // 2. Create page
        $this->actingAs($this->manager)
            ->get('/dashboard/inspections/create')
            ->assertStatus(200)
            ->assertSee('Jadwalkan Inspeksi Kamar Baru');

        // 3. Detail page
        $this->actingAs($this->staff)
            ->get("/dashboard/inspections/{$inspection->id}")
            ->assertStatus(200)
            ->assertSee('INSP-WEB-001');

        // 4. Tenant dilarang mengakses dashboard inspeksi staf
        $this->actingAs($this->tenant)
            ->get('/dashboard/inspections')
            ->assertStatus(403);
    }
}

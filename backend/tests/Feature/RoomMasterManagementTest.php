<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Room\RoomService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $tenant;
    protected Property $propertyA;
    protected Property $propertyB;
    protected RoomType $roomTypeA;
    protected RoomType $roomTypeB;
    protected RoomService $roomService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->roomService = app(RoomService::class);

        // Super Admin
        $this->superAdmin = User::factory()->create([
            'role' => Role::SUPER_ADMIN,
            'is_active' => true,
        ]);

        // Manager
        $this->manager = User::factory()->create([
            'role' => Role::MANAGER,
            'is_active' => true,
        ]);

        // Tenant
        $this->tenant = User::factory()->create([
            'role' => Role::TENANT,
            'is_active' => true,
        ]);

        // Properti A & B
        $this->propertyA = Property::create([
            'code' => 'PROP-RM-A',
            'name' => 'Kos Melati Utama',
            'type' => 'kos',
            'address' => 'Jl. Melati No. 10',
            'city' => 'Jakarta Selatan',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-RM-B',
            'name' => 'Kos Mawar Indah',
            'type' => 'kos',
            'address' => 'Jl. Mawar No. 20',
            'city' => 'Bandung',
            'is_active' => true,
        ]);

        // Assign manager to Property A only
        $this->manager->properties()->attach($this->propertyA->id, ['role' => 'manager']);

        // Tipe Kamar
        $this->roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe Room',
            'base_price_monthly' => 1500000,
            'base_price_daily' => 150000,
            'base_price_yearly' => 16000000,
            'max_occupants' => 2,
            'size_m2' => 16.0,
            'facilities' => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
        ]);

        $this->roomTypeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Standard Room',
            'base_price_monthly' => 1000000,
            'base_price_daily' => 100000,
            'base_price_yearly' => 11000000,
            'max_occupants' => 1,
            'size_m2' => 12.0,
            'facilities' => ['Kipas Angin', 'Kasur'],
        ]);
    }

    public function test_room_list_enforces_property_isolation(): void
    {
        // Buat kamar di Properti A & B
        $roomA = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        $roomB = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $this->roomTypeB->id,
            'room_number' => '201',
            'floor' => 2,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        // 1. Super Admin melihat kamar Properti A dan B
        $adminRooms = $this->roomService->getRoomsQuery($this->superAdmin)->get();
        $this->assertTrue($adminRooms->contains('id', $roomA->id));
        $this->assertTrue($adminRooms->contains('id', $roomB->id));

        // 2. Manager hanya melihat kamar Properti A
        $managerRooms = $this->roomService->getRoomsQuery($this->manager)->get();
        $this->assertTrue($managerRooms->contains('id', $roomA->id));
        $this->assertFalse($managerRooms->contains('id', $roomB->id));

        // 3. Web Dashboard view: Tenant mendapat 403
        $response = $this->actingAs($this->tenant)->get(route('dashboard.rooms.index'));
        $response->assertStatus(403);

        // 4. Web Dashboard view: Manager melihat halaman kamar Properti A
        $response = $this->actingAs($this->manager)->get(route('dashboard.rooms.index'));
        $response->assertStatus(200);
        $response->assertSee('Kamar 101');
        $response->assertDontSee('Kamar 201');
    }

    public function test_room_number_must_be_unique_per_property_but_can_duplicate_across_properties(): void
    {
        // Buat Kamar 101 di Properti A
        $roomA = $this->roomService->createRoom($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
        ]);
        $this->assertEquals('101', $roomA->room_number);

        // Coba buat lagi Kamar 101 di Properti A -> Harus ditolak
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Nomor kamar '101' sudah terdaftar pada properti ini.");

        $this->roomService->createRoom($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
        ]);
    }

    public function test_same_room_number_can_exist_in_different_property(): void
    {
        // Kamar 101 di Properti A
        $roomA = $this->roomService->createRoom($this->superAdmin, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
        ]);

        // Kamar 101 di Properti B -> Boleh!
        $roomB = $this->roomService->createRoom($this->superAdmin, [
            'property_id' => $this->propertyB->id,
            'room_type_id' => $this->roomTypeB->id,
            'room_number' => '101',
            'floor' => 1,
        ]);

        $this->assertEquals('101', $roomA->room_number);
        $this->assertEquals('101', $roomB->room_number);
        $this->assertNotEquals($roomA->property_id, $roomB->property_id);
    }

    public function test_room_creation_and_update_records_audit_trail_in_change_histories(): void
    {
        // 1. Buat kamar
        $room = $this->roomService->createRoom($this->manager, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '301',
            'floor' => 3,
            'building' => 'Sayap Barat',
            'price_monthly' => 1750000,
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $room->id,
            'room_number' => '301',
            'price_monthly' => 1750000,
        ]);

        $this->assertDatabaseHas('room_change_histories', [
            'room_id' => $room->id,
            'action' => 'created',
            'user_id' => $this->manager->id,
        ]);

        // 2. Edit kamar
        $updatedRoom = $this->roomService->updateRoom($room, $this->manager, [
            'room_number' => '301-A',
            'price_monthly' => 1800000,
            'change_reason' => 'Upgrade fasilitas AC baru dan penyesuaian nomor unit.',
        ]);

        $this->assertEquals('301-A', $updatedRoom->room_number);
        $this->assertEquals(1800000, $updatedRoom->price_monthly);

        $this->assertDatabaseHas('room_change_histories', [
            'room_id' => $room->id,
            'action' => 'updated',
            'notes' => 'Upgrade fasilitas AC baru dan penyesuaian nomor unit.',
        ]);
    }

    public function test_cannot_deactivate_or_mark_available_room_with_active_booking(): void
    {
        $room = $this->roomService->createRoom($this->manager, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '102',
            'floor' => 1,
            'status' => RoomStatus::OCCUPIED->value,
        ]);

        // Buat active booking
        Booking::create([
            'booking_code' => 'BKG-TEST-102',
            'property_id' => $this->propertyA->id,
            'room_id' => $room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(25)->toDateString(),
            'total_amount' => 1500000,
            'status' => BookingStatus::ACTIVE,
        ]);

        $this->assertTrue($room->hasActiveBooking());

        // 1. Coba nonaktifkan kamar saat sedang dihuni -> Harus ditolak
        try {
            $this->roomService->toggleActive($room, $this->manager, 'Coba nonaktifkan kamar aktif');
            $this->fail('Seharusnya gagal menonaktifkan kamar dengan booking aktif.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('tidak dapat dinonaktifkan', $e->getMessage());
        }

        // 2. Coba paksa status menjadi Tersedia (available) -> Harus ditolak
        try {
            $this->roomService->changeStatus($room, $this->manager, RoomStatus::AVAILABLE);
            $this->fail('Seharusnya gagal mengubah status ke Tersedia saat ada booking aktif.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('tidak dapat diubah menjadi Tersedia', $e->getMessage());
        }
    }

    public function test_cannot_delete_room_with_booking_history(): void
    {
        $room = $this->roomService->createRoom($this->manager, [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '103',
            'floor' => 1,
        ]);

        Booking::create([
            'booking_code' => 'BKG-TEST-103',
            'property_id' => $this->propertyA->id,
            'room_id' => $room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
            'total_amount' => 1500000,
            'status' => BookingStatus::COMPLETED,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Kamar tidak dapat dihapus karena memiliki riwayat pemesanan.');

        $this->roomService->deleteRoom($room, $this->manager);
    }

    public function test_web_controller_crud_flow(): void
    {
        // 1. Store via POST
        $response = $this->actingAs($this->manager)->post(route('dashboard.rooms.store'), [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '501',
            'floor' => 5,
            'building' => 'Tower A',
            'price_monthly' => 2000000,
            'facilities' => 'AC, Smart TV, Balkon',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $room = Room::where('room_number', '501')->where('property_id', $this->propertyA->id)->firstOrFail();
        $this->assertEquals(2000000, $room->price_monthly);
        $this->assertEquals(['AC', 'Smart TV', 'Balkon'], $room->facilities);

        // 2. Show via GET
        $showResponse = $this->actingAs($this->manager)->get(route('dashboard.rooms.show', $room->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Kamar 501');
        $showResponse->assertSee('Tower A');
        $showResponse->assertSee('Rp 2.000.000');

        // 3. Update via PUT
        $updateResponse = $this->actingAs($this->manager)->put(route('dashboard.rooms.update', $room->id), [
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '501-VIP',
            'floor' => 5,
            'building' => 'Tower A Penthouse',
            'change_reason' => 'Upgrade unit ke status VIP',
        ]);

        $updateResponse->assertRedirect(route('dashboard.rooms.show', $room->id));
        $updateResponse->assertSessionHas('success');

        $this->assertEquals('501-VIP', $room->fresh()->room_number);
    }
}

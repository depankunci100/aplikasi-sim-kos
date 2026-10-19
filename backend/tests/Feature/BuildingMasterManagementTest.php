<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Building;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildingMasterManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $manager;
    protected User $tenant;
    protected Property $propertyA;
    protected Property $propertyB;
    protected RoomType $roomTypeA;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Users
        $this->superAdmin = User::factory()->create(['role' => Role::SUPER_ADMIN, 'is_active' => true]);
        $this->manager = User::factory()->create(['role' => Role::MANAGER, 'is_active' => true]);
        $this->tenant = User::factory()->create(['role' => Role::TENANT, 'is_active' => true]);

        // 2. Properties
        $this->propertyA = Property::create([
            'code' => 'PROP-BLD-A',
            'name' => 'Kos Permata Melati',
            'type' => 'kos',
            'address' => 'Jl. Melati No. 1',
            'city' => 'Jakarta Barat',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-BLD-B',
            'name' => 'Guesthouse Mawar Indah',
            'type' => 'guesthouse',
            'address' => 'Jl. Mawar No. 8',
            'city' => 'Yogyakarta',
            'is_active' => true,
        ]);

        $this->manager->properties()->attach($this->propertyA->id, ['role' => 'manager']);

        // 3. Room Type
        $this->roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe Room',
            'base_price' => 2000000,
            'description' => 'Tipe Deluxe',
            'is_active' => true,
        ]);
    }

    public function test_manager_can_view_buildings_index_page(): void
    {
        Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'GDG-A',
            'name' => 'Gedung Sayap Timur',
            'total_floors' => 3,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->manager)->get(route('dashboard.buildings.index'));
        $response->assertStatus(200);
        $response->assertSee('Master Gedung');
        $response->assertSee('Gedung Sayap Timur');
        $response->assertSee('GDG-A');
    }

    public function test_manager_can_create_building_with_unique_code(): void
    {
        $response = $this->actingAs($this->manager)->post(route('dashboard.buildings.store'), [
            'property_id' => $this->propertyA->id,
            'code' => 'gdg-utama',
            'name' => 'Gedung Utama Melati',
            'total_floors' => 4,
            'address_notes' => 'Menghadap jalan utama',
            'description' => 'Gedung utama kos lantai 1-4',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('dashboard.buildings.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('buildings', [
            'property_id' => $this->propertyA->id,
            'code' => 'GDG-UTAMA',
            'name' => 'Gedung Utama Melati',
            'total_floors' => 4,
        ]);
    }

    public function test_duplicate_building_code_in_same_property_is_rejected(): void
    {
        Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'BLOK-A',
            'name' => 'Blok Anggrek',
            'total_floors' => 2,
        ]);

        $response = $this->actingAs($this->manager)->post(route('dashboard.buildings.store'), [
            'property_id' => $this->propertyA->id,
            'code' => 'BLOK-A',
            'name' => 'Blok Anyar',
            'total_floors' => 2,
        ]);

        $response->assertSessionHas('error');
    }

    public function test_same_building_code_in_different_property_is_allowed(): void
    {
        Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'BLOK-A',
            'name' => 'Blok Anggrek Properti A',
            'total_floors' => 2,
        ]);

        // Super Admin creates same code on Property B
        $response = $this->actingAs($this->superAdmin)->post(route('dashboard.buildings.store'), [
            'property_id' => $this->propertyB->id,
            'code' => 'BLOK-A',
            'name' => 'Blok Anggrek Properti B',
            'total_floors' => 2,
        ]);

        $response->assertRedirect(route('dashboard.buildings.index'));
        $this->assertDatabaseHas('buildings', [
            'property_id' => $this->propertyB->id,
            'code' => 'BLOK-A',
        ]);
    }

    public function test_manager_can_update_building_details(): void
    {
        $building = Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'TWR-1',
            'name' => 'Tower Satu',
            'total_floors' => 5,
        ]);

        $response = $this->actingAs($this->manager)->put(route('dashboard.buildings.update', $building->id), [
            'code' => 'TWR-01',
            'name' => 'Tower Satu Renovasi',
            'total_floors' => 6,
            'address_notes' => 'Pintu gerbang barat',
            'description' => 'Lantai 1-6 aktif',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('dashboard.buildings.index'));
        $response->assertSessionHas('success');

        $this->assertEquals('TWR-01', $building->fresh()->code);
        $this->assertEquals('Tower Satu Renovasi', $building->fresh()->name);
        $this->assertEquals(6, $building->fresh()->total_floors);
    }

    public function test_manager_can_toggle_building_active_status(): void
    {
        $building = Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'BLOK-C',
            'name' => 'Blok Cemara',
            'total_floors' => 2,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->manager)->post(route('dashboard.buildings.toggle-active', $building->id));
        $response->assertRedirect();
        $this->assertFalse($building->fresh()->is_active);

        $this->actingAs($this->manager)->post(route('dashboard.buildings.toggle-active', $building->id));
        $this->assertTrue($building->fresh()->is_active);
    }

    public function test_building_with_linked_rooms_cannot_be_deleted(): void
    {
        $building = Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'GDG-B',
            'name' => 'Gedung B',
            'total_floors' => 2,
        ]);

        // Buat kamar yang ditautkan ke gedung
        Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => 'B-101',
            'floor' => 1,
            'building_id' => $building->id,
            'building' => $building->name,
            'status' => 'available',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->manager)->delete(route('dashboard.buildings.destroy', $building->id));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('buildings', ['id' => $building->id]);
    }

    public function test_building_without_rooms_can_be_deleted(): void
    {
        $building = Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'GDG-KOSONG',
            'name' => 'Gedung Kosong',
            'total_floors' => 1,
        ]);

        $response = $this->actingAs($this->manager)->delete(route('dashboard.buildings.destroy', $building->id));
        $response->assertRedirect(route('dashboard.buildings.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('buildings', ['id' => $building->id]);
    }

    public function test_room_can_be_assigned_to_building_and_displays_building_name(): void
    {
        $building = Building::create([
            'property_id' => $this->propertyA->id,
            'code' => 'PAV-1',
            'name' => 'Paviliun Melati Barat',
            'total_floors' => 1,
        ]);

        $storeResponse = $this->actingAs($this->manager)->post(route('dashboard.rooms.store'), [
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => 'PAV-01',
            'floor' => 1,
            'building_id' => $building->id,
            'price_monthly' => 2500000,
        ]);

        $storeResponse->assertRedirect();
        $room = Room::where('room_number', 'PAV-01')->firstOrFail();

        $this->assertEquals($building->id, $room->building_id);
        $this->assertEquals('Paviliun Melati Barat', $room->building_name);

        // Akses halaman show kamar
        $showResponse = $this->actingAs($this->manager)->get(route('dashboard.rooms.show', $room->id));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Paviliun Melati Barat');
    }
}

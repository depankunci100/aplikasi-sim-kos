<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyAndBookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_properties(): void
    {
        $property = Property::create([
            'code' => 'TEST-01',
            'name' => 'Kos Test Bahagia',
            'type' => 'kos',
            'address' => 'Jl. Mawar 10',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/properties');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.name', 'Kos Test Bahagia');
    }

    public function test_user_can_create_booking_and_auto_generates_invoice(): void
    {
        $user = User::factory()->create(['role' => Role::TENANT]);
        $property = Property::create([
            'code' => 'TEST-02',
            'name' => 'Kos Test Amanah',
            'type' => 'kos',
            'address' => 'Jl. Melati 12',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        $type = RoomType::create([
            'property_id' => $property->id,
            'name' => 'Standar',
            'base_price_monthly' => 1000000.00,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'room_number' => '101',
            'status' => 'available',
            'is_active' => true,
        ]);

        $payload = [
            'room_id' => $room->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addMonths(1)->toDateString(),
            'notes' => 'Catatan tes booking',
        ];

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.room_id', $room->id);

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('invoices', [
            'user_id' => $user->id,
            'invoice_type' => 'rent',
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Booking;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAndIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_only_sees_their_own_bookings(): void
    {
        $tenant1 = User::factory()->create(['role' => Role::TENANT]);
        $tenant2 = User::factory()->create(['role' => Role::TENANT]);

        $property = Property::create([
            'code' => 'PROP-ISO-1',
            'name' => 'Kos Isolasi',
            'type' => 'kos',
            'address' => 'Jl. Test 1',
            'city' => 'Malang',
        ]);

        $type = RoomType::create([
            'property_id' => $property->id,
            'name' => 'Standard',
            'base_price_monthly' => 1000000,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'room_number' => '101',
            'status' => 'available',
        ]);

        // Booking milik tenant 1
        Booking::create([
            'booking_code' => 'BK-T1',
            'property_id' => $property->id,
            'room_id' => $room->id,
            'user_id' => $tenant1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rate_amount' => 1000000,
            'deposit_amount' => 500000,
            'total_amount' => 1500000,
            'status' => 'confirmed',
        ]);

        // Booking milik tenant 2
        Booking::create([
            'booking_code' => 'BK-T2',
            'property_id' => $property->id,
            'room_id' => $room->id,
            'user_id' => $tenant2->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addMonths(2)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'rate_amount' => 1000000,
            'deposit_amount' => 500000,
            'total_amount' => 1500000,
            'status' => 'confirmed',
        ]);

        // Tenant 1 mengakses daftar booking
        $response = $this->actingAs($tenant1, 'sanctum')->getJson('/api/v1/bookings');

        $response->assertStatus(200);
        $data = $response->json('data.data');

        $this->assertCount(1, $data);
        $this->assertEquals('BK-T1', $data[0]['booking_code']);
    }

    public function test_booking_store_rejects_overlapping_dates(): void
    {
        $tenant1 = User::factory()->create(['role' => Role::TENANT]);
        $tenant2 = User::factory()->create(['role' => Role::TENANT]);

        $property = Property::create([
            'code' => 'PROP-OVL-1',
            'name' => 'Kos Overlap Test',
            'type' => 'kos',
            'address' => 'Jl. Test 2',
            'city' => 'Malang',
        ]);

        $type = RoomType::create([
            'property_id' => $property->id,
            'name' => 'Deluxe',
            'base_price_monthly' => 1200000,
        ]);

        $room = Room::create([
            'property_id' => $property->id,
            'room_type_id' => $type->id,
            'room_number' => '201',
            'status' => 'available',
        ]);

        // Booking pertama berhasil
        $payload1 = [
            'room_id' => $room->id,
            'rental_type' => 'monthly',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ];

        $resp1 = $this->actingAs($tenant1, 'sanctum')->postJson('/api/v1/bookings', $payload1);
        $resp1->assertStatus(201);

        // Ubah status booking 1 ke confirmed
        Booking::where('room_id', $room->id)->update(['status' => 'confirmed']);

        // Booking kedua yang bertabrakan tanggalnya harus ditolak (422)
        $payload2 = [
            'room_id' => $room->id,
            'rental_type' => 'monthly',
            'start_date' => '2026-11-15',
            'end_date' => '2026-12-15',
        ];

        $resp2 = $this->actingAs($tenant2, 'sanctum')->postJson('/api/v1/bookings', $payload2);
        $resp2->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}

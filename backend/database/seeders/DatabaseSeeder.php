<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Super Admin
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@simkos.id'],
            [
                'name' => 'Super Administrator',
                'phone' => '081100000001',
                'password' => Hash::make('Password123!'),
                'role' => Role::SUPER_ADMIN,
                'is_active' => true,
            ]
        );

        // 2. Owner
        $owner = User::updateOrCreate(
            ['email' => 'owner@simkos.id'],
            [
                'name' => 'Haji Huda (Owner)',
                'phone' => '081100000002',
                'password' => Hash::make('Password123!'),
                'role' => Role::OWNER,
                'is_active' => true,
            ]
        );

        // 3. Receptionist / Kasir
        $receptionist = User::updateOrCreate(
            ['email' => 'resepsionis@simkos.id'],
            [
                'name' => 'Siti Resepsionis',
                'phone' => '081100000003',
                'password' => Hash::make('Password123!'),
                'role' => Role::RECEPTIONIST,
                'is_active' => true,
            ]
        );

        // 4. Tenant / Penyewa
        $tenant = User::updateOrCreate(
            ['email' => 'penyewa@simkos.id'],
            [
                'name' => 'Rizky Mahasiswa',
                'phone' => '081100000004',
                'password' => Hash::make('Password123!'),
                'role' => Role::TENANT,
                'is_active' => true,
            ]
        );

        // 5. Sample Property: Wisma Melati Harmoni
        $property = Property::updateOrCreate(
            ['code' => 'PROP-MLG-01'],
            [
                'name' => 'Wisma Melati Harmoni & Guesthouse',
                'type' => 'hybrid',
                'address' => 'Jl. Bendungan Sigura-gura No. 18, Lowokwaru',
                'city' => 'Malang',
                'province' => 'Jawa Timur',
                'postal_code' => '65145',
                'latitude' => -7.9542000,
                'longitude' => 112.6135000,
                'phone' => '081234567890',
                'rules' => "1. Wajib lapor tamu menginap.\n2. Dilarang merokok di dalam kamar ber-AC.\n3. Gerbang utama dikunci pukul 23:00 WIB.",
                'facilities' => ['WiFi 100Mbps', 'Parkir Motor & Mobil', 'CCTV 24 Jam', 'Dapur Bersama', 'Rooftop Jemuran'],
                'is_active' => true,
            ]
        );

        // Hubungkan Owner ke Properti
        $property->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner'],
            $receptionist->id => ['role' => 'receptionist'],
        ]);

        // 6. Room Types
        $typeDeluxe = RoomType::updateOrCreate(
            ['property_id' => $property->id, 'name' => 'Deluxe AC (Kamar Mandi Dalam)'],
            [
                'description' => 'Kamar modern dengan AC, water heater, kasur queen, meja belajar, dan lemari 2 pintu.',
                'base_price_daily' => 180000.00,
                'base_price_monthly' => 1500000.00,
                'base_price_yearly' => 16500000.00,
                'max_occupants' => 2,
                'size_m2' => 16.00,
                'facilities' => ['AC 1 PK', 'Water Heater', 'Smart TV', 'Kasur Springbed', 'WiFi', 'Kamar Mandi Dalam'],
                'photos' => [],
            ]
        );

        $typeStandard = RoomType::updateOrCreate(
            ['property_id' => $property->id, 'name' => 'Standard Fan (Ventilasi Alami)'],
            [
                'description' => 'Kamar sejuk dengan kipas angin gantung, kasur single, meja belajar, dan lemari.',
                'base_price_daily' => 100000.00,
                'base_price_monthly' => 850000.00,
                'base_price_yearly' => 9500000.00,
                'max_occupants' => 1,
                'size_m2' => 12.00,
                'facilities' => ['Kipas Angin Gantung', 'Kasur Busa Tebal', 'Meja & Kursi', 'WiFi', 'Kamar Mandi Luar'],
                'photos' => [],
            ]
        );

        // 7. Rooms
        $roomNumbers = [
            ['101', 1, $typeDeluxe->id, RoomStatus::AVAILABLE],
            ['102', 1, $typeDeluxe->id, RoomStatus::AVAILABLE],
            ['103', 1, $typeDeluxe->id, RoomStatus::AVAILABLE],
            ['201', 2, $typeStandard->id, RoomStatus::AVAILABLE],
            ['202', 2, $typeStandard->id, RoomStatus::AVAILABLE],
            ['203', 2, $typeStandard->id, RoomStatus::AVAILABLE],
        ];

        foreach ($roomNumbers as [$num, $floor, $typeId, $status]) {
            Room::updateOrCreate(
                ['property_id' => $property->id, 'room_number' => $num],
                [
                    'room_type_id' => $typeId,
                    'floor' => $floor,
                    'status' => $status,
                    'is_active' => true,
                ]
            );
        }
    }
}

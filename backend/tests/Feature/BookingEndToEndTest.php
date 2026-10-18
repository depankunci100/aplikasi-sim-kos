<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\RentalType;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Booking\BookingService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected Property $propertyA;
    protected Property $propertyB;
    protected RoomType $roomTypeA;
    protected Room $roomA1;
    protected Room $roomA2;
    protected Room $roomB1;
    protected User $superAdmin;
    protected User $staffA;
    protected User $staffB;
    protected User $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Properti A (Dengan Kebijakan Uang Muka DP)
        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Griya Sejahtera',
            'type' => 'kos',
            'address' => 'Jl. Kaliurang KM 5',
            'city' => 'Yogyakarta',
            'dp_required' => true,
            'dp_type' => 'percentage',
            'dp_percentage' => 20.00,
            'payment_deadline_hours' => 24,
            'deposit_amount' => 500000.00,
            'is_active' => true,
        ]);

        // 2. Setup Properti B (Tanpa DP)
        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Guesthouse Malioboro',
            'type' => 'guesthouse',
            'address' => 'Jl. Malioboro No. 10',
            'city' => 'Yogyakarta',
            'dp_required' => false,
            'dp_amount' => 0.00,
            'payment_deadline_hours' => 12,
            'deposit_amount' => 0.00,
            'is_active' => true,
        ]);

        // 3. Room Types
        $this->roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe Room',
            'base_price_monthly' => 1500000.00,
            'base_price_daily' => 150000.00,
            'max_occupants' => 2,
            'facilities' => ['AC', 'WiFi', 'Kamar Mandi Dalam'],
        ]);

        $roomTypeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Standard Room',
            'base_price_monthly' => 1000000.00,
            'base_price_daily' => 100000.00,
            'max_occupants' => 1,
            'facilities' => ['Kipas Angin', 'WiFi'],
        ]);

        // 4. Rooms
        $this->roomA1 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        $this->roomA2 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '102',
            'floor' => 1,
            'status' => RoomStatus::MAINTENANCE, // Kamar maintenance
            'is_active' => true,
        ]);

        $this->roomB1 = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $roomTypeB->id,
            'room_number' => '201',
            'floor' => 2,
            'status' => RoomStatus::AVAILABLE,
            'is_active' => true,
        ]);

        // 5. Users
        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'role' => Role::SUPER_ADMIN,
        ]);

        $this->staffA = User::factory()->create([
            'name' => 'Staf Properti A',
            'role' => Role::RECEPTIONIST,
        ]);
        DB::table('property_users')->insert([
            'property_id' => $this->propertyA->id,
            'user_id' => $this->staffA->id,
            'role' => 'receptionist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->staffB = User::factory()->create([
            'name' => 'Staf Properti B',
            'role' => Role::RECEPTIONIST,
        ]);
        DB::table('property_users')->insert([
            'property_id' => $this->propertyB->id,
            'user_id' => $this->staffB->id,
            'role' => 'receptionist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tenant = User::factory()->create([
            'name' => 'Penyewa Terdaftar',
            'role' => Role::TENANT,
        ]);
    }

    /**
     * Test 1: Booking Publik Guest Berhasil (Membuat booking, token unik, DP & invoice awal)
     */
    public function test_public_guest_booking_success(): void
    {
        $startDate = now()->addDays(2)->toDateString();
        $endDate = now()->addDays(32)->toDateString();

        $payload = [
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'guest_name' => 'Doni Saputra',
            'guest_email' => 'doni@example.com',
            'guest_phone' => '081234567890',
            'guest_id_card' => '3201234567890001',
            'notes' => 'Tolong sediakan kunci cadangan',
            'agree_terms' => 1,
        ];

        $response = $this->postJson('/api/v1/public/bookings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.room.room_number', '101');

        $this->assertDatabaseHas('bookings', [
            'room_id' => $this->roomA1->id,
            'guest_name' => 'Doni Saputra',
            'guest_email' => 'doni@example.com',
            'status' => 'pending',
        ]);

        // Pastikan invoice terbit otomatis dan user_id bernilai null (guest)
        $booking = Booking::where('guest_email', 'doni@example.com')->first();
        $this->assertNotNull($booking->access_token);
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'user_id' => null,
            'status' => 'unpaid',
        ]);
    }

    /**
     * Test 2: Kebijakan Uang Muka (DP) Opsional Per Properti
     */
    public function test_down_payment_policy_calculated_correctly_per_property(): void
    {
        $service = app(BookingService::class);

        // Properti A: DP 20% dari total (sewa 1.5jt + deposit 500rb = 2jt => DP = 400rb)
        $pricingA = $service->calculatePricing(
            $this->roomA1,
            'monthly',
            now()->addDay()->toDateString(),
            now()->addDays(31)->toDateString()
        );
        $this->assertTrue($pricingA['dp_required']);
        $this->assertEquals(1500000.00, $pricingA['rate_amount']);
        $this->assertEquals(500000.00, $pricingA['deposit_amount']);
        $this->assertEquals(2000000.00, $pricingA['total_amount']);
        $this->assertEquals(400000.00, $pricingA['down_payment_amount']);

        // Properti B: Tanpa DP
        $pricingB = $service->calculatePricing(
            $this->roomB1,
            'daily',
            now()->addDay()->toDateString(),
            now()->addDays(3)->toDateString()
        );
        $this->assertFalse($pricingB['dp_required']);
        $this->assertEquals(0.00, $pricingB['down_payment_amount']);
        $this->assertEquals(200000.00, $pricingB['rate_amount']); // 2 hari * 100rb
    }

    /**
     * Test 3: Guest Tidak Dapat Mengakses Endpoint Internal (401 Unauthorized)
     */
    public function test_guest_cannot_access_internal_endpoints(): void
    {
        $response = $this->getJson('/api/v1/bookings');
        $response->assertStatus(401);

        $responsePost = $this->postJson('/api/v1/bookings', [
            'room_id' => $this->roomA1->id,
        ]);
        $responsePost->assertStatus(401);
    }

    /**
     * Test 4: Kamar Maintenance / Tidak Tersedia Ditolak
     */
    public function test_booking_maintenance_room_is_rejected(): void
    {
        $payload = [
            'room_id' => $this->roomA2->id, // Room A2 berstatus MAINTENANCE
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'guest_name' => 'Ferry',
            'guest_email' => 'ferry@example.com',
            'guest_phone' => '08123456789',
            'agree_terms' => 1,
        ];

        $response = $this->postJson('/api/v1/public/bookings', $payload);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room_id']);
    }

    /**
     * Test 5: Periode Tidak Bertabrakan Dapat Dipesan
     */
    public function test_non_overlapping_periods_can_be_booked(): void
    {
        $service = app(BookingService::class);

        // Booking 1: 1 Nov - 10 Nov
        $b1 = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'guest_name' => 'Tamu 1',
            'guest_email' => 'tamu1@test.com',
            'guest_phone' => '0811111111',
        ]);
        $b1->status = BookingStatus::CONFIRMED;
        $b1->save();

        // Booking 2: 15 Nov - 20 Nov (Tidak overlap)
        $b2 = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-15',
            'end_date' => '2026-11-20',
            'guest_name' => 'Tamu 2',
            'guest_email' => 'tamu2@test.com',
            'guest_phone' => '0822222222',
        ]);

        $this->assertEquals(BookingStatus::PENDING, $b2->status);
        $this->assertDatabaseCount('bookings', 2);
    }

    /**
     * Test 6: Periode Bertabrakan Ditolak (Anti Double-Booking)
     */
    public function test_overlapping_periods_are_rejected(): void
    {
        $service = app(BookingService::class);

        // Booking 1: 1 Nov - 10 Nov (Confirmed)
        $b1 = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-10',
            'guest_name' => 'Tamu 1',
            'guest_email' => 'tamu1@test.com',
            'guest_phone' => '0811111111',
        ]);
        $b1->status = BookingStatus::CONFIRMED;
        $b1->save();

        // Booking 2 bertabrakan: 5 Nov - 15 Nov
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-15',
            'guest_name' => 'Tamu 2',
            'guest_email' => 'tamu2@test.com',
            'guest_phone' => '0822222222',
        ]);
    }

    /**
     * Test 7: Multi-Property Isolation (Staf A tidak dapat mengonfirmasi/melihat booking Properti B)
     */
    public function test_multi_property_isolation_enforced(): void
    {
        $service = app(BookingService::class);

        $bookingB = $service->createBooking([
            'room_id' => $this->roomB1->id,
            'rental_type' => 'daily',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'guest_name' => 'Tamu B',
            'guest_email' => 'tamub@test.com',
            'guest_phone' => '0833333333',
        ]);

        // Staf A mencoba akses show booking B
        $response = $this->actingAs($this->staffA, 'sanctum')
            ->getJson("/api/v1/bookings/{$bookingB->id}");
        $response->assertStatus(404);

        // Staf A mencoba konfirmasi booking B
        $responseConfirm = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$bookingB->id}/confirm");
        $responseConfirm->assertStatus(404);

        // Staf B berwenang dapat melihat booking B
        $responseStafB = $this->actingAs($this->staffB, 'sanctum')
            ->getJson("/api/v1/bookings/{$bookingB->id}");
        $responseStafB->assertStatus(200);
    }

    /**
     * Test 8: Konfirmasi Booking oleh Staf Berwenang
     */
    public function test_authorized_staff_can_confirm_booking(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(33)->toDateString(),
            'guest_name' => 'Calon Penyewa',
            'guest_email' => 'calon@test.com',
            'guest_phone' => '0812334455',
        ]);

        $response = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/confirm", [
                'notes' => 'DP diterima di kasir',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
        $this->assertEquals($this->staffA->id, $booking->fresh()->confirmed_by);
    }

    /**
     * Test 9: Penolakan Booking dengan Alasan Wajib
     */
    public function test_reject_booking_with_mandatory_reason_cancels_invoice(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
            'guest_name' => 'Calon Tolak',
            'guest_email' => 'tolak@test.com',
            'guest_phone' => '0812334456',
        ]);

        // Coba tanpa alasan -> 422
        $responseNoReason = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/reject", []);
        $responseNoReason->assertStatus(422);

        // Dengan alasan valid
        $response = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/reject", [
                'rejection_reason' => 'Kamar dialokasikan untuk perbaikan atap',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(BookingStatus::REJECTED, $booking->fresh()->status);
        $this->assertEquals('Kamar dialokasikan untuk perbaikan atap', $booking->fresh()->rejection_reason);

        // Invoice terkait dibatalkan
        $this->assertDatabaseHas('invoices', [
            'booking_id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }

    /**
     * Test 10: Pembatalan Booking Melepaskan Kamar
     */
    public function test_cancel_booking_releases_room_hold(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'guest_name' => 'Calon Batal',
            'guest_email' => 'batal@test.com',
            'guest_phone' => '0812334457',
        ]);

        $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/cancel", [
                'cancellation_reason' => 'Permintaan calon tamu karena perubahan rencana',
            ])->assertStatus(200);

        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);

        // Kamar sekarang tersedia untuk dipesan pada tanggal yang sama
        $isAvailable = $service->checkRoomAvailability($this->roomA1->id, '2026-12-01', '2026-12-05');
        $this->assertTrue($isAvailable);
    }

    /**
     * Test 11: Check-in Mengubah Status Booking Menjadi ACTIVE dan Kamar Menjadi OCCUPIED
     */
    public function test_check_in_booking_activates_rental_and_occupies_room(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'guest_name' => 'Penyewa Masuk',
            'guest_email' => 'masuk@test.com',
            'guest_phone' => '0812334458',
        ]);

        // Booking harus CONFIRMED terlebih dahulu
        $service->confirmBooking($booking, $this->staffA);

        $response = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/check-in");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(BookingStatus::ACTIVE, $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->actual_check_in);
        $this->assertEquals($this->staffA->id, $booking->fresh()->checked_in_by);

        // Status kamar berubah menjadi OCCUPIED
        $this->assertEquals(RoomStatus::OCCUPIED, $this->roomA1->fresh()->status);

        // Check-in ganda ditolak
        $responseSecondCheckIn = $this->actingAs($this->staffA, 'sanctum')
            ->postJson("/api/v1/bookings/{$booking->id}/check-in");
        $responseSecondCheckIn->assertStatus(422);
    }

    /**
     * Test 12: Integrasi Pembayaran Terverifikasi Mengonfirmasi Booking Otomatis
     */
    public function test_verified_payment_auto_confirms_pending_booking(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
            'guest_name' => 'Bayar DP',
            'guest_email' => 'dp@test.com',
            'guest_phone' => '0812334459',
        ]);

        $this->assertEquals(BookingStatus::PENDING, $booking->status);
        $invoice = $booking->invoices->first();

        // Buat pembayaran sebesar DP (400.000)
        $payment = Payment::create([
            'payment_code' => 'PAY-TEST-001',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 400000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'pending', // Belum terverifikasi
        ]);

        // Verifikasi pembayaran melalui PaymentService
        $paymentService = app(PaymentService::class);
        $paymentService->verifyPayment($payment, $this->staffA, 'Bukti transfer valid');

        // Status booking otomatis berubah menjadi CONFIRMED
        $this->assertEquals(BookingStatus::CONFIRMED, $booking->fresh()->status);
    }

    /**
     * Test 13: Pelacakan Publik Menggunakan Access Token yang Aman (Anti IDOR & Enumerasi)
     */
    public function test_public_tracking_via_secure_access_token(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(31)->toDateString(),
            'guest_name' => 'Track User',
            'guest_email' => 'track@test.com',
            'guest_phone' => '0812334460',
        ]);

        $token = $booking->access_token;
        $this->assertNotEmpty($token);

        // Akses via token valid
        $response = $this->getJson("/api/v1/public/bookings/track/{$token}");
        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.booking_code', $booking->booking_code)
            ->assertJsonPath('data.property.name', $this->propertyA->name);

        // Akses dengan token ngawur ditolak (404)
        $responseInvalid = $this->getJson("/api/v1/public/bookings/track/random-invalid-token-xyz");
        $responseInvalid->assertStatus(404);
    }

    /**
     * Test 14: Pembersihan Booking Kedaluwarsa (Hold Expiry)
     */
    public function test_purge_expired_bookings_releases_room(): void
    {
        $service = app(BookingService::class);

        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'rental_type' => 'daily',
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-15',
            'guest_name' => 'Tamu Expired',
            'guest_email' => 'expired@test.com',
            'guest_phone' => '0812334461',
        ]);

        // Simulasikan waktu hold telah lewat 2 hari yang lalu
        $booking->expires_at = now()->subDays(2);
        $booking->save();

        // Jalankan pembersihan
        $purgedCount = $service->purgeExpiredBookings($this->propertyA->id);
        $this->assertEquals(1, $purgedCount);
        $this->assertEquals(BookingStatus::EXPIRED, $booking->fresh()->status);

        // Kamar langsung tersedia kembali
        $isAvailable = $service->checkRoomAvailability($this->roomA1->id, '2026-12-10', '2026-12-15');
        $this->assertTrue($isAvailable);
    }

    /**
     * Test 15: Website Routes (Katalog Publik, Detail Kamar, Web Dashboard Booking, Show & Tracking)
     */
    public function test_website_booking_routes_accessible(): void
    {
        // 1. Katalog publik
        $this->get('/catalog')->assertStatus(200);

        // 2. Detail kamar publik
        $this->get("/rooms/{$this->roomA1->id}")->assertStatus(200);

        // 3. Dashboard booking index (Authenticated Staf)
        $this->actingAs($this->staffA)
            ->get('/dashboard/bookings')
            ->assertStatus(200);

        // 4. Create booking via BookingService with invoice
        $service = app(BookingService::class);
        $booking = $service->createBooking([
            'room_id' => $this->roomA1->id,
            'customer_type' => 'guest',
            'guest_name' => 'Web Guest Test',
            'guest_email' => 'webguest@test.com',
            'guest_phone' => '081234567890',
            'rental_type' => 'monthly',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-01',
        ]);

        // 5. Test Public Tracking page (renders invoice badgeClass without error)
        $this->get("/booking/track/{$booking->access_token}")
            ->assertStatus(200)
            ->assertSee($booking->booking_code)
            ->assertSee('Web Guest Test');

        // 6. Test Dashboard Booking Show page (renders invoice badgeClass without error)
        $this->actingAs($this->staffA)
            ->get("/dashboard/bookings/{$booking->id}")
            ->assertStatus(200)
            ->assertSee($booking->booking_code);

        // 7. Test Dashboard Booking Store via POST
        $postResponse = $this->actingAs($this->staffA)->post('/dashboard/bookings', [
            'room_id' => $this->roomA1->id,
            'customer_type' => 'guest',
            'guest_name' => 'Drawer Created Guest',
            'guest_email' => 'drawer@test.com',
            'guest_phone' => '08987654321',
            'rental_type' => 'monthly',
            'start_date' => '2026-12-05',
            'end_date' => '2027-01-05',
        ]);
        $postResponse->assertSessionHasNoErrors();
        $postResponse->assertStatus(302);
        $this->assertDatabaseHas('bookings', [
            'guest_name' => 'Drawer Created Guest',
            'room_id' => $this->roomA1->id,
        ]);
    }
}

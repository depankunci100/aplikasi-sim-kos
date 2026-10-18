<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\DepositMutationType;
use App\Enums\DepositStatus;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\DepositMutation;
use App\Models\Inspection;
use App\Models\JournalEntry;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use App\Services\Deposit\DepositService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CheckOutHotfixTest extends TestCase
{
    use RefreshDatabase;

    protected Property $propertyA;
    protected Property $propertyB;
    protected RoomType $roomTypeA;
    protected Room $roomA;
    protected Room $roomB;
    protected User $superAdmin;
    protected User $managerA;
    protected User $staffA;
    protected User $staffB;
    protected User $tenantA;
    protected User $tenantB;
    protected Booking $bookingA;
    protected DepositService $depositService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depositService = app(DepositService::class);

        // Properties
        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Permata Hijau',
            'type' => 'boarding_house',
            'address' => 'Jl. Kaliurang KM 5',
            'city' => 'Yogyakarta',
            'deposit_amount' => 1000000.00,
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Guesthouse Malioboro',
            'type' => 'guesthouse',
            'address' => 'Jl. Malioboro No. 10',
            'city' => 'Yogyakarta',
            'deposit_amount' => 500000.00,
            'is_active' => true,
        ]);

        // Rooms
        $this->roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Deluxe Room',
            'base_price_monthly' => 2000000.00,
            'base_price_daily' => 200000.00,
            'max_occupants' => 2,
        ]);

        $this->roomA = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '101',
            'floor' => 1,
            'status' => RoomStatus::OCCUPIED,
            'is_active' => true,
        ]);

        $roomTypeB = RoomType::create([
            'property_id' => $this->propertyB->id,
            'name' => 'Standard Room',
            'base_price_monthly' => 1500000.00,
            'base_price_daily' => 150000.00,
            'max_occupants' => 1,
        ]);

        $this->roomB = Room::create([
            'property_id' => $this->propertyB->id,
            'room_type_id' => $roomTypeB->id,
            'room_number' => '201',
            'floor' => 2,
            'status' => RoomStatus::OCCUPIED,
            'is_active' => true,
        ]);

        // Users
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'phone' => '081100000001',
            'password' => bcrypt('password'),
            'role' => Role::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->managerA = User::create([
            'name' => 'Manager Kos A',
            'email' => 'managerA@test.com',
            'phone' => '081100000002',
            'password' => bcrypt('password'),
            'role' => Role::MANAGER,
            'is_active' => true,
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Kos A',
            'email' => 'staffA@test.com',
            'phone' => '081100000003',
            'password' => bcrypt('password'),
            'role' => Role::STAFF,
            'is_active' => true,
        ]);

        $this->staffB = User::create([
            'name' => 'Staff Kos B',
            'email' => 'staffB@test.com',
            'phone' => '081100000004',
            'password' => bcrypt('password'),
            'role' => Role::STAFF,
            'is_active' => true,
        ]);

        $this->tenantA = User::create([
            'name' => 'Tenant Rizky',
            'email' => 'tenantA@test.com',
            'phone' => '081100000005',
            'password' => bcrypt('password'),
            'role' => Role::TENANT,
            'is_active' => true,
        ]);

        $this->tenantB = User::create([
            'name' => 'Tenant Siti',
            'email' => 'tenantB@test.com',
            'phone' => '081100000006',
            'password' => bcrypt('password'),
            'role' => Role::TENANT,
            'is_active' => true,
        ]);

        // Property-User Bindings
        DB::table('property_users')->insert([
            ['property_id' => $this->propertyA->id, 'user_id' => $this->managerA->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->staffA->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyB->id, 'user_id' => $this->staffB->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Active Booking A with Held Deposit
        $this->bookingA = Booking::create([
            'booking_code' => 'BKG-TEST-001',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenantA->id,
            'start_date' => now()->subMonths(1)->toDateString(),
            'end_date' => now()->toDateString(),
            'rental_type' => 'monthly',
            'status' => BookingStatus::ACTIVE,
            'total_amount' => 2000000.00,
            'rate_amount' => 2000000.00,
            'deposit_amount' => 1000000.00,
            'down_payment_amount' => 0.00,
            'guest_name' => 'Tenant Rizky',
            'guest_email' => 'tenantA@test.com',
            'guest_phone' => '081100000005',
        ]);

        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->managerA, [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'DEP-INIT-001',
        ]);
    }

    /**
     * Skenario 1: Checkout normal tanpa potongan deposit
     */
    public function test_01_checkout_normal_without_deductions(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'BCA 123456789 a/n Rizky',
        ]);

        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);
        $this->assertEquals(0, $settlement->total_deductions);
        $this->assertEquals(1000000.00, (float) $settlement->net_refund_amount);

        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789 a/n Rizky',
            'refund_payment_reference' => 'REF-TEST-001',
        ]);

        $this->assertEquals(SettlementStatus::SETTLED, $finalized->status);
        $this->bookingA->refresh();
        $this->assertEquals(BookingStatus::COMPLETED, $this->bookingA->status);
        $this->roomA->refresh();
        $this->assertEquals(RoomStatus::DIRTY, $this->roomA->status);
    }

    /**
     * Skenario 2: Checkout dengan potongan kerusakan yang valid
     */
    public function test_02_checkout_with_valid_damage_deduction(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'BCA 123456789 a/n Rizky',
            'custom_deductions' => [
                [
                    'title' => 'Kerusakan Gagang Pintu',
                    'amount' => 150000.00,
                    'description' => 'Gagang pintu kamar patah',
                ],
            ],
        ]);

        $this->assertEquals(SettlementStatus::PENDING_TENANT_APPROVAL, $settlement->status);
        $this->assertEquals(150000.00, (float) $settlement->total_deductions);
        $this->assertEquals(850000.00, (float) $settlement->net_refund_amount);

        // Tenant menyetujui
        $this->depositService->submitTenantDecision($settlement, $this->tenantA, true);
        $settlement->refresh();
        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);

        // Finalisasi
        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789 a/n Rizky',
        ]);

        $this->assertEquals(SettlementStatus::SETTLED, $finalized->status);
        $ledger = $this->bookingA->depositLedger->fresh();
        $this->assertEquals(150000.00, (float) $ledger->total_deductions);
        $this->assertEquals(850000.00, (float) $ledger->total_refunded);
        $this->assertEquals(0.00, (float) $ledger->current_balance);
    }

    /**
     * Skenario 3: Checkout dengan refund deposit (memverifikasi mutasi keluar dan direction)
     */
    public function test_03_checkout_with_deposit_refund_mutation_records(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'Mandiri 987654321',
        ]);

        $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'Mandiri 987654321',
            'refund_payment_reference' => 'MANDIRI-REF-01',
        ]);

        $refundMutation = DepositMutation::where('transaction_type', DepositMutationType::REFUND_PAYOUT)->first();
        $this->assertNotNull($refundMutation);
        $this->assertEquals('out', $refundMutation->direction);
        $this->assertEquals(1000000.00, (float) $refundMutation->amount);
        $this->assertEquals(1000000.00, (float) $refundMutation->balance_before);
        $this->assertEquals(0.00, (float) $refundMutation->balance_after);
    }

    /**
     * Skenario 4: Checkout tanpa deposit atau dengan saldo deposit nol
     */
    public function test_04_checkout_without_deposit_or_zero_balance(): void
    {
        $room2 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '102',
            'floor' => 1,
            'status' => RoomStatus::OCCUPIED,
            'is_active' => true,
        ]);

        $bookingZeroDep = Booking::create([
            'booking_code' => 'BKG-ZERO-DEP',
            'property_id' => $this->propertyA->id,
            'room_id' => $room2->id,
            'user_id' => $this->tenantB->id,
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->toDateString(),
            'rental_type' => 'daily',
            'status' => BookingStatus::ACTIVE,
            'total_amount' => 500000.00,
            'rate_amount' => 500000.00,
            'deposit_amount' => 0.00, // Tanpa deposit
            'down_payment_amount' => 0.00,
            'guest_name' => 'Tenant Siti',
        ]);

        $settlement = $this->depositService->initiateCheckOutSettlement($bookingZeroDep, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        $this->assertEquals(0.00, (float) $settlement->deposit_balance_before);
        $this->assertEquals(0.00, (float) $settlement->net_refund_amount);
        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);

        // Finalisasi tidak memerlukan tujuan transfer rekening karena net refund adalah 0
        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'cash',
        ]);

        $this->assertEquals(SettlementStatus::SETTLED, $finalized->status);
        $bookingZeroDep->refresh();
        $this->assertEquals(BookingStatus::COMPLETED, $bookingZeroDep->status);
        $room2->refresh();
        $this->assertEquals(RoomStatus::DIRTY, $room2->status);
    }

    /**
     * Skenario 5: Checkout ketika inspeksi belum lengkap / tanpa inspeksi
     */
    public function test_05_checkout_when_inspection_incomplete_or_absent(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
            'inspection_id' => null, // Tanpa inspeksi
        ]);

        $this->assertNull($settlement->inspection_id);
        $this->assertEquals(0, $settlement->deductions()->count());
        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);
    }

    /**
     * Skenario 6: Checkout berulang atau request retry (mencegah settlement duplikat)
     */
    public function test_06_cannot_initiate_or_finalize_duplicate_checkout(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        // Coba inisiasi kedua kali pada booking yang sama -> DomainException
        $this->expectException(DomainException::class);
        $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);
    }

    /**
     * Skenario 7: Tenant tidak berwenang memfinalisasi checkout / refund
     */
    public function test_07_tenant_unauthorized_to_finalize_checkout(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        $res = $this->actingAs($this->tenantA, 'sanctum')->postJson("/api/v1/settlements/{$settlement->id}/finalize", [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789',
        ]);

        $res->assertStatus(403);
    }

    /**
     * Skenario 8: Staf properti A tidak dapat checkout booking properti B
     */
    public function test_08_staff_property_a_cannot_checkout_property_b_booking(): void
    {
        $bookingB = Booking::create([
            'booking_code' => 'BKG-PROP-B',
            'property_id' => $this->propertyB->id,
            'room_id' => $this->roomB->id,
            'user_id' => $this->tenantB->id,
            'start_date' => now()->subDays(3)->toDateString(),
            'end_date' => now()->toDateString(),
            'rental_type' => 'daily',
            'status' => BookingStatus::ACTIVE,
            'total_amount' => 450000.00,
            'rate_amount' => 450000.00,
            'deposit_amount' => 500000.00,
            'guest_name' => 'Tenant Siti',
        ]);

        $settlementB = $this->depositService->initiateCheckOutSettlement($bookingB, $this->superAdmin, [
            'check_out_date' => now()->toDateString(),
        ]);

        // Staf A mencoba akses settlement properti B via Web
        $resWeb = $this->actingAs($this->staffA)->get("/dashboard/deposits/settlements/{$settlementB->id}");
        $resWeb->assertStatus(403);

        // Staf A mencoba akses via API
        $resApi = $this->actingAs($this->staffA, 'sanctum')->getJson("/api/v1/settlements/{$settlementB->id}");
        $resApi->assertStatus(403);
    }

    /**
     * Skenario 9: Data booking / penyewa tidak ditemukan atau tidak valid
     */
    public function test_09_not_found_handling(): void
    {
        $resApi = $this->actingAs($this->managerA, 'sanctum')->getJson('/api/v1/settlements/999999');
        $resApi->assertStatus(404);

        $resWeb = $this->actingAs($this->managerA)->get('/dashboard/deposits/settlements/999999');
        $resWeb->assertStatus(404);
    }

    /**
     * Skenario 10: Kegagalan transaksi me-rollback seluruh perubahan terkait
     */
    public function test_10_transaction_rollback_on_failure(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        // Mock JournalPostingService untuk melempar exception saat posting jurnal
        $this->mock(JournalPostingService::class, function ($mock) {
            $mock->shouldReceive('postCheckOutSettlementJournals')
                ->andThrow(new \RuntimeException('Koneksi sistem akuntansi terputus'));
        });

        try {
            $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
                'refund_method' => 'bank_transfer',
                'refund_destination' => 'BCA 123456789',
            ]);
            $this->fail('Harusnya melempar exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Koneksi sistem akuntansi terputus', $e->getMessage());
        }

        // Verifikasi status booking dan kamar TIDAK berubah (di-rollback)
        $this->bookingA->refresh();
        $this->assertEquals(BookingStatus::ACTIVE, $this->bookingA->status);
        $this->roomA->refresh();
        $this->assertEquals(RoomStatus::OCCUPIED, $this->roomA->status);
        $settlement->refresh();
        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);
    }

    /**
     * Skenario 11: Kamar berubah ke status DIRTY (Housekeeping) setelah checkout
     */
    public function test_11_room_status_becomes_dirty_after_checkout(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789',
        ]);

        $this->roomA->refresh();
        $this->assertEquals(RoomStatus::DIRTY, $this->roomA->status);
        $this->assertNotEquals(RoomStatus::AVAILABLE, $this->roomA->status);
    }

    /**
     * Skenario 12: Data lama yang memiliki field opsional kosong (guest booking, tanpa user_id)
     */
    public function test_12_legacy_data_with_empty_optional_fields(): void
    {
        $room3 = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => '103',
            'floor' => 1,
            'status' => RoomStatus::OCCUPIED,
            'is_active' => true,
        ]);

        $legacyBooking = Booking::create([
            'booking_code' => 'BKG-LEGACY-001',
            'property_id' => $this->propertyA->id,
            'room_id' => $room3->id,
            'user_id' => null, // Guest tanpa akun user
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'rental_type' => 'daily',
            'status' => BookingStatus::ACTIVE,
            'total_amount' => 400000.00,
            'rate_amount' => 400000.00,
            'deposit_amount' => 0.00,
            'guest_name' => 'Tamu Lama',
            'guest_phone' => null,
            'guest_email' => null,
            'notes' => null,
        ]);

        $settlement = $this->depositService->initiateCheckOutSettlement($legacyBooking, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);

        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'cash',
        ]);

        $this->assertEquals(SettlementStatus::SETTLED, $finalized->status);
        $room3->refresh();
        $this->assertEquals(RoomStatus::DIRTY, $room3->status);
    }

    /**
     * Skenario 13: Website routing /dashboard/deposits/settlements berfungsi tanpa TypeError
     */
    public function test_13_web_route_settlements_index_and_show_no_collision(): void
    {
        // 1. GET /dashboard/deposits/settlements harus me-resolve settlementsIndex (200 OK)
        $resIndex = $this->actingAs($this->managerA)->get('/dashboard/deposits/settlements');
        $resIndex->assertStatus(200);
        $resIndex->assertSee('Penyelesaian Check-Out');

        // 2. Inisiasi settlement
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        // 3. GET /dashboard/deposits/settlements/{id} harus me-resolve settlementShow (200 OK)
        $resShow = $this->actingAs($this->managerA)->get("/dashboard/deposits/settlements/{$settlement->id}");
        $resShow->assertStatus(200);
        $resShow->assertSee($settlement->settlement_code);

        // 4. GET /dashboard/deposits/{id} tetap me-resolve deposit ledger show (200 OK)
        $ledger = $this->bookingA->depositLedger;
        $resDeposit = $this->actingAs($this->managerA)->get("/dashboard/deposits/{$ledger->id}");
        $resDeposit->assertStatus(200);
        $resDeposit->assertSee($ledger->deposit_code);
    }

    /**
     * Skenario 14: API endpoint contracts for Mobile / Flutter
     */
    public function test_14_api_endpoints_contract_for_flutter(): void
    {
        // 1. GET /api/v1/settlements
        $resList = $this->actingAs($this->managerA, 'sanctum')->getJson('/api/v1/settlements');
        $resList->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                ],
            ]);

        // 2. POST /api/v1/settlements (Initiate)
        $resInit = $this->actingAs($this->managerA, 'sanctum')->postJson('/api/v1/settlements', [
            'booking_id' => $this->bookingA->id,
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'BCA 123456789',
        ]);
        $resInit->assertStatus(201)
            ->assertJsonPath('success', true);

        $settlementId = $resInit->json('data.id');

        // 3. GET /api/v1/settlements/{id}
        $resDetail = $this->actingAs($this->tenantA, 'sanctum')->getJson("/api/v1/settlements/{$settlementId}");
        $resDetail->assertStatus(200)
            ->assertJsonPath('data.id', $settlementId);

        // 4. POST /api/v1/settlements/{id}/finalize
        $resFinal = $this->actingAs($this->managerA, 'sanctum')->postJson("/api/v1/settlements/{$settlementId}/finalize", [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789',
        ]);
        $resFinal->assertStatus(200)
            ->assertJsonPath('data.status', 'settled');
    }

    /**
     * Skenario 15: Tidak ada refund, mutasi deposit, settlement, atau jurnal duplikat
     */
    public function test_15_no_duplicate_mutations_or_journals_on_settlement(): void
    {
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'BCA 123456789',
        ]);

        $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789',
        ]);

        $refundMutationsCount = DepositMutation::where('deposit_ledger_id', $this->bookingA->depositLedger->id)
            ->where('transaction_type', DepositMutationType::REFUND_PAYOUT)
            ->count();
        $this->assertEquals(1, $refundMutationsCount);

        // Jurnal check out settlement
        $journalsCount = JournalEntry::where('source_type', 'check_out_settlement')
            ->where('source_id', $settlement->id)
            ->count();
        $this->assertEquals(1, $journalsCount);

        // Verifikasi debit dan kredit jurnal seimbang
        $journal = JournalEntry::where('source_type', 'check_out_settlement')->first();
        $totalDebit = (float) $journal->lines()->sum('debit');
        $totalCredit = (float) $journal->lines()->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
    }

    /**
     * Skenario Tambahan: Booking dengan status OVERSTAY dapat check out
     */
    public function test_16_overstay_booking_can_check_out(): void
    {
        $this->bookingA->status = BookingStatus::OVERSTAY;
        $this->bookingA->save();

        $this->assertTrue($this->bookingA->canCheckOut());

        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->managerA, [
            'check_out_date' => now()->toDateString(),
        ]);

        $this->assertNotNull($settlement);
        $this->assertEquals($this->bookingA->id, $settlement->booking_id);
    }
}

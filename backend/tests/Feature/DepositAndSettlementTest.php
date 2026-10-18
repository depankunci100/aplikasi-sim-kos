<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\DepositMutationType;
use App\Enums\DepositStatus;
use App\Enums\InspectionItemCondition;
use App\Enums\InspectionStatus;
use App\Enums\InspectionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Enums\SettlementStatus;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\DepositMutation;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SettlementDeduction;
use App\Models\User;
use App\Services\Deposit\DepositService;
use App\Services\Finance\FinancialReportService;
use App\Services\Payment\PaymentService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepositAndSettlementTest extends TestCase
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
    protected PaymentService $paymentService;
    protected FinancialReportService $financialReportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->depositService = app(DepositService::class);
        $this->paymentService = app(PaymentService::class);
        $this->financialReportService = app(FinancialReportService::class);

        // 1. Setup Properties
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

        // 2. Setup Rooms
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

        // 3. Setup Users
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

        // Property access mapping
        DB::table('property_users')->insert([
            ['property_id' => $this->propertyA->id, 'user_id' => $this->managerA->id, 'role' => 'manager', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyA->id, 'user_id' => $this->staffA->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->propertyB->id, 'user_id' => $this->staffB->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 4. Setup Booking A
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
    }

    /**
     * Requirement 1: Deposit received and balance calculation
     */
    public function test_deposit_received_and_balance_calculation()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->assertEquals(0, $ledger->current_balance);
        $this->assertEquals(DepositStatus::PENDING, $ledger->status);

        // Record receipt of 1,000,000
        $mutation = $this->depositService->recordDepositReceived(
            $ledger,
            1000000.00,
            $this->staffA,
            ['payment_method' => 'bank_transfer', 'notes' => 'Penerimaan deposit via transfer BCA']
        );

        $ledger->refresh();
        $this->assertEquals(1000000.00, $ledger->current_balance);
        $this->assertEquals(1000000.00, $ledger->total_received);
        $this->assertEquals(DepositStatus::HELD, $ledger->status);
        $this->assertEquals(DepositMutationType::DEPOSIT_RECEIVED, $mutation->transaction_type);
        $this->assertEquals(0, $mutation->balance_before);
        $this->assertEquals(1000000.00, $mutation->balance_after);
    }

    /**
     * Requirement 2: Deposit is NOT counted as rental revenue
     */
    public function test_deposit_is_not_counted_as_rental_revenue()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        $summary = $this->financialReportService->getDepositSummary($this->superAdmin, ['property_id' => $this->propertyA->id]);
        $this->assertEquals(1000000.00, $summary['total_deposit_held']);
        $this->assertEquals(1000000.00, $summary['deposits_received_period']);
        $this->assertEquals(0, $summary['deductions_period']);
        $this->assertEquals(0, $summary['refunds_paid_period']);

        // Rental revenue from invoices should be separate (no paid rental invoices yet)
        $cashFlow = $this->financialReportService->getCashFlowReport($this->superAdmin, ['property_id' => $this->propertyA->id]);
        $this->assertEquals(0, $cashFlow['summary']['total_cash_in']);
    }

    /**
     * Requirement 3: DP is distinct from security deposit
     */
    public function test_dp_is_distinct_from_security_deposit()
    {
        $bookingWithDp = Booking::create([
            'booking_code' => 'BKG-DP-TEST',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenantA->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rental_type' => 'monthly',
            'status' => BookingStatus::CONFIRMED,
            'total_amount' => 2000000.00,
            'rate_amount' => 2000000.00,
            'deposit_amount' => 1000000.00,
            'down_payment_amount' => 500000.00, // DP sewa
            'guest_name' => 'Tenant Rizky',
            'guest_email' => 'tenantA@test.com',
            'guest_phone' => '081100000005',
        ]);

        $ledger = $this->depositService->getOrCreateLedgerForBooking($bookingWithDp);

        // Ledger initial deposit target must be 1,000,000, NOT 500,000 (which is DP sewa)
        $this->assertEquals(1000000.00, $ledger->initial_deposit_amount);
        $this->assertEquals(500000.00, $bookingWithDp->down_payment_amount);
        $this->assertNotEquals($bookingWithDp->down_payment_amount, $ledger->initial_deposit_amount);
    }

    /**
     * Requirement 4: Full and partial refund recording
     */
    public function test_full_and_partial_refund_recording()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        // Partial refund of 400,000
        $mut1 = $this->depositService->recordRefundPayout($ledger, 400000.00, $this->staffA, [
            'payment_method' => 'bank_transfer',
            'destination' => 'BCA 123456789 a.n. Rizky',
        ]);

        $ledger->refresh();
        $this->assertEquals(600000.00, $ledger->current_balance);
        $this->assertEquals(400000.00, $ledger->total_refunded);
        $this->assertEquals(DepositStatus::PARTIALLY_REFUNDED, $ledger->status);
        $this->assertEquals(400000.00, $mut1->amount);
        $this->assertEquals(600000.00, $mut1->balance_after);

        // Remaining refund of 600,000 (Full refund)
        $mut2 = $this->depositService->recordRefundPayout($ledger, 600000.00, $this->staffA, [
            'payment_method' => 'bank_transfer',
            'destination' => 'BCA 123456789 a.n. Rizky',
        ]);

        $ledger->refresh();
        $this->assertEquals(0.00, $ledger->current_balance);
        $this->assertEquals(1000000.00, $ledger->total_refunded);
        $this->assertEquals(DepositStatus::REFUNDED, $ledger->status);
        $this->assertEquals(0.00, $mut2->balance_after);
    }

    /**
     * Requirement 5: Over-refund is strictly rejected
     */
    public function test_over_refund_is_strictly_rejected()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 500000.00, $this->staffA);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('melebihi saldo deposit yang tersedia');

        // Attempt to refund 600,000 when available is only 500,000
        $this->depositService->recordRefundPayout($ledger, 600000.00, $this->staffA, [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Requirement 6 & 7: Tenant dispute holds the settlement & deductions cannot be finalized without resolution
     */
    public function test_tenant_dispute_holds_settlement_and_deductions()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        // Initiate settlement with a custom deduction
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'custom_deductions' => [
                ['title' => 'Cat Tembok Kotor', 'description' => 'Ada coretan di dinding', 'amount' => 300000.00],
            ],
            'refund_destination' => 'BCA 123456789',
        ]);

        $this->assertEquals(SettlementStatus::PENDING_TENANT_APPROVAL, $settlement->status);
        $this->assertEquals(300000.00, $settlement->total_deductions);
        $this->assertEquals(700000.00, $settlement->net_refund_amount);

        // Tenant submits objection (dispute)
        $disputedSettlement = $this->depositService->submitTenantDecision(
            $settlement,
            $this->tenantA,
            false,
            'Coretan dinding sudah ada saat awal masuk check-in'
        );

        $this->assertEquals(SettlementStatus::DISPUTED, $disputedSettlement->status);
        $this->assertTrue($disputedSettlement->deductions->first()->is_disputed);
        $this->assertFalse($disputedSettlement->deductions->first()->is_approved);

        // Attempting to finalize while in dispute MUST fail
        try {
            $this->depositService->finalizeCheckOutAndRefund($disputedSettlement, $this->managerA, [
                'refund_method' => 'bank_transfer',
            ]);
            $this->fail('Finalization succeeded on disputed settlement without resolution.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Settlement harus berstatus Disetujui (Approved)', $e->getMessage());
        }

        // Manager resolves the dispute: reduces deduction to 100,000
        $deductionId = $disputedSettlement->deductions->first()->id;
        $resolvedSettlement = $this->depositService->resolveSettlementDispute(
            $disputedSettlement,
            $this->managerA,
            [$deductionId => 100000.00],
            'Disepakati pembagian biaya pembersihan separuh ditanggung manajemen kos'
        );

        $this->assertEquals(SettlementStatus::APPROVED, $resolvedSettlement->status);
        $this->assertEquals(100000.00, $resolvedSettlement->total_deductions);
        $this->assertEquals(900000.00, $resolvedSettlement->net_refund_amount);
        $this->assertEquals($this->managerA->id, $resolvedSettlement->resolved_by);
    }

    /**
     * Requirement 8: Duplicate request and idempotency in payment verification
     */
    public function test_idempotent_payment_verification_prevents_duplicate_deposit_receipt()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'booking_id' => $this->bookingA->id,
            'property_id' => $this->propertyA->id,
            'tenant_id' => $this->tenantA->id,
            'type' => 'deposit',
            'amount' => 1000000.00,
            'due_date' => now()->addDays(2),
            'status' => InvoiceStatus::UNPAID,
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-TEST-001',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenantA->id,
            'amount' => 1000000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        // First verification
        $this->paymentService->verifyPayment($payment, $this->staffA);

        $ledger->refresh();
        $this->assertEquals(1000000.00, $ledger->current_balance);
        $this->assertEquals(1, DepositMutation::where('payment_id', $payment->id)->count());

        // Attempting to re-verify or handle same payment must not create a duplicate mutation
        $this->depositService->handleVerifiedDepositPayment($payment, $this->staffA);

        $ledger->refresh();
        $this->assertEquals(1000000.00, $ledger->current_balance);
        $this->assertEquals(1, DepositMutation::where('payment_id', $payment->id)->count());
    }

    /**
     * Requirement 10: Check-out and inspection integration (Phase 3)
     */
    public function test_check_out_settlement_imports_findings_from_room_inspection()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        // Create an inspection of type CHECK_OUT
        $inspection = Inspection::create([
            'inspection_code' => 'INSP-CHECKOUT-001',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'inspector_id' => $this->staffA->id,
            'booking_id' => $this->bookingA->id,
            'type' => InspectionType::CHECK_OUT,
            'scheduled_date' => now()->toDateString(),
            'status' => InspectionStatus::COMPLETED,
        ]);

        // Add 2 inspection items: 1 in good condition, 1 damaged with repair cost
        InspectionItem::create([
            'inspection_id' => $inspection->id,
            'item_name' => 'Kasur Springbed',
            'condition' => InspectionItemCondition::GOOD,
            'repair_required' => false,
            'estimated_cost' => 0.00,
        ]);

        InspectionItem::create([
            'inspection_id' => $inspection->id,
            'item_name' => 'Remote AC Pecah',
            'condition' => InspectionItemCondition::DAMAGED,
            'repair_required' => true,
            'estimated_cost' => 150000.00,
            'notes' => 'Casing pecah dan tombol power hilang',
        ]);

        // Initiate settlement with inspection linked
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'inspection_id' => $inspection->id,
        ]);

        $this->assertEquals(1, $settlement->deductions->count());
        $deduction = $settlement->deductions->first();
        $this->assertEquals('Kerusakan/Kehilangan: Remote AC Pecah', $deduction->title);
        $this->assertEquals(150000.00, $deduction->approved_amount);
        $this->assertEquals(150000.00, $settlement->total_deductions);
        $this->assertEquals(850000.00, $settlement->net_refund_amount);
    }

    /**
     * Requirement 14 & Operational Rule: Finalization sets booking to COMPLETED and room to DIRTY
     */
    public function test_settlement_finalization_sets_booking_completed_and_room_dirty()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'custom_deductions' => [
                ['title' => 'Kunci Kamar Hilang', 'amount' => 50000.00],
            ],
            'refund_destination' => 'BCA 123456789',
        ]);

        // Tenant approves
        $this->depositService->submitTenantDecision($settlement, $this->tenantA, true);
        $settlement->refresh();
        $this->assertEquals(SettlementStatus::APPROVED, $settlement->status);

        // Finalize settlement & payout
        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_payment_reference' => 'TRF-BCA-998877',
        ]);

        $this->assertEquals(SettlementStatus::SETTLED, $finalized->status);
        $this->assertEquals($this->managerA->id, $finalized->refunded_by);

        // Subledger assertions
        $ledger->refresh();
        $this->assertEquals(0.00, $ledger->current_balance);
        $this->assertEquals(50000.00, $ledger->total_deductions);
        $this->assertEquals(950000.00, $ledger->total_refunded);
        $this->assertEquals(DepositStatus::REFUNDED, $ledger->status);

        // Mutations check: 1 receipt, 1 deduction, 1 refund payout
        $this->assertEquals(3, $ledger->mutations()->count());

        // Booking status check: MUST be COMPLETED
        $this->bookingA->refresh();
        $this->assertEquals(BookingStatus::COMPLETED, $this->bookingA->status);
        $this->assertNotNull($this->bookingA->actual_check_out);

        // CRITICAL OPERATIONAL RULE: Room status MUST be DIRTY (NOT AVAILABLE)
        $this->roomA->refresh();
        $this->assertEquals(RoomStatus::DIRTY, $this->roomA->status);
    }

    /**
     * Requirement 11: Tenant isolation & property isolation
     */
    public function test_tenant_cannot_view_other_tenants_deposits()
    {
        $ledgerA = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);

        // Tenant B trying to access Tenant A's deposit via API
        $response = $this->actingAs($this->tenantB, 'sanctum')
            ->getJson("/api/v1/deposits/{$ledgerA->id}");

        $response->assertStatus(403);
    }

    public function test_property_isolation_prevents_unauthorized_staff_access()
    {
        $ledgerA = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);

        // Staff B belongs only to Property B, trying to access Property A deposit
        $response = $this->actingAs($this->staffB)
            ->get("/dashboard/deposits/{$ledgerA->id}");

        $response->assertStatus(403);
    }

    /**
     * Requirement 12: Role permissions for resolving dispute and finalizing refund
     */
    public function test_tenant_cannot_finalize_settlement_or_resolve_dispute()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'custom_deductions' => [
                ['title' => 'Denda Kunci', 'amount' => 50000.00],
            ],
        ]);

        // Tenant attempts to finalize settlement via API
        $response = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson("/api/v1/settlements/{$settlement->id}/finalize-refund", [
                'refund_method' => 'bank_transfer',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Requirement 16: Blade dashboard routes and views render properly
     */
    public function test_blade_deposit_and_settlement_pages_render_correctly()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        // Deposit index
        $res1 = $this->actingAs($this->managerA)->get('/dashboard/deposits');
        $res1->assertStatus(200);
        $res1->assertSee('Uang Jaminan (Deposit)');
        $res1->assertSee($this->bookingA->booking_code);

        // Deposit show
        $res2 = $this->actingAs($this->managerA)->get("/dashboard/deposits/{$ledger->id}");
        $res2->assertStatus(200);
        $res2->assertSee('Rekonsiliasi Saldo Uang Jaminan');
        $res2->assertSee('Rp 1.000.000');

        // Settlements index
        $res3 = $this->actingAs($this->managerA)->get('/dashboard/settlements');
        $res3->assertStatus(200);
        $res3->assertSee('Penyelesaian Check-Out & Refund');
    }

    /**
     * Requirement 17: REST API endpoints contract
     */
    public function test_api_endpoints_contract_for_deposits_and_settlements()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        // 1. List deposits API
        $res1 = $this->actingAs($this->managerA, 'sanctum')->getJson('/api/v1/deposits');
        $res1->assertStatus(200);
        $res1->assertJsonPath('success', true);

        // 2. Deposit detail API
        $res2 = $this->actingAs($this->managerA, 'sanctum')->getJson("/api/v1/deposits/{$ledger->id}");
        $res2->assertStatus(200);
        $res2->assertJsonPath('data.deposit_code', $ledger->deposit_code);
        $this->assertEquals(1000000.00, (float) $res2->json('data.current_balance'));

        // 3. Initiate settlement API
        $res3 = $this->actingAs($this->managerA, 'sanctum')->postJson('/api/v1/settlements', [
            'booking_id' => $this->bookingA->id,
            'check_out_date' => now()->toDateString(),
            'refund_destination' => 'BCA 123456789 a.n. Rizky',
            'custom_deductions' => [
                ['title' => 'Biaya Kebersihan Ekstra', 'amount' => 100000.00],
            ],
        ]);
        $res3->assertStatus(201);
        $settlementId = $res3->json('data.id');

        // 4. Tenant approval API
        $res4 = $this->actingAs($this->tenantA, 'sanctum')->postJson("/api/v1/settlements/{$settlementId}/tenant-decision", [
            'decision' => 'approve',
        ]);
        $res4->assertStatus(200);
        $res4->assertJsonPath('data.status', 'approved');

        // 5. Finalize refund API
        $res5 = $this->actingAs($this->managerA, 'sanctum')->postJson("/api/v1/settlements/{$settlementId}/finalize-refund", [
            'refund_method' => 'bank_transfer',
            'refund_destination' => 'BCA 123456789 a.n. Rizky',
            'refund_payment_reference' => 'BCA-TRF-API-1122',
        ]);
        $res5->assertStatus(200);
        $res5->assertJsonPath('data.status', 'settled');
    }

    /**
     * Requirement: Adjustment credit and debit with audit trail
     */
    public function test_adjustment_credit_and_debit_with_audit_trail()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 500000.00, $this->staffA);

        // Adjustment credit +100,000
        $mutCredit = $this->depositService->recordAdjustment(
            $ledger,
            100000.00,
            DepositMutationType::ADJUSTMENT_CREDIT->value,
            $this->superAdmin,
            'Koreksi selisih kurs transfer deposit penyewa'
        );

        $ledger->refresh();
        $this->assertEquals(600000.00, $ledger->current_balance);
        $this->assertEquals(DepositMutationType::ADJUSTMENT_CREDIT, $mutCredit->transaction_type);
        $this->assertStringContainsString('Koreksi selisih kurs', $mutCredit->notes);

        // Adjustment debit -50,000
        $mutDebit = $this->depositService->recordAdjustment(
            $ledger,
            50000.00,
            DepositMutationType::ADJUSTMENT_DEBIT->value,
            $this->superAdmin,
            'Koreksi kelebihan pencatatan'
        );

        $ledger->refresh();
        $this->assertEquals(550000.00, $ledger->current_balance);
        $this->assertEquals(DepositMutationType::ADJUSTMENT_DEBIT, $mutDebit->transaction_type);
    }

    /**
     * Requirement: Cannot initiate multiple active settlements for same booking
     */
    public function test_cannot_initiate_multiple_active_settlements_for_same_booking()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 1000000.00, $this->staffA);

        $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'refund_destination' => 'BCA 123456789',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Sudah ada proses settlement check-out aktif');

        // Second attempt must fail
        $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'refund_destination' => 'BCA 123456789',
        ]);
    }

    /**
     * Requirement: Deductions exceeding deposit balance cap at available balance and keep ledger exact
     */
    public function test_deduction_exceeding_deposit_balance_caps_at_available_balance()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);
        $this->depositService->recordDepositReceived($ledger, 500000.00, $this->staffA);

        // Huge damage of 1,200,000 on a 500,000 deposit
        $settlement = $this->depositService->initiateCheckOutSettlement($this->bookingA, $this->staffA, [
            'custom_deductions' => [
                ['title' => 'Kerusakan TV LED', 'amount' => 1200000.00],
            ],
        ]);

        $this->depositService->submitTenantDecision($settlement, $this->tenantA, true);
        $settlement->refresh();

        $finalized = $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
        ]);

        $ledger->refresh();
        $this->assertEquals(0.00, $ledger->current_balance);
        $this->assertEquals(500000.00, $ledger->total_deductions);
        $this->assertEquals(0.00, $ledger->total_refunded);
        $this->assertEquals(DepositStatus::SETTLED, $ledger->status);

        // Mutation check: amount must be exactly 500,000 (absorbed by deposit)
        $deductionMutation = DepositMutation::where('deposit_ledger_id', $ledger->id)
            ->where('transaction_type', DepositMutationType::DAMAGE_DEDUCTION)
            ->first();
        $this->assertNotNull($deductionMutation);
        $this->assertEquals(500000.00, $deductionMutation->amount);
        $this->assertEquals(500000.00, $deductionMutation->balance_before);
        $this->assertEquals(0.00, $deductionMutation->balance_after);
    }

    /**
     * Requirement: Re-verification of payment is rejected and does not duplicate mutations
     */
    public function test_re_verification_of_already_verified_payment_is_rejected()
    {
        $ledger = $this->depositService->getOrCreateLedgerForBooking($this->bookingA);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-REVERIFY-001',
            'booking_id' => $this->bookingA->id,
            'property_id' => $this->propertyA->id,
            'tenant_id' => $this->tenantA->id,
            'type' => 'deposit',
            'amount' => 1000000.00,
            'due_date' => now()->addDays(2),
            'status' => InvoiceStatus::UNPAID,
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-REVERIFY-001',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenantA->id,
            'amount' => 1000000.00,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $this->paymentService->verifyPayment($payment, $this->staffA);
        $payment->refresh();
        $this->assertEquals('verified', $payment->status);

        // Re-verification must throw DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("sudah berstatus 'verified' dan tidak dapat diproses lagi");

        $this->paymentService->verifyPayment($payment, $this->staffA);
    }
}

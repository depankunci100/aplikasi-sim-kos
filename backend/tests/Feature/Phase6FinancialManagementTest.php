<?php

namespace Tests\Feature;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase6FinancialManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $owner;
    protected User $manager;
    protected User $staff;
    protected User $tenant;
    protected Property $property;
    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Property
        $this->property = Property::create([
            'code' => 'PROP-KPH',
            'name' => 'Kos Permata Hijau',
            'slug' => 'kos-permata-hijau',
            'type' => 'boarding_house',
            'address' => 'Jl. Hijau No. 1',
            'city' => 'Jakarta Selatan',
            'is_active' => true,
        ]);

        // 2. Setup Users
        $this->superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'phone' => '081100000001',
            'password' => bcrypt('Password123!'),
            'role' => Role::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->owner = User::create([
            'name' => 'Owner Haji Huda',
            'email' => 'owner@test.com',
            'phone' => '081100000002',
            'password' => bcrypt('Password123!'),
            'role' => Role::OWNER,
            'is_active' => true,
        ]);
        $this->property->users()->attach($this->owner->id, ['role' => 'owner']);

        $this->manager = User::create([
            'name' => 'Manager Budi',
            'email' => 'manager@test.com',
            'phone' => '081100000003',
            'password' => bcrypt('Password123!'),
            'role' => Role::MANAGER,
            'is_active' => true,
        ]);
        $this->property->users()->attach($this->manager->id, ['role' => 'manager']);

        $this->staff = User::create([
            'name' => 'Staff Agus',
            'email' => 'staff@test.com',
            'phone' => '081100000004',
            'password' => bcrypt('Password123!'),
            'role' => Role::STAFF,
            'is_active' => true,
        ]);
        $this->property->users()->attach($this->staff->id, ['role' => 'staff']);

        $this->tenant = User::create([
            'name' => 'Tenant Siti',
            'email' => 'tenant@test.com',
            'phone' => '081100000005',
            'password' => bcrypt('Password123!'),
            'role' => Role::TENANT,
            'is_active' => true,
        ]);

        // 3. Category
        $this->category = ExpenseCategory::create([
            'property_id' => $this->property->id,
            'name' => 'Listrik & Air',
            'code' => 'electricity_water',
            'description' => 'Utilitas operasional',
            'is_active' => true,
        ]);
    }

    public function test_staff_can_create_expense_draft()
    {
        $response = $this->actingAs($this->staff)->post(route('dashboard.expenses.store'), [
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'title' => 'Beli Token Listrik Utama',
            'vendor_name' => 'PLN Mobile',
            'amount' => 500000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'action_type' => 'draft',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', [
            'title' => 'Beli Token Listrik Utama',
            'amount' => 500000,
            'approval_status' => 'draft',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_user_can_submit_draft_expense_for_approval()
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-20261016-0001',
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'user_id' => $this->staff->id,
            'title' => 'Perbaikan Kran Air',
            'category' => 'maintenance',
            'amount' => 150000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::DRAFT,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->staff)->post(route('dashboard.expenses.submit', $expense->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'approval_status' => 'submitted',
        ]);
    }

    public function test_segregation_of_duties_prevents_submitter_from_approving_own_expense()
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-20261016-0002',
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'user_id' => $this->manager->id, // Created by Manager
            'title' => 'Pembelian Alat Kebersihan',
            'category' => 'housekeeping',
            'amount' => 200000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::SUBMITTED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'paid_amount' => 0,
        ]);

        // Manager tries to approve his own expense
        $response = $this->actingAs($this->manager)->post(route('dashboard.expenses.approve', $expense->id));

        $response->assertSessionHas('error');
        $this->assertEquals(ExpenseApprovalStatus::SUBMITTED, $expense->fresh()->approval_status);

        // Owner (independent reviewer) can approve it
        $approveResponse = $this->actingAs($this->owner)->post(route('dashboard.expenses.approve', $expense->id), [
            'notes' => 'Disetujui untuk dibeli',
        ]);
        $approveResponse->assertSessionHas('success');
        $this->assertEquals(ExpenseApprovalStatus::APPROVED, $expense->fresh()->approval_status);
        $this->assertEquals($this->owner->id, $expense->fresh()->approved_by);
    }

    public function test_manager_can_reject_submitted_expense_with_reason()
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-20261016-0003',
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'user_id' => $this->staff->id,
            'title' => 'Beli Meja Tambahan',
            'category' => 'supplies',
            'amount' => 800000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::SUBMITTED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->manager)->post(route('dashboard.expenses.reject', $expense->id), [
            'rejection_reason' => 'Anggaran bulan ini sudah melebihi plafon.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'approval_status' => 'rejected',
            'rejection_reason' => 'Anggaran bulan ini sudah melebihi plafon.',
        ]);
    }

    public function test_recording_full_and_partial_payment_prevents_overpayment()
    {
        $expense = Expense::create([
            'expense_number' => 'EXP-20261016-0004',
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'user_id' => $this->staff->id,
            'title' => 'Pengecatan Tembok Depan',
            'category' => 'maintenance',
            'amount' => 1000000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::APPROVED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'paid_amount' => 0,
        ]);

        // 1. Partial payment Rp 400.000
        $resp1 = $this->actingAs($this->manager)->post(route('dashboard.expenses.pay', $expense->id), [
            'amount' => 400000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);
        $resp1->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals(400000, $expense->paid_amount);
        $this->assertEquals(ExpensePaymentStatus::PARTIALLY_PAID, $expense->payment_status);

        // 2. Overpayment attempt: Try to pay Rp 700.000 (Remaining is only 600.000)
        $resp2 = $this->actingAs($this->manager)->post(route('dashboard.expenses.pay', $expense->id), [
            'amount' => 700000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);
        $resp2->assertSessionHas('error');

        // 3. Final payment of remaining Rp 600.000
        $resp3 = $this->actingAs($this->manager)->post(route('dashboard.expenses.pay', $expense->id), [
            'amount' => 600000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
        ]);
        $resp3->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals(1000000, $expense->paid_amount);
        $this->assertEquals(ExpensePaymentStatus::PAID, $expense->payment_status);
        $this->assertEquals(2, $expense->payments()->count());
    }

    public function test_prevent_payment_on_unapproved_expense()
    {
        $draftExpense = Expense::create([
            'expense_number' => 'EXP-20261016-0005',
            'property_id' => $this->property->id,
            'user_id' => $this->staff->id,
            'title' => 'Perbaikan Pintu',
            'category' => 'maintenance',
            'amount' => 300000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::DRAFT,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'paid_amount' => 0,
        ]);

        $response = $this->actingAs($this->manager)->post(route('dashboard.expenses.pay', $draftExpense->id), [
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHas('error');
        $this->assertEquals(0, $draftExpense->fresh()->paid_amount);
    }

    public function test_tenant_cannot_access_financial_management_or_reports()
    {
        // Tenant cannot view expenses index
        $response = $this->actingAs($this->tenant)->get(route('dashboard.expenses.index'));
        $response->assertStatus(403);

        // Tenant cannot view finance dashboard
        $response = $this->actingAs($this->tenant)->get(route('dashboard.finance.index'));
        $response->assertStatus(403);

        // Tenant cannot view income report
        $response = $this->actingAs($this->tenant)->get(route('dashboard.reports.income'));
        $response->assertStatus(403);
    }

    public function test_financial_reports_and_csv_export()
    {
        // Setup room, booking, and invoice
        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Deluxe Room',
            'base_price' => 2000000,
        ]);

        $room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'occupied',
        ]);

        $booking = Booking::create([
            'booking_code' => 'BK-TEST-001',
            'property_id' => $this->property->id,
            'room_id' => $room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'total_amount' => 2000000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-20261016-0001',
            'property_id' => $this->property->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'room_rental',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 2000000,
            'total_amount' => 2000000,
            'paid_amount' => 2000000,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->addDays(5)->toDateString(),
            'paid_at' => now(),
        ]);

        // Verified Payment
        Payment::create([
            'payment_code' => 'PAY-TEST-001',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenant->id,
            'payment_method' => 'bank_transfer',
            'amount' => 2000000,
            'status' => 'verified',
            'verified_by' => $this->manager->id,
            'verified_at' => now(),
        ]);

        // Test Income Report View
        $incomeResp = $this->actingAs($this->manager)->get(route('dashboard.reports.income'));
        $incomeResp->assertStatus(200);
        $incomeResp->assertSee('2.000.000');

        // Test Cash Flow View
        $cashFlowResp = $this->actingAs($this->manager)->get(route('dashboard.reports.cash-flow'));
        $cashFlowResp->assertStatus(200);

        // Test Operating Summary View
        $summaryResp = $this->actingAs($this->owner)->get(route('dashboard.reports.operating-summary'));
        $summaryResp->assertStatus(200);

        // Test CSV Export
        $exportResp = $this->actingAs($this->manager)->get(route('dashboard.reports.export', ['type' => 'income']));
        $exportResp->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $exportResp->headers->get('content-type'));
    }

    public function test_api_v1_expense_and_financial_analytics()
    {
        // 1. API: List expenses
        $apiResp = $this->actingAs($this->manager, 'sanctum')->getJson('/api/v1/expenses');
        $apiResp->assertStatus(200);
        $apiResp->assertJsonPath('success', true);

        // 2. API: Create expense via API
        $createResp = $this->actingAs($this->manager, 'sanctum')->postJson('/api/v1/expenses', [
            'property_id' => $this->property->id,
            'expense_category_id' => $this->category->id,
            'title' => 'Pengeluaran via API',
            'amount' => 350000,
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'action_type' => 'submit',
        ]);
        $createResp->assertStatus(201);
        $newExpenseId = $createResp->json('data.id');

        // 3. API: Financial Dashboard Analytics
        $dashResp = $this->actingAs($this->owner, 'sanctum')->getJson('/api/v1/finance/dashboard');
        $dashResp->assertStatus(200);
        $dashResp->assertJsonStructure([
            'success',
            'data' => [
                'kpis' => [
                    'total_verified_receipts',
                    'total_paid_expenses',
                    'net_cash_flow',
                    'total_receivables',
                    'occupancy_rate',
                ],
                'chart_trend',
                'expense_categories',
            ],
        ]);
    }
}

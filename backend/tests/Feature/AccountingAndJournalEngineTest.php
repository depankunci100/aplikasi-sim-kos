<?php

namespace Tests\Feature;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\BookingStatus;
use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\Role;
use App\Enums\RoomStatus;
use App\Enums\SettlementStatus;
use App\Models\Account;
use App\Models\Booking;
use App\Models\CheckOutSettlement;
use App\Models\DepositLedger;
use App\Models\DepositMutation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SettlementDeduction;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Deposit\DepositService;
use App\Services\Finance\ExpenseService;
use App\Services\Payment\PaymentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountingAndJournalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected Property $propertyA;
    protected Property $propertyB;
    protected User $superAdmin;
    protected User $managerA;
    protected User $managerB;
    protected User $tenant;
    protected Room $roomA;
    protected ChartOfAccountService $coaService;
    protected JournalPostingService $journalService;
    protected AccountingReportService $reportService;
    protected PaymentService $paymentService;
    protected ExpenseService $expenseService;
    protected DepositService $depositService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->coaService = app(ChartOfAccountService::class);
        $this->journalService = app(JournalPostingService::class);
        $this->reportService = app(AccountingReportService::class);
        $this->paymentService = app(PaymentService::class);
        $this->expenseService = app(ExpenseService::class);
        $this->depositService = app(DepositService::class);

        // 1. Buat properti uji
        $this->propertyA = Property::create([
            'code' => 'PROP-A',
            'name' => 'Kos Permata Hijau A',
            'type' => 'boarding_house',
            'address' => 'Jl. Hijau No. 1',
            'city' => 'Jakarta Selatan',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-B',
            'name' => 'Guesthouse Melati B',
            'type' => 'guesthouse',
            'address' => 'Jl. Melati No. 2',
            'city' => 'Bandung',
            'is_active' => true,
        ]);

        // 2. Buat Pengguna
        $this->superAdmin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@test.id',
            'phone' => '0811000001',
            'password' => bcrypt('password'),
            'role' => Role::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->managerA = User::create([
            'name' => 'Manager Properti A',
            'email' => 'manager.a@test.id',
            'phone' => '0811000002',
            'password' => bcrypt('password'),
            'role' => Role::MANAGER,
            'is_active' => true,
        ]);
        $this->managerA->properties()->attach($this->propertyA->id, ['role' => 'manager']);

        $this->managerB = User::create([
            'name' => 'Manager Properti B',
            'email' => 'manager.b@test.id',
            'phone' => '0811000003',
            'password' => bcrypt('password'),
            'role' => Role::MANAGER,
            'is_active' => true,
        ]);
        $this->managerB->properties()->attach($this->propertyB->id, ['role' => 'manager']);

        $this->tenant = User::create([
            'name' => 'Penyewa Budi',
            'email' => 'tenant.budi@test.id',
            'phone' => '0811000004',
            'password' => bcrypt('password'),
            'role' => Role::TENANT,
            'is_active' => true,
        ]);

        // 3. Buat Kamar
        $roomType = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Kamar Standard',
            'base_price' => 1500000,
        ]);

        $this->roomA = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => RoomStatus::AVAILABLE,
        ]);
    }

    /**
     * 1. Test Inisialisasi COA Bawaan dan Isolasi Properti
     */
    public function test_default_coa_seeded_and_property_isolated(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);

        $accountsA = Account::where('property_id', $this->propertyA->id)->get();
        $this->assertGreaterThanOrEqual(15, $accountsA->count());

        // Pastikan akun wajib ada
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '1101')->first()); // Kas
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '1102')->first()); // Bank
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '2101')->first()); // Deposit
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '2102')->first()); // Sewa di Muka
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '4101')->first()); // Pendapatan Kos
        $this->assertNotNull(Account::where('property_id', $this->propertyA->id)->where('code', '5101')->first()); // Beban Listrik

        // Manager B tidak boleh melihat akun Properti A
        $response = $this->actingAs($this->managerB)
            ->getJson("/api/v1/finance/accounting/coa?property_id={$this->propertyA->id}");

        $response->assertStatus(403);
    }

    /**
     * 2. Test Mesin Jurnal Wajib Seimbang (Total Debit == Total Kredit)
     */
    public function test_journal_posting_requires_balanced_debit_credit(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');

        // Skenario A: Jurnal Seimbang -> Berhasil
        $lines = [
            ['account_id' => $bankAcc->id, 'debit' => 1500000, 'credit' => 0, 'memo' => 'Debit Kas'],
            ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 1500000, 'memo' => 'Kredit Pendapatan'],
        ];

        $journal = $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:balanced:01',
            'Test Jurnal Seimbang',
            $lines,
            now(),
            $this->managerA
        );

        $this->assertNotNull($journal);
        $this->assertEquals(JournalEntryStatus::POSTED, $journal->status);
        $this->assertEquals(1500000, (float) $journal->total_amount);
        $this->assertTrue($journal->isBalanced());

        // Skenario B: Jurnal Tidak Seimbang -> Harus Melempar DomainException
        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/tidak seimbang/i');

        $unbalancedLines = [
            ['account_id' => $bankAcc->id, 'debit' => 1500000, 'credit' => 0],
            ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 1000000], // Selisih 500.000!
        ];

        $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:unbalanced:01',
            'Test Jurnal Tidak Seimbang',
            $unbalancedLines,
            now(),
            $this->managerA
        );
    }

    /**
     * 3. Test Penolakan Baris Jurnal dengan Nilai Debit & Kredit Positif Bersamaan
     */
    public function test_journal_line_cannot_have_both_positive_debit_and_credit(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessageMatches('/dilarang memuat nilai debit dan kredit positif/i');

        $invalidLines = [
            ['account_id' => $bankAcc->id, 'debit' => 500000, 'credit' => 500000], // Tidak valid!
            ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => 0],
        ];

        $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:invalid_line:01',
            'Test Baris Tidak Valid',
            $invalidLines,
            now(),
            $this->managerA
        );
    }

    /**
     * 4. Test Idempotensi Posting Jurnal (Mencegah Duplikasi Jurnal)
     */
    public function test_journal_posting_is_strictly_idempotent(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');

        $lines = [
            ['account_id' => $bankAcc->id, 'debit' => 2000000, 'credit' => 0],
            ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 2000000],
        ];

        $key = 'test:idempotent:key:123';

        // Posting pertama
        $journal1 = $this->journalService->postJournal(
            $this->propertyA->id,
            'payment',
            100,
            $key,
            'Posting Pertama',
            $lines,
            now(),
            $this->managerA
        );

        // Posting kedua dengan kunci yang sama
        $journal2 = $this->journalService->postJournal(
            $this->propertyA->id,
            'payment',
            100,
            $key,
            'Posting Kedua (Harus Idempoten)',
            $lines,
            now(),
            $this->managerA
        );

        $this->assertEquals($journal1->id, $journal2->id);
        $this->assertEquals(1, JournalEntry::where('idempotency_key', $key)->count());
    }

    /**
     * 5. Test Pembayaran Sewa Periode Berjalan Otomatis Masuk Pendapatan Sewa (4101)
     */
    public function test_rental_payment_posts_to_revenue_for_current_period(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-ACC-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => now()->subDays(5)->toDateString(), // Periode sudah berjalan
            'end_date' => now()->addDays(25)->toDateString(),
            'total_amount' => 1500000,
            'deposit_amount' => 0,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-ACC-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => now()->subDays(5)->toDateString(),
            'period_end' => now()->addDays(25)->toDateString(),
            'total_amount' => 1500000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(1)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-ACC-01',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 1500000,
            'payment_method' => 'manual_transfer',
            'status' => 'pending',
        ]);

        // Verifikasi pembayaran oleh manager
        $verifiedPayment = $this->paymentService->verifyPayment($payment, $this->managerA, 'Pembayaran sewa sah');

        $this->assertEquals('verified', $verifiedPayment->status);

        // Periksa jurnal otomatis yang terbentuk
        $journal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:rent")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(JournalEntryStatus::POSTED, $journal->status);
        $this->assertEquals(1500000, (float) $journal->total_amount);

        // Baris Debit: 1102 (Bank Operasional), Baris Kredit: 4101 (Pendapatan Sewa Kamar Kos)
        $lines = $journal->lines()->with('account')->get();
        $this->assertCount(2, $lines);

        $debitLine = $lines->firstWhere('debit', '>', 0);
        $creditLine = $lines->firstWhere('credit', '>', 0);

        $this->assertEquals('1102', $debitLine->account->code);
        $this->assertEquals('4101', $creditLine->account->code);
    }

    /**
     * 6. Test Pembayaran Sewa di Muka (Advance Payment / DP) Masuk Kewajiban (2102)
     */
    public function test_advance_rental_payment_posts_to_unearned_revenue(): void
    {
        $futureStart = now()->addDays(15); // Periode sewa baru mulai 15 hari lagi

        $booking = Booking::create([
            'booking_code' => 'BOOK-ADV-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => $futureStart->toDateString(),
            'end_date' => $futureStart->copy()->addMonth()->toDateString(),
            'total_amount' => 1500000,
            'deposit_amount' => 0,
            'status' => BookingStatus::CONFIRMED,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-ADV-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => $futureStart->toDateString(),
            'period_end' => $futureStart->copy()->addMonth()->toDateString(),
            'total_amount' => 1500000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(1)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-ADV-01',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 1500000,
            'payment_method' => 'manual_transfer',
            'status' => 'pending',
        ]);

        $this->paymentService->verifyPayment($payment, $this->managerA, 'DP / Pembayaran di muka');

        // Jurnal sewa di muka
        $journal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:advance_rent")->first();
        $this->assertNotNull($journal);

        $lines = $journal->lines()->with('account')->get();
        $creditLine = $lines->firstWhere('credit', '>', 0);

        // Wajib mengkredit Akun 2102 (Pendapatan Sewa Diterima di Muka - Kewajiban Lancar), BUKAN 4101!
        $this->assertEquals('2102', $creditLine->account->code);
        $this->assertEquals(AccountType::LIABILITY, $creditLine->account->type);
    }

    /**
     * 7. Test Pemisahan Uang Jaminan (Deposit) dari Pendapatan (Masuk Akun 2101)
     */
    public function test_deposit_payment_strictly_credited_to_deposit_liability_not_revenue(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-DEP-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_amount' => 2000000, // Sewa 1.500.000 + Deposit 500.000
            'deposit_amount' => 500000,
            'status' => BookingStatus::PENDING,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-DEP-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'total_amount' => 2000000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(1)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-DEP-01',
            'invoice_id' => $invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 2000000,
            'payment_method' => 'manual_transfer',
            'status' => 'pending',
        ]);

        $this->paymentService->verifyPayment($payment, $this->managerA, 'Lunas sewa dan uang jaminan');

        // Harus ada 2 jurnal terpisah yang bersih:
        // 1. Jurnal Deposit: Rp 500.000 (Debit Bank, Kredit 2101 Titipan Uang Jaminan)
        $depositJournal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:deposit")->first();
        $this->assertNotNull($depositJournal);
        $this->assertEquals(500000, (float) $depositJournal->total_amount);

        $depCredit = $depositJournal->lines()->with('account')->get()->firstWhere('credit', '>', 0);
        $this->assertEquals('2101', $depCredit->account->code);
        $this->assertEquals(AccountType::LIABILITY, $depCredit->account->type);

        // 2. Jurnal Sewa: Rp 1.500.000 (Debit Bank, Kredit 4101 Pendapatan Sewa Kamar)
        $rentJournal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:rent")->first();
        $this->assertNotNull($rentJournal);
        $this->assertEquals(1500000, (float) $rentJournal->total_amount);
    }

    /**
     * 8. Test Jurnal Pengeluaran Biaya Operasional (Expense Payment)
     */
    public function test_expense_payment_debits_appropriate_expense_category_and_credits_cash(): void
    {
        $category = ExpenseCategory::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Listrik & Air PLN/PDAM',
            'code' => 'electricity_water',
            'is_active' => true,
        ]);

        $expense = Expense::create([
            'property_id' => $this->propertyA->id,
            'expense_category_id' => $category->id,
            'user_id' => $this->managerA->id,
            'title' => 'Tagihan Listrik PLN Bulan Oktober',
            'category' => 'electricity_water',
            'amount' => 1250000,
            'paid_amount' => 0,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::APPROVED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
        ]);

        $payment = $this->expenseService->recordPayment(
            $this->managerA,
            $expense,
            [
                'amount' => 1250000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
                'notes' => 'Pembayaran tunai kasir',
            ]
        );

        // Periksa jurnal otomatis beban
        $journal = JournalEntry::where('idempotency_key', "expense_payment:{$payment->id}")->first();
        $this->assertNotNull($journal);
        $this->assertEquals(1250000, (float) $journal->total_amount);

        $lines = $journal->lines()->with('account')->get();
        $debitLine = $lines->firstWhere('debit', '>', 0);
        $creditLine = $lines->firstWhere('credit', '>', 0);

        // Debit: 5101 (Beban Listrik & Air), Kredit: 1101 (Kas Tunai)
        $this->assertEquals('5101', $debitLine->account->code);
        $this->assertEquals('1101', $creditLine->account->code);
    }

    /**
     * 9. Test Jurnal Check-Out Settlement (Potongan Kerusakan & Net Refund)
     */
    public function test_checkout_settlement_journal_records_damage_revenue_and_refund_cash_out(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-SETTL-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_amount' => 2000000,
            'deposit_amount' => 500000,
            'status' => BookingStatus::ACTIVE,
        ]);

        // Buat ledger dengan deposit terisi Rp 500.000
        $ledger = $this->depositService->getOrCreateLedgerForBooking($booking);
        $this->depositService->recordDepositReceived($ledger, 500000, $this->managerA, [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TEST-DEP',
        ]);

        // Buat settlement: Denda kerusakan 200.000, Net refund 300.000
        $settlement = CheckOutSettlement::create([
            'settlement_code' => 'SET-TEST-01',
            'booking_id' => $booking->id,
            'deposit_ledger_id' => $ledger->id,
            'property_id' => $this->propertyA->id,
            'tenant_id' => $this->tenant->id,
            'check_out_date' => now()->toDateString(),
            'status' => SettlementStatus::APPROVED,
            'deposit_balance_before' => 500000,
            'total_deductions' => 200000,
            'net_refund_amount' => 300000,
            'refund_destination' => 'BCA 1234567890 a/n Budi',
        ]);

        SettlementDeduction::create([
            'settlement_id' => $settlement->id,
            'title' => 'Kerusakan Kunci Pintu',
            'proposed_amount' => 200000,
            'approved_amount' => 200000,
            'is_approved' => true,
        ]);

        // Finalisasi check-out
        $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
            'refund_payment_reference' => 'REF-TRF-01',
        ]);

        // 1. Jurnal Potongan Kerusakan: Debit 2101 (Rp 200.000), Kredit 4104 (Rp 200.000)
        $deductionJournal = JournalEntry::where('idempotency_key', "settlement:{$settlement->id}:deduction")->first();
        $this->assertNotNull($deductionJournal);
        $this->assertEquals(200000, (float) $deductionJournal->total_amount);

        $dDebit = $deductionJournal->lines()->with('account')->get()->firstWhere('debit', '>', 0);
        $dCredit = $deductionJournal->lines()->with('account')->get()->firstWhere('credit', '>', 0);
        $this->assertEquals('2101', $dDebit->account->code);
        $this->assertEquals('4104', $dCredit->account->code);

        // 2. Jurnal Refund Sisa Deposit: Debit 2101 (Rp 300.000), Kredit 1102 (Rp 300.000)
        $refundJournal = JournalEntry::where('idempotency_key', "settlement:{$settlement->id}:refund")->first();
        $this->assertNotNull($refundJournal);
        $this->assertEquals(300000, (float) $refundJournal->total_amount);

        $rDebit = $refundJournal->lines()->with('account')->get()->firstWhere('debit', '>', 0);
        $rCredit = $refundJournal->lines()->with('account')->get()->firstWhere('credit', '>', 0);
        $this->assertEquals('2101', $rDebit->account->code);
        $this->assertEquals('1102', $rCredit->account->code);
    }

    /**
     * 10. Test Pembalikan Jurnal (Reversal) Membentuk Jurnal Pembalik Seimbang
     */
    public function test_journal_reversal_creates_compensating_balanced_entry(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');

        $lines = [
            ['account_id' => $bankAcc->id, 'debit' => 1000000, 'credit' => 0],
            ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 1000000],
        ];

        $journal = $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:reversal:original:01',
            'Jurnal Asli',
            $lines,
            now(),
            $this->managerA
        );

        $this->assertTrue($journal->isPosted());

        // Eksekusi reversal
        $reversal = $this->journalService->reverseJournal($journal, $this->managerA, 'Salah catat nominal');

        $this->assertTrue($journal->fresh()->isReversed());
        $this->assertTrue($reversal->isPosted());
        $this->assertEquals($journal->id, $reversal->reversed_journal_id);
        $this->assertTrue($reversal->isBalanced());

        // Baris debit dan kredit tertukar
        $revLines = $reversal->lines()->with('account')->get();
        $revDebit = $revLines->firstWhere('debit', '>', 0);
        $revCredit = $revLines->firstWhere('credit', '>', 0);

        $this->assertEquals('4101', $revDebit->account->code); // Pendapatan sekarang didebit!
        $this->assertEquals('1102', $revCredit->account->code); // Bank sekarang dikredit!
    }

    /**
     * 11. Test Neraca Saldo (Trial Balance) Seimbang
     */
    public function test_trial_balance_is_balanced(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');
        $expAcc = $this->coaService->getAccountByCode($this->propertyA->id, '5101');

        // 1. Catat Pendapatan Kas masuk Rp 5.000.000
        $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:tb:rev:01',
            'Pendapatan Sewa',
            [
                ['account_id' => $bankAcc->id, 'debit' => 5000000, 'credit' => 0],
                ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 5000000],
            ],
            now(),
            $this->managerA
        );

        // 2. Catat Beban Listrik Rp 1.500.000
        $this->journalService->postJournal(
            $this->propertyA->id,
            'manual_adjustment',
            null,
            'test:tb:exp:01',
            'Beban Listrik',
            [
                ['account_id' => $expAcc->id, 'debit' => 1500000, 'credit' => 0],
                ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => 1500000],
            ],
            now(),
            $this->managerA
        );

        $report = $this->reportService->getTrialBalance($this->propertyA->id);

        $this->assertTrue($report['summary']['is_balanced']);
        $this->assertEquals(0.00, $report['summary']['difference']);
        $this->assertEquals($report['summary']['total_debit'], $report['summary']['total_credit']);
    }

    /**
     * 12. Test Laporan Laba Rugi dan Neraca Keuangan
     */
    public function test_income_statement_and_balance_sheet(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');
        $expAcc = $this->coaService->getAccountByCode($this->propertyA->id, '5101');

        // Pendapatan 10.000.000
        $this->journalService->postJournal(
            $this->propertyA->id,
            'payment',
            null,
            'test:is:rev',
            'Pendapatan',
            [
                ['account_id' => $bankAcc->id, 'debit' => 10000000, 'credit' => 0],
                ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 10000000],
            ],
            now(),
            $this->managerA
        );

        // Beban 3.000.000
        $this->journalService->postJournal(
            $this->propertyA->id,
            'expense_payment',
            null,
            'test:is:exp',
            'Beban',
            [
                ['account_id' => $expAcc->id, 'debit' => 3000000, 'credit' => 0],
                ['account_id' => $bankAcc->id, 'debit' => 0, 'credit' => 3000000],
            ],
            now(),
            $this->managerA
        );

        // Laba Rugi
        $inc = $this->reportService->getIncomeStatement($this->propertyA->id);
        $this->assertEquals(10000000, $inc['total_revenue']);
        $this->assertEquals(3000000, $inc['total_expense']);
        $this->assertEquals(7000000, $inc['net_income']);

        // Neraca Keuangan
        $bs = $this->reportService->getBalanceSheet($this->propertyA->id);
        $this->assertTrue($bs['is_balanced']);
        $this->assertEquals(7000000, $bs['assets']['total']); // Saldo bank: 10jt - 3jt = 7jt
        $this->assertEquals(7000000, $bs['total_liabilities_and_equity']); // Laba tahun berjalan: 7jt
    }

    /**
     * 13. Test Rekonsiliasi Subledger terhadap Jurnal
     */
    public function test_reconciliation_report_checks_match(): void
    {
        $this->coaService->ensureDefaultAccountsForProperty($this->propertyA->id);

        $recon = $this->reportService->getReconciliationReport($this->propertyA->id);

        $this->assertArrayHasKey('cash_inflow_reconciliation', $recon);
        $this->assertArrayHasKey('cash_outflow_reconciliation', $recon);
        $this->assertArrayHasKey('security_deposit_reconciliation', $recon);
        $this->assertTrue($recon['cash_inflow_reconciliation']['is_reconciled']);
        $this->assertTrue($recon['cash_outflow_reconciliation']['is_reconciled']);
        $this->assertTrue($recon['security_deposit_reconciliation']['is_reconciled']);
    }

    /**
     * 14. Test Artisan Command Backfill Idempoten & Mendukung Dry-Run
     */
    public function test_backfill_artisan_command_is_idempotent_and_supports_dry_run(): void
    {
        // Siapkan kandidat transaksi tanpa jurnal
        $booking = Booking::create([
            'booking_code' => 'BOOK-BF-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_amount' => 1200000,
            'deposit_amount' => 0,
            'status' => BookingStatus::ACTIVE,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-BF-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 1200000,
            'total_amount' => 1200000,
            'paid_amount' => 1200000,
            'status' => InvoiceStatus::PAID,
            'due_date' => now()->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-BF-01',
            'invoice_id' => $invoice->id,
            'property_id' => $this->propertyA->id,
            'user_id' => $this->tenant->id,
            'amount' => 1200000,
            'payment_method' => 'bank_transfer',
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $this->managerA->id,
        ]);

        $initialJournalCount = JournalEntry::where('property_id', $this->propertyA->id)->count();

        // 1. Tes dry-run (Simulasi tanpa menulis ke DB)
        $exitCode = Artisan::call('finance:backfill-journals', [
            '--property' => $this->propertyA->id,
            '--dry-run' => true,
        ]);
        $this->assertEquals(0, $exitCode);
        $this->assertEquals($initialJournalCount, JournalEntry::where('property_id', $this->propertyA->id)->count());

        // Bukti transaksi sumber tidak diubah oleh dry-run
        $this->assertEquals('verified', $payment->fresh()->status);
        $this->assertEquals(1200000, (float) $payment->fresh()->amount);

        // 2. Tes eksekusi nyata
        $exitCode2 = Artisan::call('finance:backfill-journals', [
            '--property' => $this->propertyA->id,
        ]);
        $this->assertEquals(0, $exitCode2);

        $postedJournalCount = JournalEntry::where('property_id', $this->propertyA->id)->count();
        $this->assertGreaterThan($initialJournalCount, $postedJournalCount);

        // Bukti transaksi sumber tetap valid dan nominal tidak bermutasi
        $this->assertEquals('verified', $payment->fresh()->status);
        $this->assertEquals(1200000, (float) $payment->fresh()->amount);

        // 3. Tes eksekusi berulang (harus idempoten dan skip yang sudah ada, 0 jurnal baru dibuat)
        $exitCode3 = Artisan::call('finance:backfill-journals', [
            '--property' => $this->propertyA->id,
        ]);
        $this->assertEquals(0, $exitCode3);
        $this->assertEquals($postedJournalCount, JournalEntry::where('property_id', $this->propertyA->id)->count());
    }

    /**
     * 15. Test Penyewa Ditolak (403 Forbidden) Mengakses API & Web Akuntansi
     */
    public function test_tenant_is_forbidden_from_accounting_api_and_web(): void
    {
        // API Test
        $this->actingAs($this->tenant)
            ->getJson('/api/v1/finance/accounting/journals')
            ->assertStatus(403);

        $this->actingAs($this->tenant)
            ->getJson('/api/v1/finance/accounting/reports/trial-balance')
            ->assertStatus(403);

        $this->actingAs($this->tenant)
            ->getJson('/api/v1/finance/accounting/reports/balance-sheet')
            ->assertStatus(403);

        // Web Test
        $this->actingAs($this->tenant)
            ->get('/dashboard/accounting/coa')
            ->assertStatus(403);

        $this->actingAs($this->tenant)
            ->get('/dashboard/accounting/journals')
            ->assertStatus(403);
    }

    /**
     * 16. Test Transaksi Guesthouse (Harian) Masuk ke Akun 4102, Berbeda dari Kos (4101) & Denda (4104)
     */
    public function test_guesthouse_daily_rental_posts_to_4102_distinct_from_monthly_kos_and_damage(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-GUEST-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'rental_type' => \App\Enums\RentalType::DAILY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'total_amount' => 600000,
            'deposit_amount' => 0,
            'status' => BookingStatus::ACTIVE,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-GUEST-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addDays(2)->toDateString(),
            'subtotal' => 600000,
            'total_amount' => 600000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-GUEST-01',
            'invoice_id' => $invoice->id,
            'property_id' => $this->propertyA->id,
            'user_id' => $this->tenant->id,
            'amount' => 600000,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $this->paymentService->verifyPayment($payment, $this->managerA, 'Verifikasi sewa guesthouse harian');

        // Verifikasi Jurnal Terposting ke Akun 4102 (Pendapatan Sewa Guesthouse)
        $journal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:rent")->first();
        $this->assertNotNull($journal);

        $creditLine = $journal->lines->where('credit', '>', 0)->first();
        $this->assertEquals('4102', $creditLine->account->code);
        $this->assertEquals('Pendapatan Sewa Guesthouse', $creditLine->account->name);
        $this->assertEquals(600000, (float) $creditLine->credit);

        // Pastikan akun 4101 (Kos) dan 4104 (Denda) TIDAK tercampur
        $this->assertNotEquals('4101', $creditLine->account->code);
        $this->assertNotEquals('4104', $creditLine->account->code);
    }

    /**
     * 17. Test Pembayaran Utilitas yang Digabung dalam Invoice Terpecah ke 4103 & 4101
     */
    public function test_bundled_utility_invoice_splits_rent_to_4101_and_utility_to_4103(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-BUNDLED-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'rental_type' => \App\Enums\RentalType::MONTHLY,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'total_amount' => 1700000,
            'deposit_amount' => 0,
            'status' => BookingStatus::ACTIVE,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-BUNDLED-01',
            'property_id' => $this->propertyA->id,
            'booking_id' => $booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'period_start' => now()->toDateString(),
            'period_end' => now()->addMonth()->toDateString(),
            'subtotal' => 1700000,
            'total_amount' => 1700000,
            'paid_amount' => 0,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // Tambahkan item utilitas listrik Rp 200.000 ke dalam invoice sewa
        \App\Models\InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => 'electricity',
            'name' => 'Biaya Listrik 100 kWh',
            'quantity' => 100,
            'unit' => 'kWh',
            'rate' => 2000,
            'amount' => 200000,
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-BUNDLED-01',
            'invoice_id' => $invoice->id,
            'property_id' => $this->propertyA->id,
            'user_id' => $this->tenant->id,
            'amount' => 1700000,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
        ]);

        $this->paymentService->verifyPayment($payment, $this->managerA, 'Verifikasi paket sewa + utilitas');

        // 1. Jurnal Utilitas (Rp 200.000 ke 4103)
        $utilJournal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:utility")->first();
        $this->assertNotNull($utilJournal);
        $utilCredit = $utilJournal->lines->where('credit', '>', 0)->first();
        $this->assertEquals('4103', $utilCredit->account->code);
        $this->assertEquals(200000, (float) $utilCredit->credit);

        // 2. Jurnal Sewa (Rp 1.500.000 ke 4101)
        $rentJournal = JournalEntry::where('idempotency_key', "payment:{$payment->id}:rent")->first();
        $this->assertNotNull($rentJournal);
        $rentCredit = $rentJournal->lines->where('credit', '>', 0)->first();
        $this->assertEquals('4101', $rentCredit->account->code);
        $this->assertEquals(1500000, (float) $rentCredit->credit);
    }

    /**
     * 18. Test Denda Melebihi Saldo Deposit (Mencatat Piutang Denda 1203 & Pendapatan 4104)
     */
    public function test_damage_deduction_exceeding_deposit_posts_excess_to_receivable_1203(): void
    {
        $booking = Booking::create([
            'booking_code' => 'BOOK-EXCESS-01',
            'property_id' => $this->propertyA->id,
            'room_id' => $this->roomA->id,
            'user_id' => $this->tenant->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->toDateString(),
            'total_amount' => 2000000,
            'deposit_amount' => 500000,
            'status' => BookingStatus::ACTIVE,
        ]);

        $ledger = $this->depositService->getOrCreateLedgerForBooking($booking);
        $this->depositService->recordDepositReceived($ledger, 500000, $this->managerA, [
            'payment_method' => 'bank_transfer',
            'payment_reference' => 'TEST-DEP-EXC',
        ]);

        // Total denda kerusakan Rp 800.000 (melebihi deposit Rp 500.000 dengan selisih Rp 300.000)
        $settlement = CheckOutSettlement::create([
            'settlement_code' => 'SET-EXCESS-01',
            'booking_id' => $booking->id,
            'deposit_ledger_id' => $ledger->id,
            'property_id' => $this->propertyA->id,
            'tenant_id' => $this->tenant->id,
            'check_out_date' => now()->toDateString(),
            'deposit_balance_before' => 500000,
            'total_deductions' => 800000,
            'net_refund_amount' => 0,
            'status' => \App\Enums\SettlementStatus::APPROVED,
            'created_by' => $this->managerA->id,
        ]);

        \App\Models\SettlementDeduction::create([
            'settlement_id' => $settlement->id,
            'title' => 'Kerusakan AC & Dinding',
            'proposed_amount' => 800000,
            'approved_amount' => 800000,
            'is_approved' => true,
        ]);

        $this->depositService->finalizeCheckOutAndRefund($settlement, $this->managerA, [
            'refund_method' => 'bank_transfer',
        ]);

        // 1. Jurnal Pemotongan Deposit (Rp 500.000: Debit 2101, Credit 4104)
        $jDed = JournalEntry::where('idempotency_key', "settlement:{$settlement->id}:deduction")->first();
        $this->assertNotNull($jDed);
        $this->assertEquals(500000, (float) $jDed->total_amount);
        $this->assertEquals('2101', $jDed->lines->where('debit', '>', 0)->first()->account->code);
        $this->assertEquals('4104', $jDed->lines->where('credit', '>', 0)->first()->account->code);

        // 2. Jurnal Piutang Denda Melebihi Deposit (Rp 300.000: Debit 1203, Credit 4104)
        $jExcess = JournalEntry::where('idempotency_key', "settlement:{$settlement->id}:excess_damage")->first();
        $this->assertNotNull($jExcess);
        $this->assertEquals(300000, (float) $jExcess->total_amount);

        $excessDebit = $jExcess->lines->where('debit', '>', 0)->first();
        $excessCredit = $jExcess->lines->where('credit', '>', 0)->first();

        $this->assertEquals('1203', $excessDebit->account->code); // Piutang Denda & Ganti Rugi
        $this->assertEquals('4104', $excessCredit->account->code); // Pendapatan Denda & Ganti Rugi
        $this->assertEquals(300000, (float) $excessDebit->debit);
        $this->assertEquals(300000, (float) $excessCredit->credit);
    }

    /**
     * 19. Test Jurnal Posted Dilarang Dihapus dan Nilai Pokoknya Dilarang Diubah
     */
    public function test_posted_journal_cannot_be_deleted_or_mutated(): void
    {
        $bankAcc = $this->coaService->getAccountByCode($this->propertyA->id, '1102');
        $revAcc = $this->coaService->getAccountByCode($this->propertyA->id, '4101');

        $journal = $this->journalService->postJournal(
            $this->propertyA->id,
            'manual',
            null,
            'test:immutable:01',
            'Jurnal Uji Immutabilitas',
            [
                ['account_id' => $bankAcc->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $revAcc->id, 'debit' => 0, 'credit' => 500000],
            ],
            now(),
            $this->managerA
        );

        $this->assertTrue($journal->isPosted());

        // 1. Coba hapus jurnal posted -> Harus melempar DomainException
        try {
            $journal->delete();
            $this->fail('Harusnya gagal menghapus jurnal yang sudah berstatus posted.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('dilarang dihapus', $e->getMessage());
        }

        // 2. Coba ubah total_amount jurnal posted -> Harus melempar DomainException
        try {
            $journal->update(['total_amount' => 9999999]);
            $this->fail('Harusnya gagal mengubah nominal total_amount jurnal yang sudah berstatus posted.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('dilarang diubah', $e->getMessage());
        }
    }
}


<?php

namespace Tests\Feature;

use App\Enums\AccountNormalBalance;
use App\Enums\AccountType;
use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\Role;
use App\Models\Account;
use App\Models\Booking;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use App\Services\Accounting\JournalPostingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase9SecurityAndOperationalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected Property $propertyA;
    protected Property $propertyB;
    protected RoomType $roomTypeA;
    protected Room $roomA;
    protected User $staffA;
    protected User $staffB;
    protected User $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->propertyA = Property::create([
            'code' => 'PROP-SEC-A',
            'name' => 'Kos Keamanan A',
            'slug' => 'kos-keamanan-a',
            'type' => 'boarding_house',
            'address' => 'Jl. A No. 1',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->propertyB = Property::create([
            'code' => 'PROP-SEC-B',
            'name' => 'Kos Keamanan B',
            'slug' => 'kos-keamanan-b',
            'type' => 'boarding_house',
            'address' => 'Jl. B No. 2',
            'city' => 'Jakarta',
            'is_active' => true,
        ]);

        $this->roomTypeA = RoomType::create([
            'property_id' => $this->propertyA->id,
            'name' => 'VIP Suite',
            'base_price_monthly' => 2500000,
            'deposit_monthly' => 500000,
        ]);

        $this->roomA = Room::create([
            'property_id' => $this->propertyA->id,
            'room_type_id' => $this->roomTypeA->id,
            'room_number' => 'A-101',
            'status' => 'available',
        ]);

        $this->staffA = User::create([
            'name' => 'Staff Property A',
            'email' => 'staff_a@test.com',
            'phone' => '08123456701',
            'password' => bcrypt('Password123!'),
            'role' => Role::STAFF,
            'is_active' => true,
        ]);
        $this->staffA->properties()->attach($this->propertyA->id, ['role' => 'staff']);

        $this->staffB = User::create([
            'name' => 'Staff Property B',
            'email' => 'staff_b@test.com',
            'phone' => '08123456702',
            'password' => bcrypt('Password123!'),
            'role' => Role::STAFF,
            'is_active' => true,
        ]);
        $this->staffB->properties()->attach($this->propertyB->id, ['role' => 'staff']);

        $this->tenant = User::create([
            'name' => 'Penyewa Keamanan',
            'email' => 'tenant_sec@test.com',
            'phone' => '08123456703',
            'password' => bcrypt('Password123!'),
            'role' => Role::TENANT,
            'is_active' => true,
        ]);
    }

    public function test_tenant_is_forbidden_from_accounting_and_expense_endpoints(): void
    {
        // 1. API COA accounts
        $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/finance/accounting/coa?property_id=' . $this->propertyA->id)
            ->assertStatus(403);

        // 2. API Journals
        $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/finance/accounting/journals?property_id=' . $this->propertyA->id)
            ->assertStatus(403);

        // 3. API Financial Reports
        $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/finance/accounting/reports/trial-balance?property_id=' . $this->propertyA->id)
            ->assertStatus(403);

        // 4. Web COA Dashboard
        $this->actingAs($this->tenant)
            ->get('/dashboard/accounting/coa')
            ->assertStatus(403);

        // 5. API Expenses list
        $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/expenses')
            ->assertStatus(403);
    }

    public function test_property_isolation_on_expense_and_private_cache_headers(): void
    {
        $category = ExpenseCategory::create([
            'property_id' => $this->propertyA->id,
            'name' => 'Operasional A',
            'code' => 'ops_a',
            'is_active' => true,
        ]);

        $file = UploadedFile::fake()->create('receipt.jpg', 200, 'image/jpeg');
        $storedPath = $file->store('expenses/receipts', 'local');

        $expense = Expense::create([
            'expense_number' => 'EXP-SEC-001',
            'property_id' => $this->propertyA->id,
            'expense_category_id' => $category->id,
            'user_id' => $this->staffA->id,
            'title' => 'Beli Alat Kebersihan',
            'amount' => 150000,
            'expense_date' => now()->toDateString(),
            'approval_status' => ExpenseApprovalStatus::SUBMITTED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'receipt_photo' => $storedPath,
        ]);

        // Staff B (Property B) tries to view receipt via API -> 403
        $this->actingAs($this->staffB, 'sanctum')
            ->getJson("/api/v1/expenses/{$expense->id}/receipt")
            ->assertStatus(403);

        // Staff B tries to view receipt via Web -> 403
        $this->actingAs($this->staffB)
            ->get("/dashboard/expenses/{$expense->id}/receipt")
            ->assertStatus(403);

        // Staff A (Property A) views receipt via API -> 200 with private cache control
        $apiResp = $this->actingAs($this->staffA, 'sanctum')
            ->get("/api/v1/expenses/{$expense->id}/receipt");
        $apiResp->assertStatus(200);
        $this->assertStringContainsString('private', (string) $apiResp->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $apiResp->headers->get('Cache-Control'));

        // Staff A views receipt via Web -> 200 with private cache control
        $webResp = $this->actingAs($this->staffA)
            ->get("/dashboard/expenses/{$expense->id}/receipt");
        $webResp->assertStatus(200);
        $this->assertStringContainsString('private', (string) $webResp->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', (string) $webResp->headers->get('Cache-Control'));
    }

    public function test_booking_creation_ignores_client_forged_rates_and_statuses(): void
    {
        // Malicious client tries to forge rate_amount = 500, deposit_amount = 0, status = confirmed
        $payload = [
            'room_id' => $this->roomA->id,
            'rental_type' => 'monthly',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
            'rate_amount' => 500,
            'deposit_amount' => 0,
            'total_amount' => 500,
            'status' => 'confirmed',
        ];

        $resp = $this->actingAs($this->tenant, 'sanctum')
            ->postJson('/api/v1/bookings', $payload);

        $resp->assertStatus(201);
        $bookingId = $resp->json('data.id');

        $booking = Booking::findOrFail($bookingId);
        // Rate must come from RoomType (2,500,000), not forged 500
        $this->assertEquals(2500000, (float) $booking->rate_amount);
        // Status must NOT be confirmed immediately
        $this->assertNotEquals('confirmed', $booking->status->value);
    }

    public function test_journal_engine_rejects_unbalanced_entries(): void
    {
        $cashAccount = Account::create([
            'property_id' => $this->propertyA->id,
            'code' => '1001-TEST',
            'name' => 'Kas Operasional Test',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'is_system' => true,
            'is_active' => true,
        ]);

        $revenueAccount = Account::create([
            'property_id' => $this->propertyA->id,
            'code' => '4001-TEST',
            'name' => 'Pendapatan Sewa Test',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'is_system' => true,
            'is_active' => true,
        ]);

        $journalService = app(JournalPostingService::class);

        // Attempting to post unbalanced lines (Debit 1,000,000 vs Credit 800,000)
        $this->expectException(DomainException::class);
        $journalService->postJournal(
            propertyId: $this->propertyA->id,
            sourceType: 'manual',
            sourceId: null,
            idempotencyKey: 'manual_unbalanced_test_' . uniqid(),
            description: 'Unbalanced test transaction',
            lines: [
                [
                    'account_id' => $cashAccount->id,
                    'debit' => 1000000,
                    'credit' => 0,
                    'memo' => 'Kas Masuk',
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => 800000, // Unbalanced by 200,000!
                    'memo' => 'Pendapatan Sewa',
                ],
            ],
            entryDate: now(),
            actor: $this->staffA
        );
    }

    public function test_journal_engine_idempotency_prevents_duplicate_entries(): void
    {
        $cashAccount = Account::create([
            'property_id' => $this->propertyA->id,
            'code' => '1002-TEST',
            'name' => 'Kas Operasional Test 2',
            'type' => AccountType::ASSET,
            'normal_balance' => AccountNormalBalance::DEBIT,
            'is_system' => true,
            'is_active' => true,
        ]);

        $revenueAccount = Account::create([
            'property_id' => $this->propertyA->id,
            'code' => '4002-TEST',
            'name' => 'Pendapatan Sewa Test 2',
            'type' => AccountType::REVENUE,
            'normal_balance' => AccountNormalBalance::CREDIT,
            'is_system' => true,
            'is_active' => true,
        ]);

        $journalService = app(JournalPostingService::class);
        $key = 'idempotent_test_key_' . uniqid();

        $lines = [
            [
                'account_id' => $cashAccount->id,
                'debit' => 500000,
                'credit' => 0,
                'memo' => 'Penerimaan',
            ],
            [
                'account_id' => $revenueAccount->id,
                'debit' => 0,
                'credit' => 500000,
                'memo' => 'Pendapatan',
            ],
        ];

        $entry1 = $journalService->postJournal(
            propertyId: $this->propertyA->id,
            sourceType: 'manual',
            sourceId: null,
            idempotencyKey: $key,
            description: 'Idempotency verification 1',
            lines: $lines,
            entryDate: now(),
            actor: $this->staffA
        );

        $entry2 = $journalService->postJournal(
            propertyId: $this->propertyA->id,
            sourceType: 'manual',
            sourceId: null,
            idempotencyKey: $key,
            description: 'Idempotency verification 2 (duplicate call)',
            lines: $lines,
            entryDate: now(),
            actor: $this->staffA
        );

        // Same ID returned, no duplicate record in DB
        $this->assertEquals($entry1->id, $entry2->id);
    }
}

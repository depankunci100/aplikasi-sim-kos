<?php

namespace Tests\Feature;

use App\Enums\ExpenseApprovalStatus;
use App\Enums\ExpensePaymentStatus;
use App\Enums\Role;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RightSideDrawerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $manager;
    protected Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'name' => 'Kos Graha Asri Utama',
            'code' => 'GRAHA-01',
            'address' => 'Jl. Asri Indah No. 5',
            'city' => 'Jakarta Selatan',
            'type' => 'kos',
            'status' => 'active',
        ]);

        $roomType = \App\Models\RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Standard Deluxe',
            'base_price_monthly' => 1500000,
        ]);

        Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role' => Role::OWNER,
            'name' => 'Pak Bos Pemilik',
        ]);
        $this->owner->properties()->attach($this->property->id, ['role' => 'owner']);

        $this->manager = User::factory()->create([
            'role' => Role::MANAGER,
            'name' => 'Ibu Manajer Operasional',
        ]);
        $this->manager->properties()->attach($this->property->id, ['role' => 'manager']);
    }

    public function test_expenses_index_renders_create_expense_drawer(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Listrik & Utilitas',
            'code' => 'listrik_utilitas',
            'property_id' => $this->property->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->owner)->get(route('dashboard.expenses.index'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Check Trigger Button
        $this->assertStringContainsString('data-bs-toggle="offcanvas"', $content);
        $this->assertStringContainsString('data-bs-target="#createExpenseDrawer"', $content);

        // Check Offcanvas Structure
        $this->assertStringContainsString('id="createExpenseDrawer"', $content);
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-lg', $content);
        $this->assertStringContainsString('name="_drawer_id" value="createExpenseDrawer"', $content);
        $this->assertStringContainsString(route('dashboard.expenses.store'), $content);
        $this->assertStringContainsString('name="title"', $content);
        $this->assertStringContainsString('name="amount"', $content);
        $this->assertStringContainsString('name="expense_date"', $content);
        $this->assertStringContainsString('name="action_type" value="draft"', $content);
        $this->assertStringContainsString('name="action_type" value="submit"', $content);
    }

    public function test_expense_categories_renders_create_category_drawer(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard.expenses.categories'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Check Trigger Button
        $this->assertStringContainsString('data-bs-toggle="offcanvas"', $content);
        $this->assertStringContainsString('data-bs-target="#createCategoryDrawer"', $content);

        // Check Offcanvas Structure
        $this->assertStringContainsString('id="createCategoryDrawer"', $content);
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-md', $content);
        $this->assertStringContainsString('name="_drawer_id" value="createCategoryDrawer"', $content);
        $this->assertStringContainsString(route('dashboard.expenses.categories.store'), $content);
        $this->assertStringContainsString('name="name"', $content);
        $this->assertStringContainsString('name="code"', $content);
    }

    public function test_expense_show_renders_standard_modal_confirm_dialogs(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Kebersihan',
            'code' => 'kebersihan',
            'property_id' => $this->property->id,
            'is_active' => true,
        ]);

        $expense = Expense::create([
            'expense_number' => 'EXP-202610-0099',
            'property_id' => $this->property->id,
            'expense_category_id' => $category->id,
            'user_id' => $this->owner->id,
            'title' => 'Pembelian Alat Pel dan Sabun',
            'amount' => 250000,
            'paid_amount' => 0,
            'expense_date' => '2026-10-15',
            'approval_status' => ExpenseApprovalStatus::SUBMITTED,
            'payment_status' => ExpensePaymentStatus::UNPAID,
            'payment_method' => 'cash',
        ]);

        // Manager views expense (can approve or reject)
        $response = $this->actingAs($this->manager)->get(route('dashboard.expenses.show', $expense->id));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Check Modal Approve
        $this->assertStringContainsString('modal fade modal-confirm', $content);
        $this->assertStringContainsString('id="modalApprove"', $content);
        $this->assertStringContainsString(route('dashboard.expenses.approve', $expense->id), $content);

        // Check Modal Reject
        $this->assertStringContainsString('id="modalReject"', $content);
        $this->assertStringContainsString('name="rejection_reason"', $content);
        $this->assertStringContainsString(route('dashboard.expenses.reject', $expense->id), $content);

        // Check Modal Record Payment structure exists in show
        $this->assertStringContainsString('id="modalRecordPayment"', $content);
        $this->assertStringContainsString(route('dashboard.expenses.pay', $expense->id), $content);
    }

    public function test_operational_modules_render_drawers(): void
    {
        // 1. Tariffs
        $respTariff = $this->actingAs($this->owner)->get(route('dashboard.utilities.tariffs.index'));
        $respTariff->assertStatus(200);
        $this->assertStringContainsString('data-bs-target="#createTariffDrawer"', $respTariff->getContent());
        $this->assertStringContainsString('id="createTariffDrawer"', $respTariff->getContent());
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-md', $respTariff->getContent());

        // 2. Meters
        $respMeter = $this->actingAs($this->owner)->get(route('dashboard.utilities.meters.index'));
        $respMeter->assertStatus(200);
        $this->assertStringContainsString('data-bs-target="#createMeterDrawer"', $respMeter->getContent());
        $this->assertStringContainsString('id="createMeterDrawer"', $respMeter->getContent());
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-md', $respMeter->getContent());

        // 3. Maintenance
        $respMaint = $this->actingAs($this->owner)->get(route('dashboard.maintenance.index'));
        $respMaint->assertStatus(200);
        $this->assertStringContainsString('data-bs-target="#createMaintenanceDrawer"', $respMaint->getContent());
        $this->assertStringContainsString('id="createMaintenanceDrawer"', $respMaint->getContent());
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-lg', $respMaint->getContent());

        // 4. Complaints
        $respCompl = $this->actingAs($this->owner)->get(route('dashboard.complaints.index'));
        $respCompl->assertStatus(200);
        $this->assertStringContainsString('data-bs-target="#createComplaintDrawer"', $respCompl->getContent());
        $this->assertStringContainsString('id="createComplaintDrawer"', $respCompl->getContent());
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-lg', $respCompl->getContent());

        // 5. Housekeeping
        $respHouse = $this->actingAs($this->owner)->get(route('dashboard.housekeeping.index'));
        $respHouse->assertStatus(200);
        $this->assertStringContainsString('data-bs-target="#createHousekeepingDrawer"', $respHouse->getContent());
        $this->assertStringContainsString('id="createHousekeepingDrawer"', $respHouse->getContent());
        $this->assertStringContainsString('offcanvas offcanvas-end drawer-md', $respHouse->getContent());
    }

    public function test_standalone_create_routes_remain_functional_as_fallbacks(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard.expenses.create'))->assertStatus(200);
        $this->actingAs($this->owner)->get(route('dashboard.utilities.meters.create'))->assertStatus(200);
        $this->actingAs($this->owner)->get(route('dashboard.maintenance.create'))->assertStatus(200);
        $this->actingAs($this->owner)->get(route('dashboard.complaints.create'))->assertStatus(200);
        $this->actingAs($this->owner)->get(route('dashboard.housekeeping.create'))->assertStatus(200);
    }
}

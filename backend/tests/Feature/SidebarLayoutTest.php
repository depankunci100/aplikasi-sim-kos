<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed basic property
        Property::create([
            'name' => 'Kos Bahagia Utama',
            'code' => 'KOS-01',
            'address' => 'Jl. Mawar No. 10',
            'city' => 'Jakarta Selatan',
            'type' => 'kos',
            'status' => 'active',
        ]);
    }

    public function test_sidebar_renders_full_menus_for_owner_and_super_admin(): void
    {
        $owner = User::factory()->create([
            'role' => Role::OWNER,
            'name' => 'Pak Budi Pemilik',
        ]);
        $owner->properties()->attach(Property::first()->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->get(route('dashboard.index'));

        $response->assertStatus(200);
        $content = $response->getContent();

        $this->assertStringContainsString('id="sidebarMenu"', $content);
        $this->assertStringContainsString('id="sidebarToggleBtn"', $content);
        $this->assertStringContainsString('SIM KOS', $content);
        $this->assertStringContainsString('& Guesthouse', $content);

        // Extract sidebar content only
        $start = strpos($content, 'id="sidebarMenu"');
        $end = strpos($content, '</aside>', $start);
        $sidebar = substr($content, $start, $end - $start);

        // Menu groups check inside sidebar
        $this->assertStringContainsString('Dashboard & Analitik', $sidebar);
        $this->assertStringContainsString('Dashboard Utama', $sidebar);
        $this->assertStringContainsString('Dashboard Keuangan', $sidebar);
        $this->assertStringContainsString('Operasional Sewa', $sidebar);
        $this->assertStringContainsString('Tagihan (Invoice)', $sidebar);
        $this->assertStringContainsString(route('dashboard.payments.index'), $sidebar);
        $this->assertStringContainsString('Utilitas & Meteran', $sidebar);
        $this->assertStringContainsString('Master Meter Listrik/Air', $sidebar);
        $this->assertStringContainsString('Fasilitas & Layanan', $sidebar);
        $this->assertStringContainsString('Perbaikan (Maintenance)', $sidebar);
        $this->assertStringContainsString('Keluhan Penghuni', $sidebar);
        $this->assertStringContainsString('Kebersihan (Housekeeping)', $sidebar);
        $this->assertStringContainsString('Finansial & Laporan', $sidebar);
        $this->assertStringContainsString('Pengeluaran Operasional', $sidebar);
        $this->assertStringContainsString('Laporan Pendapatan', $sidebar);
        $this->assertStringContainsString('Laporan Arus Kas', $sidebar);

        // User info check
        $this->assertStringContainsString('Pak Budi Pemilik', $sidebar);
        $this->assertStringContainsString('Pemilik Properti', $sidebar);
    }

    public function test_sidebar_restricts_tenant_from_financial_and_staff_menus(): void
    {
        $tenant = User::factory()->create([
            'role' => Role::TENANT,
            'name' => 'Siti Penyewa',
        ]);

        $response = $this->actingAs($tenant)->get(route('dashboard.index'));
        $response->assertStatus(200);

        $content = $response->getContent();
        $start = strpos($content, 'id="sidebarMenu"');
        $end = strpos($content, '</aside>', $start);
        $sidebar = substr($content, $start, $end - $start);

        // Allowed for tenant in sidebar
        $this->assertStringContainsString('Dashboard Utama', $sidebar);
        $this->assertStringContainsString('Tagihan (Invoice)', $sidebar);
        $this->assertStringContainsString('Perbaikan (Maintenance)', $sidebar);
        $this->assertStringContainsString('Keluhan Penghuni', $sidebar);

        // Forbidden routes for tenant in sidebar
        $this->assertStringNotContainsString(route('dashboard.finance.index'), $sidebar);
        $this->assertStringNotContainsString(route('dashboard.payments.index'), $sidebar);
        $this->assertStringNotContainsString(route('dashboard.utilities.meters.index'), $sidebar);
        $this->assertStringNotContainsString(route('dashboard.expenses.index'), $sidebar);
        $this->assertStringNotContainsString(route('dashboard.reports.cash-flow'), $sidebar);
        $this->assertStringNotContainsString('Utilitas & Meteran', $sidebar);
        $this->assertStringNotContainsString('Finansial & Laporan', $sidebar);
    }

    public function test_active_route_highlight_on_sidebar(): void
    {
        $admin = User::factory()->create([
            'role' => Role::SUPER_ADMIN,
            'name' => 'Super Administrator',
        ]);

        // When visiting dashboard.index
        $resIndex = $this->actingAs($admin)->get(route('dashboard.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertSee('sidebar-link active" href="' . route('dashboard.index') . '"', false);

        // When visiting expenses index
        $resExpenses = $this->actingAs($admin)->get(route('dashboard.expenses.index'));
        $resExpenses->assertStatus(200);
        $resExpenses->assertSee('sidebar-link active" href="' . route('dashboard.expenses.index') . '"', false);

        // When visiting reports cash-flow
        $resCashFlow = $this->actingAs($admin)->get(route('dashboard.reports.cash-flow'));
        $resCashFlow->assertStatus(200);
        $resCashFlow->assertSee('sidebar-sublink active" href="' . route('dashboard.reports.cash-flow') . '"', false);
    }

    public function test_logout_csrf_forms_exist_in_sidebar_and_topbar(): void
    {
        $user = User::factory()->create([
            'role' => Role::MANAGER,
            'name' => 'Manajer Cabang',
        ]);

        $response = $this->actingAs($user)->get(route('dashboard.index'));
        $response->assertStatus(200);

        // Form action must be logout route
        $response->assertSee('action="' . route('logout') . '"', false);
        $response->assertSee('method="POST"', false);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $receptionist;
    protected User $staff;
    protected Property $property;
    protected Booking $defaultBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'code' => 'PROP-WEB-01',
            'name' => 'Wisma Web Demo',
            'type' => 'hybrid',
            'address' => 'Jl. Web No. 1',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create([
            'role' => Role::OWNER,
            'name' => 'Haji Huda',
            'password' => bcrypt('Password123!'),
        ]);

        $this->receptionist = User::factory()->create([
            'role' => Role::RECEPTIONIST,
            'name' => 'Siti Kasir',
            'password' => bcrypt('Password123!'),
        ]);

        $this->staff = User::factory()->create([
            'role' => Role::STAFF,
            'name' => 'Joko Staff',
            'password' => bcrypt('Password123!'),
        ]);

        DB::table('property_users')->insert([
            ['property_id' => $this->property->id, 'user_id' => $this->owner->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property->id, 'user_id' => $this->receptionist->id, 'role' => 'receptionist', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property->id, 'user_id' => $this->staff->id, 'role' => 'staff', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Standar',
            'base_price_monthly' => 1000000.00,
        ]);

        $room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'available',
            'is_active' => true,
        ]);

        $tenant = User::factory()->create(['role' => Role::TENANT]);

        $this->defaultBooking = Booking::create([
            'booking_code' => 'BK-WEB-INIT',
            'property_id' => $this->property->id,
            'room_id' => $room->id,
            'user_id' => $tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rate_amount' => 1000000.00,
            'deposit_amount' => 500000.00,
            'total_amount' => 1500000.00,
            'status' => BookingStatus::PENDING,
        ]);
    }

    public function test_guest_is_redirected_to_login_page(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_login_via_web_form_and_redirects_to_dashboard(): void
    {
        $response = $this->post('/login', [
            'email' => $this->owner->email,
            'password' => 'Password123!',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->owner);
    }

    public function test_owner_can_access_dashboard_overview(): void
    {
        $response = $this->actingAs($this->owner)->get('/dashboard');

        $response->assertStatus(200)
            ->assertSee('Ringkasan Operasional')
            ->assertSee('Haji Huda')
            ->assertSee('Wisma Web Demo');
    }

    public function test_receptionist_can_view_pending_payments_queue(): void
    {
        $tenant = User::factory()->create(['role' => Role::TENANT]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-WEB-001',
            'property_id' => $this->property->id,
            'booking_id' => $this->defaultBooking->id,
            'user_id' => $tenant->id,
            'invoice_type' => 'rent',
            'total_amount' => 1500000.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-WEB-001',
            'invoice_id' => $invoice->id,
            'user_id' => $tenant->id,
            'amount' => 1500000.00,
            'status' => 'pending',
            'payment_method' => 'manual_transfer',
        ]);

        $response = $this->actingAs($this->receptionist)->get('/dashboard/payments');

        $response->assertStatus(200)
            ->assertSee('PAY-WEB-001')
            ->assertSee('INV-WEB-001');
    }

    public function test_receptionist_can_verify_payment_via_web(): void
    {
        $tenant = User::factory()->create(['role' => Role::TENANT]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-WEB-002',
            'property_id' => $this->property->id,
            'booking_id' => $this->defaultBooking->id,
            'user_id' => $tenant->id,
            'invoice_type' => 'rent',
            'total_amount' => 1000000.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-WEB-002',
            'invoice_id' => $invoice->id,
            'user_id' => $tenant->id,
            'amount' => 1000000.00,
            'status' => 'pending',
            'payment_method' => 'manual_transfer',
        ]);

        $response = $this->actingAs($this->receptionist)
            ->post("/dashboard/payments/{$payment->id}/verify", [
                'notes' => 'Disetujui dari web dashboard',
            ]);

        $response->assertRedirect('/dashboard/payments');
        $response->assertSessionHas('success');

        $payment->refresh();
        $this->assertEquals('verified', $payment->status);
        $this->assertEquals($this->receptionist->id, $payment->verified_by);

        $invoice->refresh();
        $this->assertEquals(InvoiceStatus::PAID, $invoice->status);
    }

    public function test_staff_cannot_verify_payment_due_to_role_middleware(): void
    {
        $tenant = User::factory()->create(['role' => Role::TENANT]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-WEB-003',
            'property_id' => $this->property->id,
            'booking_id' => $this->defaultBooking->id,
            'user_id' => $tenant->id,
            'invoice_type' => 'rent',
            'total_amount' => 1000000.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-WEB-003',
            'invoice_id' => $invoice->id,
            'user_id' => $tenant->id,
            'amount' => 1000000.00,
            'status' => 'pending',
            'payment_method' => 'manual_transfer',
        ]);

        // Staff mencoba verifikasi (harus 403 Forbidden)
        $response = $this->actingAs($this->staff)
            ->post("/dashboard/payments/{$payment->id}/verify");

        $response->assertStatus(403);
    }
}

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $tenant;
    protected User $receptionist;
    protected Property $property;
    protected Room $room;
    protected Booking $booking;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->property = Property::create([
            'code' => 'PROP-TEST-01',
            'name' => 'Wisma Test Harmoni',
            'type' => 'kos',
            'address' => 'Jl. Test No. 1',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->tenant = User::factory()->create([
            'role' => Role::TENANT,
            'name' => 'Budi Penyewa',
        ]);

        $this->receptionist = User::factory()->create([
            'role' => Role::RECEPTIONIST,
            'name' => 'Siti Kasir',
        ]);

        // Hubungkan kasir ke properti
        DB::table('property_users')->insert([
            'property_id' => $this->property->id,
            'user_id' => $this->receptionist->id,
            'role' => 'receptionist',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomType = RoomType::create([
            'property_id' => $this->property->id,
            'name' => 'Deluxe AC',
            'base_price_monthly' => 1500000.00,
        ]);

        $this->room = Room::create([
            'property_id' => $this->property->id,
            'room_type_id' => $roomType->id,
            'room_number' => '101',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'booking_code' => 'BK-TEST-001',
            'property_id' => $this->property->id,
            'room_id' => $this->room->id,
            'user_id' => $this->tenant->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rate_amount' => 1500000.00,
            'deposit_amount' => 500000.00,
            'total_amount' => 2000000.00,
            'status' => BookingStatus::PENDING,
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-001',
            'property_id' => $this->property->id,
            'booking_id' => $this->booking->id,
            'user_id' => $this->tenant->id,
            'invoice_type' => 'rent',
            'subtotal' => 1500000.00,
            'total_amount' => 2000000.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(3)->toDateString(),
        ]);
    }

    public function test_tenant_can_view_own_invoices(): void
    {
        $response = $this->actingAs($this->tenant, 'sanctum')
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.invoice_number', 'INV-TEST-001');
    }

    public function test_tenant_cannot_view_other_users_invoice(): void
    {
        $otherTenant = User::factory()->create(['role' => Role::TENANT]);

        $response = $this->actingAs($otherTenant, 'sanctum')
            ->getJson("/api/v1/invoices/{$this->invoice->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_can_submit_valid_payment_with_proof_photo(): void
    {
        $file = UploadedFile::fake()->image('bukti_transfer.jpg', 600, 800);

        $payload = [
            'amount' => 1000000.00,
            'payment_method' => 'manual_transfer',
            'payment_provider' => 'manual_bca',
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'BCA',
            'proof_file' => $file,
        ];

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.amount', '1000000.00');

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 1000000.00,
            'status' => 'pending',
            'sender_bank' => 'BCA',
        ]);

        $payment = Payment::first();
        $this->assertNotNull($payment->proof_photo);
        Storage::disk('local')->assertExists($payment->proof_photo);
    }

    public function test_submission_rejects_overpayment(): void
    {
        $payload = [
            'amount' => 2500000.00, // Total tagihan hanya 2.000.000
            'payment_method' => 'manual_transfer',
        ];

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_submission_rejects_unsupported_file_extension(): void
    {
        $badFile = UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream');

        $payload = [
            'amount' => 500000.00,
            'payment_method' => 'manual_transfer',
            'proof_file' => $badFile,
        ];

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['proof_file']);
    }

    public function test_tenant_cannot_verify_their_own_payment(): void
    {
        $payment = Payment::create([
            'payment_code' => 'PAY-TEST-001',
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 1000000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->tenant, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify");

        // Ditolak oleh role middleware (403)
        $response->assertStatus(403);
    }

    public function test_receptionist_can_verify_payment_and_updates_balance_and_booking(): void
    {
        // Bayar lunas 2.000.000
        $payment = Payment::create([
            'payment_code' => 'PAY-FULL-001',
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 2000000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify", ['notes' => 'Mutasi bank cocok']);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'verified');

        $this->invoice->refresh();
        $this->assertEquals(2000000.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(InvoiceStatus::PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);

        // Booking status otomatis terupdate ke CONFIRMED
        $this->booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $this->booking->status);
    }

    public function test_duplicate_verification_is_prevented(): void
    {
        $payment = Payment::create([
            'payment_code' => 'PAY-DUP-001',
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 1000000.00,
            'status' => 'verified',
            'verified_by' => $this->receptionist->id,
            'verified_at' => now(),
        ]);

        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/verify");

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_receptionist_can_reject_payment_with_reason(): void
    {
        $payment = Payment::create([
            'payment_code' => 'PAY-REJ-001',
            'invoice_id' => $this->invoice->id,
            'user_id' => $this->tenant->id,
            'amount' => 500000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->receptionist, 'sanctum')
            ->postJson("/api/v1/payments/{$payment->id}/reject", [
                'rejection_reason' => 'Nominal transfer tidak muncul pada mutasi bank rekening penampung.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $payment->refresh();
        $this->assertEquals('rejected', $payment->status);
        $this->assertEquals('Nominal transfer tidak muncul pada mutasi bank rekening penampung.', $payment->rejection_reason);

        // Saldo invoice TIDAK boleh terpotong
        $this->invoice->refresh();
        $this->assertEquals(0.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(InvoiceStatus::UNPAID, $this->invoice->status);
    }
}

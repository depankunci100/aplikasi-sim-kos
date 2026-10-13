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

class PaymentLifecycleEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected Property $property1;
    protected Property $property2;
    protected User $tenantA;
    protected User $tenantB;
    protected User $cashierProp1;
    protected User $cashierProp2;
    protected User $ownerProp1;
    protected Booking $booking;
    protected Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Setup Properti 1 & 2
        $this->property1 = Property::create([
            'code' => 'PROP-E2E-01',
            'name' => 'Wisma E2E Utama',
            'type' => 'hybrid',
            'address' => 'Jl. E2E No. 1',
            'city' => 'Malang',
            'is_active' => true,
        ]);

        $this->property2 = Property::create([
            'code' => 'PROP-E2E-02',
            'name' => 'Wisma E2E Cabang Lain',
            'type' => 'kos',
            'address' => 'Jl. Lain No. 99',
            'city' => 'Surabaya',
            'is_active' => true,
        ]);

        // Users
        $this->tenantA = User::factory()->create(['name' => 'Tenant A', 'role' => Role::TENANT]);
        $this->tenantB = User::factory()->create(['name' => 'Tenant B', 'role' => Role::TENANT]);
        $this->cashierProp1 = User::factory()->create(['name' => 'Kasir Prop 1', 'role' => Role::RECEPTIONIST]);
        $this->cashierProp2 = User::factory()->create(['name' => 'Kasir Prop 2', 'role' => Role::RECEPTIONIST]);
        $this->ownerProp1 = User::factory()->create(['name' => 'Owner Prop 1', 'role' => Role::OWNER]);

        // Assignment Cabang
        DB::table('property_users')->insert([
            ['property_id' => $this->property1->id, 'user_id' => $this->cashierProp1->id, 'role' => 'receptionist', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property1->id, 'user_id' => $this->ownerProp1->id, 'role' => 'owner', 'created_at' => now(), 'updated_at' => now()],
            ['property_id' => $this->property2->id, 'user_id' => $this->cashierProp2->id, 'role' => 'receptionist', 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Rooms & Booking
        $roomType = RoomType::create([
            'property_id' => $this->property1->id,
            'name' => 'Deluxe Suite',
            'base_price_monthly' => 1500000.00,
        ]);

        $room = Room::create([
            'property_id' => $this->property1->id,
            'room_type_id' => $roomType->id,
            'room_number' => 'D-101',
            'status' => 'available',
            'is_active' => true,
        ]);

        $this->booking = Booking::create([
            'booking_code' => 'BK-E2E-101',
            'property_id' => $this->property1->id,
            'room_id' => $room->id,
            'user_id' => $this->tenantA->id,
            'rental_type' => 'monthly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'rate_amount' => 1500000.00,
            'deposit_amount' => 500000.00,
            'total_amount' => 2000000.00,
            'status' => BookingStatus::PENDING,
        ]);

        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-E2E-101',
            'property_id' => $this->property1->id,
            'booking_id' => $this->booking->id,
            'user_id' => $this->tenantA->id,
            'invoice_type' => 'rent',
            'subtotal' => 1500000.00,
            'total_amount' => 2000000.00,
            'paid_amount' => 0.00,
            'status' => InvoiceStatus::UNPAID,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function test_complete_payment_lifecycle_end_to_end(): void
    {
        // 1. Isolasi Akses Tenant: Tenant A bisa akses invoice, Tenant B ditolak
        $respA = $this->actingAs($this->tenantA, 'sanctum')->getJson("/api/v1/invoices/{$this->invoice->id}");
        $respA->assertStatus(200)->assertJsonPath('data.invoice_number', 'INV-E2E-101');

        $respB = $this->actingAs($this->tenantB, 'sanctum')->getJson("/api/v1/invoices/{$this->invoice->id}");
        $respB->assertStatus(404);

        // 2. Tenant A Mengajukan Pembayaran Tahap 1 (DP Rp 1.500.000 dari total Rp 2.000.000)
        $fakeProofFile = UploadedFile::fake()->image('struk_bca.png', 800, 600);
        $submitPayload = [
            'amount' => 1500000.00,
            'payment_method' => 'manual_transfer',
            'payment_provider' => 'manual_bca',
            'sender_name' => 'Tenant A Test',
            'sender_bank' => 'BCA',
            'proof_file' => $fakeProofFile,
        ];

        $submitResp = $this->actingAs($this->tenantA, 'sanctum')
            ->postJson("/api/v1/invoices/{$this->invoice->id}/payments", $submitPayload);

        $submitResp->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $paymentId1 = $submitResp->json('data.id');
        $payment1 = Payment::findOrFail($paymentId1);

        // 3. Bukti Tersimpan di Storage Privat dan Dilindungi Otorisasi
        $this->assertNotNull($payment1->proof_photo);
        Storage::disk('local')->assertExists($payment1->proof_photo);

        // Akses bukti tanpa login -> 401
        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/payments/{$paymentId1}/proof")->assertStatus(401);

        // Akses bukti oleh Tenant B (orang lain) -> 403
        $this->actingAs($this->tenantB, 'sanctum')->getJson("/api/v1/payments/{$paymentId1}/proof")->assertStatus(403);

        // Akses bukti oleh Kasir Prop 1 -> 200 Stream file
        $this->actingAs($this->cashierProp1, 'sanctum')->getJson("/api/v1/payments/{$paymentId1}/proof")->assertStatus(200);

        // 4. Pembayaran Pending Belum Mengurangi Saldo Invoice
        $this->invoice->refresh();
        $this->assertEquals(0.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(2000000.00, $this->invoice->remaining_balance);
        $this->assertEquals(InvoiceStatus::UNPAID, $this->invoice->status);

        // 5. Isolasi Lintas Cabang: Kasir Prop 2 Dilarang Memverifikasi Pembayaran Prop 1
        $crossResp = $this->actingAs($this->cashierProp2, 'sanctum')
            ->postJson("/api/v1/payments/{$paymentId1}/verify", ['notes' => 'Kasir cab lain coba verifikasi']);
        $crossResp->assertStatus(403);

        // 6. Kasir Prop 1 Menyetujui Pembayaran Tahap 1
        $verifyResp = $this->actingAs($this->cashierProp1, 'sanctum')
            ->postJson("/api/v1/payments/{$paymentId1}/verify", [
                'notes' => 'Mutasi rekening koran BCA 1.500.000 valid',
            ]);

        $verifyResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'verified');

        $payment1->refresh();
        $this->assertEquals('verified', $payment1->status);
        $this->assertEquals($this->cashierProp1->id, $payment1->verified_by);
        $this->assertEquals('Mutasi rekening koran BCA 1.500.000 valid', $payment1->verification_notes);

        // 7. Verifikasi Saldo & Status Invoice dan Booking Pasca Verifikasi Tahap 1
        $this->invoice->refresh();
        $this->assertEquals(1500000.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(500000.00, $this->invoice->remaining_balance);
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $this->invoice->status);

        $this->booking->refresh();
        $this->assertEquals(BookingStatus::CONFIRMED, $this->booking->status);

        // 8. Perlindungan Double Verification (Idempotensi & Anti-Race Condition)
        $dupResp = $this->actingAs($this->cashierProp1, 'sanctum')
            ->postJson("/api/v1/payments/{$paymentId1}/verify");
        $dupResp->assertStatus(422)
            ->assertJsonPath('success', false);

        // Saldo tetap bertambah tepat 1 kali
        $this->invoice->refresh();
        $this->assertEquals(1500000.00, (float)$this->invoice->paid_amount);

        // 9. Uji Penolakan Bukti Transfer (Rejection Flow)
        // Tenant A mengajukan pelunasan sisa Rp 500.000
        $proof2 = UploadedFile::fake()->image('struk_palsu.jpg', 600, 400);
        $submit2 = $this->actingAs($this->tenantA, 'sanctum')->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'amount' => 500000.00,
            'payment_method' => 'manual_transfer',
            'payment_provider' => 'manual_mandiri',
            'proof_file' => $proof2,
        ]);
        $submit2->assertStatus(201);
        $paymentId2 = $submit2->json('data.id');

        // Kasir menolak pembayaran tahap 2 karena bukti tidak jelas
        $rejectResp = $this->actingAs($this->cashierProp1, 'sanctum')
            ->postJson("/api/v1/payments/{$paymentId2}/reject", [
                'rejection_reason' => 'Slip mutasi buram dan nama rekening pengirim tidak tertera.',
            ]);

        $rejectResp->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $payment2 = Payment::findOrFail($paymentId2);
        $this->assertEquals('rejected', $payment2->status);
        $this->assertEquals('Slip mutasi buram dan nama rekening pengirim tidak tertera.', $payment2->rejection_reason);

        // Saldo invoice TIDAK terpotong oleh penolakan
        $this->invoice->refresh();
        $this->assertEquals(1500000.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(500000.00, $this->invoice->remaining_balance);
        $this->assertEquals(InvoiceStatus::PARTIALLY_PAID, $this->invoice->status);

        // 10. Pelunasan Penuh (Full Payment)
        $proof3 = UploadedFile::fake()->image('struk_valid_500k.jpg', 600, 400);
        $submit3 = $this->actingAs($this->tenantA, 'sanctum')->postJson("/api/v1/invoices/{$this->invoice->id}/payments", [
            'amount' => 500000.00,
            'payment_method' => 'manual_transfer',
            'payment_provider' => 'manual_mandiri',
            'proof_file' => $proof3,
        ]);
        $submit3->assertStatus(201);
        $paymentId3 = $submit3->json('data.id');

        $verifyFinal = $this->actingAs($this->cashierProp1, 'sanctum')
            ->postJson("/api/v1/payments/{$paymentId3}/verify", ['notes' => 'Pelunasan sisa saldo 500.000']);
        $verifyFinal->assertStatus(200);

        // Invoice harus berstatus PAID, saldo sisa 0
        $this->invoice->refresh();
        $this->assertEquals(2000000.00, (float)$this->invoice->paid_amount);
        $this->assertEquals(0.00, $this->invoice->remaining_balance);
        $this->assertEquals(InvoiceStatus::PAID, $this->invoice->status);
        $this->assertNotNull($this->invoice->paid_at);
    }
}

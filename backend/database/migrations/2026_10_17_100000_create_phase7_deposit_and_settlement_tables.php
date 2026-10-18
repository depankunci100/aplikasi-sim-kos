<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Subledger Deposit
        if (!Schema::hasTable('deposit_ledgers')) {
            Schema::create('deposit_ledgers', function (Blueprint $table) {
                $table->id();
                $table->string('deposit_code', 50)->unique();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('initial_deposit_amount', 14, 2)->default(0);
                $table->decimal('total_received', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('total_refunded', 14, 2)->default(0);
                $table->decimal('current_balance', 14, 2)->default(0);
                $table->string('status', 30)->default('pending'); // pending, held, partially_refunded, refunded, settled, forfeited
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['property_id', 'status']);
                $table->index(['tenant_id', 'status']);
            });
        }

        // 2. Tabel Riwayat Mutasi Deposit (Immutable Ledger Entries)
        if (!Schema::hasTable('deposit_mutations')) {
            Schema::create('deposit_mutations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deposit_ledger_id')->constrained('deposit_ledgers')->cascadeOnDelete();
                $table->string('mutation_code', 50)->unique();
                $table->string('transaction_type', 40); // deposit_received, damage_deduction, unpaid_bill_deduction, refund_payout, adjustment_credit, adjustment_debit
                $table->string('direction', 10); // in, out
                $table->decimal('amount', 14, 2);
                $table->decimal('balance_before', 14, 2);
                $table->decimal('balance_after', 14, 2);
                $table->string('payment_method', 50)->nullable();
                $table->string('payment_reference', 100)->nullable();
                $table->foreignId('inspection_id')->nullable()->constrained('inspections')->nullOnDelete();
                $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
                $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
                $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['deposit_ledger_id', 'created_at']);
            });
        }

        // 3. Tabel Check-Out Settlements
        if (!Schema::hasTable('check_out_settlements')) {
            Schema::create('check_out_settlements', function (Blueprint $table) {
                $table->id();
                $table->string('settlement_code', 50)->unique();
                $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('deposit_ledger_id')->constrained('deposit_ledgers')->cascadeOnDelete();
                $table->foreignId('inspection_id')->nullable()->constrained('inspections')->nullOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
                $table->date('check_out_date');
                $table->string('status', 30)->default('draft'); // draft, pending_tenant_approval, disputed, approved, settled, cancelled
                $table->decimal('deposit_balance_before', 14, 2)->default(0);
                $table->decimal('total_deductions', 14, 2)->default(0);
                $table->decimal('net_refund_amount', 14, 2)->default(0);
                $table->text('tenant_objection_notes')->nullable();
                $table->timestamp('tenant_approved_at')->nullable();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->text('resolution_notes')->nullable();
                $table->string('refund_method', 50)->nullable();
                $table->string('refund_destination', 150)->nullable();
                $table->string('refund_payment_reference', 100)->nullable();
                $table->string('refund_receipt_proof', 255)->nullable();
                $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('refunded_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['property_id', 'status']);
                $table->index(['booking_id']);
            });
        }

        // 4. Tabel Settlement Deductions (Rincian Potongan)
        if (!Schema::hasTable('settlement_deductions')) {
            Schema::create('settlement_deductions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('settlement_id')->constrained('check_out_settlements')->cascadeOnDelete();
                $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->nullOnDelete();
                $table->string('title', 150);
                $table->text('description')->nullable();
                $table->decimal('proposed_amount', 14, 2)->default(0);
                $table->decimal('approved_amount', 14, 2)->default(0);
                $table->boolean('is_approved')->default(true);
                $table->boolean('is_disputed')->default(false);
                $table->text('dispute_reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_deductions');
        Schema::dropIfExists('check_out_settlements');
        Schema::dropIfExists('deposit_mutations');
        Schema::dropIfExists('deposit_ledgers');
    }
};

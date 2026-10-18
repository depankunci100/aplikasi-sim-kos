<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Chart of Accounts (Bagan Akun)
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name', 100);
                $table->string('type', 30); // asset, liability, equity, revenue, expense
                $table->string('normal_balance', 10); // debit, credit
                $table->string('category', 50)->nullable(); // cash_bank, receivable, current_liability, payable, equity, operating_revenue, operating_expense, etc.
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_system')->default(false);
                $table->timestamps();

                $table->index(['property_id', 'code']);
                $table->index(['type', 'is_active']);
            });
        }

        // 2. Account Mappings (Pemetaan Otomatis Transaksi ke Akun Debit/Kredit)
        if (!Schema::hasTable('account_mappings')) {
            Schema::create('account_mappings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
                $table->string('transaction_type', 60); // payment_rent_current, payment_rent_advance, payment_deposit, payment_utility, expense_payment, checkout_damage_deduction, checkout_refund
                $table->foreignId('debit_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->foreignId('credit_account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['property_id', 'transaction_type']);
            });
        }

        // 3. Journal Entries (Kepala Jurnal Pembukuan Berpasangan)
        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('journal_number', 60)->unique();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->date('entry_date');
                $table->string('source_type', 50); // payment, expense_payment, deposit_mutation, check_out_settlement, invoice, manual_adjustment, reversal
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('idempotency_key', 120)->unique();
                $table->string('status', 20)->default('posted'); // draft, posted, reversed
                $table->text('description');
                $table->text('notes')->nullable();
                $table->decimal('total_amount', 14, 2)->default(0.00);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->foreignId('reversed_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->timestamps();

                $table->index(['property_id', 'entry_date', 'status']);
                $table->index(['source_type', 'source_id']);
            });
        }

        // 4. Journal Lines (Rincian Baris Debit & Kredit Jurnal)
        if (!Schema::hasTable('journal_lines')) {
            Schema::create('journal_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->decimal('debit', 14, 2)->default(0.00);
                $table->decimal('credit', 14, 2)->default(0.00);
                $table->string('memo', 255)->nullable();
                $table->timestamps();

                $table->index(['journal_entry_id']);
                $table->index(['account_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('account_mappings');
        Schema::dropIfExists('chart_of_accounts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expense Categories Table
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('name');
            $table->string('code')->index(); // electricity_water, internet, maintenance, housekeeping, salaries, supplies, taxes_admin, other
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Enhance existing expenses table with Phase 6 workflow and financial fields
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_number')->nullable()->unique()->after('id');
            $table->foreignId('expense_category_id')->nullable()->after('property_id')->constrained('expense_categories')->nullOnDelete();
            $table->string('vendor_name')->nullable()->after('title');
            $table->string('payment_method')->nullable()->after('amount'); // cash, bank_transfer, qris, other
            $table->string('approval_status')->default('draft')->index()->after('notes'); // draft, submitted, approved, rejected
            $table->string('payment_status')->default('unpaid')->index()->after('approval_status'); // unpaid, partially_paid, paid
            $table->decimal('paid_amount', 14, 2)->default(0.00)->after('payment_status');
            $table->timestamp('submitted_at')->nullable()->after('paid_amount');
            $table->foreignId('approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->foreignId('paid_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');
        });

        // 3. Expense Payments Table (for tracking full or partial expense disbursements)
        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->string('payment_number')->unique();
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('payment_method')->default('cash');
            $table->string('proof_file')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Expense Logs Table (Audit trail for status mutations and financial modifications)
        Schema::create('expense_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // created, submitted, approved, rejected, payment_recorded, cancelled
            $table->string('from_approval_status')->nullable();
            $table->string('to_approval_status')->nullable();
            $table->string('from_payment_status')->nullable();
            $table->string('to_payment_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_logs');
        Schema::dropIfExists('expense_payments');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['expense_category_id']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['paid_by']);
            $table->dropColumn([
                'expense_number',
                'expense_category_id',
                'vendor_name',
                'payment_method',
                'approval_status',
                'payment_status',
                'paid_amount',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejection_reason',
                'paid_by',
                'paid_at',
            ]);
        });

        Schema::dropIfExists('expense_categories');
    }
};

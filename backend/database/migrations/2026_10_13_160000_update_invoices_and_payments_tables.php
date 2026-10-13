<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('invoice_number')->constrained('properties')->nullOnDelete();
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payment_provider')) {
                $table->string('payment_provider')->nullable()->after('payment_method'); // e.g. manual_bca, manual_mandiri, manual_qris, midtrans
            }
            if (!Schema::hasColumn('payments', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('amount');
            }
            if (!Schema::hasColumn('payments', 'sender_bank')) {
                $table->string('sender_bank')->nullable()->after('sender_name');
            }
            if (!Schema::hasColumn('payments', 'verification_notes')) {
                $table->text('verification_notes')->nullable()->after('rejection_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_provider', 'sender_name', 'sender_bank', 'verification_notes']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn('property_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kebijakan uang muka dan deposit pada properti
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'dp_required')) {
                $table->boolean('dp_required')->default(false)->after('phone');
            }
            if (!Schema::hasColumn('properties', 'dp_type')) {
                $table->string('dp_type')->default('fixed')->after('dp_required'); // 'fixed' atau 'percentage'
            }
            if (!Schema::hasColumn('properties', 'dp_amount')) {
                $table->decimal('dp_amount', 14, 2)->default(0)->after('dp_type');
            }
            if (!Schema::hasColumn('properties', 'dp_percentage')) {
                $table->decimal('dp_percentage', 5, 2)->default(0)->after('dp_amount');
            }
            if (!Schema::hasColumn('properties', 'payment_deadline_hours')) {
                $table->unsignedInteger('payment_deadline_hours')->default(24)->after('dp_percentage');
            }
            if (!Schema::hasColumn('properties', 'deposit_amount')) {
                $table->decimal('deposit_amount', 14, 2)->default(0)->after('payment_deadline_hours');
            }
        });

        // 2. Tambah kolom guest, token akses pelacakan, dan audit lifecycle pada bookings
        Schema::table('bookings', function (Blueprint $table) {
            // Nullable user_id untuk guest booking
            $table->foreignId('user_id')->nullable()->change();

            if (!Schema::hasColumn('bookings', 'guest_name')) {
                $table->string('guest_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('bookings', 'guest_email')) {
                $table->string('guest_email')->nullable()->after('guest_name');
            }
            if (!Schema::hasColumn('bookings', 'guest_phone')) {
                $table->string('guest_phone')->nullable()->after('guest_email');
            }
            if (!Schema::hasColumn('bookings', 'guest_id_card')) {
                $table->string('guest_id_card')->nullable()->after('guest_phone');
            }
            if (!Schema::hasColumn('bookings', 'access_token')) {
                $table->string('access_token', 64)->nullable()->unique()->after('guest_id_card');
            }
            if (!Schema::hasColumn('bookings', 'down_payment_amount')) {
                $table->decimal('down_payment_amount', 14, 2)->default(0)->after('deposit_amount');
            }
            if (!Schema::hasColumn('bookings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('bookings', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('bookings', 'confirmed_by')) {
                $table->foreignId('confirmed_by')->nullable()->after('cancellation_reason')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'cancelled_by')) {
                $table->foreignId('cancelled_by')->nullable()->after('confirmed_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'checked_in_by')) {
                $table->foreignId('checked_in_by')->nullable()->after('cancelled_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('bookings', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('checked_in_by');
            }
        });

        // 3. Nullable user_id pada invoices untuk guest booking yang menerbitkan invoice awal sebelum aktivasi akun
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Revert invoices jika diperlukan
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['confirmed_by']);
            $table->dropForeign(['cancelled_by']);
            $table->dropForeign(['checked_in_by']);
            $table->dropColumn([
                'guest_name',
                'guest_email',
                'guest_phone',
                'guest_id_card',
                'access_token',
                'down_payment_amount',
                'rejection_reason',
                'cancellation_reason',
                'confirmed_by',
                'cancelled_by',
                'checked_in_by',
                'expires_at',
            ]);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'dp_required',
                'dp_type',
                'dp_amount',
                'dp_percentage',
                'payment_deadline_hours',
                'deposit_amount',
            ]);
        });
    }
};

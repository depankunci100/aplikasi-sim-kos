<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Master Meter Utilitas Fisik
        Schema::create('utility_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('utility_type')->default('electricity'); // electricity, water
            $table->string('meter_number')->unique();
            $table->string('meter_type')->default('individual'); // individual, shared
            $table->string('unit')->default('kWh'); // kWh, m3
            $table->decimal('initial_reading', 14, 2)->default(0.00);
            $table->decimal('current_reading', 14, 2)->default(0.00);
            $table->decimal('max_reading', 14, 2)->nullable(); // Kapasitas maks sebelum rollover jika ada
            $table->date('installed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'utility_type', 'is_active']);
            $table->index(['room_id', 'utility_type']);
        });

        // 2. Pengelolaan Konfigurasi Tarif
        Schema::create('utility_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->string('utility_type')->default('electricity'); // electricity, water
            $table->decimal('rate_per_unit', 14, 2);
            $table->string('unit')->default('kWh');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'utility_type', 'is_active']);
            $table->index(['effective_from', 'effective_to']);
        });

        // 3. Tambahkan kolom pendukung ke tabel utility_readings existing
        Schema::table('utility_readings', function (Blueprint $table) {
            if (!Schema::hasColumn('utility_readings', 'meter_id')) {
                $table->foreignId('meter_id')->nullable()->after('id')->constrained('utility_meters')->nullOnDelete();
            }
            if (!Schema::hasColumn('utility_readings', 'property_id')) {
                $table->foreignId('property_id')->nullable()->after('meter_id')->constrained('properties')->nullOnDelete();
            }
            if (!Schema::hasColumn('utility_readings', 'period_start')) {
                $table->date('period_start')->nullable()->after('reading_date');
            }
            if (!Schema::hasColumn('utility_readings', 'period_end')) {
                $table->date('period_end')->nullable()->after('period_start');
            }
            if (!Schema::hasColumn('utility_readings', 'status')) {
                $table->string('status')->default('approved')->after('proof_photo'); // draft, pending_review, approved, rejected
            }
            if (!Schema::hasColumn('utility_readings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('utility_readings', 'recorded_by')) {
                $table->foreignId('recorded_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('utility_readings', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('utility_readings', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('utility_readings', 'is_billed')) {
                $table->boolean('is_billed')->default(false)->after('reviewed_at');
            }
            if (!Schema::hasColumn('utility_readings', 'notes')) {
                $table->text('notes')->nullable()->after('is_billed');
            }
        });

        // 4. Tabel Audit Trail Pembacaan dan Koreksi Meter
        Schema::create('utility_reading_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_reading_id')->constrained('utility_readings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // recorded, reviewed, approved, rejected, corrected, billed
            $table->json('previous_data')->nullable();
            $table->json('new_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['utility_reading_id', 'created_at']);
        });

        // 5. Tabel Item Tagihan (Invoice Items Breakdown)
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('item_type')->default('rent'); // rent, electricity, water, deposit, penalty, service, other
            $table->string('name');
            $table->decimal('quantity', 14, 2)->default(1.00);
            $table->string('unit')->nullable(); // kWh, m3, month, day, dll.
            $table->decimal('rate', 14, 2)->default(0.00);
            $table->decimal('amount', 14, 2)->default(0.00);
            $table->json('metadata')->nullable(); // snapshot referensi meter, reading, previous/current meter, dll.
            $table->timestamps();

            $table->index(['invoice_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('utility_reading_logs');

        Schema::table('utility_readings', function (Blueprint $table) {
            $table->dropForeign(['meter_id']);
            $table->dropForeign(['property_id']);
            $table->dropForeign(['recorded_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn([
                'meter_id',
                'property_id',
                'period_start',
                'period_end',
                'status',
                'rejection_reason',
                'recorded_by',
                'reviewed_by',
                'reviewed_at',
                'is_billed',
                'notes',
            ]);
        });

        Schema::dropIfExists('utility_tariffs');
        Schema::dropIfExists('utility_meters');
    }
};

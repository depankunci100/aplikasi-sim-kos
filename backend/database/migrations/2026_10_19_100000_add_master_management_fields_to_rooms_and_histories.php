<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom master kamar (bersifat aditif)
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'building')) {
                $table->string('building', 50)->nullable()->after('floor');
            }
            if (!Schema::hasColumn('rooms', 'size_m2')) {
                $table->decimal('size_m2', 6, 2)->nullable()->after('building');
            }
            if (!Schema::hasColumn('rooms', 'capacity')) {
                $table->unsignedSmallInteger('capacity')->nullable()->after('size_m2');
            }
            if (!Schema::hasColumn('rooms', 'description')) {
                $table->text('description')->nullable()->after('capacity');
            }
            if (!Schema::hasColumn('rooms', 'facilities')) {
                $table->json('facilities')->nullable()->after('description');
            }
            if (!Schema::hasColumn('rooms', 'price_daily')) {
                $table->decimal('price_daily', 14, 2)->nullable()->after('facilities');
            }
            if (!Schema::hasColumn('rooms', 'price_monthly')) {
                $table->decimal('price_monthly', 14, 2)->nullable()->after('price_daily');
            }
            if (!Schema::hasColumn('rooms', 'price_yearly')) {
                $table->decimal('price_yearly', 14, 2)->nullable()->after('price_monthly');
            }
            if (!Schema::hasColumn('rooms', 'deposit_amount')) {
                $table->decimal('deposit_amount', 14, 2)->nullable()->after('price_yearly');
            }
            if (!Schema::hasColumn('rooms', 'down_payment_amount')) {
                $table->decimal('down_payment_amount', 14, 2)->nullable()->after('deposit_amount');
            }
            if (!Schema::hasColumn('rooms', 'photos')) {
                $table->json('photos')->nullable()->after('down_payment_amount');
            }
            if (!Schema::hasColumn('rooms', 'internal_notes')) {
                $table->text('internal_notes')->nullable()->after('photos');
            }
        });

        // 2. Tabel Riwayat Perubahan Data Kamar (Audit Trail)
        if (!Schema::hasTable('room_change_histories')) {
            Schema::create('room_change_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 50); // created, updated, status_changed, activated, deactivated
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['room_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('room_change_histories');

        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'building',
                'size_m2',
                'capacity',
                'description',
                'facilities',
                'price_daily',
                'price_monthly',
                'price_yearly',
                'deposit_amount',
                'down_payment_amount',
                'photos',
                'internal_notes',
            ]);
        });
    }
};

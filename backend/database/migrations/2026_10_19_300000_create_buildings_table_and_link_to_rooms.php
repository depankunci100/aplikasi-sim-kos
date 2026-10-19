<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Buat Tabel buildings (Master Gedung)
        if (!Schema::hasTable('buildings')) {
            Schema::create('buildings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->string('code', 50);
                $table->string('name', 150);
                $table->unsignedSmallInteger('total_floors')->default(1);
                $table->string('address_notes')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['property_id', 'code']);
                $table->index(['property_id', 'is_active']);
            });
        }

        // 2. Tambah relasi building_id pada tabel rooms (kompatibel SQLite & MySQL)
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'building_id')) {
                $table->unsignedBigInteger('building_id')->nullable()->after('floor')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'building_id')) {
                $table->dropColumn('building_id');
            }
        });

        Schema::dropIfExists('buildings');
    }
};

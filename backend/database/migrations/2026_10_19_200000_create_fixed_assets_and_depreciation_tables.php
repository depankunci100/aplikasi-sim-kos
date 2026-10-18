<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Master Aset Tetap & Inventaris Operasional
        if (!Schema::hasTable('assets')) {
            Schema::create('assets', function (Blueprint $table) {
                $table->id();
                $table->string('asset_code', 60)->unique();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
                $table->string('name');
                $table->string('category', 50)->index(); // building_infrastructure, electronics, vehicles, furniture, operational_equipment, other
                $table->string('classification', 40)->default('fixed_asset')->index(); // fixed_asset, low_value_inventory, consumable, wip
                $table->string('location_type', 30)->default('room'); // room, common_area, warehouse, building
                $table->string('location_name', 100)->nullable();
                $table->text('description')->nullable();
                $table->date('acquisition_date');
                $table->date('in_service_date')->nullable();
                $table->decimal('purchase_cost', 14, 2)->default(0.00);
                $table->decimal('salvage_value', 14, 2)->default(0.00);
                $table->unsignedInteger('useful_life_months')->default(0);
                $table->string('depreciation_method', 30)->default('straight_line'); // straight_line, none
                $table->decimal('accumulated_depreciation', 14, 2)->default(0.00);
                $table->decimal('current_book_value', 14, 2)->default(0.00);
                $table->string('vendor_name')->nullable();
                $table->string('purchase_reference')->nullable();
                $table->string('serial_number')->nullable();
                $table->date('warranty_expiry_date')->nullable();
                $table->string('condition', 30)->default('good'); // good, slightly_damaged, broken, missing
                $table->string('lifecycle_status', 30)->default('active')->index(); // draft, pending_approval, active, in_maintenance, inactive, disposed, written_off
                $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
                $table->json('photos')->nullable();
                $table->json('documents')->nullable();
                $table->text('notes')->nullable();
                $table->date('disposal_date')->nullable();
                $table->decimal('disposal_price', 14, 2)->nullable();
                $table->decimal('disposal_cost', 14, 2)->nullable();
                $table->text('disposal_notes')->nullable();
                $table->foreignId('disposal_journal_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->timestamps();

                $table->index(['property_id', 'lifecycle_status']);
                $table->index(['property_id', 'category']);
                $table->index(['room_id']);
            });
        }

        // 2. Tabel Histori Mutasi Lokasi Aset
        if (!Schema::hasTable('asset_location_histories')) {
            Schema::create('asset_location_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->string('from_location_type', 30)->nullable();
                $table->string('from_location_name', 100)->nullable();
                $table->foreignId('from_room_id')->nullable()->constrained('rooms')->nullOnDelete();
                $table->string('to_location_type', 30);
                $table->string('to_location_name', 100)->nullable();
                $table->foreignId('to_room_id')->nullable()->constrained('rooms')->nullOnDelete();
                $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('transferred_at')->useCurrent();
                $table->string('reason')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['asset_id', 'transferred_at']);
            });
        }

        // 3. Tabel Skedul Penyusutan Periodik (Depreciation Schedules)
        if (!Schema::hasTable('asset_depreciation_schedules')) {
            Schema::create('asset_depreciation_schedules', function (Blueprint $table) {
                $table->id();
                $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
                $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
                $table->unsignedSmallInteger('fiscal_year');
                $table->unsignedTinyInteger('fiscal_month');
                $table->date('period_date');
                $table->decimal('depreciation_amount', 14, 2)->default(0.00);
                $table->decimal('accumulated_depreciation_before', 14, 2)->default(0.00);
                $table->decimal('accumulated_depreciation_after', 14, 2)->default(0.00);
                $table->decimal('book_value_beginning', 14, 2)->default(0.00);
                $table->decimal('book_value_ending', 14, 2)->default(0.00);
                $table->string('status', 20)->default('scheduled'); // scheduled, posted, skipped, reversed
                $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->string('idempotency_key', 120)->unique();
                $table->timestamps();

                $table->index(['asset_id', 'fiscal_year', 'fiscal_month']);
                $table->index(['property_id', 'status', 'period_date']);
            });
        }

        // 4. Modifikasi Tabel maintenance_requests untuk relasi aset & kapitalisasi
        Schema::table('maintenance_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_requests', 'asset_id')) {
                $table->unsignedBigInteger('asset_id')->nullable()->after('room_asset_id')->index();
            }
            if (!Schema::hasColumn('maintenance_requests', 'is_capitalized')) {
                $table->boolean('is_capitalized')->default(false)->after('total_cost');
            }
            if (!Schema::hasColumn('maintenance_requests', 'capitalized_amount')) {
                $table->decimal('capitalized_amount', 14, 2)->default(0.00)->after('is_capitalized');
            }
            if (!Schema::hasColumn('maintenance_requests', 'capitalized_at')) {
                $table->timestamp('capitalized_at')->nullable()->after('capitalized_amount');
            }
            if (!Schema::hasColumn('maintenance_requests', 'capitalized_by')) {
                $table->unsignedBigInteger('capitalized_by')->nullable()->after('capitalized_at')->index();
            }
            if (!Schema::hasColumn('maintenance_requests', 'expense_id')) {
                $table->unsignedBigInteger('expense_id')->nullable()->after('capitalized_by')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'asset_id',
                'is_capitalized',
                'capitalized_amount',
                'capitalized_at',
                'capitalized_by',
                'expense_id',
            ]);
        });

        Schema::dropIfExists('asset_depreciation_schedules');
        Schema::dropIfExists('asset_location_histories');
        Schema::dropIfExists('assets');
    }
};

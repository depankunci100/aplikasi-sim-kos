<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabel Utama Inspeksi
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('inspection_code')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('baseline_inspection_id')->nullable();

            $table->string('type')->default('check_in'); // check_in, periodic, check_out, maintenance
            $table->string('status')->default('scheduled'); // scheduled, in_progress, completed, approved_by_tenant, objected_by_tenant, cancelled
            $table->date('scheduled_date');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('overall_condition')->nullable(); // good, fair, poor
            $table->text('inspector_notes')->nullable();

            // Tenant approval / objection fields
            $table->text('tenant_feedback')->nullable();
            $table->timestamp('tenant_decision_at')->nullable();
            $table->timestamp('tenant_due_at')->nullable(); // batas waktu respon penyewa

            $table->timestamps();

            $table->foreign('baseline_inspection_id')->references('id')->on('inspections')->nullOnDelete();
            $table->index(['property_id', 'status']);
            $table->index(['room_id', 'type']);
            $table->index(['booking_id']);
            $table->index(['inspector_id', 'status']);
            $table->index(['tenant_id', 'status']);
        });

        // 2. Tabel Item Checklist Inspeksi
        Schema::create('inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('room_asset_id')->nullable()->constrained('room_assets')->nullOnDelete();
            $table->string('item_name');
            $table->string('category')->default('general'); // furniture, electronics, bathroom, structure, keys_access, cleanliness, general
            $table->string('condition')->default('good'); // good, fair, poor, damaged, missing
            $table->text('notes')->nullable();
            $table->boolean('repair_required')->default(false);
            $table->decimal('estimated_cost', 14, 2)->default(0.00);
            $table->timestamps();

            $table->index(['inspection_id', 'category']);
        });

        // 3. Tabel Media / Bukti Foto Inspeksi (Private Storage)
        Schema::create('inspection_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('inspection_item_id')->nullable()->constrained('inspection_items')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('caption')->nullable();
            $table->string('media_type')->default('photo'); // photo, video, document
            $table->timestamps();

            $table->index(['inspection_id']);
            $table->index(['inspection_item_id']);
        });

        // 4. Tabel Audit Trail Inspeksi
        Schema::create('inspection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // created, assigned, started, item_updated, photo_uploaded, completed, tenant_approved, tenant_objected, reopened, cancelled
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_logs');
        Schema::dropIfExists('inspection_media');
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
    }
};

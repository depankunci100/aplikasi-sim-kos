<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modifikasi tabel complaints existing (bersifat aditif)
        Schema::table('complaints', function (Blueprint $table) {
            if (!Schema::hasColumn('complaints', 'category')) {
                $table->string('category')->default('room')->after('user_id'); // room, facility, service, billing, environment, other
            }
            if (!Schema::hasColumn('complaints', 'maintenance_request_id')) {
                $table->unsignedBigInteger('maintenance_request_id')->nullable()->after('assigned_to');
            }
            if (!Schema::hasColumn('complaints', 'first_responded_at')) {
                $table->timestamp('first_responded_at')->nullable()->after('resolved_at');
            }
            if (!Schema::hasColumn('complaints', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('first_responded_at');
            }
            if (!Schema::hasColumn('complaints', 'reopen_reason')) {
                $table->text('reopen_reason')->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('complaints', 'reopened_at')) {
                $table->timestamp('reopened_at')->nullable()->after('reopen_reason');
            }
            if (!Schema::hasColumn('complaints', 'tenant_feedback')) {
                $table->text('tenant_feedback')->nullable()->after('reopened_at');
            }
            if (!Schema::hasColumn('complaints', 'rating')) {
                $table->unsignedTinyInteger('rating')->nullable()->after('tenant_feedback'); // 1 - 5
            }
            if (!Schema::hasColumn('complaints', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('rating');
            }
            if (!Schema::hasColumn('complaints', 'acknowledged_by')) {
                $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('complaints', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('acknowledged_by');
            }
            if (!Schema::hasColumn('complaints', 'closed_by')) {
                $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
            }
        });

        // 2. Tabel Maintenance Requests (Manajemen Pemeliharaan & Kerusakan)
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('room_asset_id')->nullable()->constrained('room_assets')->nullOnDelete();
            $table->foreignId('complaint_id')->nullable()->constrained('complaints')->nullOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('category')->default('other'); // electricity, water, plumbing, ac, furniture, door_lock, internet, cleanliness, other
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->string('status')->default('reported'); // reported, triaged, assigned, in_progress, pending_verification, completed, cancelled, rejected
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->date('scheduled_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->text('diagnosis_notes')->nullable();
            $table->text('action_taken')->nullable();
            $table->json('spare_parts')->nullable();
            $table->decimal('labor_cost', 14, 2)->default(0.00);
            $table->decimal('material_cost', 14, 2)->default(0.00);
            $table->decimal('total_cost', 14, 2)->default(0.00);
            $table->json('photos_before')->nullable();
            $table->json('photos_after')->nullable();
            $table->timestamp('sla_due_at')->nullable();
            $table->boolean('is_overdue')->default(false);
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['room_id', 'status']);
            $table->index(['assigned_to', 'status']);
            $table->index(['priority', 'status']);
            $table->index('sla_due_at');
        });

        // Hubungkan foreign key maintenance_request_id pada complaints setelah tabel maintenance_requests tersedia
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests')->nullOnDelete();
        });

        // 3. Tabel Log Audit Maintenance (Audit Trail)
        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_request_id')->constrained('maintenance_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // reported, triaged, assigned, started, submitted_verification, completed, rejected, cancelled, updated
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['maintenance_request_id', 'created_at']);
        });

        // 4. Tabel Log Audit Complaints (Audit Trail Keluhan)
        Schema::create('complaint_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // submitted, acknowledged, assigned, in_progress, resolved, reopened, closed, rejected
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        // 5. Tabel Komentar / Diskusi Keluhan (Complaint Comments)
        Schema::create('complaint_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('comment');
            $table->json('attachments')->nullable();
            $table->boolean('is_internal')->default(false); // Catatan internal staf/manajer tidak terlihat oleh tenant
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        // 6. Tabel Housekeeping Tasks (Manajemen Kebersihan)
        Schema::create('housekeeping_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_number')->unique();
            $table->foreignId('property_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->string('area_name')->nullable(); // Kamar 101, Lobi Lantai 1, Dapur Bersama, dll.
            $table->string('type')->default('room_cleaning'); // room_cleaning, turnover_cleaning, deep_cleaning, common_area_cleaning, on_demand
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            $table->date('scheduled_date');
            $table->string('scheduled_slot')->nullable(); // Pagi (08:00 - 11:00), Siang, Sore
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled'); // scheduled, assigned, in_progress, pending_verification, completed, cancelled
            $table->json('checklist')->nullable(); // Daftar item kebersihan yang terstruktur
            $table->json('photos_before')->nullable();
            $table->json('photos_after')->nullable();
            $table->text('damaged_or_missing_items')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'status']);
            $table->index(['room_id', 'scheduled_date', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        // 7. Tabel Log Audit Housekeeping
        Schema::create('housekeeping_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('housekeeping_task_id')->constrained('housekeeping_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // scheduled, assigned, started, submitted_verification, completed, cancelled
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['housekeeping_task_id', 'created_at']);
        });

        // 8. Fondasi Notifikasi Internal In-App
        Schema::create('internal_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('type'); // maintenance_assigned, complaint_resolved, housekeeping_overdue, dll.
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable(); // { "reference_id": 12, "reference_type": "maintenance", "action_url": "..." }
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index(['property_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_notifications');
        Schema::dropIfExists('housekeeping_logs');
        Schema::dropIfExists('housekeeping_tasks');
        Schema::dropIfExists('complaint_comments');
        Schema::dropIfExists('complaint_logs');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['maintenance_request_id']);
        });

        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenance_requests');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['acknowledged_by']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'category',
                'maintenance_request_id',
                'first_responded_at',
                'rejection_reason',
                'reopen_reason',
                'reopened_at',
                'tenant_feedback',
                'rating',
                'acknowledged_at',
                'acknowledged_by',
                'closed_at',
                'closed_by',
            ]);
        });
    }
};

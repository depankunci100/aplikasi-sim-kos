<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Modifikasi tabel announcements (nullable property_id & attachment)
        Schema::table('announcements', function (Blueprint $table) {
            $table->foreignId('property_id')->nullable()->change();
            $table->string('attachment_path')->nullable()->after('content');
        });

        // 2. Tabel pelacakan status baca pengumuman per-user
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['user_id', 'read_at']);
        });

        // 3. Modifikasi tenant_profiles untuk audit verifikasi NIK dan blind index
        Schema::table('tenant_profiles', function (Blueprint $table) {
            $table->char('nik_hash', 64)->nullable()->unique()->after('ktp_number');
            $table->boolean('is_nik_verified')->default(false)->after('nik_hash');
            $table->timestamp('nik_verified_at')->nullable()->after('is_nik_verified');
        });

        // 4. Tabel log audit inisiasi reset password
        Schema::create('password_reset_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('status')->default('initiated'); // initiated, completed, expired, revoked
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_logs');

        Schema::table('tenant_profiles', function (Blueprint $table) {
            $table->dropColumn(['nik_hash', 'is_nik_verified', 'nik_verified_at']);
        });

        Schema::dropIfExists('announcement_reads');

        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('attachment_path');
        });
    }
};

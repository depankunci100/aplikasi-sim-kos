<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->string('utility_type')->default('electricity'); // electricity, water
            $table->decimal('previous_meter', 10, 2);
            $table->decimal('current_meter', 10, 2);
            $table->decimal('usage', 10, 2);
            $table->decimal('rate_per_unit', 10, 2);
            $table->decimal('total_cost', 14, 2);
            $table->date('reading_date');
            $table->string('proof_photo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_readings');
    }
};

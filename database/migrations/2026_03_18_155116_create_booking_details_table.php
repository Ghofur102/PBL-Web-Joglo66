<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_booking_id')->constrained('bookings')->onDelete('cascade');
            $table->date('play_date');
            $table->time('start_play_time');
            $table->time('end_play_time');
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'waiting', 'finish', 'cancelled', 'reschedule', 'field closure', 'closed field cancelled', 'closed field reschedule'])->default('waiting');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_attributes');
    }
};

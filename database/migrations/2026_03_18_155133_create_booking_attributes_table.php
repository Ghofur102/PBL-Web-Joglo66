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
        Schema::create('booking_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('fk_attribute_id')->constrained('attributes');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('price');
            $table->unsignedInteger('total');
            $table->enum('status', ['dipinjam', 'dikembalikan', 'terlambat'])->default('dipinjam');
            $table->text('reason')->nullable();
            $table->string('customer_name', 100);
            $table->string('customer_phone', 20)->nullable();
            $table->integer('duration_hours');
            $table->date('transaction_date');
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

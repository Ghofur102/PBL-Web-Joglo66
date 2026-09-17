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
        Schema::create('fields', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->index();
            $table->text('description')->nullable();
            $table->string('image_url', 255)->nullable();
            $table->enum('category', ['futsal', 'mini soccer'])->index();
            $table->unsignedTinyInteger('min_cancel_days')->default(3);
            $table->unsignedTinyInteger('min_reschedule_days')->default(3);
            $table->unsignedTinyInteger('max_reschedule_times')->default(1);
            $table->foreignId('fk_user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};

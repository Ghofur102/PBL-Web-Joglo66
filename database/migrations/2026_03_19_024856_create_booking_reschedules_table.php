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
        Schema::create('booking_reschedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fk_booking_detail_id')->constrained('booking_details')->onDelete('cascade');
            $table->foreignId('fk_field_closure_id')->nullable()->constrained('field_closures')->onDelete('cascade');
            $table->date('old_date');
            $table->enum('status_refund', ['none', 'deposit required', 'refund required']);
            $table->text('reason')->nullable();
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('approved')->after('status_refund');
            $table->enum('sender_by', ['admin', 'tenant'])->default('admin')->after('approval_status');
            $table->text('rejection_reason')->nullable()->after('sender_by');
            $table->date('new_play_date')->nullable()->after('old_date');
            $table->time('new_start_play_time')->nullable()->after('new_play_date');
            $table->time('new_end_play_time')->nullable()->after('new_start_play_time');
            $table->integer('new_price')->nullable()->after('new_end_play_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_reschedules');
    }
};

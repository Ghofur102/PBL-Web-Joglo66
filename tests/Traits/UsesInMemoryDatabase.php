<?php

namespace Tests\Traits;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait UsesInMemoryDatabase
{
    protected function setUpInMemoryDatabase(): void
    {
        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.mysql_joglo66_app' => $connection,
        ]);
        DB::purge('mysql_joglo66_app');
    }

    protected function createUsersTable(): void
    {
        Schema::connection('mysql_joglo66_app')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
    }

    protected function createBookingsTable(): void
    {
        Schema::connection('mysql_joglo66_app')->create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_user_id');
            $table->unsignedBigInteger('fk_field_id');
            $table->string('team_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('notes')->nullable();
            $table->date('booking_date');
            $table->timestamps();
        });
    }

    protected function createBookingDetailsTable(): void
    {
        Schema::connection('mysql_joglo66_app')->create('booking_details', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_id');
            $table->date('play_date');
            $table->time('start_play_time');
            $table->time('end_play_time');
            $table->decimal('price', 10, 2);
            $table->string('status');
            $table->timestamps();
        });
    }

    protected function createPaymentsTable(bool $withReferenceId = false): void
    {
        Schema::connection('mysql_joglo66_app')->create('payments', function (Blueprint $table) use ($withReferenceId): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_id');
            $table->unsignedBigInteger('fk_booking_detail_id')->nullable();
            if ($withReferenceId) {
                $table->string('reference_id')->nullable();
                $table->string('payment_url')->nullable();
            }
            $table->string('payment_type');
            $table->string('method')->nullable();
            $table->unsignedInteger('amount');
            $table->string('status');
            if ($withReferenceId) {
                $table->timestamp('paid_at')->nullable();
            }
            $table->timestamps();
        });
    }

    protected function createExpensesTable(): void
    {
        Schema::connection('mysql_joglo66_app')->create('expenses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->unsignedBigInteger('fk_user_id');
            $table->string('name');
            $table->string('category');
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('unit_price');
            $table->date('expense_date');
            $table->string('proof_photo')->nullable();
            $table->text('note')->nullable();
            $table->date('generate_at');
            $table->timestamps();
        });
    }
}

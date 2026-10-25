<?php

namespace Tests\Unit\Services\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\FieldPrice;
use App\Models\Payment;
use App\Services\DuitkuService;
use App\Services\Tenant\Booking\TenantBookingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;
use UnexpectedValueException;

#[RunTestsInSeparateProcesses]
class TenantBookingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.connections.sqlite');
        $connection['database'] = ':memory:';
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.mysql_joglo66_app' => $connection,
        ]);
        DB::purge('mysql_joglo66_app');

        Schema::connection('mysql_joglo66_app')->create('fields', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('field_prices', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->string('day_type');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('price');
            $table->timestamps();
        });
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
        Schema::connection('mysql_joglo66_app')->create('payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_id');
            $table->unsignedBigInteger('fk_booking_detail_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('payment_url')->nullable();
            $table->string('payment_type');
            $table->string('method');
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('field_closures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->dateTime('field_closure_start_time');
            $table->dateTime('field_closure_end_time');
        });
    }

    public function test_calculate_and_group_slots(): void
    {
        FieldPrice::create([
            'fk_field_id' => 1,
            'day_type' => 'monday',
            'start_time' => '08:00:00',
            'end_time' => '10:00:00',
            'price' => 150000,
        ]);

        $result = (new TenantBookingService)->calculateAndGroupSlots(1, [
            ['date' => '2026-10-26', 'jam' => '08:00', 'jam_akhir' => '09:00'],
            ['date' => '2026-10-26', 'jam' => '09:00', 'jam_akhir' => '10:00'],
        ]);

        $this->assertSame(300000, $result['totalPrice']);
        $this->assertCount(2, $result['groupedSlots']['2026-10-26']);
        $this->assertSame(150000, $result['groupedSlots']['2026-10-26'][1]['harga']);
    }

    public function test_process_booking_transaction(): void
    {
        $service = new TenantBookingService;
        $duitku = \Mockery::mock(DuitkuService::class);
        $duitku->shouldReceive('createInvoice')
            ->once()
            ->withArgs(fn (Booking $booking, float|int $amount): bool => $booking->team_name === 'Joglo team' && $amount === 100000)
            ->andReturn((object) ['reference' => 'INV-1', 'paymentUrl' => 'https://payment.test']);

        $result = $service->processBookingTransaction(
            7,
            (object) ['phone_number' => '0812345678', 'email' => 'tenant@example.com'],
            [
                'field_id' => 1,
                'team_name' => 'Joglo team',
                'payment_type' => PaymentType::FINAL_PAYMENT->value,
            ],
            ['2026-10-26' => [['jam' => '08:00', 'jam_akhir' => '09:00', 'harga' => 100000]]],
            $duitku
        );

        $this->assertSame('Joglo team', $result['booking']->team_name);
        $this->assertSame(1, BookingDetail::count());
        $this->assertSame(100000, Payment::first()->amount);
        $this->assertSame(PaymentStatus::PENDING->value, Payment::first()->status);
    }

    public function test_get_booking_success_data(): void
    {
        $booking = Booking::create([
            'fk_user_id' => 7,
            'fk_field_id' => 2,
            'team_name' => 'Team',
            'booking_date' => '2026-10-25',
        ]);

        $result = (new TenantBookingService)->getBookingSuccessData($booking->id, 7);

        $this->assertSame($booking->id, $result->id);
        $this->assertTrue($result->relationLoaded('details'));
        $this->assertTrue($result->relationLoaded('field'));
    }

    public function test_validate_slots_availability(): void
    {
        $method = new ReflectionMethod(TenantBookingService::class, 'validateSlotsAvailability');

        $method->invoke(new TenantBookingService, 1, [
            '2026-10-26' => [['jam' => '08:00:00', 'jam_akhir' => '09:00:00']],
        ]);

        $this->assertTrue(true);
    }

    public function test_check_booking_conflict(): void
    {
        $booking = Booking::create([
            'fk_user_id' => 7,
            'fk_field_id' => 1,
            'team_name' => 'Existing',
            'booking_date' => '2026-10-25',
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-10-26',
            'start_play_time' => '08:00:00',
            'end_play_time' => '09:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);
        $method = new ReflectionMethod(TenantBookingService::class, 'checkBookingConflict');

        $this->expectException(UnexpectedValueException::class);
        $method->invoke(new TenantBookingService, 1, '2026-10-26', [
            'jam' => '08:30:00',
            'jam_akhir' => '09:30:00',
        ]);
    }

    public function test_check_field_closure_conflict(): void
    {
        DB::connection('mysql_joglo66_app')->table('field_closures')->insert([
            'fk_field_id' => 1,
            'field_closure_start_time' => '2026-10-26 08:30:00',
            'field_closure_end_time' => '2026-10-26 09:30:00',
        ]);
        $method = new ReflectionMethod(TenantBookingService::class, 'checkFieldClosureConflict');

        $this->expectException(UnexpectedValueException::class);
        $method->invoke(new TenantBookingService, 1, '2026-10-26', [
            'jam' => '08:00:00',
            'jam_akhir' => '09:00:00',
        ]);
    }
}

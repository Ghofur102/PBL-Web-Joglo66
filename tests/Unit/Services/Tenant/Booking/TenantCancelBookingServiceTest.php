<?php

namespace Tests\Unit\Services\Tenant\Booking;

use App\Enums\CancelRefundStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingCancelled;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Services\RefundRuleService;
use App\Services\Tenant\Booking\TenantCancelBookingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class TenantCancelBookingServiceTest extends TestCase
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

        Schema::connection('mysql_joglo66_app')->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('fields', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->unsignedTinyInteger('min_cancel_days')->nullable();
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
            $table->string('payment_type');
            $table->string('method')->nullable();
            $table->unsignedInteger('amount');
            $table->string('status');
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('booking_cancelled', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_detail_id');
            $table->unsignedBigInteger('fk_field_closure_id')->nullable();
            $table->date('cancle_date');
            $table->string('reason')->nullable();
            $table->string('status_refund');
            $table->string('approval_status');
            $table->string('sender_by');
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });
        Schema::connection('mysql_joglo66_app')->create('booking_reschedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_booking_detail_id');
            $table->string('approval_status');
        });
        Schema::connection('mysql_joglo66_app')->create('field_workers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fk_field_id');
            $table->unsignedBigInteger('fk_user_id');
        });
    }

    public function test_constructor(): void
    {
        $service = new TenantCancelBookingService(new RefundRuleService);

        $this->assertInstanceOf(TenantCancelBookingService::class, $service);
    }

    public function test_get_cancellation_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00'));
        $detail = $this->createBookingDetail('active', '2026-11-01');
        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'amount' => 5000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::REFUND->value,
            'amount' => 1000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        $refundRules = \Mockery::mock(RefundRuleService::class);
        $refundRules->shouldReceive('getPolicy')->once()->andReturn([
            'terms' => [['min_hours_before' => 24, 'refund_percent' => 80, 'text' => 'Terms']],
            'deadline' => Carbon::parse('2026-10-31 10:00:00'),
            'currentPercent' => 80,
            'isWithinWindow' => true,
        ]);

        $result = (new TenantCancelBookingService($refundRules))->getCancellationData($detail);

        $this->assertSame(4000, $result['netPaid']);
        $this->assertSame(4000, $result['refundAmount']);
        $this->assertSame(3200, $result['estimateRefundAmount']);
        $this->assertTrue($result['canSubmit']);
        Carbon::setTestNow();
    }

    public function test_process_cancellation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00:00'));
        $tenant = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com', 'password' => 'secret']);
        $detail = $this->createBookingDetail('active', '2026-11-01', $tenant->id);
        Auth::login($tenant);
        $refundRules = \Mockery::mock(RefundRuleService::class);
        $refundRules->shouldReceive('getPolicy')->twice()->andReturn([
            'terms' => [['min_hours_before' => 24, 'refund_percent' => 80, 'text' => 'Terms']],
            'deadline' => Carbon::parse('2026-10-31 10:00:00'),
            'currentPercent' => 80,
            'isWithinWindow' => true,
        ]);

        (new TenantCancelBookingService($refundRules))->processCancellation($detail, 'Schedule changed');

        $cancel = BookingCancelled::first();
        $this->assertNotNull($cancel);
        $this->assertSame('pending', $cancel->approval_status);
        $this->assertSame(CancelRefundStatus::FULL->value, $cancel->status_refund);
        $this->assertSame('Schedule changed', $cancel->reason);
        Carbon::setTestNow();
    }

    public function test_get_policy(): void
    {
        $detail = $this->createBookingDetail('active', '2026-11-01');
        $refundRules = \Mockery::mock(RefundRuleService::class);
        $refundRules->shouldReceive('getPolicy')->once()->withArgs(
            fn (int $fieldId, string $type, Carbon $playAt): bool => $fieldId === 1 && $type === 'cancel' && $playAt->toDateString() === '2026-11-01'
        )->andReturn(['terms' => [], 'deadline' => null, 'currentPercent' => null, 'isWithinWindow' => false]);
        $method = new ReflectionMethod(TenantCancelBookingService::class, 'getPolicy');

        $policy = $method->invoke(new TenantCancelBookingService($refundRules), $detail);

        $this->assertFalse($policy['isWithinWindow']);
        $this->assertSame([], $policy['terms']);
    }

    public function test_calculate_payment_totals(): void
    {
        $detail = $this->createBookingDetail('active', '2026-11-01');
        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'amount' => 10000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::RESCHEDULE_FEE->value,
            'amount' => 3000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        Payment::create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::REFUND->value,
            'amount' => 2000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $this->assertSame(11000, (new TenantCancelBookingService(new RefundRuleService))->calculatePaymentTotals($detail));
    }

    private function createBookingDetail(string $status, string $playDate, ?int $userId = null): BookingDetail
    {
        $userId ??= User::create(['name' => 'Tenant', 'email' => 'tenant@example.com'])->id;
        $field = Field::create(['name' => 'Main field', 'min_cancel_days' => 3]);
        $booking = Booking::create([
            'fk_user_id' => $userId,
            'fk_field_id' => $field->id,
            'team_name' => 'Team',
            'booking_date' => '2026-10-20',
        ]);

        return BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $playDate,
            'start_play_time' => '12:00:00',
            'end_play_time' => '13:00:00',
            'price' => 10000,
            'status' => $status,
        ]);
    }
}

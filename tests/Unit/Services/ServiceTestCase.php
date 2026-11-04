<?php

namespace Tests\Unit\Services;

use App\Enums\GeneralStatus;
use App\Models\Attribute;
use App\Models\Booking;
use App\Models\BookingCancelled;
use App\Models\BookingAttribute;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\Payment;
use App\Models\FieldPrice;
use App\Models\FieldRefundRule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use App\Services\RefundRuleService;
use Tests\TestCase;

abstract class ServiceTestCase extends TestCase
{
    public const DATABASE_CONNECTION_APP = 'mysql_joglo66_app';
    public const TABLE_BOOKING_ATTRIBUTES = 'booking_attributes';
    public const TABLE_ATTRIBUTES = 'attributes';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql_joglo66_app',
        ]);

        DB::purge('mysql');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', [
            '--database' => 'mysql',
            '--force' => true,
        ])->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        DB::disconnect('mysql_joglo66_app');
        DB::disconnect('mysql');

        parent::tearDown();
    }

    protected function createUser(
        string $role,
        string $emailPrefix,
        string $name = 'Test User',
    ): User {
        return User::query()->create([
            'name' => $name,
            'email' => $emailPrefix.'@example.test',
            'password' => 'password',
            'role' => $role,
        ]);
    }

    protected function createField(User $owner, string $name): Field
    {
        return Field::query()->create([
            'name' => $name,
            'category' => 'futsal',
            'fk_user_id' => $owner->id,
        ]);
    }

    protected function createBooking(
        User $customer,
        Field $field,
        string $teamName,
        string $phone,
    ): Booking {
        return Booking::query()->create([
            'fk_user_id' => $customer->id,
            'fk_field_id' => $field->id,
            'booking_date' => '2026-10-25',
            'team_name' => $teamName,
            'customer_phone' => $phone,
            'customer_email' => 'customer@example.test',
            'notes' => '-',
        ]);
    }

    protected function createBookingDetail(
        Booking $booking,
        string $playDate,
        string $startTime,
        string $endTime,
        string $status,
    ): BookingDetail {
        return BookingDetail::query()->create([
            'fk_booking_id' => $booking->id,
            'play_date' => $playDate,
            'start_play_time' => $startTime,
            'end_play_time' => $endTime,
            'price' => 100000,
            'status' => $status,
        ]);
    }

    protected function createAttribute(
        Field $field,
        string $name,
        int $stock,
        int $price,
        string $status = GeneralStatus::ACTIVE->value,
        string $type = 'peralatan',
    ): Attribute {
        return Attribute::query()->create([
            'fk_field_id' => $field->id,
            'name' => $name,
            'type' => $type,
            'stock' => $stock,
            'price_hour' => $price,
            'status' => $status,
        ]);
    }

    protected function createRental(
        BookingDetail $detail,
        Attribute $attribute,
        int $quantity,
        string $status,
        string $transactionDate,
        string $customerName = 'Dina',
    ): BookingAttribute {
        return BookingAttribute::query()->create([
            'fk_booking_detail_id' => $detail->id,
            'fk_attribute_id' => $attribute->id,
            'quantity' => $quantity,
            'price' => $attribute->price_hour,
            'total' => $quantity * $attribute->price_hour,
            'transaction_date' => $transactionDate,
            'status' => $status,
            'customer_name' => $customerName,
            'customer_phone' => '085555555555',
            'duration_hours' => 1,
        ]);
    }

    protected function createDetail(User $owner, User $tenant): BookingDetail
    {
        $field = $this->createField($owner, ServiceTestData::FIELD_SECONDARY);
        $booking = $this->createBooking(
            $tenant,
            $field,
            ServiceTestData::TEAM_SECONDARY,
            ServiceTestData::PHONE_SECONDARY,
        );

        return $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_11_00,
            ServiceTestData::TIME_12_00,
            ServiceTestData::BOOKING_ACTIVE,
        );
    }

    protected function createPendingCancellation(BookingDetail $detail): BookingCancelled
    {
        return BookingCancelled::query()->create([
            'fk_booking_detail_id' => $detail->id,
            'cancle_date' => ServiceTestData::DATE_25_OCT_2026,
            'reason' => ServiceTestData::CANCEL_REASON,
            'status_refund' => ServiceTestData::CANCEL_REFUND_NONE,
            'approval_status' => ServiceTestData::APPROVAL_PENDING,
            'sender_by' => ServiceTestData::ROLE_TENANT,
        ]);
    }

    protected function createPendingDetail(User $owner, User $tenant): BookingDetail
    {
        $detail = $this->createDetail($owner, $tenant);
        $this->createPendingCancellation($detail);

        return $detail;
    }

    protected function createPendingPayment(BookingDetail $detail): Payment
    {
        return Payment::query()->create([
            'fk_booking_id' => $detail->fk_booking_id,
            'fk_booking_detail_id' => $detail->id,
            'reference_id' => 'PAY-PENDING-' . $detail->id,
            'payment_type' => ServiceTestData::PAYMENT_DOWN_PAYMENT,
            'method' => ServiceTestData::PAYMENT_METHOD_CASH,
            'amount' => ServiceTestData::ONE_HUNDRED_THOUSAND,
            'status' => ServiceTestData::PAYMENT_PENDING,
        ]);
    }

    protected function createCancelledPaymentFixture(User $owner): array
    {
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_SITI, ServiceTestData::CUSTOMER_SITI);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_TERTIARY);
        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_24_OCT_2026,
            ServiceTestData::TIME_14_00,
            ServiceTestData::TIME_15_00,
            ServiceTestData::BOOKING_CANCELLED,
        );

        return [$booking, $detail];
    }

    protected function createNotification(User $user, string $type, ?array $data = null, ?string $readAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $type,
            'data' => $data ?? [
                'type' => $type,
                'title' => ServiceTestData::NOTIFICATION_TITLE,
                'message' => ServiceTestData::NOTIFICATION_MESSAGE,
            ],
            'read_at' => $readAt,
        ]);
    }

    protected function createRescheduleFixture(): array
    {
        Carbon::setTestNow(ServiceTestData::dateTime(ServiceTestData::DATE_20_OCT_2026, ServiceTestData::TIME_10_00));
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $field->update(['max_reschedule_times' => ServiceTestData::ONE]);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_10_00,
            ServiceTestData::TIME_11_00,
            ServiceTestData::BOOKING_ACTIVE,
        );

        FieldPrice::query()->create([
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'price' => ServiceTestData::ONE_HUNDRED_THOUSAND,
        ]);
        FieldRefundRule::query()->create([
            'fk_field_id' => $field->id,
            'rule_type' => 'reschedule',
            'min_hours_before' => ServiceTestData::ZERO,
            'refund_percent' => ServiceTestData::ONE_HUNDRED_THOUSAND / ServiceTestData::ONE_THOUSAND,
            'is_active' => true,
        ]);

        return [$detail, $tenant, $owner];
    }

    protected function createRefundRuleService(): RefundRuleService
    {
        return new RefundRuleService();
    }

    protected function invokeServiceMethod(object $service, string $methodName, array $arguments = []): mixed
    {
        $method = new \ReflectionMethod($service, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($service, $arguments);
    }
}

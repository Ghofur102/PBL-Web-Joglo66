<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\BookingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class BookingServiceTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'joglo66_booking_test_');
        if ($databasePath === false) {
            throw new \RuntimeException('Unable to create the isolated test database.');
        }
        $this->testDatabasePath = $databasePath;

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = $this->testDatabasePath;
        $sqlite['url'] = null;
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.sqlite' => $sqlite,
            'database.connections.mysql_joglo66_app' => $sqlite,
        ]);
        DB::purge('sqlite');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::disconnect('mysql_joglo66_app');
        if (isset($this->testDatabasePath) && file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }

        parent::tearDown();
    }

    public function test_get_booking_list(): void
    {
        $booking = $this->createBooking();
        $this->createDetail($booking, ['play_date' => '2027-01-04']);
        $this->createDetail($booking, ['play_date' => '2027-01-05']);

        $result = app(BookingService::class)->getBookingList([$booking->fk_field_id], []);

        $this->assertCount(2, $result['grouped_bookings']);
        $this->assertSame('2027-01-04', $result['grouped_bookings'][0]['play_date']);
        $this->assertSame('Joglo FC', $result['grouped_bookings'][0]['bookings'][0]['title']);
        $this->assertSame(0, $result['closed_affected_count']);
    }

    public function test_create_booking(): void
    {
        $user = $this->createUser();
        $field = $this->createField($user);
        $payload = [
            'user_id' => $user->id,
            'field_id' => $field->id,
            'team_name' => 'New Team',
            'booking_date' => '2027-01-01',
            'customer_phone' => '08123456789',
            'customer_email' => 'team@example.test',
            'notes' => 'Evening game',
            'details' => [
                [
                    'play_date' => '2027-01-04',
                    'start_play_time' => '10:00:00',
                    'end_play_time' => '11:00:00',
                    'price' => 100000,
                ],
                [
                    'play_date' => '2027-01-05',
                    'start_play_time' => '11:00:00',
                    'end_play_time' => '12:00:00',
                    'price' => 125000,
                ],
            ],
        ];

        $booking = app(BookingService::class)->createBooking($payload);

        $this->assertSame('New Team', $booking->team_name);
        $this->assertSame(2, $booking->details()->count());
        $this->assertSame(
            [BookingDetailStatus::WAITING->value, BookingDetailStatus::WAITING->value],
            $booking->details()->orderBy('id')->pluck('status')->all()
        );
    }

    public function test_get_booking_detail_info(): void
    {
        $booking = $this->createBooking();
        $detail = $this->createDetail($booking, ['price' => 100000]);
        $this->createPayment($booking, [
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'amount' => 70000,
        ]);

        $result = app(BookingService::class)->getBookingDetailInfo($detail);

        $this->assertSame($booking->id, $result['booking_id']);
        $this->assertSame('Joglo FC', $result['user_info']['team_name']);
        $this->assertSame(100000, $result['service_info']['total_price']);
        $this->assertSame(70000, $result['payment_details']['total_paid']);
        $this->assertSame(30000, $result['sessions'][0]['remaining_payment']);
        $this->assertSame([], $result['sessions'][0]['attributes']);
    }

    public function test_execute_refund_overpayment(): void
    {
        $booking = $this->createBooking();
        $detail = $this->createDetail($booking, ['price' => 100000]);
        $this->createPayment($booking, [
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'amount' => 125000,
        ]);

        app(BookingService::class)->executeRefundOverpayment($detail);

        $refund = $booking->payments()->where('payment_type', PaymentType::REFUND->value)->first();
        $this->assertNotNull($refund);
        $this->assertSame(25000, (int) $refund->amount);
        $this->assertSame(PaymentStatus::SUCCESS->value, $refund->status);
        $this->assertSame($detail->id, $refund->fk_booking_detail_id);
    }

    public function test_calculate_total_paid_for_detail(): void
    {
        $booking = $this->createBooking();
        $first = $this->createDetail($booking);
        $second = $this->createDetail($booking, ['play_date' => '2027-01-05']);
        $this->createPayment($booking, ['amount' => 100000]);
        $this->createPayment($booking, [
            'fk_booking_detail_id' => $first->id,
            'amount' => 40000,
        ]);
        $this->createPayment($booking, [
            'fk_booking_detail_id' => $first->id,
            'payment_type' => PaymentType::REFUND->value,
            'amount' => 10000,
        ]);

        $booking->load(['payments', 'details']);

        $this->assertSame(
            80000.0,
            app(BookingService::class)->calculateTotalPaidForDetail($booking, $first)
        );
        $this->assertSame(
            50000.0,
            app(BookingService::class)->calculateTotalPaidForDetail($booking, $second)
        );
    }

    public function test_apply_booking_filters(): void
    {
        $first = $this->createBooking('Alpha');
        $second = $this->createBooking('Beta');
        $query = Booking::query();
        $this->invokePrivate(app(BookingService::class), 'applyBookingFilters', $query, [$first->fk_field_id], [
            'field_id' => $first->fk_field_id,
            'search' => 'Alpha',
        ]);

        $this->assertSame([$first->id], $query->pluck('id')->all());
        $this->assertNotSame($first->fk_field_id, $second->fk_field_id);
    }

    public function test_extract_all_booking_details(): void
    {
        $booking = $this->createBooking();
        $included = $this->createDetail($booking, ['play_date' => '2027-01-04']);
        $this->createDetail($booking, ['play_date' => '2027-01-05']);
        $booking->load(['user', 'field', 'details', 'payments']);

        $result = $this->invokePrivate(
            app(BookingService::class),
            'extractAllBookingDetails',
            collect([$booking]),
            ['start_date' => '2027-01-04']
        );

        $this->assertCount(1, $result);
        $this->assertSame($included->id, $result[0]['id']);
        $this->assertSame('Lapangan Test', $result[0]['description']);
    }

    public function test_group_details_by_date(): void
    {
        $details = [
            ['play_date' => '2027-01-04', 'id' => 1],
            ['play_date' => '2027-01-04', 'id' => 2],
            ['play_date' => '2027-01-05', 'id' => 3],
        ];

        $grouped = $this->invokePrivate(app(BookingService::class), 'groupDetailsByDate', $details);

        $this->assertSame([1, 2], array_column($grouped['2027-01-04'], 'id'));
        $this->assertSame([3], array_column($grouped['2027-01-05'], 'id'));
    }

    public function test_limit_to_latest_dates(): void
    {
        Carbon::setTestNow('2027-01-04 08:00:00');
        try {
            $dates = [];
            foreach (range(3, 10) as $day) {
                $date = sprintf('2027-01-%02d', $day);
                $dates[$date] = [['play_date' => $date]];
            }
            $dates['2027-01-03'] = [['play_date' => '2027-01-03']];

            $result = $this->invokePrivate(app(BookingService::class), 'limitToLatestDates', $dates);

            $this->assertSame([
                '2027-01-04',
                '2027-01-05',
                '2027-01-06',
                '2027-01-07',
                '2027-01-08',
            ], array_keys($result));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_build_result_groups(): void
    {
        $groups = $this->invokePrivate(app(BookingService::class), 'buildResultGroups', [
            '2027-01-04' => [['id' => 12]],
        ]);

        $this->assertSame('Senin, 04 Januari 2027', $groups[0]['date_label']);
        $this->assertSame('2027-01-04', $groups[0]['play_date']);
        $this->assertSame([['id' => 12]], $groups[0]['bookings']);
    }

    public function test_calculate_global_paid_for_booking(): void
    {
        $payments = collect([
            $this->paymentForCalculation(PaymentType::DOWN_PAYMENT->value, 100000, null),
            $this->paymentForCalculation(PaymentType::RESCHEDULE_FEE->value, 20000, null),
            $this->paymentForCalculation(PaymentType::REFUND->value, 5000, null),
            $this->paymentForCalculation(PaymentType::FINAL_PAYMENT->value, 30000, 4),
        ]);

        $this->assertSame(
            120000.0,
            $this->invokePrivate(app(BookingService::class), 'calculateGlobalPaidForBooking', $payments)
        );
    }

    public function test_is_closed_or_cancelled_status(): void
    {
        $service = app(BookingService::class);

        $this->assertTrue($this->invokePrivate($service, 'isClosedOrCancelledStatus', 'FIELD CLOSURE'));
        $this->assertTrue($this->invokePrivate($service, 'isClosedOrCancelledStatus', BookingDetailStatus::CANCELLED->value));
        $this->assertFalse($this->invokePrivate($service, 'isClosedOrCancelledStatus', BookingDetailStatus::WAITING->value));
    }

    public function test_should_skip_detail(): void
    {
        $service = app(BookingService::class);

        $this->assertFalse($this->invokePrivate($service, 'shouldSkipDetail', '2027-01-05', '2027-01-04', '2027-01-06'));
        $this->assertTrue($this->invokePrivate($service, 'shouldSkipDetail', '2027-01-07', '2027-01-04', '2027-01-06'));
        $this->assertTrue($this->invokePrivate($service, 'shouldSkipDetail', '2027-01-05', '2027-01-04', null));
    }

    public function test_build_booking_item(): void
    {
        $service = app(BookingService::class);
        $booking = new Booking(['team_name' => 'Joglo FC']);
        $booking->setRelation('user', (object) ['name' => 'Tenant One']);
        $booking->setRelation('details', collect([(object) ['id' => 5]]));
        $booking->setRelation('payments', collect([
            $this->paymentForCalculation(PaymentType::DOWN_PAYMENT->value, 40000, 5),
            $this->paymentForCalculation(PaymentType::REFUND->value, 5000, 5),
        ]));
        $detail = new BookingDetail([
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);
        $detail->id = 5;

        $item = $this->invokePrivate($service, 'buildBookingItem', $booking, $detail, 'Lapangan A');

        $this->assertSame('Joglo FC', $item['title']);
        $this->assertSame('Tenant One', $item['tenant_name']);
        $this->assertSame('10:00 - 11:00', $item['time']);
        $this->assertSame(35000.0, $item['total_paid']);
        $this->assertSame(65000.0, $item['remaining_payment']);
        $this->assertSame(5000, $item['refund_amount']);
    }

    public function test_count_closed_affected_bookings(): void
    {
        $booking = $this->createBooking();
        $this->createDetail($booking, ['status' => BookingDetailStatus::FIELD_CLOSURE->value]);
        $otherBooking = $this->createBooking('Other Team');
        $this->createDetail($otherBooking, ['status' => BookingDetailStatus::FIELD_CLOSURE->value]);

        $count = $this->invokePrivate(
            app(BookingService::class),
            'countClosedAffectedBookings',
            [$booking->fk_field_id],
            null
        );

        $this->assertSame(1, $count);
    }

    public function test_validate_and_calculate_details(): void
    {
        $service = app(BookingService::class);
        $details = [[
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
        ], [
            'play_date' => '2027-01-04',
            'start_play_time' => '11:00:00',
            'end_play_time' => '12:00:00',
            'price' => 125000,
        ]];

        $this->assertSame(225000, $this->invokePrivate($service, 'validateAndCalculateDetails', 999, $details));
        $this->expectException(HttpException::class);
        $this->invokePrivate($service, 'validateAndCalculateDetails', 999, [[
            'play_date' => '2027-01-04',
            'start_play_time' => '11:00:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
        ]]);
    }

    public function test_has_field_conflict(): void
    {
        $booking = $this->createBooking();
        $this->createDetail($booking, [
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
        ]);
        $service = app(BookingService::class);
        $conflict = [
            'play_date' => '2027-01-04',
            'start_play_time' => '10:30:00',
            'end_play_time' => '11:30:00',
        ];
        $separate = [
            'play_date' => '2027-01-04',
            'start_play_time' => '11:00:00',
            'end_play_time' => '12:00:00',
        ];

        $this->assertTrue($this->invokePrivate($service, 'hasFieldConflict', $booking->fk_field_id, $conflict));
        $this->assertFalse($this->invokePrivate($service, 'hasFieldConflict', $booking->fk_field_id, $separate));
    }

    public function test_validate_field_price(): void
    {
        $booking = $this->createBooking();
        FieldPrice::create([
            'fk_field_id' => $booking->fk_field_id,
            'day_type' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'price' => 100000,
        ]);
        $service = app(BookingService::class);
        $detail = [
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
        ];

        $this->assertTrue($this->invokePrivate($service, 'validateFieldPrice', $booking->fk_field_id, $detail));
        $detail['price'] = 125000;
        $this->assertFalse($this->invokePrivate($service, 'validateFieldPrice', $booking->fk_field_id, $detail));
    }

    public function test_format_indonesian_date(): void
    {
        $formatted = $this->invokePrivate(app(BookingService::class), 'formatIndonesianDate', '2027-01-04');

        $this->assertSame('Senin, 04 Januari 2027', $formatted);
    }

    private function createUser(): User
    {
        static $number = 0;
        $number++;

        return User::create([
            'name' => 'Tenant '.$number,
            'email' => "tenant{$number}@example.test",
            'password' => 'password',
            'role' => 'tenant',
        ]);
    }

    private function createField(User $user): Field
    {
        static $number = 0;
        $number++;

        return Field::create([
            'name' => 'Lapangan Test',
            'category' => 'futsal',
            'fk_user_id' => $user->id,
        ]);
    }

    private function createBooking(string $teamName = 'Joglo FC'): Booking
    {
        $user = $this->createUser();
        $field = $this->createField($user);

        return Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => $teamName,
            'booking_date' => '2027-01-01',
        ]);
    }

    private function createDetail(Booking $booking, array $overrides = []): BookingDetail
    {
        return BookingDetail::create(array_merge([
            'fk_booking_id' => $booking->id,
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ], $overrides));
    }

    private function createPayment(Booking $booking, array $overrides = []): Payment
    {
        static $number = 0;
        $number++;

        return Payment::create(array_merge([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REF-'.$number,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2027-01-04 09:00:00',
        ], $overrides));
    }

    private function paymentForCalculation(string $type, int $amount, ?int $detailId): Payment
    {
        $payment = new Payment([
            'payment_type' => $type,
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        $payment->fk_booking_detail_id = $detailId;

        return $payment;
    }

    private function invokePrivate(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}

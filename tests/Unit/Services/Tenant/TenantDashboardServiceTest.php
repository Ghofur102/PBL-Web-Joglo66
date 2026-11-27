<?php

namespace Tests\Unit\Services\Tenant;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\User;
use App\Services\Tenant\TenantDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;
use Tests\Traits\UsesInMemoryDatabase;

#[RunTestsInSeparateProcesses]
class TenantDashboardServiceTest extends TestCase
{
    use UsesInMemoryDatabase;

    private const MAIN_FIELD = 'Main field';
    private const TEAM_NAME = 'Joglo team';
    private const NOW_20261025_120000 = '2026-10-25 12:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpInMemoryDatabase();

        $this->createUsersTable();
        Schema::connection('mysql_joglo66_app')->create('fields', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('fk_user_id')->nullable();
            $table->timestamps();
        });
        $this->createBookingsTable();
        $this->createBookingDetailsTable();
    }

    public function test_get_cached_fields(): void
    {
        Cache::flush();
        Field::create(['name' => self::MAIN_FIELD]);

        $service = new TenantDashboardService;
        $firstResult = $service->getCachedFields();
        Field::create(['name' => 'Second field']);
        $cachedResult = $service->getCachedFields();

        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $cachedResult);
        $this->assertSame(self::MAIN_FIELD, $cachedResult->first()->name);
    }

    public function test_get_nearest_bookings(): void
    {
        $detail = $this->createBookingDetail('2026-10-01', '2026-10-26', '14:00:00', '15:00:00');

        $nearest = (new TenantDashboardService)->getNearestBookings(
            $detail->booking->fk_field_id,
            Carbon::parse(self::NOW_20261025_120000)
        );

        $this->assertCount(1, $nearest);
        $this->assertSame(self::TEAM_NAME, $nearest->first()['team_name']);
        $this->assertSame('14:00 - 15:00', $nearest->first()['time']);
        $this->assertSame('Mulai dalam 1 hari lagi', $nearest->first()['status_text']);
    }

    public function test_get_user_bookings(): void
    {
        $detail = $this->createBookingDetail('2026-10-20', '2026-10-26', '08:30:00', '10:00:00');

        $bookings = (new TenantDashboardService)->getUserBookings(
            $detail->booking->fk_user_id,
            $detail->booking->fk_field_id
        );

        $this->assertCount(1, $bookings);
        $this->assertSame(self::TEAM_NAME, $bookings->first()['team_name']);
        $this->assertSame('08:30 - 10:00', $bookings->first()['time']);
        $this->assertSame('✓ Done', $bookings->first()['status_text']);
    }

    public function test_format_time_difference(): void
    {
        $method = new ReflectionMethod(TenantDashboardService::class, 'formatTimeDifference');
        $service = new TenantDashboardService;

        $this->assertSame(
            'Mulai dalam 2 jam lagi',
            $method->invoke($service, Carbon::parse('2026-10-25 10:00:00'), Carbon::parse(self::NOW_20261025_120000))
        );
        $this->assertSame(
            'Sedang bermain / Lewat',
            $method->invoke($service, Carbon::parse('2026-10-25 13:00:00'), Carbon::parse(self::NOW_20261025_120000))
        );
    }

    public function test_resolve_days_difference(): void
    {
        $method = new ReflectionMethod(TenantDashboardService::class, 'resolveDaysDifference');
        $service = new TenantDashboardService;

        $this->assertSame('Mulai dalam 6 hari lagi', $method->invoke($service, 6));
        $this->assertSame('Mulai dalam 1 minggu 2 hari lagi', $method->invoke($service, 9));
        $this->assertSame('Mulai dalam 1 bulan++ lagi', $method->invoke($service, 30));
        $this->assertSame('Mulai dalam 1 tahun lagi', $method->invoke($service, 365));
    }

    private function createBookingDetail(string $bookingDate, string $playDate, string $startTime, string $endTime): BookingDetail
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $field = Field::create(['name' => self::MAIN_FIELD]);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => self::TEAM_NAME,
            'booking_date' => $bookingDate,
        ]);

        return BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $playDate,
            'start_play_time' => $startTime,
            'end_play_time' => $endTime,
            'price' => 100000,
            'status' => 'active',
        ]);
    }
}

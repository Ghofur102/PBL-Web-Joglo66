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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class TenantDashboardServiceTest extends TestCase
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
            $table->string('name');
            $table->unsignedBigInteger('fk_user_id')->nullable();
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
    }

    public function test_get_cached_fields(): void
    {
        Cache::flush();
        Field::create(['name' => 'Main field']);

        $service = new TenantDashboardService;
        $firstResult = $service->getCachedFields();
        Field::create(['name' => 'Second field']);
        $cachedResult = $service->getCachedFields();

        $this->assertCount(1, $firstResult);
        $this->assertCount(1, $cachedResult);
        $this->assertSame('Main field', $cachedResult->first()->name);
    }

    public function test_get_nearest_bookings(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $field = Field::create(['name' => 'Main field']);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Joglo team',
            'booking_date' => '2026-10-01',
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-10-26',
            'start_play_time' => '14:00:00',
            'end_play_time' => '15:00:00',
            'price' => 100000,
            'status' => 'active',
        ]);

        $nearest = (new TenantDashboardService)->getNearestBookings($field->id, Carbon::parse('2026-10-25 12:00:00'));

        $this->assertCount(1, $nearest);
        $this->assertSame('Joglo team', $nearest->first()['team_name']);
        $this->assertSame('14:00 - 15:00', $nearest->first()['time']);
        $this->assertSame('Mulai dalam 1 hari lagi', $nearest->first()['status_text']);
    }

    public function test_get_user_bookings(): void
    {
        $user = User::create(['name' => 'Tenant', 'email' => 'tenant@example.com']);
        $field = Field::create(['name' => 'Main field']);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Joglo team',
            'booking_date' => '2026-10-20',
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2026-10-26',
            'start_play_time' => '08:30:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
            'status' => 'active',
        ]);

        $bookings = (new TenantDashboardService)->getUserBookings($user->id, $field->id);

        $this->assertCount(1, $bookings);
        $this->assertSame('Joglo team', $bookings->first()['team_name']);
        $this->assertSame('08:30 - 10:00', $bookings->first()['time']);
        $this->assertSame('✓ Done', $bookings->first()['status_text']);
    }

    public function test_format_time_difference(): void
    {
        $method = new ReflectionMethod(TenantDashboardService::class, 'formatTimeDifference');
        $service = new TenantDashboardService;

        $this->assertSame(
            'Mulai dalam 2 jam lagi',
            $method->invoke($service, Carbon::parse('2026-10-25 10:00:00'), Carbon::parse('2026-10-25 12:00:00'))
        );
        $this->assertSame(
            'Sedang bermain / Lewat',
            $method->invoke($service, Carbon::parse('2026-10-25 13:00:00'), Carbon::parse('2026-10-25 12:00:00'))
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
}

<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Attribute;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Services\Admin\DashboardService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class DashboardServiceTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'joglo66_dashboard_test_');
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

    public function test_get_dashboard_data(): void
    {
        $today = now()->toDateString();
        $user = User::create([
            'name' => 'Tenant',
            'email' => 'dashboard@example.test',
            'password' => 'password',
            'role' => 'tenant',
        ]);
        $field = Field::create([
            'name' => 'Lapangan Dashboard',
            'category' => 'futsal',
            'fk_user_id' => $user->id,
        ]);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Dashboard FC',
            'booking_date' => $today,
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $today,
            'start_play_time' => '09:00:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'DASH-DP',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 200000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'DASH-REFUND',
            'payment_type' => PaymentType::REFUND->value,
            'method' => 'cash',
            'amount' => 25000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'DASH-PENDING',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::PENDING->value,
            'paid_at' => now(),
        ]);
        $attribute = Attribute::create([
            'fk_field_id' => $field->id,
            'name' => 'Bola',
            'stock' => 5,
            'price_hour' => 10000,
            'type' => 'olahraga',
        ]);
        DB::table('booking_attributes')->insert([
            'fk_booking_id' => $booking->id,
            'fk_attribute_id' => $attribute->id,
            'quantity' => 1,
            'price' => 30000,
            'total' => 30000,
            'status' => 'dipinjam',
            'customer_name' => 'Dashboard FC',
            'duration_hours' => 1,
            'transaction_date' => $today,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(DashboardService::class)->getDashboardData([$field->id]);

        $this->assertSame(205000, $result['today_income']);
        $this->assertSame(205000, $result['monthly_income']);
        $this->assertSame(1, $result['today_schedules']);
        $this->assertSame([
            'id' => 1,
            'team_name' => 'Dashboard FC',
            'field_name' => 'Lapangan Dashboard',
            'play_date' => $today,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ], $result['schedules'][0]);
    }
}

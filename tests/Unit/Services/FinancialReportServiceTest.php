<?php

namespace Tests\Unit\Services;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialReportService;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class FinancialReportServiceTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'joglo66_financial_test_');
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

    public function test_get_monthly_report(): void
    {
        [$user, $field, $booking] = $this->createBooking();
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REPORT-DP',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2027-01-05 10:00:00',
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REPORT-REFUND',
            'payment_type' => PaymentType::REFUND->value,
            'method' => 'cash',
            'amount' => 20000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2027-01-06 10:00:00',
        ]);
        DB::table('booking_attributes')->insert([
            'fk_booking_id' => $booking->id,
            'fk_attribute_id' => $this->createAttribute($field->id),
            'quantity' => 1,
            'price' => 10000,
            'total' => 10000,
            'status' => 'dipinjam',
            'customer_name' => 'Joglo FC',
            'duration_hours' => 1,
            'transaction_date' => '2027-01-12',
            'created_at' => '2027-01-12 10:00:00',
            'updated_at' => '2027-01-12 10:00:00',
        ]);
        DB::table('expenses')->insert([
            'fk_field_id' => $field->id,
            'fk_user_id' => $user->id,
            'name' => 'Cleaning',
            'category' => 'operational',
            'quantity' => 3,
            'unit_price' => 5000,
            'expense_date' => '2027-01-20',
            'generate_at' => '2027-01-20',
            'created_at' => '2027-01-20 10:00:00',
            'updated_at' => '2027-01-20 10:00:00',
        ]);

        $report = app(FinancialReportService::class)->getMonthlyReport(1, 2027);

        $this->assertSame(100000, $report['gross_booking_income']);
        $this->assertSame(10000, $report['total_attribute_income']);
        $this->assertSame(110000, $report['gross_income']);
        $this->assertSame(20000, $report['total_refund']);
        $this->assertSame(90000, $report['net_income']);
        $this->assertSame(15000, $report['total_expense']);
        $this->assertSame(75000, $report['net_profit']);
        $this->assertSame(['expense', 'income', 'refund', 'income'], array_column($report['transactions'], 'type'));
        $this->assertSame($report['net_profit'], $report['summary']['net_profit']);
    }

    public function test_build_transaction_list(): void
    {
        [, $field, $booking] = $this->createBooking();
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'RENTAL-DP',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 80000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2027-01-05 10:00:00',
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'RENTAL-REFUND',
            'payment_type' => PaymentType::REFUND->value,
            'method' => 'cash',
            'amount' => 5000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2027-01-06 10:00:00',
        ]);
        DB::table('booking_attributes')->insert([
            'fk_booking_id' => $booking->id,
            'fk_attribute_id' => $this->createAttribute($field->id),
            'quantity' => 1,
            'price' => 20000,
            'total' => 20000,
            'status' => 'dipinjam',
            'customer_name' => 'Joglo FC',
            'duration_hours' => 1,
            'transaction_date' => '2027-01-12',
            'created_at' => '2027-01-12 10:00:00',
            'updated_at' => '2027-01-12 10:00:00',
        ]);
        DB::table('expenses')->insert([
            'fk_field_id' => $field->id,
            'fk_user_id' => $booking->fk_user_id,
            'name' => 'Cleaning',
            'category' => 'operational',
            'quantity' => 1,
            'unit_price' => 10000,
            'expense_date' => '2027-01-20',
            'generate_at' => '2027-01-20',
            'created_at' => '2027-01-20 10:00:00',
            'updated_at' => '2027-01-20 10:00:00',
        ]);

        $report = app(FinancialReportService::class)->getMonthlyReport(1, 2027, ['report_type' => 'rental']);

        $this->assertSame(80000, $report['gross_booking_income']);
        $this->assertSame(0, $report['total_attribute_income']);
        $this->assertSame(0, $report['total_expense']);
        $this->assertCount(1, $report['transactions']);
        $this->assertSame('income', $report['transactions'][0]['type']);
        $this->assertSame('PAY-'.Payment::where('reference_id', 'RENTAL-DP')->value('id'), $report['transactions'][0]['id']);
    }

    private function createBooking(): array
    {
        $user = User::create([
            'name' => 'Tenant',
            'email' => 'financial@example.test',
            'password' => 'password',
            'role' => 'tenant',
        ]);
        $field = Field::create([
            'name' => 'Lapangan Finance',
            'category' => 'futsal',
            'fk_user_id' => $user->id,
        ]);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Joglo FC',
            'booking_date' => '2027-01-01',
        ]);

        return [$user, $field, $booking];
    }

    private function createAttribute(int $fieldId): int
    {
        return DB::table('attributes')->insertGetId([
            'fk_field_id' => $fieldId,
            'name' => 'Bola',
            'stock' => 5,
            'price_hour' => 10000,
            'type' => 'olahraga',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

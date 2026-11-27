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
use Tests\Unit\Services\Concerns\UsesIsolatedSqliteDatabase;

#[RunTestsInSeparateProcesses]
class FinancialReportServiceTest extends TestCase
{
    use UsesIsolatedSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedSqliteDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedSqliteDatabase();
        parent::tearDown();
    }

    public function test_get_monthly_report(): void
    {
        [$user, $field, $booking] = $this->createBooking();
        $this->createSuccessfulPayment($booking, 'REPORT-DP', PaymentType::DOWN_PAYMENT->value, 100000, '2027-01-05');
        $this->createSuccessfulPayment($booking, 'REPORT-REFUND', PaymentType::REFUND->value, 20000, '2027-01-06');
        $this->createBookingAttribute($booking, $field, 10000, '2027-01-12');
        $this->createExpense($field, $user->id, 3, 5000);

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
        $this->createSuccessfulPayment($booking, 'RENTAL-DP', PaymentType::DOWN_PAYMENT->value, 80000, '2027-01-05');
        $this->createSuccessfulPayment($booking, 'RENTAL-REFUND', PaymentType::REFUND->value, 5000, '2027-01-06');
        $this->createBookingAttribute($booking, $field, 20000, '2027-01-12');
        $this->createExpense($field, $booking->fk_user_id, 1, 10000);

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

    private function createSuccessfulPayment(
        Booking $booking,
        string $referenceId,
        string $paymentType,
        int $amount,
        string $paidDate
    ): void {
        Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => $referenceId,
            'payment_type' => $paymentType,
            'method' => 'cash',
            'amount' => $amount,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => "{$paidDate} 10:00:00",
        ]);
    }

    private function createBookingAttribute(Booking $booking, Field $field, int $price, string $date): void
    {
        DB::table('booking_attributes')->insert([
            'fk_booking_id' => $booking->id,
            'fk_attribute_id' => $this->createAttribute($field->id),
            'quantity' => 1,
            'price' => $price,
            'total' => $price,
            'status' => 'dipinjam',
            'customer_name' => 'Joglo FC',
            'duration_hours' => 1,
            'transaction_date' => $date,
            'created_at' => "{$date} 10:00:00",
            'updated_at' => "{$date} 10:00:00",
        ]);
    }

    private function createExpense(Field $field, int $userId, int $quantity, int $unitPrice): void
    {
        DB::table('expenses')->insert([
            'fk_field_id' => $field->id,
            'fk_user_id' => $userId,
            'name' => 'Cleaning',
            'category' => 'operational',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'expense_date' => '2027-01-20',
            'generate_at' => '2027-01-20',
            'created_at' => '2027-01-20 10:00:00',
            'updated_at' => '2027-01-20 10:00:00',
        ]);
    }
}

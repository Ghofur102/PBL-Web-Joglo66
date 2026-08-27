<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Field;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Attribute;
use App\Models\Payment;
use App\Models\BookingAttribute;
use App\Models\Expense;
use App\Enums\PaymentType;
use App\Enums\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialReportFeatureTest extends TestCase
{
    use RefreshDatabase;

    // KITA HAPUS $connectionsToTransact karena berbahaya untuk database asli.

    protected function setUp(): void
    {
        parent::setUp();

        // TRIK AMAN: Paksa koneksi 'mysql_joglo66_app' untuk menggunakan
        // database testing (db_joglo66_testing) selama pengujian.
        // Ini menjamin database asli Anda (production) tidak akan pernah tersentuh!
        config(['database.connections.mysql_joglo66_app' => config('database.connections.testing')]);
    }

    public function test_owner_gets_accurate_financial_calculation(): void
    {
        $owner = User::factory()->create(['role' => 'owner']);
        $field = Field::factory()->create();

        $booking = Booking::factory()->create([
            'fk_field_id' => $field->id,
            'fk_user_id'  => $owner->id
        ]);

        $bookingDetail = BookingDetail::factory()->create([
            'fk_booking_id' => $booking->id
        ]);

        $attribute = Attribute::factory()->create(['fk_field_id' => $field->id]);

        $testMonth = 8;
        $testYear = 2026;
        $thisMonth = Carbon::create($testYear, $testMonth, 15);
        $lastMonth = Carbon::create($testYear, $testMonth - 1, 15);

        Payment::factory()->create([
            'fk_booking_id' => $booking->id,
            'amount'        => 100000,
            'payment_type'  => PaymentType::DOWN_PAYMENT->value,
            'status'        => PaymentStatus::SUCCESS->value,
            'paid_at'       => $thisMonth,
        ]);

        BookingAttribute::factory()->create([
            'fk_booking_detail_id' => $bookingDetail->id,
            'fk_attribute_id'      => $attribute->id,
            'total'                => 50000,
            'status'               => 'dikembalikan',
            'transaction_date'     => $thisMonth,
        ]);

        Expense::factory()->create([
            'fk_field_id'  => $field->id,
            'quantity'     => 1,
            'unit_price'   => 20000,
            'expense_date' => $thisMonth,
        ]);

        Payment::factory()->create([
            'fk_booking_id' => $booking->id,
            'amount'        => 500000,
            'payment_type'  => PaymentType::FINAL_PAYMENT->value,
            'status'        => PaymentStatus::SUCCESS->value,
            'paid_at'       => $lastMonth,
        ]);

        $response = $this->actingAs($owner)
            ->getJson("/api/laporan-bulanan?month={$testMonth}&year={$testYear}");

        $response->assertStatus(200);

        $response->assertJsonPath('data.summary.gross_booking_income', 100000)
                 ->assertJsonPath('data.summary.total_attribute_income', 50000)
                 ->assertJsonPath('data.summary.gross_income', 150000)
                 ->assertJsonPath('data.summary.total_expense', 20000)
                 ->assertJsonPath('data.summary.net_profit', 130000);
    }

    public function test_worker_gets_restricted_financial_data(): void
    {
        $worker = User::factory()->create(['role' => 'worker']);

        $fieldA = Field::factory()->create();
        $fieldB = Field::factory()->create();

        // FIX LOCK TIMEOUT: Gunakan koneksi yang sama dengan Model agar tidak bentrok
        DB::connection('mysql_joglo66_app')->table('field_workers')->insert([
            'fk_user_id'  => $worker->id,
            'fk_field_id' => $fieldA->id,
        ]);

        $bookingA = Booking::factory()->create([
            'fk_field_id' => $fieldA->id,
            'fk_user_id'  => $worker->id
        ]);

        $bookingB = Booking::factory()->create([
            'fk_field_id' => $fieldB->id,
            'fk_user_id'  => $worker->id
        ]);

        $thisMonth = Carbon::create(2026, 8, 15);

        Payment::factory()->create([
            'fk_booking_id' => $bookingA->id,
            'amount'        => 100000,
            'payment_type'  => PaymentType::FINAL_PAYMENT->value,
            'status'        => PaymentStatus::SUCCESS->value,
            'paid_at'       => $thisMonth,
        ]);

        Payment::factory()->create([
            'fk_booking_id' => $bookingB->id,
            'amount'        => 50000,
            'payment_type'  => PaymentType::FINAL_PAYMENT->value,
            'status'        => PaymentStatus::SUCCESS->value,
            'paid_at'       => $thisMonth,
        ]);

        $response = $this->actingAs($worker)
            ->getJson('/api/laporan-bulanan?month=8&year=2026');

        $response->assertStatus(200);
        $response->assertJsonPath('data.summary.gross_booking_income', 100000);
    }
}

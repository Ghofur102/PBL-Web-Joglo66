<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Services\Admin\PaymentService;
use App\Models\Booking;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Unit\Services\ServiceTestData;
use Tests\Unit\Services\ServiceTestCase;

class PaymentServiceTest extends ServiceTestCase
{
    public function test_process(): void
    {
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_10_00,
            ServiceTestData::TIME_11_00,
            ServiceTestData::BOOKING_WAITING,
        );

        $result = app(PaymentService::class)->process([
            'booking_id' => $booking->id,
            'booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => ServiceTestData::PAYMENT_METHOD,
            'amount' => ServiceTestData::ONE_THOUSAND, // padahal harga detail adalah 100.000, maka dp harusnya 50.000
            'reference_id' => ServiceTestData::PAYMENT_REFERENCE,
        ]);

        $expectedDp = (int) ((int) $detail->price * 0.5);
        $this->assertSame($expectedDp, $result['payment']->amount);
        $this->assertStringContainsString(ServiceTestData::MESSAGE_WARNING_SECURE, $result['warning']);
        $this->assertSame(PaymentStatus::SUCCESS->value, $result['payment']->status);
        $this->assertSame(ServiceTestData::BOOKING_ACTIVE, $detail->fresh()->status);
        $this->assertDatabaseHas('payments', [
            'reference_id' => ServiceTestData::PAYMENT_REFERENCE,
            'fk_booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'status' => PaymentStatus::SUCCESS->value,
        ], self::DATABASE_CONNECTION_APP);

        [$cancelledBooking, $cancelledDetail] = $this->createCancelledPaymentFixture($owner);
        try {
            app(PaymentService::class)->process([
                'booking_id' => $cancelledBooking->id,
                'booking_detail_id' => $cancelledDetail->id,
                'payment_type' => PaymentType::DOWN_PAYMENT->value,
                'method' => ServiceTestData::PAYMENT_METHOD,
                'amount' => ServiceTestData::ONE_THOUSAND,
            ]);
            $this->fail('Pembayaran booking yang seluruh detailnya dibatalkan seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('Cannot process payment for a fully cancelled booking.', $exception->getMessage());
        }
    }

    public function test_verify_booking_integrity(): void
    {
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $service = app(PaymentService::class);

        try {
            $this->invokeServiceMethod($service, 'verifyBookingIntegrity', [$booking->fresh('details')]);
            $this->fail('Booking tanpa detail seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }

        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_10_00,
            ServiceTestData::TIME_11_00,
            ServiceTestData::BOOKING_CANCELLED,
        );
        try {
            $this->invokeServiceMethod($service, 'verifyBookingIntegrity', [$booking->fresh('details')]);
            $this->fail('Booking seluruhnya dibatalkan seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }

        $detail->update(['status' => ServiceTestData::BOOKING_ACTIVE]);
        $this->invokeServiceMethod($service, 'verifyBookingIntegrity', [$booking->fresh('details')]);
        $this->assertSame(ServiceTestData::BOOKING_ACTIVE, $detail->fresh()->status);
    }

    public function test_verify_and_adjust_financials(): void
    {
        // **test data**
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_RINA, ServiceTestData::CUSTOMER_RINA);
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $booking = $this->createBooking($tenant, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $detail = $this->createBookingDetail(
            $booking,
            ServiceTestData::DATE_25_OCT_2026,
            ServiceTestData::TIME_10_00,
            ServiceTestData::TIME_11_00,
            ServiceTestData::BOOKING_WAITING,
        );
        $service = app(PaymentService::class);
        $booking = $booking->fresh('details');

        $result = $this->invokeServiceMethod($service, 'verifyAndAdjustFinancials', [$booking, [
            'booking_detail_id' => $detail->id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'amount' => ServiceTestData::ONE_THOUSAND,
        ]]);

        // **memastikan harga dp sesuai dengan 50% dari harga detail**
        $this->assertSame((int)($detail->price * 0.5), $result['adjusted_amount']);
        $this->assertStringContainsString(ServiceTestData::MESSAGE_WARNING_SECURE, $result['warning']);

        // **memastikan pembayaran untuk detail yang tidak dimiliki booking ditolak**
        try {
            $this->invokeServiceMethod($service, 'verifyAndAdjustFinancials', [$booking, [
                'booking_detail_id' => ServiceTestData::ONE_HUNDRED_THOUSAND,
                'payment_type' => PaymentType::DOWN_PAYMENT->value,
                'amount' => ServiceTestData::ONE_THOUSAND,
            ]]);
            $this->fail('Detail pembayaran yang tidak dimiliki booking seharusnya ditolak.');
        } catch (HttpException $exception) {
            $this->assertSame(400, $exception->getStatusCode());
        }
    }

}

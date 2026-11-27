<?php

namespace Tests\Unit\Services\Tenant\Payment;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\Payment;
use App\Models\User;
use App\Services\Tenant\Payment\TenantDuitkuService;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;
use UnexpectedValueException;

class TenantDuitkuServiceTest extends TestCase
{
    private const VALID_LEGACY_MD5_SIGNATURE = '1a376ba4c4601e991d3789b0364cbe74';

    protected TenantDuitkuService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantDuitkuService();

        config([
            'services.duitku.merchant_code' => 'TEST_MERCHANT_CODE',
            'services.duitku.merchant_key' => 'TEST_API_KEY',
        ]);
    }

    public function test_process_webhook_callback(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Duitku FC',
            'booking_date' => now()->toDateString(),
        ]);

        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->toDateString(),
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        $reference = 'REF-WEBHOOK-001';
        $orderId = "{$booking->id}-ORDER-1";
        $amount = 100000;

        $payment = Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => $reference,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'duitku',
            'amount' => $amount,
            'status' => PaymentStatus::PENDING->value,
        ]);

        $signatureStr = 'TEST_MERCHANT_CODE' . $amount . $orderId . 'TEST_API_KEY';
        $signature = hash('sha256', $signatureStr);

        $payload = [
            'merchantCode' => 'TEST_MERCHANT_CODE',
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'signature' => $signature,
            'reference' => $reference,
            'resultCode' => '00',
            'paymentCode' => 'VA_BCA',
        ];

        // 1. Success callback execution
        $this->service->processWebhookCallback($payload);

        $payment->refresh();
        $this->assertSame(PaymentStatus::SUCCESS->value, $payment->status);
        $this->assertSame('VA_BCA', $payment->method);
        $this->assertNotNull($payment->paid_at);

        $detail->refresh();
        $this->assertSame(BookingDetailStatus::ACTIVE->value, $detail->status);

        // 2. Exception when reference is not found in database
        $payloadNotFound = $payload;
        $payloadNotFound['reference'] = 'REF-NONEXISTENT';
        try {
            $this->service->processWebhookCallback($payloadNotFound);
            $this->fail('Expected UnexpectedValueException 404 was not thrown');
        } catch (UnexpectedValueException $e) {
            $this->assertSame(404, $e->getCode());
            $this->assertSame('Not Found', $e->getMessage());
        }
    }

    public function test_verify_duitku_signature(): void
    {
        $method = new ReflectionMethod(TenantDuitkuService::class, 'verifyDuitkuSignature');
        $method->setAccessible(true);

        $merchantCode = 'M1001';
        $apiKey = 'SECRETKEY123';
        $amount = 75000;
        $orderId = 'ORD-999';

        $payloadStr = $merchantCode . $amount . $orderId . $apiKey;

        // 1. Valid MD5 signature passes
        $validMd5Payload = [
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'signature' => self::VALID_LEGACY_MD5_SIGNATURE,
            'reference' => 'REF-001',
        ];
        $method->invoke($this->service, $validMd5Payload, $merchantCode, $apiKey);
        $this->assertTrue(true); // Reached without exception

        // 2. Valid SHA-256 signature passes
        $validShaPayload = [
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'signature' => hash('sha256', $payloadStr),
            'reference' => 'REF-002',
        ];
        $method->invoke($this->service, $validShaPayload, $merchantCode, $apiKey);
        $this->assertTrue(true); // Reached without exception

        // 3. Invalid signature throws InvalidArgumentException
        $invalidPayload = [
            'amount' => $amount,
            'merchantOrderId' => $orderId,
            'signature' => 'invalid_signature_hash',
            'reference' => 'REF-003',
        ];

        try {
            $method->invoke($this->service, $invalidPayload, $merchantCode, $apiKey);
            $this->fail('Expected InvalidArgumentException was not thrown for bad signature');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Bad Signature', $e->getMessage());
            $this->assertSame(400, $e->getCode());
        }
    }

    public function test_update_payment_and_booking_states(): void
    {
        $method = new ReflectionMethod(TenantDuitkuService::class, 'updatePaymentAndBookingStates');
        $method->setAccessible(true);

        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $field = Field::factory()->create();

        $booking = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $field->id,
            'team_name' => 'State Test',
            'booking_date' => now()->toDateString(),
        ]);

        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => now()->toDateString(),
            'start_play_time' => '14:00:00',
            'end_play_time' => '15:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        // 1. Successful payment result ('00')
        $paymentSuccess = Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REF-STATE-1',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'duitku',
            'amount' => 100000,
            'status' => PaymentStatus::PENDING->value,
        ]);

        $payloadSuccess = [
            'merchantOrderId' => "{$booking->id}-ORDER-SUCCESS",
            'resultCode' => '00',
            'paymentCode' => 'QRIS',
        ];

        $method->invoke($this->service, $paymentSuccess, $payloadSuccess);

        $paymentSuccess->refresh();
        $this->assertSame(PaymentStatus::SUCCESS->value, $paymentSuccess->status);
        $this->assertSame('QRIS', $paymentSuccess->method);
        $this->assertNotNull($paymentSuccess->paid_at);

        $detail->refresh();
        $this->assertSame(BookingDetailStatus::ACTIVE->value, $detail->status);

        // 2. Failed payment result ('01')
        $paymentFailed = Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REF-STATE-2',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'duitku',
            'amount' => 100000,
            'status' => PaymentStatus::PENDING->value,
        ]);

        $payloadFailed = [
            'merchantOrderId' => "{$booking->id}-ORDER-FAILED",
            'resultCode' => '01',
            'paymentCode' => 'QRIS',
        ];

        $method->invoke($this->service, $paymentFailed, $payloadFailed);

        $paymentFailed->refresh();
        $this->assertSame(PaymentStatus::FAILED->value, $paymentFailed->status);

        $detail->refresh();
        $this->assertSame(BookingDetailStatus::CANCELLED->value, $detail->status);

        // 3. Already non-pending payment is ignored
        $paymentAlreadySuccess = Payment::create([
            'fk_booking_id' => $booking->id,
            'reference_id' => 'REF-STATE-3',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'QRIS',
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $payloadIgnored = [
            'merchantOrderId' => "{$booking->id}-ORDER-IGNORED",
            'resultCode' => '01', // Even if failed result received
            'paymentCode' => 'QRIS',
        ];

        $method->invoke($this->service, $paymentAlreadySuccess, $payloadIgnored);

        $paymentAlreadySuccess->refresh();
        $this->assertSame(PaymentStatus::SUCCESS->value, $paymentAlreadySuccess->status); // Remains SUCCESS
    }
}

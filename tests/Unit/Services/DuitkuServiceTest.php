<?php

namespace Tests\Unit\Services;

use App\Services\DuitkuService;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionProperty;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class DuitkuServiceTest extends TestCase
{
    public function test_constructor(): void
    {
        config([
            'services.duitku.merchant_code' => '  merchant-123  ',
            'services.duitku.merchant_key' => '  secret-key  ',
            'services.duitku.sandbox_mode' => 'false',
        ]);

        $service = new DuitkuService;

        $this->assertSame('merchant-123', (new ReflectionProperty($service, 'merchantCode'))->getValue($service));
        $this->assertSame('secret-key', (new ReflectionProperty($service, 'merchantKey'))->getValue($service));
        $this->assertFalse((new ReflectionProperty($service, 'sandboxMode'))->getValue($service));
    }

    public function test_create_invoice(): void
    {
        config([
            'services.duitku.merchant_code' => 'D123',
            'services.duitku.merchant_key' => 'key-abc',
            'services.duitku.sandbox_mode' => true,
        ]);
        Http::fake([
            'https://api-sandbox.duitku.com/api/merchant/createInvoice' => Http::response([
                'statusCode' => '00',
                'statusMessage' => 'Success',
                'paymentUrl' => 'https://example.test/pay/123',
            ], 200),
        ]);

        $booking = (object) [
            'id' => 123,
            'team_name' => 'Joglo FC',
            'customer_phone' => '+62 (812) 3456-7890',
            'customer_email' => 'team@example.test',
        ];
        $result = (new DuitkuService)->createInvoice($booking, 100000.6);

        $this->assertSame('00', $result->statusCode);
        $this->assertSame('https://example.test/pay/123', $result->paymentUrl);
        Http::assertSent(function ($request) use ($booking): bool {
            $payload = $request->data();
            $expectedOrderId = $payload['merchantOrderId'];
            $expectedSignature = md5('D123'.$expectedOrderId.'100001key-abc');

            return $request->url() === 'https://api-sandbox.duitku.com/api/merchant/createInvoice'
                && $payload['merchantCode'] === 'D123'
                && $payload['paymentAmount'] === 100001
                && $payload['productDetails'] === 'Penyewaan Lapangan - Joglo FC'
                && $payload['email'] === $booking->customer_email
                && $payload['phoneNumber'] === '6281234567890'
                && $payload['returnUrl'] === route('tenant.booking.success', ['booking_id' => $booking->id])
                && $payload['signature'] === $expectedSignature;
        });
    }
}

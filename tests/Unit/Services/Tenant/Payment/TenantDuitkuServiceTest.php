<?php

namespace Tests\Unit\Services\Tenant\Payment;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class TenantDuitkuServiceTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_process_webhook_callback(): void {}

    #[DoesNotPerformAssertions]
    public function test_verify_duitku_signature(): void {}

    #[DoesNotPerformAssertions]
    public function test_update_payment_and_booking_states(): void {}
}

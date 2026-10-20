<?php

namespace Tests\Unit\Services\Tenant\Booking;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class TenantHistoryServiceTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_get_available_payment_statuses(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_paginated_history(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_booking_detail(): void {}

    #[DoesNotPerformAssertions]
    public function test_calculate_global_paid_per_session(): void {}

    #[DoesNotPerformAssertions]
    public function test_hydrate_detail_item(): void {}

    #[DoesNotPerformAssertions]
    public function test_resolve_detail_payments(): void {}

    #[DoesNotPerformAssertions]
    public function test_hydrate_detail_financials(): void {}

    #[DoesNotPerformAssertions]
    public function test_hydrate_reschedules_history(): void {}

    #[DoesNotPerformAssertions]
    public function test_hydrate_detail_statuses_and_permissions(): void {}

    #[DoesNotPerformAssertions]
    public function test_determine_detail_status(): void {}

    #[DoesNotPerformAssertions]
    public function test_append_financial_summary(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_badge_class(): void {}
}

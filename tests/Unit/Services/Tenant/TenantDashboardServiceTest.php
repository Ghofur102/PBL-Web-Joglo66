<?php

namespace Tests\Unit\Services\Tenant;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class TenantDashboardServiceTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_get_cached_fields(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_nearest_bookings(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_user_bookings(): void {}

    #[DoesNotPerformAssertions]
    public function test_format_time_difference(): void {}

    #[DoesNotPerformAssertions]
    public function test_resolve_days_difference(): void {}
}

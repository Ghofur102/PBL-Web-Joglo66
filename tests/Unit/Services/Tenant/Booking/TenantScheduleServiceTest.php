<?php

namespace Tests\Unit\Services\Tenant\Booking;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class TenantScheduleServiceTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_get_available_hourly_slots(): void {}

    #[DoesNotPerformAssertions]
    public function test_calculate_single_slot_metrics(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_field_closures_from_database(): void {}

    #[DoesNotPerformAssertions]
    public function test_is_slot_overlapping_with_closures(): void {}
}

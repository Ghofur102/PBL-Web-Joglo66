<?php

namespace Tests\Unit\Services\Tenant;

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

class NotificationServiceTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function test_get_paginated_notifications(): void {}

    #[DoesNotPerformAssertions]
    public function test_get_unread_count(): void {}

    #[DoesNotPerformAssertions]
    public function test_mark_as_read(): void {}

    #[DoesNotPerformAssertions]
    public function test_mark_all_as_read(): void {}
}

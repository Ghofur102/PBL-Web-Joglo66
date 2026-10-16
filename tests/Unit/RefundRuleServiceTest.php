<?php

namespace Tests\Unit;

use App\Services\RefundRuleService;
use PHPUnit\Framework\TestCase;

class RefundRuleServiceTest extends TestCase
{
    public function test_default_rules_cover_cancel_and_reschedule(): void
    {
        $rules = RefundRuleService::defaultRules();

        $this->assertSame(['cancel', 'reschedule'], array_column($rules, 'rule_type'));
        $this->assertSame([72, 72], array_column($rules, 'min_hours_before'));
        $this->assertSame([100, 100], array_column($rules, 'refund_percent'));
    }
}

<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminRescheduleBookingTest extends ServiceTestCase
{
    public function test_admin_can_reschedule_booking_successfully()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/reschedule-booking/{$detail->id}", [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_time' => ServiceTestData::TIME_10_30_SHORT,
            'new_end_time' => ServiceTestData::TIME_11_30_SHORT,
            'reason' => ServiceTestData::RESCHEDULE_REASON,
            'new_price' => ServiceTestData::ONE_HUNDRED_THOUSAND,
            'financial_action' => 'Settle_Later',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_reschedule_booking_fails_unauthorized()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $otherAdmin = $this->createUser(ServiceTestData::ROLE_WORKER, 'other_admin_resched', 'Other Admin');
        Sanctum::actingAs($otherAdmin);

        $response = $this->postJson("/api/admin/reschedule-booking/{$detail->id}", [
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_time' => ServiceTestData::TIME_10_30_SHORT,
            'new_end_time' => ServiceTestData::TIME_11_30_SHORT,
            'reason' => ServiceTestData::RESCHEDULE_REASON,
            'new_price' => ServiceTestData::ONE_HUNDRED_THOUSAND,
            'financial_action' => 'Settle_Later',
        ]);

        $response->assertStatus(403);
    }
}

<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;

class TenantBookingConfirmationRescheduleTest extends ServiceTestCase
{
    public function test_tenant_can_submit_confirmation_reschedule()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.confirmation.reschedule'), [
            'detail_booking_id' => $detail->id,
            'new_play_date' => ServiceTestData::DATE_26_OCT_2026,
            'new_start_play_time' => ServiceTestData::TIME_10_30_SHORT,
            'new_end_play_time' => ServiceTestData::TIME_11_30_SHORT,
            'reason' => ServiceTestData::RESCHEDULE_REASON,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.reschedule.review');
        $response->assertViewHas('detail');
    }

    public function test_tenant_confirmation_reschedule_validation_fails_on_missing_fields()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.confirmation.reschedule'), [
            'detail_booking_id' => $detail->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['new_play_date', 'new_start_play_time']);
    }
}

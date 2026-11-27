<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;

class TenantBookingConfirmationCancelledTest extends ServiceTestCase
{
    public function test_tenant_can_submit_cancel_confirmation()
    {
        [$detail, $tenant] = $this->createCancellationFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.confirmation.cancelled'), [
            'detail_booking_id' => $detail->id,
            'reason' => ServiceTestData::CANCEL_REASON,
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.cancel.review');
        $response->assertViewHas('detail');
    }

    public function test_tenant_cancel_confirmation_validation_fails_without_reason()
    {
        [$detail, $tenant] = $this->createCancellationFixture();

        $response = $this->actingAs($tenant)->post(route('tenant.booking.confirmation.cancelled'), [
            'detail_booking_id' => $detail->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['reason']);
    }
}

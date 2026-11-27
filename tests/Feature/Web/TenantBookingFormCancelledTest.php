<?php

namespace Tests\Feature\Web;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;

class TenantBookingFormCancelledTest extends ServiceTestCase
{
    public function test_tenant_can_view_cancel_form_successfully()
    {
        [$detail, $tenant] = $this->createCancellationFixture();

        $response = $this->actingAs($tenant)->get(route('tenant.booking.form.cancelled', $detail->id));

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.cancel.index');
        $response->assertViewHas('detail');
    }

    public function test_tenant_cannot_view_cancel_form_for_other_users_booking()
    {
        [$detail] = $this->createCancellationFixture();
        $otherTenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'other_tenant_cancel', 'Other Tenant');

        $response = $this->actingAs($otherTenant)->get(route('tenant.booking.form.cancelled', $detail->id));

        $response->assertStatus(403);
    }
}

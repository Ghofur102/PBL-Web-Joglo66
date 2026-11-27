<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminRefundOverpaymentTest extends ServiceTestCase
{
    public function test_admin_can_refund_overpayment_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_refund_op', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_refund_op', 'Tenant');
        $detail = $this->createDetail($owner, $tenant);
        $this->createSuccessfulPayment($detail, 150000);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/refund-overpayment/{$detail->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_admin_refund_overpayment_fails_when_not_found()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_refund_op2', 'Owner');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/refund-overpayment/99999");

        $response->assertStatus(404);
    }
}

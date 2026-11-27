<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminCancelRejectTest extends ServiceTestCase
{
    public function test_admin_can_reject_cancel_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_rej', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_rej', 'Tenant');
        $detail = $this->createPendingDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/{$detail->id}/reject", [
            'rejection_reason' => 'Pembatalan ditolak sesuai ketentuan',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_reject_cancel_validation_fails_without_reason()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_cancel_rej2', 'Owner');
        $tenant = $this->createUser(ServiceTestData::ROLE_TENANT, 'tenant_cancel_rej2', 'Tenant');
        $detail = $this->createPendingDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/cancel-booking/{$detail->id}/reject");

        $response->assertStatus(422);
    }
}

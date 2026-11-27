<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminRescheduleRejectTest extends ServiceTestCase
{
    public function test_admin_can_reject_reschedule_successfully()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $pendingDetail = $this->createPendingDetail($owner, $tenant);
        $this->createPendingReschedule($pendingDetail);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/reschedule-booking/{$pendingDetail->id}/reject", [
            'rejection_reason' => 'Jadwal tidak tersedia',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'success']);
    }

    public function test_admin_reject_reschedule_validation_fails_without_reason()
    {
        [$detail, $tenant, $owner] = $this->createRescheduleFixture();
        $pendingDetail = $this->createPendingDetail($owner, $tenant);
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/admin/reschedule-booking/{$pendingDetail->id}/reject");

        $response->assertStatus(422);
    }
}

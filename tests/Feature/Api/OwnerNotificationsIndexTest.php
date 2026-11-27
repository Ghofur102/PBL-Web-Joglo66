<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class OwnerNotificationsIndexTest extends ServiceTestCase
{
    public function test_owner_can_get_notifications_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_idx2', 'Owner');
        $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/owner/notifications");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}

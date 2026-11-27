<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class OwnerNotificationsUnreadCountTest extends ServiceTestCase
{
    public function test_owner_can_get_unread_count_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_cnt2', 'Owner');
        $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/owner/notifications/unread-count");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}

<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class OwnerNotificationsReadAllTest extends ServiceTestCase
{
    public function test_owner_can_mark_all_notifications_as_read_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_notif_all2', 'Owner');
        $this->createNotification($owner, 'App\Notifications\TestNotification');
        Sanctum::actingAs($owner);

        $response = $this->postJson("/api/owner/notifications/read-all");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}

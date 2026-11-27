<?php

namespace Tests\Feature\Api;

use Tests\ServiceTestCase;
use Tests\ServiceTestData;
use Laravel\Sanctum\Sanctum;

class AdminListCloseBookingTest extends ServiceTestCase
{
    public function test_admin_can_get_list_close_booking_successfully()
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, 'owner_close_list', 'Owner');
        Sanctum::actingAs($owner);

        $response = $this->getJson("/api/admin/list-close-booking");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_unauthenticated_user_cannot_get_list_close_booking()
    {
        $response = $this->getJson("/api/admin/list-close-booking");

        $response->assertStatus(401);
    }
}

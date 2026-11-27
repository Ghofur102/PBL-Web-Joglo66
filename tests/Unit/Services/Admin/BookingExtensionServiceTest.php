<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\User;
use App\Services\Admin\BookingExtensionService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;
use Tests\Unit\Services\Concerns\UsesIsolatedSqliteDatabase;

#[RunTestsInSeparateProcesses]
class BookingExtensionServiceTest extends TestCase
{
    use UsesIsolatedSqliteDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpIsolatedSqliteDatabase();
    }

    protected function tearDown(): void
    {
        $this->tearDownIsolatedSqliteDatabase();
        parent::tearDown();
    }

    public function test_extend_session_time(): void
    {
        $user = User::create([
            'name' => 'Tenant',
            'email' => 'tenant-extension@example.test',
            'password' => 'password',
            'role' => 'tenant',
        ]);
        $field = Field::create([
            'name' => 'Lapangan A',
            'category' => 'futsal',
            'fk_user_id' => $user->id,
        ]);
        $booking = Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => 'Joglo FC',
            'booking_date' => '2027-01-01',
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => 'waiting',
        ]);
        FieldPrice::create([
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'price' => 120000,
        ]);

        $cacheKey = "field_slots_{$field->id}_2027-01-04";
        Cache::put($cacheKey, ['stale'], 60);

        $result = app(BookingExtensionService::class)->extendSessionTime($detail->id, 30);

        $this->assertSame([
            'detail_id' => $detail->id,
            'old_end_time' => '11:00',
            'new_end_time' => '11:30',
            'extend_minutes' => 30,
            'additional_price' => 60000,
            'total_session_price' => 160000,
        ], $result);
        $this->assertSame('11:30:00', $detail->fresh()->end_play_time);
        $this->assertSame(160000, (int) $detail->fresh()->price);
        $this->assertNull(Cache::get($cacheKey));
    }
}

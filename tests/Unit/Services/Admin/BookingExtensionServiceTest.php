<?php

namespace Tests\Unit\Services\Admin;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\User;
use App\Services\Admin\BookingExtensionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class BookingExtensionServiceTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'joglo66_extension_test_');
        if ($databasePath === false) {
            throw new \RuntimeException('Unable to create the isolated test database.');
        }
        $this->testDatabasePath = $databasePath;

        $sqlite = config('database.connections.sqlite');
        $sqlite['database'] = $this->testDatabasePath;
        $sqlite['url'] = null;
        config([
            'database.default' => 'mysql_joglo66_app',
            'database.connections.sqlite' => $sqlite,
            'database.connections.mysql_joglo66_app' => $sqlite,
        ]);
        DB::purge('sqlite');
        DB::purge('mysql_joglo66_app');

        $this->artisan('migrate:fresh', ['--database' => 'sqlite', '--force' => true])
            ->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        DB::disconnect('mysql_joglo66_app');
        if (isset($this->testDatabasePath) && file_exists($this->testDatabasePath)) {
            unlink($this->testDatabasePath);
        }

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

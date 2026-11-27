<?php

namespace Tests\Unit\Services\Tenant\Booking;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\User;
use App\Services\Tenant\Booking\TenantScheduleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class TenantScheduleServiceTest extends TestCase
{
    private const TEST_MONDAY_DATE = '2026-11-02';

    protected TenantScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TenantScheduleService();
        Cache::flush();
    }

    public function test_get_available_hourly_slots(): void
    {
        $field = Field::factory()->create();
        $date = self::TEST_MONDAY_DATE; // Monday
        $dayName = 'monday';

        // Set field price covering 06:00 to 23:00
        FieldPrice::create([
            'fk_field_id' => $field->id,
            'day_type' => $dayName,
            'start_time' => '06:00:00',
            'end_time' => '23:00:00',
            'price' => 100000,
        ]);

        $slots = $this->service->getAvailableHourlySlots($field->id, $date);

        $this->assertIsArray($slots);
        $this->assertCount(17, $slots); // 06 to 23 = 17 hourly slots
        $firstSlot = $slots[0];
        $this->assertSame('06:00', $firstSlot['jam']);
        $this->assertSame('07:00', $firstSlot['jam_akhir']);
        $this->assertSame('kosong', $firstSlot['status']);
        $this->assertSame(100000, $firstSlot['harga']);

        // Verify caching
        $cacheKey = "tenant_slots_field_{$field->id}_{$date}";
        $this->assertTrue(Cache::has($cacheKey));
    }

    public function test_calculate_single_slot_metrics(): void
    {
        $method = new ReflectionMethod(TenantScheduleService::class, 'calculateSingleSlotMetrics');
        $method->setAccessible(true);

        $fieldPrice = new FieldPrice([
            'start_time' => '06:00:00',
            'end_time' => '23:00:00',
            'price' => 120000,
        ]);
        $fieldPrices = collect([$fieldPrice]);

        // 1. Available (kosong) slot
        $bookedSlotsEmpty = collect();
        $slotKosong = $method->invoke($this->service, 10, self::TEST_MONDAY_DATE, $fieldPrices, $bookedSlotsEmpty, []);
        $this->assertNotNull($slotKosong);
        $this->assertSame('10:00', $slotKosong['jam']);
        $this->assertSame('11:00', $slotKosong['jam_akhir']);
        $this->assertSame('kosong', $slotKosong['status']);
        $this->assertSame(120000, $slotKosong['harga']);

        // 2. Booked (terisi) slot
        $bookedDetail = new BookingDetail([
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
        ]);
        $slotTerisi = $method->invoke($this->service, 10, self::TEST_MONDAY_DATE, $fieldPrices, collect([$bookedDetail]), []);
        $this->assertSame('terisi', $slotTerisi['status']);

        // 3. Closed (tutup) slot
        $closure = (object) [
            'field_closure_start_time' => '2026-11-02 09:30:00',
            'field_closure_end_time' => '2026-11-02 11:30:00',
        ];
        $slotTutup = $method->invoke($this->service, 10, self::TEST_MONDAY_DATE, $fieldPrices, $bookedSlotsEmpty, [$closure]);
        $this->assertSame('tutup', $slotTutup['status']);

        // 4. No matching price rule returns null
        $noPriceSlot = $method->invoke($this->service, 5, self::TEST_MONDAY_DATE, collect(), $bookedSlotsEmpty, []);
        $this->assertNull($noPriceSlot);
    }

    public function test_get_field_closures_from_database(): void
    {
        $method = new ReflectionMethod(TenantScheduleService::class, 'getFieldClosuresFromDatabase');
        $method->setAccessible(true);

        $user = User::factory()->create(['role' => UserRole::WORKER->value]);
        $field = Field::factory()->create();
        $date = self::TEST_MONDAY_DATE;

        DB::connection('mysql_joglo66_app')->table('field_closures')->insert([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'field_closure_start_time' => "{$date} 08:00:00",
            'field_closure_end_time' => "{$date} 12:00:00",
            'reason' => 'Perbaikan rumput',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $closures = $method->invoke($this->service, $date, $field->id);

        $this->assertIsArray($closures);
        $this->assertCount(1, $closures);
        $this->assertSame('Perbaikan rumput', $closures[0]->reason);
    }

    public function test_is_slot_overlapping_with_closures(): void
    {
        $method = new ReflectionMethod(TenantScheduleService::class, 'isSlotOverlappingWithClosures');
        $method->setAccessible(true);

        $closure = (object) [
            'field_closure_start_time' => '2026-11-02 10:00:00',
            'field_closure_end_time' => '2026-11-02 14:00:00',
        ];
        $closures = [$closure];

        // 1. Overlapping slot (11:00 - 12:00)
        $isOverlap = $method->invoke($this->service, '2026-11-02 11:00:00', '2026-11-02 12:00:00', $closures);
        $this->assertTrue($isOverlap);

        // 2. Non-overlapping slot before closure (08:00 - 09:00)
        $noOverlapBefore = $method->invoke($this->service, '2026-11-02 08:00:00', '2026-11-02 09:00:00', $closures);
        $this->assertFalse($noOverlapBefore);

        // 3. Non-overlapping slot after closure (15:00 - 16:00)
        $noOverlapAfter = $method->invoke($this->service, '2026-11-02 15:00:00', '2026-11-02 16:00:00', $closures);
        $this->assertFalse($noOverlapAfter);
    }
}

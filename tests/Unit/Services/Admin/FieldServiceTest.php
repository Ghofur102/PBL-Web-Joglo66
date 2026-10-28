<?php

namespace Tests\Unit\Services\Admin;

use App\Enums\BookingDetailStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldClosure;
use App\Models\FieldPrice;
use App\Models\FieldRefundRule;
use App\Models\User;
use App\Services\Admin\FieldService;
use App\Services\RefundRuleService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ReflectionMethod;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
class FieldServiceTest extends TestCase
{
    private string $testDatabasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $databasePath = tempnam(sys_get_temp_dir(), 'joglo66_field_test_');
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

    public function test_constructor(): void
    {
        $refundRuleService = new RefundRuleService;
        $service = new FieldService($refundRuleService);
        $dependency = (new \ReflectionProperty($service, 'refundRuleService'))->getValue($service);

        $this->assertSame($refundRuleService, $dependency);
    }

    public function test_get_field_list(): void
    {
        $field = $this->createField('Futsal Central');
        $other = $this->createField('Soccer Park', 'mini soccer');
        FieldRefundRule::create([
            'fk_field_id' => $field->id,
            'rule_type' => 'cancel',
            'min_hours_before' => 72,
            'refund_percent' => 100,
            'is_active' => true,
        ]);

        $result = app(FieldService::class)->getFieldList([$field->id, $other->id], 'Central', 10);

        $this->assertCount(1, $result);
        $this->assertSame($field->id, $result[0]['id']);
        $this->assertSame('Futsal Central', $result[0]['name']);
        $this->assertSame('N/A', $result[0]['location']);
        $this->assertSame('active', $result[0]['status']);
        $this->assertSame(100, $result[0]['refund_rules'][0]['refund_percent']);
    }

    public function test_update_field_and_pricing(): void
    {
        $field = $this->createField('Old Name');
        FieldPrice::create([
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'price' => 80000,
        ]);
        $request = Request::create('/', 'PUT', [
            'pricing_rules' => [
                [
                    'day_type' => 'MONDAY',
                    'start_time' => '09:00:00',
                    'end_time' => '10:00:00',
                    'price' => 100000,
                ],
            ],
        ]);
        $refundRules = [
            [
                'rule_type' => 'cancel',
                'min_hours_before' => 72,
                'refund_percent' => 100,
            ],
            [
                'rule_type' => 'reschedule',
                'min_hours_before' => 24,
                'refund_percent' => 50,
            ],
        ];

        $updated = app(FieldService::class)->updateFieldAndPricing($field, [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'refund_rules' => $refundRules,
        ], $request);

        $this->assertSame('Updated Name', $updated->name);
        $this->assertSame('Updated description', $updated->description);
        $this->assertSame(1, $updated->fieldPrices->count());
        $this->assertSame('monday', $updated->fieldPrices[0]->day_type);
        $this->assertSame(100000, (int) $updated->fieldPrices[0]->price);
        $this->assertSame(2, $updated->refundRules->count());
        $this->assertSame(100000, (int) FieldPrice::where('fk_field_id', $field->id)->value('price'));
    }

    public function test_check_slot_availability(): void
    {
        $field = $this->createField();
        FieldPrice::create([
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'price' => 100000,
        ]);
        $booking = $this->createBooking($field);
        $this->createDetail($booking, ['start_play_time' => '09:00:00', 'end_play_time' => '10:00:00']);
        FieldClosure::create([
            'fk_user_id' => $field->fk_user_id,
            'fk_field_id' => $field->id,
            'field_closure_start_time' => '2027-01-04 10:00:00',
            'field_closure_end_time' => '2027-01-04 11:00:00',
            'reason' => 'Maintenance',
        ]);

        $result = app(FieldService::class)->checkSlotAvailability($field->id, '2027-01-04');

        $this->assertSame(3, $result['total_available_slots']);
        $this->assertSame([
            ['start' => '08:00:00', 'end' => '09:00:00', 'price' => 100000, 'is_available' => true, 'is_closed' => false],
            ['start' => '09:00:00', 'end' => '10:00:00', 'price' => 100000, 'is_available' => false, 'is_closed' => false],
            ['start' => '10:00:00', 'end' => '11:00:00', 'price' => 100000, 'is_available' => false, 'is_closed' => true],
        ], $result['available_slots']);
    }

    public function test_execute_field_closure(): void
    {
        $field = $this->createField();
        $booking = $this->createBooking($field);
        $detail = $this->createDetail($booking, [
            'start_play_time' => '09:00:00',
            'end_play_time' => '11:00:00',
        ]);

        $result = app(FieldService::class)->executeFieldClosure([
            'fk_field_id' => $field->id,
            'field_closure_start_time' => '2027-01-04 10:00:00',
            'field_closure_end_time' => '2027-01-04 10:30:00',
            'reason' => 'Maintenance',
        ], $field->fk_user_id);

        $this->assertSame($field->id, $result['closure']->fk_field_id);
        $this->assertSame([$detail->id], $result['affected_bookings']->modelKeys());
        $this->assertSame(BookingDetailStatus::FIELD_CLOSURE->value, $detail->fresh()->status);
    }

    public function test_get_field_closures_for_date(): void
    {
        $field = $this->createField();
        FieldClosure::create([
            'fk_user_id' => $field->fk_user_id,
            'fk_field_id' => $field->id,
            'field_closure_start_time' => '2027-01-04 09:00:00',
            'field_closure_end_time' => '2027-01-04 10:00:00',
            'reason' => 'One',
        ]);
        FieldClosure::create([
            'fk_user_id' => $field->fk_user_id,
            'fk_field_id' => $field->id,
            'field_closure_start_time' => '2027-01-05 09:00:00',
            'field_closure_end_time' => '2027-01-05 10:00:00',
            'reason' => 'Two',
        ]);

        $closures = $this->invokePrivate(
            app(FieldService::class),
            'getFieldClosuresForDate',
            $field->id,
            '2027-01-04'
        );

        $this->assertCount(1, $closures);
        $this->assertSame('2027-01-04 09:00:00', $closures[0]->field_closure_start_time);
    }

    public function test_is_slot_closed_by_closure(): void
    {
        $closures = collect([
            (object) [
                'field_closure_start_time' => '2027-01-04 10:30:00',
                'field_closure_end_time' => '2027-01-04 11:30:00',
            ],
        ]);
        $service = app(FieldService::class);

        $this->assertTrue($this->invokePrivate(
            $service,
            'isSlotClosedByClosure',
            '2027-01-04 11:00:00',
            '2027-01-04 12:00:00',
            $closures
        ));
        $this->assertFalse($this->invokePrivate(
            $service,
            'isSlotClosedByClosure',
            '2027-01-04 12:00:00',
            '2027-01-04 13:00:00',
            $closures
        ));
    }

    public function test_process_image_update(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('fields/old.jpg', 'old image');
        $field = new Field(['image_url' => 'storage/fields/old.jpg']);
        $request = Request::create('/', 'POST', [], [], [
            'image' => UploadedFile::fake()->create('new.jpg', 10, 'image/jpeg'),
        ]);

        $imageUrl = $this->invokePrivate(app(FieldService::class), 'processImageUpdate', $field, $request);

        $this->assertStringStartsWith('storage/fields/', $imageUrl);
        $this->assertTrue(Storage::disk('public')->exists(str_replace('storage/', '', $imageUrl)));
        $this->assertFalse(Storage::disk('public')->exists('fields/old.jpg'));
    }

    public function test_delete_old_field_image(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('fields/old.jpg', 'old image');
        $service = app(FieldService::class);

        $this->invokePrivate($service, 'deleteOldFieldImage', 'storage/fields/old.jpg');
        $this->invokePrivate($service, 'deleteOldFieldImage', null);

        $this->assertFalse(Storage::disk('public')->exists('fields/old.jpg'));
    }

    public function test_process_pricing_rules_update(): void
    {
        $field = $this->createField();
        FieldPrice::create([
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'price' => 80000,
        ]);
        $rules = json_encode([
            [
                'day_type' => 'MONDAY',
                'start_time' => '09:00:00',
                'end_time' => '10:00:00',
                'price' => 100000,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->invokePrivate(app(FieldService::class), 'processPricingRulesUpdate', $field, $rules);

        $this->assertSame(1, FieldPrice::where('fk_field_id', $field->id)->count());
        $this->assertDatabaseHas('field_prices', [
            'fk_field_id' => $field->id,
            'day_type' => 'monday',
            'start_time' => '09:00:00',
            'price' => 100000,
        ], 'mysql_joglo66_app');
    }

    public function test_has_pricing_overlaps(): void
    {
        $service = app(FieldService::class);

        $this->assertFalse($this->invokePrivate($service, 'hasPricingOverlaps', [
            ['day_type' => 'MONDAY', 'start_time' => '08:00:00', 'end_time' => '09:00:00'],
            ['day_type' => 'monday', 'start_time' => '09:00:00', 'end_time' => '10:00:00'],
        ]));
        $this->assertTrue($this->invokePrivate($service, 'hasPricingOverlaps', [
            ['day_type' => 'monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00'],
            ['day_type' => 'monday', 'start_time' => '09:30:00', 'end_time' => '11:00:00'],
        ]));
    }

    public function test_is_slot_occupied(): void
    {
        $occupied = collect([
            (object) ['start_play_time' => '09:00:00', 'end_play_time' => '10:00:00'],
        ]);
        $service = app(FieldService::class);

        $this->assertTrue($this->invokePrivate($service, 'isSlotOccupied', '09:30:00', '10:30:00', $occupied));
        $this->assertFalse($this->invokePrivate($service, 'isSlotOccupied', '10:00:00', '11:00:00', $occupied));
    }

    private function createField(string $name = 'Lapangan A', string $category = 'futsal'): Field
    {
        static $number = 0;
        $number++;
        $user = User::create([
            'name' => "Owner {$number}",
            'email' => "owner{$number}@example.test",
            'password' => 'password',
            'role' => 'owner',
        ]);

        return Field::create([
            'name' => $name,
            'category' => $category,
            'fk_user_id' => $user->id,
        ]);
    }

    private function createBooking(Field $field): Booking
    {
        static $number = 0;
        $number++;
        $user = User::create([
            'name' => "Tenant {$number}",
            'email' => "tenant{$number}@example.test",
            'password' => 'password',
            'role' => 'tenant',
        ]);

        return Booking::create([
            'fk_user_id' => $user->id,
            'fk_field_id' => $field->id,
            'team_name' => "Team {$number}",
            'booking_date' => '2027-01-01',
        ]);
    }

    private function createDetail(Booking $booking, array $overrides = []): BookingDetail
    {
        return BookingDetail::create(array_merge([
            'fk_booking_id' => $booking->id,
            'play_date' => '2027-01-04',
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ], $overrides));
    }

    private function invokePrivate(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}

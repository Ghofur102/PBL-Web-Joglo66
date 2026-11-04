<?php

namespace Tests\Unit\Services\Admin;

use App\Services\Admin\AttributeRentalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\Unit\Services\ServiceTestCase;
use Tests\Unit\Services\ServiceTestData;

#[RunTestsInSeparateProcesses]
class AttributeRentalServiceTest extends ServiceTestCase
{
    public function test_get_active_bookings_filters_and_formats_booking_details(): void
    {
        Carbon::setTestNow(ServiceTestData::dateTime(ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_09_00));

        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);

        $otherField = $this->createField($owner, ServiceTestData::FIELD_SECONDARY);

        $customer = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_SITI, ServiceTestData::CUSTOMER_SITI);

        $booking = $this->createBooking($customer, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_SECONDARY);

        $activeDetail = $this->createBookingDetail($booking, ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_10_30, ServiceTestData::TIME_11_30, ServiceTestData::BOOKING_WAITING);

        $cancelledDetail = $this->createBookingDetail($booking, ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_12_00, ServiceTestData::TIME_13_00, ServiceTestData::BOOKING_CANCELLED);

        $pastDetail = $this->createBookingDetail($booking, ServiceTestData::DATE_24_OCT_2026, ServiceTestData::TIME_14_00, ServiceTestData::TIME_15_00, ServiceTestData::BOOKING_ACTIVE);

        $finishedDetail = $this->createBookingDetail($booking, ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_15_00, ServiceTestData::TIME_16_00, ServiceTestData::BOOKING_FINISH);

        $otherBooking = $this->createBooking($customer, $otherField, ServiceTestData::TEAM_SECONDARY, ServiceTestData::PHONE_TERTIARY);
        $otherFieldDetail = $this->createBookingDetail($otherBooking, ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_17_00, ServiceTestData::TIME_18_00, ServiceTestData::BOOKING_WAITING);

        $result = app(AttributeRentalService::class)->getActiveBookings([$field->id]);
        $this->assertSame([$activeDetail->id], $result->pluck('detail_id')->all());
        $this->assertSame($field->id, $result->first()['field_id']);
        $this->assertSame(ServiceTestData::FIELD_PRIMARY, $result->first()['field_name']);
        $this->assertSame(ServiceTestData::TEAM_PRIMARY, $result->first()['team_name']);
        $this->assertSame(ServiceTestData::CUSTOMER_SITI, $result->first()['customer_name']);
        $this->assertSame(ServiceTestData::DATE_25_OCT_2026, $result->first()['play_date']);
        $this->assertSame(ServiceTestData::TIME_10_30_SHORT, $result->first()['start_time']);
        $this->assertSame(ServiceTestData::TIME_11_30_SHORT, $result->first()['end_time']);
        $this->assertSame(ServiceTestData::PHONE_SECONDARY, $result->first()['customer_phone']);
        $this->assertNotSame($activeDetail->id, $cancelledDetail->id);
        $this->assertNotSame($activeDetail->id, $pastDetail->id);
        $this->assertNotSame($activeDetail->id, $finishedDetail->id);
        $this->assertNotSame($activeDetail->id, $otherFieldDetail->id);
    }

    public function test_execute_rental_calculates_totals_updates_stock_and_rolls_back_invalid_batch(): void
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $customer = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_BUDI, ServiceTestData::CUSTOMER_BUDI);
        $booking = $this->createBooking($customer, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_PRIMARY);
        $detail = $this->createBookingDetail($booking, ServiceTestData::DATE_26_OCT_2026, ServiceTestData::TIME_09_00, ServiceTestData::TIME_10_00, ServiceTestData::BOOKING_ACTIVE);
        $ball = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::FIVE, ServiceTestData::TWO_THOUSAND_FIVE_HUNDRED);
        $vest = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_VEST, ServiceTestData::THREE, ServiceTestData::ONE_THOUSAND);
        try {
            app(AttributeRentalService::class)->executeRental([
                'fk_booking_detail_id' => $detail->id,
                'transaction_date' => ServiceTestData::dateTime(ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_14_35),
                'duration_hours' => ServiceTestData::THREE,
                'customer_name' => ServiceTestData::CUSTOMER_BUDI,
                'customer_phone' => ServiceTestData::PHONE_PRIMARY,
                'items' => [
                    ['fk_attribute_id' => $ball->id, 'quantity' => ServiceTestData::TWO],
                    ['fk_attribute_id' => $vest->id, 'quantity' => ServiceTestData::FOUR],
                ],
            ]);
            $this->fail(ServiceTestData::MESSAGE_TEST_INSUFFICIENT_STOCK);
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertSame(ServiceTestData::MESSAGE_INSUFFICIENT_STOCK, $exception->getMessage());
        }
        $this->assertDatabaseCount(self::TABLE_BOOKING_ATTRIBUTES, ServiceTestData::ZERO, self::DATABASE_CONNECTION_APP);
        $this->assertDatabaseHas(self::TABLE_ATTRIBUTES, ['id' => $ball->id, 'stock' => ServiceTestData::FIVE], self::DATABASE_CONNECTION_APP);
        $result = app(AttributeRentalService::class)->executeRental([
            'fk_booking_detail_id' => $detail->id,
            'transaction_date' => ServiceTestData::dateTime(ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_14_35),
            'duration_hours' => ServiceTestData::THREE,
            'customer_name' => ServiceTestData::CUSTOMER_BUDI,
            'customer_phone' => ServiceTestData::PHONE_PRIMARY,
            'items' => [
                ['fk_attribute_id' => $ball->id, 'quantity' => ServiceTestData::TWO],
                ['fk_attribute_id' => $vest->id, 'quantity' => ServiceTestData::ONE],
            ],
        ]);
        $this->assertSame([ServiceTestData::FIFTEEN_THOUSAND, ServiceTestData::THREE_THOUSAND], array_map(fn ($rental) => $rental->total, $result['items']));
        $this->assertSame(ServiceTestData::EIGHTEEN_THOUSAND, $result['total_price']);
        $this->assertSame(ServiceTestData::DATE_25_OCT_2026, $result['transaction_date']);
        $this->assertSame(ServiceTestData::RENTAL_BORROWED, $result['items'][ServiceTestData::ZERO]->status);
        $this->assertSame($detail->id, $result['items'][ServiceTestData::ZERO]->fk_booking_detail_id);
        $this->assertSame(ServiceTestData::ATTRIBUTE_BALL, $result['items'][ServiceTestData::ZERO]->attribute->name);
        $this->assertDatabaseHas(self::TABLE_ATTRIBUTES, ['id' => $ball->id, 'stock' => ServiceTestData::THREE], self::DATABASE_CONNECTION_APP);
        $this->assertDatabaseHas(self::TABLE_ATTRIBUTES, ['id' => $vest->id, 'stock' => ServiceTestData::TWO], self::DATABASE_CONNECTION_APP);
        $inactiveAttribute = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_INACTIVE, ServiceTestData::TWO, ServiceTestData::FIVE_HUNDRED, ServiceTestData::GENERAL_INACTIVE);
        try {
            app(AttributeRentalService::class)->executeRental([
                'fk_booking_detail_id' => $detail->id,
                'transaction_date' => ServiceTestData::DATE_25_OCT_2026,
                'duration_hours' => ServiceTestData::ONE,
                'customer_name' => ServiceTestData::CUSTOMER_BUDI,
                'items' => [['fk_attribute_id' => $inactiveAttribute->id, 'quantity' => ServiceTestData::ONE]],
            ]);
            $this->fail(ServiceTestData::MESSAGE_TEST_INACTIVE_ATTRIBUTE);
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertSame(ServiceTestData::MESSAGE_INACTIVE_ATTRIBUTE, $exception->getMessage());
        }
        $this->assertDatabaseCount(self::TABLE_BOOKING_ATTRIBUTES, ServiceTestData::TWO, self::DATABASE_CONNECTION_APP);
    }

    public function test_process_return_marks_rental_returned_and_restores_stock_once(): void
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $customer = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_RINA, ServiceTestData::CUSTOMER_RINA);
        $booking = $this->createBooking($customer, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_ALTERNATIVE);
        $detail = $this->createBookingDetail($booking, ServiceTestData::DATE_26_OCT_2026, ServiceTestData::TIME_10_00, ServiceTestData::TIME_11_00, ServiceTestData::BOOKING_ACTIVE);
        $attribute = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::TWO, ServiceTestData::TWO_THOUSAND);
        $rental = $this->createRental($detail, $attribute, ServiceTestData::THREE, ServiceTestData::RENTAL_BORROWED, ServiceTestData::DATE_25_OCT_2026, ServiceTestData::CUSTOMER_RINA);
        $returnedRental = app(AttributeRentalService::class)->processReturn($rental);
        $this->assertSame(ServiceTestData::RENTAL_RETURNED, $returnedRental->status);
        $this->assertSame(ServiceTestData::ATTRIBUTE_BALL, $returnedRental->attribute->name);
        $this->assertDatabaseHas(self::TABLE_ATTRIBUTES, ['id' => $attribute->id, 'stock' => ServiceTestData::FIVE], self::DATABASE_CONNECTION_APP);
        try {
            app(AttributeRentalService::class)->processReturn($returnedRental);
            $this->fail(ServiceTestData::MESSAGE_TEST_ALREADY_RETURNED);
        } catch (UnprocessableEntityHttpException $exception) {
            $this->assertSame(ServiceTestData::MESSAGE_ALREADY_RETURNED, $exception->getMessage());
        }
        $this->assertDatabaseHas(self::TABLE_ATTRIBUTES, ['id' => $attribute->id, 'stock' => ServiceTestData::FIVE], self::DATABASE_CONNECTION_APP);
    }

    public function test_get_history_applies_field_search_date_status_and_pagination_filters(): void
    {
        $owner = $this->createUser(ServiceTestData::ROLE_OWNER, ServiceTestData::EMAIL_OWNER);
        $field = $this->createField($owner, ServiceTestData::FIELD_PRIMARY);
        $otherField = $this->createField($owner, ServiceTestData::FIELD_SECONDARY);
        $customer = $this->createUser(ServiceTestData::ROLE_TENANT, ServiceTestData::EMAIL_TENANT_DINA, ServiceTestData::CUSTOMER_DINA);
        $booking = $this->createBooking($customer, $field, ServiceTestData::TEAM_PRIMARY, ServiceTestData::PHONE_CONTACT);
        $detail = $this->createBookingDetail($booking, ServiceTestData::DATE_26_OCT_2026, ServiceTestData::TIME_10_00, ServiceTestData::TIME_11_00, ServiceTestData::BOOKING_ACTIVE);
        $otherBooking = $this->createBooking($customer, $otherField, ServiceTestData::TEAM_SECONDARY, ServiceTestData::PHONE_OTHER);
        $otherDetail = $this->createBookingDetail($otherBooking, ServiceTestData::DATE_26_OCT_2026, ServiceTestData::TIME_12_00, ServiceTestData::TIME_13_00, ServiceTestData::BOOKING_ACTIVE);
        $attribute = $this->createAttribute($field, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::TEN, ServiceTestData::ONE_THOUSAND);
        $otherAttribute = $this->createAttribute($otherField, ServiceTestData::ATTRIBUTE_BALL, ServiceTestData::TEN, ServiceTestData::ONE_THOUSAND);
        $this->createRental($detail, $attribute, ServiceTestData::ONE, ServiceTestData::RENTAL_RETURNED, ServiceTestData::DATE_20_OCT_2026, ServiceTestData::CUSTOMER_PRIMARY);
        $matchingRental = $this->createRental($detail, $attribute, ServiceTestData::ONE, ServiceTestData::RENTAL_RETURNED, ServiceTestData::DATE_22_OCT_2026, ServiceTestData::CUSTOMER_MATCHING);
        $latestMatchingRental = $this->createRental($detail, $attribute, ServiceTestData::ONE, ServiceTestData::RENTAL_RETURNED, ServiceTestData::DATE_23_OCT_2026, ServiceTestData::CUSTOMER_LATEST);
        DB::table(self::TABLE_BOOKING_ATTRIBUTES)->where('id', $matchingRental->id)->update(['created_at' => ServiceTestData::dateTime(ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_10_00)]);
        DB::table(self::TABLE_BOOKING_ATTRIBUTES)->where('id', $latestMatchingRental->id)->update(['created_at' => ServiceTestData::dateTime(ServiceTestData::DATE_25_OCT_2026, ServiceTestData::TIME_11_00)]);
        $this->createRental($detail, $attribute, ServiceTestData::ONE, ServiceTestData::RENTAL_RETURNED, ServiceTestData::DATE_24_OCT_2026, ServiceTestData::CUSTOMER_BUDI);
        $this->createRental($otherDetail, $otherAttribute, ServiceTestData::ONE, ServiceTestData::RENTAL_RETURNED, ServiceTestData::DATE_24_OCT_2026, ServiceTestData::CUSTOMER_OTHER);
        $result = app(AttributeRentalService::class)->getHistory([$field->id], [
            'search' => ServiceTestData::CUSTOMER_DINA,
            'start_date' => ServiceTestData::DATE_21_OCT_2026,
            'end_date' => ServiceTestData::DATE_24_OCT_2026,
            'status' => ServiceTestData::RENTAL_RETURNED,
            'limit' => ServiceTestData::ONE,
        ]);
        $this->assertSame(ServiceTestData::TWO, $result->total());
        $this->assertCount(ServiceTestData::ONE, $result->items());
        $this->assertSame($latestMatchingRental->id, $result->items()[ServiceTestData::ZERO]->id);
        $this->assertNotSame($matchingRental->id, $result->items()[0]->id);
    }
}

<?php

namespace Tests\Feature\Tenant\Booking;

use App\Enums\BookingDetailStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\User;
use App\Models\FieldPrice;
use App\Services\DuitkuService;
use App\Services\Tenant\Booking\TenantBookingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;
use UnexpectedValueException;
use App\Enums\PaymentType;
use App\Enums\PaymentStatus;

class BookingIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected $connectionsToTransact = ['mysql_joglo66_app'];

    protected User $tenant;
    protected Field $field;
    protected $duitkuServiceMock;

    // --- DOMAIN: ROUTES ---
    const ROUTE_CONFIRM_FORM = '/tenant/booking/confirm-form';
    const ROUTE_BOOKING_STORE = '/tenant/booking/store';

    // --- DOMAIN: DATES ---
    const DATE_PRIMARY = '2026-09-01';
    const DATE_ROLLBACK = '2026-09-02';
    const DATE_MULTI_1 = '2026-09-05';
    const DATE_MULTI_2 = '2026-09-06';
    const DATE_PAYMENT_SUCCESS = '2026-09-10';
    const DATE_PAYMENT_FAIL = '2026-09-11';
    const DATE_SATURDAY = '2026-09-19';
    const DATE_OVERLAP = '2026-10-10';

    // --- DOMAIN: TIMES (DB FORMAT H:i:s) ---
    const TIME_08_00 = '08:00:00';
    const TIME_09_00 = '09:00:00';
    const TIME_10_00 = '10:00:00';
    const TIME_10_30 = '10:30:00';
    const TIME_11_00 = '11:00:00';
    const TIME_11_30 = '11:30:00';
    const TIME_12_00 = '12:00:00';
    const TIME_13_00 = '13:00:00';
    const TIME_14_00 = '14:00:00';
    const TIME_15_00 = '15:00:00';
    const TIME_16_00 = '16:00:00';
    const TIME_22_00 = '22:00:00';

    // --- DOMAIN: TIMES (JSON FORMAT H:i) ---
    const TIME_18_00_SHORT = '18:00';
    const TIME_19_00_SHORT = '19:00';
    const TIME_20_00_SHORT = '20:00';

    // --- DOMAIN: PRICES ---
    const PRICE_BASE = 50000;
    const PRICE_MEDIUM = 70000;
    const PRICE_HIGH = 75000;
    const PRICE_PREMIUM = 100000;
    const PRICE_WEEKEND = 150000;

    // --- DOMAIN: EXTERNAL SERVICES (DUITKU) ---
    const MOCK_REF_DEFAULT = 'TEST-REF-123';
    const MOCK_REF_MULTI = 'REF-MULTI-3SLOT';
    const MOCK_REF_DUITKU = 'REF-DUITKU-999';
    const MOCK_PAYMENT_URL = 'https://duitku.com/pay';
    const MOCK_SANDBOX_URL = 'https://duitku.sandbox.com/pay/999';

    // --- DOMAIN: ERROR MESSAGES ---
    const ERR_OVERLAP = 'sudah dipesan orang lain';
    const ERR_CACHE_LOCK = 'Salah satu slot jam pilihan Anda baru saja diproses oleh orang lain. Silakan pilih slot waktu yang lain.';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql_joglo66_app' => config('database.connections.testing')]);
        config(['database.default' => 'mysql_joglo66_app']);
        DB::setDefaultConnection('mysql_joglo66_app');

        $this->tenant = User::factory()->create(['role' => 'tenant']);
        $this->field = Field::factory()->create();

        $this->duitkuServiceMock = Mockery::mock(DuitkuService::class);
        $this->app->instance(DuitkuService::class, $this->duitkuServiceMock);
    }

    /**
     * Skenario 1: Validasi endpoint parsing JSON tunggal
     */
    public function test_confirm_form_parses_single_json_slot_correctly(): void
    {
        $jsonPayload = '[{"date":"' . self::DATE_PRIMARY . '","jam":"' . self::TIME_18_00_SHORT . '","jam_akhir":"' . self::TIME_19_00_SHORT . '","field_id":' . $this->field->id . '}]';

        $response = $this->actingAs($this->tenant)
            ->post(self::ROUTE_CONFIRM_FORM, [
                'field_id' => $this->field->id,
                'selected_slots' => $jsonPayload,
            ]);

        $response->assertStatus(200)
            ->assertViewIs('tenant.booking.confirmation')
            ->assertViewHas('groupedSlots');

        $groupedSlots = $response->original->gatherData()['groupedSlots'];

        $this->assertNotNull($groupedSlots, 'Parsed array tidak boleh null');
        $this->assertCount(1, $groupedSlots, 'Harus terkelompok menjadi 1 tanggal');
        $this->assertArrayHasKey(self::DATE_PRIMARY, $groupedSlots, 'Parsed index 0 (key) harus tanggal pemesanan');
        $this->assertEquals(self::TIME_18_00_SHORT, $groupedSlots[self::DATE_PRIMARY][0]['jam']);
    }

    /**
     * Skenario 2: Validasi pencegahan tumpang tindih waktu (Overlap Time Constraint)
     */
    public function test_booking_throws_exception_on_time_overlap(): void
    {
        $teamName = 'Tim A';
        $existingBooking = Booking::factory()->create([
            'fk_field_id' => $this->field->id,
            'fk_user_id' => $this->tenant->id,
        ]);

        BookingDetail::factory()->create([
            'fk_booking_id' => $existingBooking->id,
            'play_date' => self::DATE_OVERLAP,
            'start_play_time' => self::TIME_10_00,
            'end_play_time' => self::TIME_11_00,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        $overlapPayload = [
            'field_id' => $this->field->id,
            'team_name' => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
        ];

        $groupedSlotsOverlap = [
            self::DATE_OVERLAP => [
                ['jam' => self::TIME_10_30, 'jam_akhir' => self::TIME_11_30, 'harga' => self::PRICE_BASE],
            ],
        ];

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage(self::ERR_OVERLAP);

        $service = app(TenantBookingService::class);
        $service->processBookingTransaction(
            $this->tenant->id,
            $this->tenant,
            $overlapPayload,
            $groupedSlotsOverlap,
            $this->duitkuServiceMock
        );
    }

    /**
     * Skenario 3: Pencegahan Double Booking akibat Race Condition (Atomic Lock Bypass)
     */
    public function test_booking_store_prevents_concurrent_double_booking_via_cache_lock(): void
    {
        $teamName = 'Tim B';
        $groupedSlotsPayload = [
            self::DATE_PRIMARY => [
                [
                    'jam' => self::TIME_18_00_SHORT,
                    'jam_akhir' => self::TIME_19_00_SHORT,
                    'harga' => self::PRICE_BASE,
                ],
            ],
        ];

        $requestPayload = [
            'field_id' => $this->field->id,
            'booking_data' => json_encode($groupedSlotsPayload),
            'team_name' => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'notes' => 'Tes balapan',
        ];

        $lockKey = "lock_field_{$this->field->id}_date_" . self::DATE_PRIMARY . "_slot_" . self::TIME_18_00_SHORT;

        $simulatedLock = Cache::lock($lockKey, 15);
        $simulatedLock->get();

        $response = $this->actingAs($this->tenant)
            ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(302);
        $response->assertRedirect(route('tenant.booking.dashboard'));
        $response->assertSessionHas('error', self::ERR_CACHE_LOCK);

        $this->assertDatabaseMissing('bookings', [
            'fk_field_id' => $this->field->id,
            'team_name' => $teamName,
        ]);

        $simulatedLock->release();
    }

    /**
     * Skenario 4: Field Closure mencegah Booking (Kondisi Tumpang Tindih)
     */
    public function test_booking_is_prevented_when_field_closure_overlaps(): void
    {
        $teamName = 'Tim C';

        DB::table('field_closures')->insert([
            'fk_field_id'              => $this->field->id,
            'fk_user_id'               => $this->tenant->id,
            'reason'                   => 'Maintenance',
            'field_closure_start_time' => self::DATE_PRIMARY . ' ' . self::TIME_10_00,
            'field_closure_end_time'   => self::DATE_PRIMARY . ' ' . self::TIME_12_00,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_PRIMARY => [['jam' => self::TIME_10_30, 'jam_akhir' => self::TIME_11_30, 'harga' => self::PRICE_BASE]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'notes'        => 'Tes tutup lapangan'
        ];

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(302);
        $response->assertSessionHas('error', "Transaksi gagal: Lapangan sedang ditutup pada slot " . self::TIME_10_30 . " - " . self::TIME_11_30 . " di tanggal " . self::DATE_PRIMARY . ".");

        $this->assertDatabaseMissing('bookings', [
            'fk_field_id' => $this->field->id,
            'team_name'   => $teamName
        ]);
    }

    /**
     * Skenario 5: Booking berhasil karena jam tidak tumpang tindih dengan Field Closure
     */
    public function test_booking_succeeds_when_field_closure_exists_but_no_overlap(): void
    {
        $teamName = 'Tim D';

        DB::table('field_closures')->insert([
            'fk_field_id'              => $this->field->id,
            'fk_user_id'               => $this->tenant->id,
            'reason'                   => 'Maintenance',
            'field_closure_start_time' => self::DATE_PRIMARY . ' ' . self::TIME_10_00,
            'field_closure_end_time'   => self::DATE_PRIMARY . ' ' . self::TIME_12_00,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_PRIMARY => [['jam' => self::TIME_13_00, 'jam_akhir' => self::TIME_14_00, 'harga' => self::PRICE_BASE]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'notes'        => 'Tes jam aman'
        ];

        $this->duitkuServiceMock->shouldReceive('createInvoice')->once()->andReturn((object)[
            'reference' => self::MOCK_REF_DEFAULT,
            'paymentUrl' => self::MOCK_PAYMENT_URL
        ]);

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.checkout');

        $this->assertDatabaseHas('bookings', [
            'fk_field_id' => $this->field->id,
            'team_name'   => $teamName
        ]);
    }

    /**
     * Skenario 6: Isolasi Evaluasi Skema (Tabel tidak ada)
     */
    public function test_booking_skips_closure_check_if_table_does_not_exist(): void
    {
        $teamName = 'Tim E';

        Schema::shouldReceive('hasTable')
            ->with('field_closures')
            ->andReturn(false);

        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_PRIMARY => [['jam' => self::TIME_10_00, 'jam_akhir' => self::TIME_11_00, 'harga' => self::PRICE_BASE]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
        ];

        $this->duitkuServiceMock->shouldReceive('createInvoice')->once()->andReturn((object)[
            'reference' => self::MOCK_REF_DEFAULT
        ]);

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(200);
        $this->assertDatabaseHas('bookings', ['team_name' => $teamName]);
    }

    /**
     * Skenario 7: Transaksional Rollback (Integritas Data Kritis)
     */
    public function test_transaction_rolls_back_booking_if_detail_insertion_fails(): void
    {
        $teamName = 'Tim F';
        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_ROLLBACK => [['jam' => self::TIME_15_00, 'jam_akhir' => self::TIME_16_00, 'harga' => self::PRICE_BASE]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'notes'        => 'Tes Rollback'
        ];

        $initialBookingCount = Booking::count();

        Event::listen('eloquent.creating: App\Models\BookingDetail', function () {
            throw new \PDOException('Simulasi kegagalan sistem internal pada BookingDetail');
        });

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(302);
        $response->assertRedirect(route('tenant.booking.dashboard'));
        $response->assertSessionHas('error', 'Transaksi gagal: Simulasi kegagalan sistem internal pada BookingDetail');

        $this->assertEquals($initialBookingCount, Booking::count(), 'Jumlah Booking harus sama dengan sebelum Act (Rollback berhasil)');
        $this->assertDatabaseMissing('bookings', [
            'fk_field_id' => $this->field->id,
            'team_name'   => $teamName
        ]);
    }

    /**
     * Skenario 8: Pembuatan Multi-Slot BookingDetail (Akurasi Relasional)
     */
    public function test_booking_creates_multiple_detail_records_accurately_for_each_slot(): void
    {
        $groupedSlotsPayload = [
            self::DATE_MULTI_1 => [
                ['jam' => self::TIME_08_00, 'jam_akhir' => self::TIME_09_00, 'harga' => self::PRICE_BASE],
                ['jam' => self::TIME_09_00, 'jam_akhir' => self::TIME_10_00, 'harga' => self::PRICE_BASE],
            ],
            self::DATE_MULTI_2 => [
                ['jam' => self::TIME_15_00, 'jam_akhir' => self::TIME_16_00, 'harga' => self::PRICE_HIGH],
            ]
        ];

        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode($groupedSlotsPayload),
            'team_name'    => 'Tim Multi Slot',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'notes'        => 'Booking 3 slot sekaligus'
        ];

        $this->duitkuServiceMock->shouldReceive('createInvoice')
             ->once()
             ->andReturn((object)[
                 'reference'  => self::MOCK_REF_MULTI,
                 'paymentUrl' => self::MOCK_PAYMENT_URL
             ]);

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.checkout');

        $booking = $response->original->gatherData()['booking'];
        $this->assertNotNull($booking, 'Booking record harus terbuat.');

        $detailsCount = BookingDetail::where('fk_booking_id', $booking->id)->count();
        $this->assertEquals(3, $detailsCount, 'Harus ada persis 3 baris BookingDetail yang terbuat.');

        $this->assertDatabaseHas('booking_details', [
            'fk_booking_id'   => $booking->id,
            'play_date'       => self::DATE_MULTI_1,
            'start_play_time' => self::TIME_08_00,
            'end_play_time'   => self::TIME_09_00,
            'price'           => self::PRICE_BASE,
            'status'          => BookingDetailStatus::WAITING->value,
        ]);

        $this->assertDatabaseHas('booking_details', [
            'fk_booking_id'   => $booking->id,
            'play_date'       => self::DATE_MULTI_1,
            'start_play_time' => self::TIME_09_00,
            'end_play_time'   => self::TIME_10_00,
            'price'           => self::PRICE_BASE,
            'status'          => BookingDetailStatus::WAITING->value,
        ]);

        $this->assertDatabaseHas('booking_details', [
            'fk_booking_id'   => $booking->id,
            'play_date'       => self::DATE_MULTI_2,
            'start_play_time' => self::TIME_15_00,
            'end_play_time'   => self::TIME_16_00,
            'price'           => self::PRICE_HIGH,
            'status'          => BookingDetailStatus::WAITING->value,
        ]);
    }

    /**
     * Skenario 9: Validasi Integrasi Gateway Pembayaran (Jalur Sukses)
     */
    public function test_booking_creates_duitku_invoice_and_inserts_payment_record(): void
    {
        $teamName = 'Tim Duitku Lancar';
        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_PAYMENT_SUCCESS => [['jam' => self::TIME_10_00, 'jam_akhir' => self::TIME_11_00, 'harga' => self::PRICE_PREMIUM]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'notes'        => 'Tes Payment'
        ];

        $expectedAmountToPay = self::PRICE_BASE;

        $this->duitkuServiceMock->shouldReceive('createInvoice')
            ->once()
            ->with(
                \Mockery::type(\App\Models\Booking::class),
                $expectedAmountToPay
            )
            ->andReturn((object)[
                'reference'  => self::MOCK_REF_DUITKU,
                'paymentUrl' => self::MOCK_SANDBOX_URL
            ]);

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(200);

        $booking = Booking::where('team_name', $teamName)->first();

        $this->assertDatabaseHas('payments', [
            'fk_booking_id' => $booking->id,
            'reference_id'  => self::MOCK_REF_DUITKU,
            'payment_type'  => PaymentType::DOWN_PAYMENT->value,
            'amount'        => $expectedAmountToPay,
            'status'        => PaymentStatus::PENDING->value,
            'method'        => 'transfer',
        ]);
    }

    /**
     * Skenario 10: Kompensasi Kegagalan Sistem Pihak Ketiga (Saga Pattern / Rollback)
     */
    public function test_booking_is_deleted_when_duitku_service_throws_exception(): void
    {
        $teamName = 'Tim Duitku Down';
        $requestPayload = [
            'field_id'     => $this->field->id,
            'booking_data' => json_encode([
                self::DATE_PAYMENT_FAIL => [['jam' => self::TIME_13_00, 'jam_akhir' => self::TIME_14_00, 'harga' => self::PRICE_MEDIUM]]
            ]),
            'team_name'    => $teamName,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
        ];

        $this->duitkuServiceMock->shouldReceive('createInvoice')
            ->once()
            ->andThrow(new \Exception('Connection Timeout to Duitku Server'));

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_BOOKING_STORE, $requestPayload);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Transaksi gagal: Gagal membuat pembayaran: Connection Timeout to Duitku Server');

        $this->assertDatabaseMissing('bookings', [
            'fk_field_id' => $this->field->id,
            'team_name'   => $teamName
        ]);
    }

    /**
     * Skenario 11: Membuktikan Controller merender View dengan data terkomputasi.
     */
    public function test_confirm_form_returns_view_with_calculated_grouped_slots_and_price(): void
    {
        $jsonPayload = json_encode([
            [
                "date"      => self::DATE_SATURDAY,
                "jam"       => self::TIME_19_00_SHORT,
                "jam_akhir" => self::TIME_20_00_SHORT,
                "field_id"  => $this->field->id
            ]
        ]);

        FieldPrice::create([
            'fk_field_id' => $this->field->id,
            'day_type'    => 'saturday',
            'start_time'  => self::TIME_18_00_SHORT,
            'end_time'    => self::TIME_22_00,
            'price'       => self::PRICE_WEEKEND
        ]);

        $response = $this->actingAs($this->tenant)
                         ->post(self::ROUTE_CONFIRM_FORM, [
                             'field_id'       => $this->field->id,
                             'selected_slots' => $jsonPayload
                         ]);

        $response->assertStatus(200);
        $response->assertViewIs('tenant.booking.confirmation');

        $viewData = $response->original->gatherData();

        $this->assertArrayHasKey('groupedSlots', $viewData);
        $this->assertArrayHasKey('totalPrice', $viewData);
        $this->assertEquals($this->field->id, $viewData['field']->id);

        $groupedSlots = $viewData['groupedSlots'];
        $this->assertEquals(self::PRICE_WEEKEND, $viewData['totalPrice'], 'Total harga harus sesuai dengan query FieldPrice.');
        $this->assertArrayHasKey(self::DATE_SATURDAY, $groupedSlots, 'Slot harus dikelompokkan berdasarkan playDate.');
        $this->assertEquals(self::TIME_19_00_SHORT, $groupedSlots[self::DATE_SATURDAY][0]['jam']);
    }

    /**
     * Skenario 12: Membuktikan sistem menolak payload malformed dengan HTTP 422.
     */
    public function test_confirm_form_rejects_invalid_json_format_with_422(): void
    {
        $invalidJson = '[{"date":"' . self::DATE_PRIMARY . '","jam":"' . self::TIME_18_00_SHORT . '"';

        $response = $this->actingAs($this->tenant)
                         ->postJson(self::ROUTE_CONFIRM_FORM, [
                             'field_id'       => $this->field->id,
                             'selected_slots' => $invalidJson
                         ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['selected_slots']);
    }

    /**
     * Skenario 13 (TP-L-I07): Membuktikan sistem menolak eksekusi jika ID lapangan tidak eksis.
     */
    public function test_confirm_form_rejects_non_existent_field_id(): void
    {
        $nonExistentFieldId = 999999;
        $jsonPayload = json_encode([
            ["date" => self::DATE_PRIMARY, "jam" => self::TIME_18_00_SHORT, "jam_akhir" => self::TIME_19_00_SHORT]
        ]);

        $response = $this->actingAs($this->tenant)
                         ->postJson(self::ROUTE_CONFIRM_FORM, [
                             'field_id'       => $nonExistentFieldId,
                             'selected_slots' => $jsonPayload
                         ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['field_id']);
    }
}

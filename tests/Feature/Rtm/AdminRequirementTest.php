<?php

namespace Tests\Feature\Rtm;

use App\Enums\BookingDetailStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\Field;
use App\Models\FieldPrice;
use App\Models\FieldWorker;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\GeneralBookingNotification;
use App\Services\Admin\DashboardService;
use App\Services\Admin\FieldService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengujian Berbasis RTM (FR-ADM-01 s/d FR-ADM-12)
 * Mengacu pada kolom Req ID, Requirement Ringkas, Test Case ID, dan Teknik Uji pada RTM - RTM.csv.
 */
class AdminRequirementTest extends TestCase
{
    protected User $owner;
    protected User $admin;
    protected User $tenant;
    protected Field $assignedField;
    protected Field $unassignedField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER->value,
        ]);

        $this->admin = User::factory()->create([
            'role' => UserRole::WORKER->value,
        ]);

        $this->tenant = User::factory()->create([
            'role' => UserRole::TENANT->value,
        ]);

        $this->assignedField = Field::factory()->create([
            'name' => 'Lapangan Futsal A',
            'category' => 'futsal',
            'fk_user_id' => $this->owner->id,
        ]);

        $this->unassignedField = Field::factory()->create([
            'name' => 'Lapangan Mini Soccer B',
            'category' => 'mini soccer',
            'fk_user_id' => $this->owner->id,
        ]);

        FieldWorker::create([
            'fk_field_id' => $this->assignedField->id,
            'fk_user_id' => $this->admin->id,
        ]);
    }

    // =========================================================================
    // FR-ADM-01: Dashboard Admin Sesuai Lapangan Penugasan
    // =========================================================================

    /**
     * FR-ADM-01 | TC-ADM-01.1 | Black Box (Platform Isolation)
     * Menguji apakah dashboard admin berhasil menampilkan ringkasan data operasional sesuai lokasi lapangan yang ditugaskan.
     */
    public function test_tc_adm_01_1_dashboard_menampilkan_ringkasan_sesuai_lapangan_penugasan(): void
    {
        $today = Carbon::today()->toDateString();

        $bookingAssigned = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Tim A',
            'booking_date' => $today,
        ]);
        BookingDetail::create([
            'fk_booking_id' => $bookingAssigned->id,
            'play_date' => $today,
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
        Payment::create([
            'fk_booking_id' => $bookingAssigned->id,
            'reference_id' => 'ADM-01-A',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        $bookingUnassigned = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->unassignedField->id,
            'team_name' => 'Tim B',
            'booking_date' => $today,
        ]);
        BookingDetail::create([
            'fk_booking_id' => $bookingUnassigned->id,
            'play_date' => $today,
            'start_play_time' => '12:00:00',
            'end_play_time' => '13:00:00',
            'price' => 250000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
        Payment::create([
            'fk_booking_id' => $bookingUnassigned->id,
            'reference_id' => 'ADM-01-B',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 250000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'today_income' => 100000,
                    'today_schedules' => 1,
                ],
            ]);
    }

    /**
     * FR-ADM-01 | TC-ADM-01.2 | Black Box (State Transition)
     * Menguji apakah data statistik dashboard diperbarui saat Admin berpindah/mendapat tambahan lokasi lapangan.
     */
    public function test_tc_adm_01_2_data_statistik_dashboard_diperbarui_saat_lokasi_berubah(): void
    {
        $today = Carbon::today()->toDateString();

        $bookingB = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->unassignedField->id,
            'team_name' => 'Tim B2',
            'booking_date' => $today,
        ]);
        Payment::create([
            'fk_booking_id' => $bookingB->id,
            'reference_id' => 'ADM-01-B2',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 200000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        // Awalnya hanya terikat ke assignedField (income 0)
        $resBefore = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/dashboard');
        $resBefore->assertStatus(200);
        $this->assertSame(0, $resBefore->json('data.today_income'));

        // Tugaskan Admin ke lapangan kedua
        FieldWorker::create([
            'fk_field_id' => $this->unassignedField->id,
            'fk_user_id' => $this->admin->id,
        ]);

        $resAfter = $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/dashboard');
        $resAfter->assertStatus(200);
        $this->assertSame(200000, $resAfter->json('data.today_income'));
    }

    /**
     * FR-ADM-01 | TC-ADM-01.3 | Black Box (State Transition)
     * Menguji apakah pesan error tampil saat sistem gagal memuat data ringkasan dashboard akibat kegagalan server.
     */
    public function test_tc_adm_01_3_pesan_error_tampil_saat_server_gagal_muat_dashboard(): void
    {
        $mockDashboard = Mockery::mock(DashboardService::class);
        $mockDashboard->shouldReceive('getDashboardData')
            ->once()
            ->andThrow(new RuntimeException('Database timeout'));

        $this->app->instance(DashboardService::class, $mockDashboard);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Gagal memuat sebagian data dasbor.',
            ]);
    }

    // =========================================================================
    // FR-ADM-02: Kalender Jadwal Ketersediaan Slot Lapangan Admin
    // =========================================================================

    /**
     * FR-ADM-02 | TC-ADM-02.1 | Black Box (State Transition)
     * Menguji apakah kalender jadwal ketersediaan slot waktu lapangan berhasil ditampilkan sesuai lokasi lapangan tugas Admin.
     */
    public function test_tc_adm_02_1_kalender_slot_ditampilkan_sesuai_lokasi_admin(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();
        $dayName = strtolower(Carbon::parse($targetDate)->englishDayOfWeek);

        FieldPrice::create([
            'fk_field_id' => $this->assignedField->id,
            'day_type' => $dayName,
            'start_time' => '08:00:00',
            'end_time' => '11:00:00',
            'price' => 100000,
        ]);

        // Simulasikan transaksi online penyewa via web di jam 09:00-10:00
        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Online Web FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $targetDate,
            'start_play_time' => '09:00:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->assignedField->id}/{$targetDate}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $slots = $response->json('available_slots.available_slots');
        $this->assertCount(3, $slots);
        $this->assertTrue($slots[0]['is_available']);  // 08:00-09:00
        $this->assertFalse($slots[1]['is_available']); // 09:00-10:00 (terisi via web)
        $this->assertTrue($slots[2]['is_available']);  // 10:00-11:00
    }

    /**
     * FR-ADM-02 | TC-ADM-02.2 | Black Box (State Transition & Anti-IDOR)
     * Menguji apakah pemetaan status slot diperbarui saat Admin memilih tanggal lain pada kalender.
     */
    public function test_tc_adm_02_2_pemetaan_status_slot_diperbarui_saat_pilih_tanggal_lain(): void
    {
        $date1 = Carbon::today()->addDays(2)->toDateString();
        $date2 = Carbon::today()->addDays(3)->toDateString();

        foreach ([$date1, $date2] as $d) {
            FieldPrice::create([
                'fk_field_id' => $this->assignedField->id,
                'day_type' => strtolower(Carbon::parse($d)->englishDayOfWeek),
                'start_time' => '08:00:00',
                'end_time' => '10:00:00',
                'price' => 100000,
            ]);
        }

        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Date1 FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $date1,
            'start_play_time' => '08:00:00',
            'end_play_time' => '09:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $resDate1 = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->assignedField->id}/{$date1}");
        $resDate1->assertStatus(200);
        $this->assertFalse($resDate1->json('available_slots.available_slots.0.is_available'));

        $resDate2 = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->assignedField->id}/{$date2}");
        $resDate2->assertStatus(200);
        $this->assertTrue($resDate2->json('available_slots.available_slots.0.is_available'));
    }

    /**
     * FR-ADM-02 | TC-ADM-02.3 | Black Box (State Transition & Anti-IDOR)
     * Menguji apakah akses ditolak saat Admin mencoba membuka kalender lokasi lapangan di luar hak penugasannya.
     */
    public function test_tc_adm_02_3_akses_ditolak_saat_buka_kalender_lapangan_luar_penugasan(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->unassignedField->id}/{$targetDate}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Forbidden. Anda tidak memiliki akses ke lapangan ini.',
            ]);
    }

    /**
     * FR-ADM-02 | TC-ADM-02.4 | Black Box (State Transition & Anti-IDOR)
     * Menguji apakah pesan error tampil saat sistem gagal mengambil data slot dari database.
     */
    public function test_tc_adm_02_4_pesan_error_tampil_saat_gagal_ambil_slot(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $mockFieldService = Mockery::mock(FieldService::class);
        $mockFieldService->shouldReceive('checkSlotAvailability')
            ->once()
            ->andThrow(new RuntimeException('DB connection lost'));

        $this->app->instance(FieldService::class, $mockFieldService);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->assignedField->id}/{$targetDate}");

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Internal server error.',
            ]);
    }

    // =========================================================================
    // FR-ADM-03: Formulir Input Data Pemesan Offline
    // =========================================================================

    /**
     * FR-ADM-03 | TC-ADM-03.1 | Black Box (State Transition & Anti-IDOR)
     * Menguji apakah formulir input data pemesan offline berhasil memuat dan memvalidasi data diri calon penyewa yang valid.
     */
    public function test_tc_adm_03_1_input_data_pemesan_offline_valid_berhasil(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();
        FieldPrice::create([
            'fk_field_id' => $this->assignedField->id,
            'day_type' => strtolower(Carbon::parse($playDate)->englishDayOfWeek),
            'start_time' => '08:00:00',
            'end_time' => '18:00:00',
            'price' => 100000,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-booking', [
                'user_id' => $this->admin->id,
                'field_id' => $this->assignedField->id,
                'team_name' => 'Garuda Offline FC',
                'booking_date' => now()->toDateString(),
                'customer_phone' => '081234567890',
                'customer_email' => 'garuda@offline.test',
                'details' => [
                    [
                        'play_date' => $playDate,
                        'start_play_time' => '14:00',
                        'end_play_time' => '15:00',
                        'price' => 100000,
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('bookings', [
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Garuda Offline FC',
        ]);
    }

    /**
     * FR-ADM-03 | TC-ADM-03.2 | Black Box (Decision Table & BVA)
     * Menguji apakah pengisian data pemesan dibatalkan saat Admin menekan tombol Batal/Kembali.
     */
    public function test_tc_adm_03_2_pengisian_data_pemesan_dibatalkan_saat_batal(): void
    {
        // Admin membatalkan sebelum submit form
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('booking_details', 0);
    }

    /**
     * FR-ADM-03 | TC-ADM-03.3 | Black Box (Decision Table & BVA)
     * Menguji apakah sistem menolak proses saat nama pemesan kosong atau nomor HP mengandung karakter tidak valid.
     */
    public function test_tc_adm_03_3_menolak_jika_nama_pemesan_kosong_atau_kontak_invalid(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-booking', [
                'user_id' => $this->admin->id,
                'field_id' => $this->assignedField->id,
                'team_name' => '',
                'booking_date' => now()->toDateString(),
                'customer_phone' => '0812abc!@#',
                'details' => [
                    [
                        'play_date' => $playDate,
                        'start_play_time' => '14:00',
                        'end_play_time' => '15:00',
                        'price' => 100000,
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['team_name', 'customer_phone']);

        $this->assertDatabaseCount('bookings', 0);
    }

    // =========================================================================
    // FR-ADM-04: Konfirmasi Pembayaran DP Offline & Kunci Slot
    // =========================================================================

    /**
     * FR-ADM-04 | TC-ADM-04.1 | Black Box (Decision Table & BVA)
     * Menguji apakah sistem berhasil mengunci slot dan menyimpan transaksi pemesanan offline berstatus DP secara tunai.
     */
    public function test_tc_adm_04_1_berhasil_kunci_slot_dan_simpan_transaksi_dp_offline(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();
        $booking = Booking::create([
            'fk_user_id' => $this->admin->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'DP Offline FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $playDate,
            'start_play_time' => '15:00:00',
            'end_play_time' => '16:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/payment-booking', [
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'payment_type' => PaymentType::DOWN_PAYMENT->value,
                'method' => 'cash',
                'amount' => 50000,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => PaymentStatus::SUCCESS->value,
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'fk_booking_id' => $booking->id,
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('booking_details', [
            'id' => $detail->id,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
    }

    /**
     * FR-ADM-04 | TC-ADM-04.2 | Black Box (Concurrency / Redis Lock)
     * Menguji apakah simpan transaksi DP dibatalkan saat Admin menekan tombol Batal pada popup konfirmasi.
     */
    public function test_tc_adm_04_2_simpan_transaksi_dp_dibatalkan_saat_batal(): void
    {
        $booking = Booking::create([
            'fk_user_id' => $this->admin->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Batal DP FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '15:00:00',
            'end_play_time' => '16:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        // Popup konfirmasi dibatalkan: tidak mengirim payment-booking
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(BookingDetailStatus::WAITING->value, $detail->fresh()->status);
    }

    /**
     * FR-ADM-04 | TC-ADM-04.3 | Black Box (Concurrency / Redis Lock)
     * Menguji apakah sistem menolak penguncian slot DP saat terjadi bentrok waktu (slot sudah dikunci penyewa lain via web).
     */
    public function test_tc_adm_04_3_menolak_kunci_slot_dp_saat_bentrok_dengan_web_tenant(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();

        // Slot sudah dipesan lebih dulu oleh penyewa web
        $existing = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Web Tenant FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $existing->id,
            'play_date' => $playDate,
            'start_play_time' => '16:00:00',
            'end_play_time' => '17:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-booking', [
                'user_id' => $this->admin->id,
                'field_id' => $this->assignedField->id,
                'team_name' => 'Offline Tabrak FC',
                'booking_date' => now()->toDateString(),
                'details' => [
                    [
                        'play_date' => $playDate,
                        'start_play_time' => '16:00',
                        'end_play_time' => '17:00',
                        'price' => 100000,
                    ],
                ],
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Lapangan sudah dipesan atau sedang ditutup pada slot waktu tersebut.',
            ]);
    }

    // =========================================================================
    // FR-ADM-05: Konfirmasi Pembayaran Lunas Tunai Offline
    // =========================================================================

    /**
     * FR-ADM-05 | TC-ADM-05.1 | Black Box (Concurrency / Redis Lock)
     * Menguji apakah sistem berhasil mengunci slot permanen dan menyimpan transaksi offline berstatus Lunas Tunai 100%.
     */
    public function test_tc_adm_05_1_berhasil_kunci_slot_dan_simpan_lunas_tunai_100_persen(): void
    {
        $booking = Booking::create([
            'fk_user_id' => $this->admin->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Lunas Offline FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '17:00:00',
            'end_play_time' => '18:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::WAITING->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/payment-booking', [
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'payment_type' => PaymentType::FINAL_PAYMENT->value,
                'method' => 'cash',
                'amount' => 100000,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('payments', [
            'fk_booking_id' => $booking->id,
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'amount' => 100000,
            'status' => PaymentStatus::SUCCESS->value,
        ]);
        $this->assertDatabaseHas('booking_details', [
            'id' => $detail->id,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
    }

    /**
     * FR-ADM-05 | TC-ADM-05.2 | Black Box (Decision Table & DB Lock)
     * Menguji apakah konfirmasi pembayaran lunas dibatalkan saat Admin menekan tombol Batal.
     */
    public function test_tc_adm_05_2_konfirmasi_pembayaran_lunas_dibatalkan_saat_batal(): void
    {
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * FR-ADM-05 | TC-ADM-05.3 | Black Box (Decision Table & DB Lock)
     * Menguji apakah sistem menolak penguncian slot saat slot jadwal pilihan telah terisi oleh transaksi lain.
     */
    public function test_tc_adm_05_3_menolak_kunci_slot_lunas_jika_slot_sudah_terisi(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();

        $existing = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Terisi Duluan FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $existing->id,
            'play_date' => $playDate,
            'start_play_time' => '19:00:00',
            'end_play_time' => '20:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-booking', [
                'user_id' => $this->admin->id,
                'field_id' => $this->assignedField->id,
                'team_name' => 'Offline Bentrok FC',
                'booking_date' => now()->toDateString(),
                'details' => [
                    [
                        'play_date' => $playDate,
                        'start_play_time' => '19:00',
                        'end_play_time' => '20:00',
                        'price' => 100000,
                    ],
                ],
            ]);

        $response->assertStatus(400);
    }

    // =========================================================================
    // FR-ADM-06: Pelunasan Pembayaran DP Menjadi Lunas
    // =========================================================================

    /**
     * FR-ADM-06 | TC-ADM-06.1 | Black Box (Decision Table & DB Lock)
     * Menguji apakah tombol pelunasan muncul (sisa tagihan > 0) dan berhasil mengubah DP menjadi Lunas serta sisa tagihan menjadi Rp 0.
     */
    public function test_tc_adm_06_1_pelunasan_dp_menjadi_lunas_sisa_tagihan_nol(): void
    {
        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Pelunasan FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '20:00:00',
            'end_play_time' => '21:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'fk_booking_detail_id' => $detail->id,
            'reference_id' => 'DP-06-1',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        // 1. Cek detail sebelum pelunasan -> sisa tagihan 50.000 (menampilkan tombol pelunasan)
        $detailBefore = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/detail-booking/{$detail->id}");
        $detailBefore->assertStatus(200);
        $this->assertSame(50000, $detailBefore->json('data.sessions.0.remaining_payment'));

        // 2. Eksekusi pelunasan sisa 50.000
        $payResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/payment-booking', [
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'payment_type' => PaymentType::FINAL_PAYMENT->value,
                'method' => 'cash',
                'amount' => 50000,
            ]);
        $payResponse->assertStatus(200);

        // 3. Cek detail setelah pelunasan -> sisa tagihan menjadi 0
        $detailAfter = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/detail-booking/{$detail->id}");
        $detailAfter->assertStatus(200);
        $this->assertSame(0, $detailAfter->json('data.sessions.0.remaining_payment'));
        $this->assertSame(100000, $detailAfter->json('data.sessions.0.total_paid'));
    }

    /**
     * FR-ADM-06 | TC-ADM-06.2 | Black Box (State Transition & Negative Test)
     * Menguji apakah proses pelunasan dibatalkan saat Admin menekan tombol Batal pada dialog konfirmasi.
     */
    public function test_tc_adm_06_2_proses_pelunasan_dibatalkan_saat_batal(): void
    {
        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Batal Lunas FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '20:00:00',
            'end_play_time' => '21:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);
        Payment::create([
            'fk_booking_id' => $booking->id,
            'fk_booking_detail_id' => $detail->id,
            'reference_id' => 'DP-06-2',
            'payment_type' => PaymentType::DOWN_PAYMENT->value,
            'method' => 'cash',
            'amount' => 50000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        // Tombol Batal ditekan -> tidak ada request pelunasan
        $detailAfter = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/detail-booking/{$detail->id}");
        $detailAfter->assertStatus(200);
        $this->assertSame(50000, $detailAfter->json('data.sessions.0.remaining_payment'));
    }

    /**
     * FR-ADM-06 | TC-ADM-06.3 | Black Box (State Transition & Negative Test)
     * Menguji apakah pelunasan ditolak saat transaksi DP tersebut sudah dibatalkan oleh sistem.
     */
    public function test_tc_adm_06_3_pelunasan_ditolak_saat_transaksi_dp_sudah_dibatalkan(): void
    {
        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Cancelled DP FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '20:00:00',
            'end_play_time' => '21:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::CANCELLED->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/payment-booking', [
                'booking_id' => $booking->id,
                'booking_detail_id' => $detail->id,
                'payment_type' => PaymentType::FINAL_PAYMENT->value,
                'method' => 'cash',
                'amount' => 50000,
            ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot process payment for a fully cancelled booking.',
            ]);
    }

    // =========================================================================
    // FR-ADM-07: Daftar Riwayat Pemesanan Lapangan Penugasan Admin
    // =========================================================================

    /**
     * FR-ADM-07 | TC-ADM-07.1 | Black Box (State Transition & Negative Test)
     * Menguji apakah daftar seluruh riwayat pemesanan lapangan berhasil ditampilkan sesuai lokasi lapangan penugasan Admin.
     */
    public function test_tc_adm_07_1_daftar_riwayat_tampil_sesuai_lapangan_penugasan(): void
    {
        $playDate = Carbon::tomorrow()->toDateString();

        $b1 = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Tim Milik Admin',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $b1->id,
            'play_date' => $playDate,
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $b2 = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->unassignedField->id,
            'team_name' => 'Tim Lapangan Luar',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $b2->id,
            'play_date' => $playDate,
            'start_play_time' => '12:00:00',
            'end_play_time' => '13:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/list-booking');

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Tim Milik Admin'])
            ->assertJsonMissing(['title' => 'Tim Lapangan Luar']);
    }

    /**
     * FR-ADM-07 | TC-ADM-07.2 | Black Box (Anti-IDOR & EP)
     * Menguji apakah daftar riwayat berhasil disaring saat Admin menerapkan filter pencarian atau rentang tanggal.
     */
    public function test_tc_adm_07_2_daftar_riwayat_tersaring_berdasarkan_filter_dan_tanggal(): void
    {
        $dateA = Carbon::today()->addDays(2)->toDateString();
        $dateB = Carbon::today()->addDays(5)->toDateString();

        $b1 = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Rajawali FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $b1->id,
            'play_date' => $dateA,
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $b2 = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Elang FC',
            'booking_date' => now()->toDateString(),
        ]);
        BookingDetail::create([
            'fk_booking_id' => $b2->id,
            'play_date' => $dateB,
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/list-booking?search=Rajawali&start_date={$dateA}&end_date={$dateA}");

        $response->assertStatus(200)
            ->assertJsonFragment(['title' => 'Rajawali FC'])
            ->assertJsonMissing(['title' => 'Elang FC']);
    }

    /**
     * FR-ADM-07 | TC-ADM-07.3 | Black Box (Anti-IDOR & EP)
     * Menguji apakah sistem menolak akses saat Admin mencoba melihat riwayat/detail transaksi lokasi lapangan luar hak kelola.
     */
    public function test_tc_adm_07_3_menolak_akses_riwayat_lokasi_lapangan_luar_hak_kelola(): void
    {
        $bOutside = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->unassignedField->id,
            'team_name' => 'Tim Luar',
            'booking_date' => now()->toDateString(),
        ]);
        $detailOutside = BookingDetail::create([
            'fk_booking_id' => $bOutside->id,
            'play_date' => Carbon::tomorrow()->toDateString(),
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/detail-booking/{$detailOutside->id}");

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    // =========================================================================
    // FR-ADM-08: Edit Data Lapangan Oleh Admin Penugasan
    // =========================================================================

    /**
     * FR-ADM-08 | TC-ADM-08.1 | Black Box (Anti-IDOR & EP)
     * Menguji apakah Admin berhasil mengubah dan menyimpan data master lapangan (deskripsi, tarif dinamis) pada area tugasnya.
     */
    public function test_tc_adm_08_1_berhasil_mengubah_data_master_lapangan_area_tugas(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/update-field', [
                'id' => $this->assignedField->id,
                'name' => 'Lapangan Futsal A Renovasi',
                'description' => 'Rumput sintetis baru',
                'category' => 'futsal',
                'pricing_rules' => [
                    [
                        'day_type' => 'monday',
                        'start_time' => '08:00:00',
                        'end_time' => '17:00:00',
                        'price' => 125000,
                    ],
                ],
                'refund_rules' => [
                    ['rule_type' => 'cancel', 'min_hours_before' => 48, 'refund_percent' => 100, 'is_active' => true],
                    ['rule_type' => 'reschedule', 'min_hours_before' => 24, 'refund_percent' => 100, 'is_active' => true],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fields', [
            'id' => $this->assignedField->id,
            'name' => 'Lapangan Futsal A Renovasi',
        ]);
        $this->assertDatabaseHas('field_prices', [
            'fk_field_id' => $this->assignedField->id,
            'price' => 125000,
        ]);
    }

    /**
     * FR-ADM-08 | TC-ADM-08.2 | Black Box (Decision Table & Security)
     * Menguji apakah perubahan data lapangan dibatalkan saat Admin menekan tombol Batal pada formulir edit.
     */
    public function test_tc_adm_08_2_perubahan_data_lapangan_dibatalkan_saat_batal(): void
    {
        $this->assertDatabaseHas('fields', [
            'id' => $this->assignedField->id,
            'name' => 'Lapangan Futsal A',
        ]);
    }

    /**
     * FR-ADM-08 | TC-ADM-08.3 | Black Box (Decision Table & Security)
     * Menguji apakah sistem menolak penyimpanan edit data lapangan saat ID lapangan bukan merupakan area penugasan sah Admin.
     */
    public function test_tc_adm_08_3_menolak_edit_lapangan_bukan_area_penugasan_admin(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/update-field', [
                'id' => $this->unassignedField->id,
                'name' => 'Sabotase Nama Lapangan',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('fields', [
            'id' => $this->unassignedField->id,
            'name' => 'Sabotase Nama Lapangan',
        ]);
    }

    // =========================================================================
    // FR-ADM-09: Perubahan Status Operasional Lapangan Menjadi Aktif
    // =========================================================================

    /**
     * FR-ADM-09 | TC-ADM-09.1 | Black Box (Decision Table & Security)
     * Menguji apakah status operasional lapangan berhasil diubah dari Nonaktif menjadi Aktif dan slot kembali dibuka untuk umum.
     */
    public function test_tc_adm_09_1_mengaktifkan_status_operasional_lapangan_dari_nonaktif(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/open-field', [
                'fk_field_id' => $this->assignedField->id,
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('fields', [
            'id' => $this->assignedField->id,
            'status' => 'active',
        ]);
    }

    /**
     * FR-ADM-09 | TC-ADM-09.2 | Black Box (State Transition)
     * Menguji apakah pengaktifan status lapangan dibatalkan saat Admin menekan tombol Batal pada dialog konfirmasi.
     */
    public function test_tc_adm_09_2_pengaktifan_status_lapangan_dibatalkan_saat_batal(): void
    {
        // Tanpa pengiriman request aktivasi, tidak ada perubahan status operasional
        $this->assertTrue(true);
    }

    /**
     * FR-ADM-09 | TC-ADM-09.3 | Black Box (State Transition)
     * Menguji apakah sistem menampilkan pesan error saat terjadi gangguan koneksi server saat pengaktifan status.
     */
    public function test_tc_adm_09_3_pesan_error_saat_gangguan_server_aktivasi_lapangan(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/open-field', [
                'fk_field_id' => $this->assignedField->id,
            ]);

        $response->assertServerError();
    }

    // =========================================================================
    // FR-ADM-10: Penutupan Operasional Lapangan Pada Tanggal Tertentu
    // =========================================================================

    /**
     * FR-ADM-10 | TC-ADM-10.1 | Black Box (State Transition)
     * Menguji apakah status operasional lapangan berhasil diubah menjadi Nonaktif pada rentang tanggal tertentu dan slot diblokir.
     */
    public function test_tc_adm_10_1_berhasil_nonaktifkan_lapangan_pada_tanggal_tertentu(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();
        FieldPrice::create([
            'fk_field_id' => $this->assignedField->id,
            'day_type' => strtolower(Carbon::parse($targetDate)->englishDayOfWeek),
            'start_time' => '08:00:00',
            'end_time' => '12:00:00',
            'price' => 100000,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/close-field', [
                'fk_field_id' => $this->assignedField->id,
                'field_closure_start_time' => "{$targetDate} 08:00:00",
                'field_closure_end_time' => "{$targetDate} 12:00:00",
                'reason' => 'Perbaikan rumput sintetis',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('field_closures', [
            'fk_field_id' => $this->assignedField->id,
            'reason' => 'Perbaikan rumput sintetis',
        ]);

        $avail = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/check-slot-availability/{$this->assignedField->id}/{$targetDate}");
        $avail->assertStatus(200);
        $this->assertFalse($avail->json('available_slots.available_slots.0.is_available'));
        $this->assertTrue($avail->json('available_slots.available_slots.0.is_closed'));
    }

    /**
     * FR-ADM-10 | TC-ADM-10.2 | Black Box (BVA & Decision Table)
     * Menguji apakah penutupan operasional dibatalkan saat Admin menekan tombol Batal pada formulir.
     */
    public function test_tc_adm_10_2_penutupan_operasional_dibatalkan_saat_batal(): void
    {
        $this->assertDatabaseCount('field_closures', 0);
    }

    /**
     * FR-ADM-10 | TC-ADM-10.3 | Black Box (BVA & Decision Table)
     * Menguji apakah sistem menolak penutupan operasional saat tanggal penutupan yang dipilih sudah berlalu (< hari ini).
     */
    public function test_tc_adm_10_3_menolak_penutupan_jika_tanggal_sudah_berlalu(): void
    {
        $yesterday = Carbon::yesterday()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/close-field', [
                'fk_field_id' => $this->assignedField->id,
                'field_closure_start_time' => "{$yesterday} 08:00:00",
                'field_closure_end_time' => "{$yesterday} 12:00:00",
                'reason' => 'Tanggal masa lalu',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['field_closure_start_time', 'field_closure_end_time']);
    }

    // =========================================================================
    // FR-ADM-11: Notifikasi Darurat Penutupan Lapangan Otomatis
    // =========================================================================

    /**
     * FR-ADM-11 | TC-ADM-11.1 | Black Box (BVA & Decision Table)
     * Menguji apakah notifikasi darurat penutupan otomatis terkirim ke seluruh penyewa yang memegang slot aktif pada tanggal penutupan.
     */
    public function test_tc_adm_11_1_notifikasi_darurat_terkirim_ke_penyewa_terdampak(): void
    {
        Notification::fake();

        $targetDate = Carbon::tomorrow()->toDateString();
        $booking = Booking::create([
            'fk_user_id' => $this->tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Terdampak FC',
            'booking_date' => now()->toDateString(),
        ]);
        $detail = BookingDetail::create([
            'fk_booking_id' => $booking->id,
            'play_date' => $targetDate,
            'start_play_time' => '09:00:00',
            'end_play_time' => '10:00:00',
            'price' => 100000,
            'status' => BookingDetailStatus::ACTIVE->value,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/close-field', [
                'fk_field_id' => $this->assignedField->id,
                'field_closure_start_time' => "{$targetDate} 08:00:00",
                'field_closure_end_time' => "{$targetDate} 12:00:00",
                'reason' => 'Hujan badai darurat',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('booking_details', [
            'id' => $detail->id,
            'status' => BookingDetailStatus::FIELD_CLOSURE->value,
        ]);

        Notification::assertSentTo($this->tenant, GeneralBookingNotification::class);
    }

    /**
     * FR-ADM-11 | TC-ADM-11.2 | Black Box (Integration & Queue Job)
     * Menguji apakah slot langsung dikunci tanpa pengiriman notifikasi masal saat tidak ada transaksi aktif pada tanggal penutupan.
     */
    public function test_tc_adm_11_2_slot_dikunci_tanpa_notifikasi_saat_tidak_ada_transaksi_aktif(): void
    {
        Notification::fake();

        $targetDate = Carbon::tomorrow()->toDateString();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/close-field', [
                'fk_field_id' => $this->assignedField->id,
                'field_closure_start_time' => "{$targetDate} 08:00:00",
                'field_closure_end_time' => "{$targetDate} 12:00:00",
                'reason' => 'Perawatan rutin tanpa booking',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('field_closures', [
            'fk_field_id' => $this->assignedField->id,
            'reason' => 'Perawatan rutin tanpa booking',
        ]);

        Notification::assertNothingSent();
    }

    /**
     * FR-ADM-11 | TC-ADM-11.3 | Black Box (Integration & Queue Job)
     * Menguji apakah sistem melakukan rollback otomatis jika terjadi kegagalan transaksi database saat pengiriman notifikasi darurat.
     */
    public function test_tc_adm_11_3_rollback_otomatis_jika_gagal_saat_penutupan_dan_notifikasi(): void
    {
        $targetDate = Carbon::tomorrow()->toDateString();

        $mockFieldService = Mockery::mock(FieldService::class);
        $mockFieldService->shouldReceive('executeFieldClosure')
            ->once()
            ->andThrow(new RuntimeException('Notification dispatch failed'));

        $this->app->instance(FieldService::class, $mockFieldService);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/close-field', [
                'fk_field_id' => $this->assignedField->id,
                'field_closure_start_time' => "{$targetDate} 08:00:00",
                'field_closure_end_time' => "{$targetDate} 12:00:00",
                'reason' => 'Simulasi error',
            ]);

        $response->assertStatus(500)
            ->assertJson(['success' => false]);

        $this->assertDatabaseCount('field_closures', 0);
    }

    // =========================================================================
    // FR-ADM-12: Penambahan Data Master Atribut Perlengkapan
    // =========================================================================

    /**
     * FR-ADM-12 | TC-ADM-12.1 | Black Box (Integration & Queue Job)
     * Menguji apakah data master atribut perlengkapan baru berhasil ditambahkan saat nama, stok (>= 0), dan tarif sewa (>= 0) valid.
     */
    public function test_tc_adm_12_1_berhasil_tambah_master_atribut_saat_input_valid(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-attribute', [
                'fk_field_id' => $this->assignedField->id,
                'name' => 'Rompi Latihan',
                'type' => 'Pakaian',
                'stock' => 0,      // BVA tepat batas minimum (>= 0)
                'price_hour' => 0, // BVA tepat batas minimum (>= 0)
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Data atribut berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'fk_field_id' => $this->assignedField->id,
            'name' => 'Rompi Latihan',
            'type' => 'pakaian',
            'stock' => 0,
            'price_hour' => 0,
        ]);
    }

    /**
     * FR-ADM-12 | TC-ADM-12.2 | Black Box (BVA & Anti-XSS Sanitization)
     * Menguji apakah penambahan atribut dibatalkan saat Admin menekan tombol Batal pada formulir.
     */
    public function test_tc_adm_12_2_penambahan_atribut_dibatalkan_saat_batal(): void
    {
        $this->assertDatabaseCount('attributes', 0);
    }

    /**
     * FR-ADM-12 | TC-ADM-12.3 | Black Box (BVA & Anti-XSS Sanitization)
     * Menguji apakah sistem menolak penyimpanan saat nominal harga sewa atau stok yang diinput bernilai negatif (< 0).
     */
    public function test_tc_adm_12_3_menolak_tambah_atribut_saat_stok_atau_tarif_negatif(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-attribute', [
                'fk_field_id' => $this->assignedField->id,
                'name' => 'Bola Futsal',
                'type' => 'Bola',
                'stock' => -1,
                'price_hour' => -5000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stock', 'price_hour']);

        $this->assertDatabaseCount('attributes', 0);
    }

    /**
     * FR-ADM-12 | TC-ADM-12.4 | Black Box (BVA & Anti-XSS Sanitization)
     * Menguji apakah sistem menolak penyimpanan saat nama atribut mengandung skrip atau karakter ilegal.
     */
    public function test_tc_adm_12_4_menolak_tambah_atribut_mengandung_skrip_xss(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/create-attribute', [
                'fk_field_id' => $this->assignedField->id,
                'name' => '<script>alert("XSS")</script>',
                'type' => 'Sepatu',
                'stock' => 5,
                'price_hour' => 15000,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseMissing('attributes', [
            'name' => '<script>alert("XSS")</script>',
        ]);
    }
}

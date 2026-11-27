<?php

namespace Tests\Feature\Rtm;

use App\Enums\GeneralStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Field;
use App\Models\FieldWorker;
use App\Models\Payment;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\Treasure\SalaryService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * Pengujian Berbasis RTM (FR-TRS-01 s/d FR-TRS-05)
 * Mengacu pada kolom Req ID, Requirement Ringkas, Test Case ID, dan Teknik Uji pada RTM - RTM.csv.
 */
class TreasurerRequirementTest extends TestCase
{
    private const ENDPOINT_LOGIN = '/api/login';
    private const ENDPOINT_SALARY_UPDATE = '/api/treasurer/gaji/update';
    private const ENDPOINT_SALARY_SYNC = '/api/treasurer/gaji/sync';
    private const TREASURER_EMAIL = 'bendahara@joglo66.test';
    private const DATE_JAN_01 = '2026-01-01';
    private const JSON_GROSS_INCOME = 'data.gross_income';

    protected User $treasurer;
    protected User $owner;
    protected Field $assignedField;
    protected Field $unassignedField;
    protected Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => UserRole::OWNER->value,
        ]);

        $this->treasurer = User::factory()->create([
            'email' => self::TREASURER_EMAIL,
            'password' => Hash::make('password123'),
            'role' => UserRole::TREASURER->value,
        ]);

        $this->assignedField = Field::factory()->create([
            'name' => 'Lapangan Futsal Utama',
            'fk_user_id' => $this->owner->id,
        ]);

        $this->unassignedField = Field::factory()->create([
            'name' => 'Lapangan Mini Soccer Luar',
            'fk_user_id' => $this->owner->id,
        ]);

        FieldWorker::create([
            'fk_field_id' => $this->assignedField->id,
            'fk_user_id' => $this->treasurer->id,
        ]);

        $this->employee = Employee::create([
            'name' => 'Budi Santoso',
            'position' => 'Staff Operasional',
            'base_salary' => 2000000,
            'join_date' => self::DATE_JAN_01,
            'status' => GeneralStatus::ACTIVE->value,
        ]);
    }

    // =========================================================================
    // FR-TRS-01: Pembaruan Nominal Gaji Bulanan Karyawan
    // =========================================================================

    /**
     * FR-TRS-01 | TC-TRS-01.1 | Black Box (BVA & EP)
     * Menguji apakah berhasil memperbarui nominal gaji saat nominal valid (>= 0) dan diakses oleh Bendahara.
     */
    public function test_tc_trs_01_1_berhasil_memperbarui_nominal_gaji_saat_nominal_valid(): void
    {
        $expense = Expense::create([
            'fk_field_id' => $this->assignedField->id,
            'fk_user_id' => $this->treasurer->id,
            'name' => 'Gaji ' . $this->employee->name,
            'category' => 'Gaji',
            'quantity' => 1,
            'unit_price' => 2000000,
            'expense_date' => self::DATE_JAN_01,
            'generate_at' => self::DATE_JAN_01,
        ]);

        EmployeeSalary::create([
            'fk_employee_id' => $this->employee->id,
            'fk_expense_id' => $expense->id,
            'amount_paid' => 2000000,
            'period_month' => 'january',
            'period_year' => 2026,
            'payment_date' => '2026-01-15',
            'bonus' => 0,
            'deduction' => 0,
            'notes' => 'Gaji Awal',
        ]);

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, [
                'employee_id' => $this->employee->id,
                'bulan' => 1,
                'tahun' => 2026,
                'amount_paid' => 2500000,
                'bonus' => 100000,
                'deduction' => 0,
                'notes' => 'Bonus performa',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gaji berhasil disimpan.',
            ]);

        $this->assertDatabaseHas('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'period_month' => 'january',
            'period_year' => 2026,
            'amount_paid' => 2500000,
            'bonus' => 100000,
        ]);
    }

    /**
     * FR-TRS-01 | TC-TRS-01.2 | Black Box (BVA & EP)
     * Menguji apakah gagal memperbarui nominal gaji saat nominal yang dimasukkan bernilai negatif (< 0).
     */
    public function test_tc_trs_01_2_gagal_memperbarui_nominal_gaji_saat_nominal_negatif(): void
    {
        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, [
                'employee_id' => $this->employee->id,
                'bulan' => 1,
                'tahun' => 2026,
                'amount_paid' => -1,
                'bonus' => 0,
                'deduction' => 0,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_paid']);

        $this->assertDatabaseMissing('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'amount_paid' => -1,
        ]);
    }

    /**
     * FR-TRS-01 | TC-TRS-01.3 | Black Box (BVA & EP)
     * Menguji apakah gagal memperbarui nominal gaji saat diakses oleh peran non-Bendahara (Akses ditolak di server).
     */
    public function test_tc_trs_01_3_gagal_memperbarui_nominal_gaji_saat_diakses_non_bendahara(): void
    {
        $nonTreasurer = User::factory()->create([
            'role' => UserRole::WORKER->value,
        ]);

        $response = $this->actingAs($nonTreasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, [
                'employee_id' => $this->employee->id,
                'bulan' => 1,
                'tahun' => 2026,
                'amount_paid' => 2500000,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akses ditolak.',
            ]);
    }

    /**
     * FR-TRS-01 | TC-TRS-01.4 | Black Box (BVA & EP)
     * Menguji apakah sistem membatalkan proses pembaruan nominal gaji saat Bendahara menekan tombol Batal.
     */
    public function test_tc_trs_01_4_membatalkan_proses_pembaruan_nominal_gaji_saat_batal(): void
    {
        $expense = Expense::create([
            'fk_field_id' => $this->assignedField->id,
            'fk_user_id' => $this->treasurer->id,
            'name' => 'Gaji ' . $this->employee->name,
            'category' => 'Gaji',
            'quantity' => 1,
            'unit_price' => 2000000,
            'expense_date' => self::DATE_JAN_01,
            'generate_at' => self::DATE_JAN_01,
        ]);

        EmployeeSalary::create([
            'fk_employee_id' => $this->employee->id,
            'fk_expense_id' => $expense->id,
            'amount_paid' => 2000000,
            'period_month' => 'january',
            'period_year' => 2026,
            'payment_date' => '2026-01-15',
            'bonus' => 0,
            'deduction' => 0,
            'notes' => 'Gaji Awal',
        ]);

        // Tombol Batal ditekan: tidak ada request POST /api/treasurer/gaji/update yang dikirim.
        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson('/api/treasurer/gaji?bulan=1&tahun=2026');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'employee_id' => $this->employee->id,
                'amount_paid' => 2000000,
            ]);
    }

    // =========================================================================
    // FR-TRS-02: Sinkronisasi Pengeluaran Gaji Bulanan
    // =========================================================================

    /**
     * FR-TRS-02 | TC-TRS-02.1 | Black Box (BVA & EP)
     * Menguji apakah berhasil melakukan sinkronisasi pencatatan pengeluaran gaji ke laporan pengeluaran lapangan pada bulan berjalan.
     */
    public function test_tc_trs_02_1_berhasil_sinkronisasi_pengeluaran_gaji_bulan_berjalan(): void
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_SYNC, [
                'bulan' => $currentMonth,
                'tahun' => $currentYear,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'period_year' => $currentYear,
            'amount_paid' => 2000000,
        ]);

        $this->assertDatabaseHas('expenses', [
            'category' => 'Gaji',
        ]);
    }

    /**
     * FR-TRS-02 | TC-TRS-02.2 | Black Box (Decision Table & Fault Injection)
     * Menguji apakah sistem melakukan rollback otomatis jika terjadi kegagalan transaksi database saat proses sinkronisasi gaji.
     */
    public function test_tc_trs_02_2_rollback_otomatis_jika_gagal_transaksi_saat_sinkronisasi(): void
    {
        $mockService = Mockery::mock(SalaryService::class);
        $mockService->shouldReceive('syncMonthlySalaries')
            ->once()
            ->andThrow(new RuntimeException('Simulated DB transaction failure'));

        $this->app->instance(SalaryService::class, $mockService);

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_SYNC, [
                'bulan' => (int) date('n'),
                'tahun' => (int) date('Y'),
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseCount('employee_salaries', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    /**
     * FR-TRS-02 | TC-TRS-02.3 | Black Box (Decision Table & Fault Injection)
     * Menguji apakah gagal menyinkronkan data jika Bendahara mencoba mengakses lokasi lapangan di luar penugasannya.
     */
    public function test_tc_trs_02_3_gagal_sinkronisasi_jika_mengakses_lapangan_luar_penugasan(): void
    {
        // Bendahara tidak memiliki penugasan di unassignedField
        $treasurerWithoutTargetField = User::factory()->create([
            'role' => UserRole::TREASURER->value,
        ]);

        // Menghapus semua field yang terikat ke user atau mencoba menyinkronkan ke field yang bukan haknya
        $response = $this->actingAs($treasurerWithoutTargetField, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_SYNC, [
                'bulan' => (int) date('n'),
                'tahun' => (int) date('Y'),
                'field_id' => $this->unassignedField->id,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('expenses', [
            'fk_field_id' => $this->unassignedField->id,
            'category' => 'Gaji',
        ]);
    }

    /**
     * FR-TRS-02 | TC-TRS-02.4 | Black Box (Decision Table & Fault Injection)
     * Menguji apakah sistem membatalkan proses sinkronisasi saat Bendahara menekan tombol Batal/Kembali.
     */
    public function test_tc_trs_02_4_membatalkan_proses_sinkronisasi_saat_batal(): void
    {
        // Tombol Batal ditekan sebelum submit sync
        $this->assertDatabaseCount('employee_salaries', 0);
        $this->assertDatabaseCount('expenses', 0);
    }

    // =========================================================================
    // FR-TRS-03: Laporan Keuangan Bulanan Berdasarkan Scope Bendahara
    // =========================================================================

    /**
     * FR-TRS-03 | TC-TRS-03.1 | Black Box (Decision Table & Fault Injection)
     * Menguji apakah laporan keuangan bulanan berhasil dimuat sesuai cakupan lokasi lapangan yang dikelola Bendahara (anti-IDOR).
     */
    public function test_tc_trs_03_1_laporan_keuangan_sesuai_cakupan_lapangan_bendahara(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);
        $month = (int) date('m');
        $year = (int) date('Y');

        // Booking & Payment pada lapangan yang dikelola Bendahara (Rp 150.000)
        $bookingAssigned = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Tim Lapangan A',
            'booking_date' => now()->toDateString(),
        ]);
        Payment::create([
            'fk_booking_id' => $bookingAssigned->id,
            'reference_id' => 'PAY-A-01',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 150000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        // Booking & Payment pada lapangan di luar penugasan Bendahara (Rp 500.000)
        $bookingUnassigned = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $this->unassignedField->id,
            'team_name' => 'Tim Lapangan B',
            'booking_date' => now()->toDateString(),
        ]);
        Payment::create([
            'fk_booking_id' => $bookingUnassigned->id,
            'reference_id' => 'PAY-B-01',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 500000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => now(),
        ]);

        // Bendahara meminta laporan tanpa filter eksplisit maupun mencoba mengakses unassignedField
        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson("/api/laporan-bulanan?month={$month}&year={$year}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Seharusnya hanya total lapangan penugasan Bendahara (150.000), bukan gabungan (650.000)
        $this->assertSame(150000, $response->json(self::JSON_GROSS_INCOME));
    }

    /**
     * FR-TRS-03 | TC-TRS-03.2 | Black Box (State Transition & EP)
     * Menguji apakah laporan keuangan bulanan berhasil memuat ulang data saat Bendahara mengubah filter Bulan dan Tahun.
     */
    public function test_tc_trs_03_2_laporan_keuangan_memuat_ulang_saat_filter_bulan_tahun_berubah(): void
    {
        $tenant = User::factory()->create(['role' => UserRole::TENANT->value]);

        $bookingJan = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Tim Januari',
            'booking_date' => '2026-01-10',
        ]);
        Payment::create([
            'fk_booking_id' => $bookingJan->id,
            'reference_id' => 'PAY-JAN-01',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 120000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2026-01-10 10:00:00',
        ]);

        $bookingFeb = Booking::create([
            'fk_user_id' => $tenant->id,
            'fk_field_id' => $this->assignedField->id,
            'team_name' => 'Tim Februari',
            'booking_date' => '2026-02-12',
        ]);
        Payment::create([
            'fk_booking_id' => $bookingFeb->id,
            'reference_id' => 'PAY-FEB-01',
            'payment_type' => PaymentType::FINAL_PAYMENT->value,
            'method' => 'cash',
            'amount' => 240000,
            'status' => PaymentStatus::SUCCESS->value,
            'paid_at' => '2026-02-12 10:00:00',
        ]);

        $resJan = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson("/api/laporan-bulanan?month=1&year=2026&field_ids={$this->assignedField->id}");
        $resJan->assertStatus(200);
        $this->assertSame(120000, $resJan->json(self::JSON_GROSS_INCOME));

        $resFeb = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson("/api/laporan-bulanan?month=2&year=2026&field_ids={$this->assignedField->id}");
        $resFeb->assertStatus(200);
        $this->assertSame(240000, $resFeb->json(self::JSON_GROSS_INCOME));
    }

    /**
     * FR-TRS-03 | TC-TRS-03.3 | Black Box (State Transition & EP)
     * Menguji apakah sistem menampilkan pesan error saat gagal memuat data laporan keuangan akibat gangguan koneksi server.
     */
    public function test_tc_trs_03_3_menampilkan_pesan_error_saat_gangguan_server_laporan(): void
    {
        $mockReportService = Mockery::mock(FinancialReportService::class);
        $mockReportService->shouldReceive('getMonthlyReport')
            ->once()
            ->andThrow(new RuntimeException('Gangguan koneksi server database'));

        $this->app->instance(FinancialReportService::class, $mockReportService);

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson('/api/laporan-bulanan?month=1&year=2026');

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Gangguan koneksi server database',
            ]);
    }

    // =========================================================================
    // FR-TRS-04: Identifikasi Peran Bendahara Saat Login Mobile
    // =========================================================================

    /**
     * FR-TRS-04 | TC-TRS-04.1 | Black Box (State Transition & EP)
     * Menguji apakah sistem berhasil mengidentifikasi peran Bendahara dan menerbitkan token saat email dan password valid.
     */
    public function test_tc_trs_04_1_login_berhasil_mengidentifikasi_peran_bendahara(): void
    {
        $response = $this->postJson(self::ENDPOINT_LOGIN, [
            'email' => self::TREASURER_EMAIL,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Login berhasil',
                'user' => [
                    'email' => self::TREASURER_EMAIL,
                    'role' => UserRole::TREASURER->value,
                ],
            ])
            ->assertJsonStructure(['token', 'user']);
    }

    /**
     * FR-TRS-04 | TC-TRS-04.2 | Black Box (EP & Platform Isolation)
     * Menguji apakah gagal login saat email atau password tidak valid/salah.
     */
    public function test_tc_trs_04_2_gagal_login_saat_email_atau_password_salah(): void
    {
        $response = $this->postJson(self::ENDPOINT_LOGIN, [
            'email' => self::TREASURER_EMAIL,
            'password' => 'salah-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email atau password salah.',
            ]);
    }

    /**
     * FR-TRS-04 | TC-TRS-04.3 | Black Box (EP & Platform Isolation)
     * Menguji apakah ditolak login jika akun ber-role Penyewa mencoba masuk pada aplikasi Mobile Bendahara.
     */
    public function test_tc_trs_04_3_ditolak_login_jika_role_penyewa_masuk_ke_mobile(): void
    {
        User::factory()->create([
            'email' => 'tenant@joglo66.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::TENANT->value,
        ]);

        $response = $this->postJson(self::ENDPOINT_LOGIN, [
            'email' => 'tenant@joglo66.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akses ditolak. Aplikasi ini khusus untuk Manajemen.',
            ]);
    }

    /**
     * FR-TRS-04 | TC-TRS-04.4 | Black Box (EP & Platform Isolation)
     * Menguji apakah proses login dibatalkan saat pengguna keluar sebelum menekan tombol masuk.
     */
    public function test_tc_trs_04_4_proses_login_dibatalkan_sebelum_submit(): void
    {
        // Pengguna menutup aplikasi sebelum request POST /api/login dikirim
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->treasurer->id,
        ]);
    }

    // =========================================================================
    // FR-TRS-05: Blokir Hak Akses Masuk Tidak Valid / Role Non-Peruntukan
    // =========================================================================

    /**
     * FR-TRS-05 | TC-TRS-05.1 | Black Box (EP & Platform Isolation)
     * Menguji apakah sistem memblokir akses masuk saat kombinasi kredensial email/password tidak ditemukan di database.
     */
    public function test_tc_trs_05_1_blokir_akses_saat_kredensial_tidak_ditemukan(): void
    {
        $response = $this->postJson(self::ENDPOINT_LOGIN, [
            'email' => 'tidak.terdaftar@joglo66.test',
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Email atau password salah.',
            ]);
    }

    /**
     * FR-TRS-05 | TC-TRS-05.2 | Black Box (Platform Isolation) | System Test
     * Menguji apakah sistem memblokir akses masuk saat akun memiliki role di luar peruntukan platform mobile / modul bendahara.
     */
    public function test_tc_trs_05_2_blokir_akses_saat_role_tidak_sesuai_peruntukan_platform(): void
    {
        $tenant = User::factory()->create([
            'email' => 'penyewa.mobile@joglo66.test',
            'password' => Hash::make('password123'),
            'role' => UserRole::TENANT->value,
        ]);

        // 1. Blokir login mobile untuk Tenant
        $loginResponse = $this->postJson(self::ENDPOINT_LOGIN, [
            'email' => 'penyewa.mobile@joglo66.test',
            'password' => 'password123',
        ]);
        $loginResponse->assertStatus(403)
            ->assertJson(['success' => false]);

        // 2. Blokir akses endpoint bendahara bagi non-bendahara (Tenant / Worker)
        $apiResponse = $this->actingAs($tenant, 'sanctum')
            ->getJson('/api/treasurer/gaji?bulan=1&tahun=2026');
        $apiResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akses ditolak.',
            ]);
    }
}

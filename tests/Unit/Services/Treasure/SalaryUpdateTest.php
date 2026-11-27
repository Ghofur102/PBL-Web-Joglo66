<?php
namespace Tests\Unit\Services\Treasure;

use App\Enums\GeneralStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Expense;
use App\Models\Field;
use App\Models\User;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Tests\TestCase;

class SalaryUpdateTest extends TestCase
{
    private const ENDPOINT_SALARY_UPDATE = '/api/treasurer/gaji/update';
    private const DATE_JAN_01 = '2026-01-01';

    use InteractsWithDatabase;
    use MakesHttpRequests;

    protected User $treasurer;

    protected Field $field;

    protected Employee $employee;

    protected Expense $expense;

    protected EmployeeSalary $salary;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Siapkan user bendahara
        $this->treasurer = User::factory()->create([
            'role' => UserRole::TREASURER->value,
        ]);

        // 2. Siapkan data lapangan (dibutuhkan relasi expense di SalaryService)
        $this->field = Field::factory()->create();

        // 3. Siapkan data karyawan aktif
        $this->employee = Employee::create([
            'name' => 'Budi Santoso',
            'position' => 'Staff Operasional',
            'base_salary' => 2000000,
            'join_date' => self::DATE_JAN_01,
            'status' => GeneralStatus::ACTIVE->value,
        ]);

        // 4. Siapkan data pengeluaran (expense) awal
        $this->expense = Expense::create([
            'fk_field_id' => $this->field->id,
            'fk_user_id' => $this->treasurer->id,
            'name' => 'Gaji '.$this->employee->name,
            'category' => 'Gaji',
            'quantity' => 1,
            'unit_price' => 2000000,
            'expense_date' => self::DATE_JAN_01,
            'generate_at' => self::DATE_JAN_01,
        ]);

        // 5. Siapkan data gaji awal karyawan (periode Januari 2026: Rp 2.000.000)
        $this->salary = EmployeeSalary::create([
            'fk_employee_id' => $this->employee->id,
            'fk_expense_id' => $this->expense->id,
            'amount_paid' => 2000000,
            'period_month' => 'january',
            'period_year' => 2026,
            'payment_date' => '2026-01-15',
            'bonus' => 0,
            'deduction' => 0,
            'notes' => 'Gaji Pokok Awal',
        ]);
    }

    /**
     * TC-TRS-01.1
     * Menguji apakah berhasil memperbarui nominal gaji saat nominal valid (>= 0) dan diakses oleh Bendahara.
     */
    public function test_tc_trs_01_1_berhasil_memperbarui_nominal_gaji_saat_nominal_valid_dan_diakses_bendahara(): void
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'bulan' => 1, // Januari
            'tahun' => 2026,
            'amount_paid' => 2500000, // Valid (>= 0)
            'bonus' => 100000,
            'deduction' => 0,
            'notes' => 'Bonus performa lembur',
        ];

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, $payload);

        // Assert HTTP 200 OK & response JSON sesuai spesifikasi
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Gaji berhasil disimpan.',
            ]);

        // Assert data di database berhasil diperbarui
        $this->assertDatabaseHas('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'period_month' => 'january',
            'period_year' => 2026,
            'amount_paid' => 2500000,
            'bonus' => 100000,
            'notes' => 'Bonus performa lembur',
        ]);
    }

    /**
     * TC-TRS-01.2
     * Menguji apakah gagal memperbarui nominal gaji saat nominal yang dimasukkan bernilai negatif (< 0).
     */
    public function test_tc_trs_01_2_gagal_memperbarui_nominal_gaji_saat_nominal_negatif(): void
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'bulan' => 1,
            'tahun' => 2026,
            'amount_paid' => -1, // Bernilai negatif (< 0)
            'bonus' => 0,
            'deduction' => 0,
            'notes' => 'Uji nominal negatif',
        ];

        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, $payload);

        // Assert HTTP 422 Unprocessable Entity karena gagal validasi 'amount_paid' => min:0
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount_paid']);

        // Assert data gaji di database TIDAK berubah (tetap nilai awal Rp 2.000.000)
        $this->assertInitialSalaryUnchanged(-1);
    }

    /**
     * TC-TRS-01.3
     * Menguji apakah gagal memperbarui nominal gaji saat diakses oleh peran non-Bendahara (Akses ditolak di server 403).
     */
    public function test_tc_trs_01_3_gagal_memperbarui_nominal_gaji_saat_diakses_non_bendahara(): void
    {
        // Siapkan user dengan peran selain Bendahara (misalnya Tenant)
        $nonTreasurer = new User(User::factory()->raw([
            'role' => UserRole::TENANT->value,
        ]));
        $nonTreasurer->save();

        $payload = [
            'employee_id' => $this->employee->id,
            'bulan' => 1,
            'tahun' => 2026,
            'amount_paid' => 3000000,
        ];

        $response = $this->actingAs($nonTreasurer, 'sanctum')
            ->postJson(self::ENDPOINT_SALARY_UPDATE, $payload);

        // Assert HTTP 403 Forbidden dengan pesan 'Akses ditolak.'
        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Akses ditolak.',
            ]);

        // Assert data gaji di database TIDAK berubah
        $this->assertInitialSalaryUnchanged(3000000);
    }

    /**
     * TC-TRS-01.4
     * Menguji apakah sistem membatalkan proses pembaruan nominal gaji saat Bendahara menekan tombol Batal.
     */
    public function test_tc_trs_01_4_membatalkan_proses_pembaruan_nominal_gaji_saat_tombol_batal_ditekan(): void
    {
        // 1. Bendahara membuka form, nominal baru sempat diisi misalnya Rp 3.500.000
        $draftNominal = 3500000;

        // 2. Bendahara menekan tombol Batal -> request pembaruan TIDAK dieksekusi ke server
        // (Simulasi aksi pembatalan: tidak mengirim payload perubahan ke endpoint update)

        // 3. Verifikasi data di database tetap mempertahankan nominal awal Rp 2.000.000
        $this->assertInitialSalaryUnchanged($draftNominal);

        // 4. Verifikasi bahwa data yang tampil saat bendahara membuka data gaji tetap nominal awal
        $response = $this->actingAs($this->treasurer, 'sanctum')
            ->getJson('/api/treasurer/gaji?bulan=1&tahun=2026');

        $response->assertStatus(200)
            ->assertJsonFragment([
                'employee_id' => $this->employee->id,
                'amount_paid' => 2000000,
            ]);
    }

    private function assertInitialSalaryUnchanged(int $rejectedAmount): void
    {
        $this->assertDatabaseHas('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'period_month' => 'january',
            'period_year' => 2026,
            'amount_paid' => 2000000,
        ]);

        $this->assertDatabaseMissing('employee_salaries', [
            'fk_employee_id' => $this->employee->id,
            'amount_paid' => $rejectedAmount,
        ]);
    }
}

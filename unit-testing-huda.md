# 🎓 BUKU PANDUAN & CHEAT SHEET UJIAN LISAN: UNIT TESTING HUDA (PBL JOGLO 66)

> **Catatan Pribadi Huda**: Dokumen ini memuat rangkuman lengkap, bedah logika kode, konsep pengujian, serta contekan jawaban ujian lisan untuk **7 file dan 36 fungsi Unit Test** yang menjadi tanggung jawab Huda di PBL Joglo 66.

---

## 📑 DAFTAR ISI
1. [Ringkasan Portofolio Unit Testing Huda (7 File, 36 Fungsi)](#1-ringkasan-portofolio-unit-testing-huda)
2. [5 Nilai Plus Kodingan Huda di Mata Dosen Penguji](#2-5-nilai-plus-kodingan-huda-di-mata-dosen)
3. [Bedah Mendalam 7 File Uji Huda](#3-bedah-mendalam-7-file-uji-huda)
   - [File 1: Admin\RescheduleServiceTest (3 Fungsi)](#file-1-adminrescheduleservicetest-3-fungsi)
   - [File 2: Api\AuthServiceTest (3 Fungsi)](#file-2-apiauthservicetest-3-fungsi)
   - [File 3: Owner\EmployeeServiceTest (8 Fungsi)](#file-3-owneremployeeservicetest-8-fungsi)
   - [File 4: Tenant\Auth\TenantAuthServiceTest (3 Fungsi)](#file-4-tenantauthtenantauthservicetest-3-fungsi)
   - [File 5: Tenant\Booking\TenantHistoryServiceTest (12 Fungsi)](#file-5-tenantbookingtenanthistoryservicetest-12-fungsi)
   - [File 6: Tenant\Booking\TenantScheduleServiceTest (4 Fungsi)](#file-6-tenantbookingtenantscheduleservicetest-4-fungsi)
   - [File 7: Tenant\Payment\TenantDuitkuServiceTest (3 Fungsi)](#file-7-tenantpaymenttenantduitkuservicetest-3-fungsi)
4. [Koneksi ke Teori Modul: White Box, CFG, dan $V(G)$ pada Kode Huda](#4-koneksi-ke-teori-modul-white-box-cfg-dan-vg)
5. [Tanya Jawab Lisan (Simulasi Pertanyaan Khusus untuk Huda)](#5-tanya-jawab-lisan-simulasi-pertanyaan-khusus-huda)
6. [Daftar Perintah Terminal Resmi (Sesuai Slide 7 Dosen)](#6-daftar-perintah-terminal-resmi)

---

## 1. Ringkasan Portofolio Unit Testing Huda

| No | File Test | Service yang Diuji | Jml Fungsi | Fokus Logika Bisnis |
|:--:|:---|:---|:---:|:---|
| 1 | `tests/Unit/Services/Admin/RescheduleServiceTest.php` | `Admin\RescheduleService` | **3** | Alur ganti jadwal (eksekusi langsung, persetujuan/approval, penolakan/reject) & deteksi slot bentrok. |
| 2 | `tests/Unit/Services/Api/AuthServiceTest.php` | `Api\AuthService` | **3** | Autentikasi API manajemen (Worker, Owner, Treasurer), hak akses lapangan, & revoke token logout. |
| 3 | `tests/Unit/Services/Owner/EmployeeServiceTest.php` | `Owner\EmployeeService` | **8** | CRUD karyawan, integritas relasi penggajian, & sinkronisasi akun sistem (`ReflectionMethod`). |
| 4 | `tests/Unit/Services/Tenant/Auth/TenantAuthServiceTest.php` | `Tenant\Auth\TenantAuthService` | **3** | Autentikasi penyewa/tenant, registrasi akun baru, dan update profil. |
| 5 | `tests/Unit/Services/Tenant/Booking/TenantHistoryServiceTest.php` | `Tenant\Booking\TenantHistoryService` | **12** | Alokasi dana bayar global (DP/Lunas), kalkulasi sisa bayar, status slot main, & badge CSS. |
| 6 | `tests/Unit/Services/Tenant/Booking/TenantScheduleServiceTest.php` | `Tenant\Booking\TenantScheduleService` | **4** | Jam slot operasional lapangan, kalkulasi harga harian, dan deteksi tumpang-tindih (overlap) penutupan lapangan. |
| 7 | `tests/Unit/Services/Tenant/Payment/TenantDuitkuServiceTest.php` | `Tenant\Payment\TenantDuitkuService` | **3** | Webhook callback Duitku, verifikasi kriptografi Signature MD5, & mutasi status transaksi. |
| | **TOTAL** | **7 File Layanan Inti PBL** | **36** | **Menguji Logika Bisnis End-to-End** |

---

## 2. 5 Nilai Plus Kodingan Huda di Mata Dosen

Saat dosen menguji, kamu bisa dengan bangga memamerkan 5 teknik pengujian tingkat lanjut yang sudah kamu tulis di kodinganmu:

1. **Pengujian Private Method Menggunakan `ReflectionMethod`**:
   * *Di mana*: `EmployeeServiceTest.php` (fungsi `syncUserAccount`, `createOrUpdateSystemUser`, `removeSystemUser`, `syncFieldWorkers`).
   * *Nilai Plus*: Menunjukkan bahwa kamu tahu cara menguji method `private/protected` yang tidak bisa dipanggil dari luar class tanpa harus mengubah visibilitas kode produksinya.
2. **Pengujian Mocking/Faking dengan `Notification::fake()`**:
   * *Di mana*: `RescheduleServiceTest.php`.
   * *Nilai Plus*: Mengisolasi side-effect notifikasi email/database agar test tidak benar-benar mengirim notifikasi keluar, tetapi tetap memverifikasi bahwa event notifikasi terkirim (`Notification::assertSentTo`).
3. **Exception Testing yang Lengkap (Bukan Cuma Happy Path)**:
   * Kamu menguji beragam jenis exception: `DomainException`, `AccessDeniedHttpException`, `ConflictHttpException`, `ValidationException`, `UnexpectedValueException`, dan `InvalidArgumentException`.
4. **Verifikasi Keamanan Kriptografi (Security Testing)**:
   * *Di mana*: `TenantDuitkuServiceTest.php`.
   * *Nilai Plus*: Menguji algoritma kalkulasi hashing MD5 (`merchantCode + amount + orderId + apiKey`) dan memastikan request bajakan/palsu langsung ditolak.
5. **State Mutation & Database Assertion yang Presisi**:
   * Memakai `$detail->refresh()` untuk memastikan data di memori terupdate dengan database, lalu memverifikasi relasi dengan `$this->assertDatabaseHas` dan `$this->assertDatabaseMissing`.

---

## 3. Bedah Mendalam 7 File Uji Huda

---

### File 1: `Admin\RescheduleServiceTest.php` (3 Fungsi)
*Class yang diuji*: `App\Services\Admin\RescheduleService`

#### 1. `test_execute()`
* **Tujuan**: Menguji saat admin secara sepihak mengubah jadwal main pelanggan.
* **Pola AAA**:
  * **Arrange**: Siapkan user tenant, user admin, lapangan, booking, dan detail booking aktif. Siapkan array `$data` berisi jam baru, tanggal baru, selisih biaya (`reconciled_amount = 50000`). Pasang `Notification::fake()`.
  * **Act**: Panggil `$this->service->execute($detail, $data, $admin);`.
  * **Assert**:
    - Record tabel `booking_reschedules` terbentuk dengan `status_refund = 'deposit required'`, `approval_status = 'approved'`, `sender_by = 'admin'`.
    - `$detail->refresh()` berubah tanggalnya, jamnya, dan statusnya menjadi `RESCHEDULE`.
    - Record tabel `payments` terbentuk untuk selisih biaya Rp 50.000 dengan status `SUCCESS`.
    - Notifikasi `GeneralBookingNotification` terkirim ke tenant dengan tipe `'reschedule_by_admin'`.

#### 2. `test_approve()`
* **Tujuan**: Menguji saat admin menyetujui (*approve*) pengajuan reschedule dari tenant.
* **Skenario Percabangan (Path)**:
  * **Path 1 (Gagal - Tidak ada pengajuan)**: Melempar `DomainException` dengan pesan *"Tidak ada pengajuan reschedule yang menunggu persetujuan."*.
  * **Path 2 (Gagal - Bentrok)**: Ada pemesanan lain di slot jam tersebut. Melempar `DomainException` dengan pesan *"Slot jadwal yang diajukan telah diambil oleh pemesanan lain."*.
  * **Path 3 (Sukses)**: Hapus jadwal yang bentrok, panggil `approve()`, status reschedule berubah jadi `approved`, detail slot terupdate, notifikasi `reschedule_approved` terkirim.

#### 3. `test_reject()`
* **Tujuan**: Menguji penolakan reschedule oleh admin.
* **Skenario**:
  * Melempar `DomainException` jika tidak ada permohonan pending.
  * Sukses menolak: status berubah jadi `rejected`, `rejection_reason` tersimpan, notifikasi `reschedule_rejected` terkirim ke tenant.

---

### File 2: `Api\AuthServiceTest.php` (3 Fungsi)
*Class yang diuji*: `App\Services\Api\AuthService` (Layanan Auth untuk aplikasi mobile/API manajemen)

#### 1. `test_login()`
* **Tujuan**: Menguji validasi kredensial dan hak akses berbasis Role (*Role-Based Access Control / RBAC*).
* **5 Skenario Uji (Independent Paths)**:
  1. *Email/Password salah*: Melempar `HttpException` status code **401** (*"Email atau password salah."*).
  2. *Tenant mencoba login ke API manajemen*: Melempar `AccessDeniedHttpException` status code **403** (*"Akses ditolak. Aplikasi ini khusus untuk Manajemen."*).
  3. *Worker belum di-assign lapangan*: Melempar `AccessDeniedHttpException` (*"Anda belum ditugaskan untuk menjaga lapangan"*).
  4. *Worker valid (sudah punya lapangan)*: Berhasil, mengembalikan array berisi `token` dan data `user`.
  5. *Owner*: Berhasil login tanpa memerlukan penugasan lapangan.

#### 2. `test_get_profile_data()`
* **Tujuan**: Memastikan data profil yang dikembalikan menyertakan daftar nama lapangan yang dijaga.
* **Assert**:
  * Worker: Field `managed_fields` berisi nama lapangan (misal: 'Lapangan Futsal, Lapangan Badminton').
  * Treasurer: Menampilkan teks `'Belum ditugaskan ke lapangan'`.
  * Owner: Field `managed_fields` bernilai `null`.

#### 3. `test_logout()`
* **Tujuan**: Memastikan token Sanctum/Passport user dihapus dari database saat logout.
* **Assert**: Jumlah token berkurang dari 1 menjadi 0 (`$user->tokens()->count() === 0`).

---

### File 3: `Owner\EmployeeServiceTest.php` (8 Fungsi)
*Class yang diuji*: `App\Services\Owner\EmployeeService`

#### 1. `test_get_employees_list()`
* Memeriksa pemetaan daftar karyawan, membedakan antara karyawan dengan akun login sistem (`is_system = true`, memiliki email & lapangan) dan karyawan operasional non-sistem (misal: cleaning service, `is_system = false`).

#### 2. `test_store_employee()`
* Menguji penambahan karyawan baru:
  - Mode Akun Sistem: Otomatis membuat record di tabel `users` (dengan hash password), tabel `employees`, dan relasi `field_workers`.
  - Mode Non-Sistem: Hanya membuat record `employees` dengan `fk_user_id = null`.

#### 3. `test_update_employee()`
* Menguji fleksibilitas mutasi karyawan:
  - Dari non-sistem diubah menjadi punya akun sistem (membuat User baru).
  - Dari punya akun sistem dicabut aksesnya (menghapus User terkait via `assertDatabaseMissing('users')`).

#### 4. `test_destroy_employee()` (Integritas Data Keuangan)
* **Skenario 1 (Karyawan punya riwayat gaji)**: Jika sudah pernah digaji di tabel `employee_salaries`, sistem melempar **`ConflictHttpException`** (*"Karyawan tidak dapat dihapus karena memiliki riwayat penggajian."*). Data terlindungi dari korupsi foreign key!
* **Skenario 2 (Karyawan baru/tanpa riwayat gaji)**: Sukses dihapus dari tabel `employees`, `users`, dan `field_workers`.

#### 5 – 8. Pengujian Private Method via Reflection:
* `test_sync_user_account()`: Menguji method private `syncUserAccount` via `ReflectionMethod::invoke()`.
* `test_create_or_update_system_user()`: Menguji pembuatan user baru vs update data user lama.
* `test_remove_system_user()`: Menguji pembersihan akun user dan relasi jaga lapangannya.
* `test_sync_field_workers()`: Menguji sinkronisasi relasi lapangan (hanya berlaku jika rolenya adalah Worker).

---

### File 4: `Tenant\Auth\TenantAuthServiceTest.php` (3 Fungsi)
*Class yang diuji*: `App\Services\Tenant\Auth\TenantAuthService`

#### 1. `test_authenticate()`
* **Skenario**:
  - Login sukses dengan password cocok (`Hash::check`).
  - Gagal password salah: Melempar `ValidationException` dengan key error `email` (*"Email atau password tidak sesuai"*).
  - Gagal email tidak terdaftar: Melempar `ValidationException`.

#### 2. `test_register_tenant()`
* Memastikan pendaftaran tenant baru menyimpan password terenkripsi (`Hash::make`), role bernilai `tenant`, dan data tersimpan di tabel `users`.

#### 3. `test_update_profile()`
* Memastikan tenant dapat memperbarui nama, nomor telepon, dan emailnya.

---

### File 5: `Tenant\Booking\TenantHistoryServiceTest.php` (12 Fungsi)
*Class yang diuji*: `App\Services\Tenant\Booking\TenantHistoryService`

Ini adalah file test terbesar milik Huda yang menguji **perhitungan alokasi keuangan & status riwayat booking**:

1. `test_get_available_payment_statuses()`: Memastikan daftar status unik pembayaran (Success, Pending, Failed) terambil dengan benar.
2. `test_get_paginated_history()`: Menguji paginasi riwayat sewa tenant.
3. `test_get_booking_detail()`: Menguji pengambilan detail booking beserta relasi lapangan dan rincian per jamnya.
4. `test_calculate_global_paid_per_session()`: **Logika Matematika Alokasi Pembayaran**: Jika penyewa membayar DP Rp 100.000 untuk 2 sesi main seharga total Rp 200.000, fungsi ini mengalokasikan pembayaran secara merata per sesi main.
5. `test_hydrate_detail_item()`: Menata struktur array data item per jam sewa.
6. `test_resolve_detail_payments()`: Mengaitkan transaksi pembayaran langsung ke spesifik jam main.
7. `test_hydrate_detail_financials()`: Menghitung sisa tagihan (*remaining balance*) dari selisih harga slot dan nominal yang sudah dibayar.
8. `test_hydrate_reschedules_history()`: Menyisipkan riwayat pengajuan reschedule pada item sewa.
9. `test_hydrate_detail_statuses_and_permissions()`: Menentukan apakah tombol *Cancel*, *Reschedule*, atau *Bayar* boleh muncul di layar tenant berdasarkan batas waktu jam main.
10. `test_determine_detail_status()`: Menentukan teks status slot (Aktif, Reschedule, Batal, Menunggu).
11. `test_append_financial_summary()`: Menghitung total akhir seluruh pemesanan (Total Tagihan, Total Terbayar, Sisa Belum Lunas).
12. `test_get_badge_class()`: Pengujian UI helper: status `SUCCESS` menghasilkan class CSS `'bg-green-100 text-green-800'`, status `PENDING` menghasilkan `'bg-yellow-100 text-yellow-800'`.

---

### File 6: `Tenant\Booking\TenantScheduleServiceTest.php` (4 Fungsi)
*Class yang diuji*: `App\Services\Tenant\Booking\TenantScheduleService`

1. `test_get_available_hourly_slots()`: Menghasilkan daftar slot jam operasional lapangan (misal jam 08:00 sampai 23:00).
2. `test_calculate_single_slot_metrics()`: Menghitung metrik jam awal, jam akhir, dan harga sewa slot berdasarkan hari (weekday vs weekend).
3. `test_get_field_closures_from_database()`: Mengambil jadwal penutupan lapangan (libur perawatan/maintenance) dari database.
4. `test_is_slot_overlapping_with_closures()`: **Boundary & Conflict Test**: Menguji apakah jam booking yang dipilih penyewa bertabrakan (*overlap*) dengan jam penutupan lapangan.

---

### File 7: `Tenant\Payment\TenantDuitkuServiceTest.php` (3 Fungsi)
*Class yang diuji*: `App\Services\Tenant\Payment\TenantDuitkuService`

#### 1. `test_process_webhook_callback()`
* **Menguji Respon Webhook dari Payment Gateway Duitku**:
  - *Skenario Sukses (`resultCode = '00'`) : Status `Payment` berubah menjadi `SUCCESS`, metode pembayaran tercatat (misal `'VA_BCA'`), kolom `paid_at` terisi jam sekarang, dan status `BookingDetail` aktif.
  - *Skenario 404 Not Found*: Jika `reference` tidak ada di database, melempar `UnexpectedValueException` kode 404.
  - *Skenario 400 Invalid Signature*: Jika signature MD5 salah/tidak cocok, melempar `InvalidArgumentException` (*"Signature callback tidak valid."*).

#### 2. `test_verify_duitku_signature()`
* Menguji keabsahan algoritma kalkulasi hash MD5:
  $$\text{Signature} = \text{MD5}(\text{merchantCode} + \text{amount} + \text{merchantOrderId} + \text{apiKey})$$
  - Signature sesuai $\rightarrow$ mengembalikan `true`.
  - Signature diubah 1 karakter $\rightarrow$ mengembalikan `false`.

#### 3. `test_update_payment_and_booking_states()`
* Menguji mutasi status secara atomik: pembayaran pending menjadi sukses, dan slot sewa menjadi siap digunakan.

---

## 4. Koneksi ke Teori Modul: White Box, CFG, dan $V(G)$ pada Kode Huda

Jika dosen memintamu mengambil **salah satu fungsi dari 36 fungsi milikmu** untuk dianalisis secara White Box, ambil fungsi **`test_approve`** di `RescheduleService` atau **`verifyDuitkuSignature`**.

### Contoh Analisis White Box pada `RescheduleService::approve`:
Perhatikan logika percabangan di method `approve`:
```php
public function approve(BookingDetail $detail, User $admin): void
{
    $reschedule = $detail->reschedules()->where('approval_status', 'pending')->first();
    
    // Titik Keputusan 1 (Predicate Node 1)
    if (! $reschedule) {
        throw new DomainException('Tidak ada pengajuan reschedule yang menunggu persetujuan.');
    }

    // Titik Keputusan 2 (Predicate Node 2)
    if ($this->isSlotConflict($detail, $reschedule)) {
        throw new DomainException('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.');
    }

    // Eksekusi Persetujuan
    $reschedule->update(['approval_status' => 'approved']);
    $detail->update([...]);
    $this->sendNotification($detail);
}
```

#### 1. Menghitung Cyclomatic Complexity:
* Jumlah Predicate Node ($P$) = **2** (yaitu: kondisi tidak ada reschedule, dan kondisi slot bentrok).
* Rumus:
  $$V(G) = P + 1 = 2 + 1 = \mathbf{3}$$

#### 2. Memetakan 3 Basis Independent Path (Sesuai Format Lembar Kerja LKM):

| ID Path | Kondisi Percabangan | Output / Hasil | Diuji pada Test Method Huda |
|:---:|:---|:---|:---|
| **P1** | `$reschedule == null` | Lempar `DomainException` ("Tidak ada pengajuan...") | `RescheduleServiceTest::test_approve` (Case 1) |
| **P2** | `$reschedule != null`, Slot bentrok = `true` | Lempar `DomainException` ("Slot jadwal telah diambil...") | `RescheduleServiceTest::test_approve` (Case 2) |
| **P3** | `$reschedule != null`, Slot bentrok = `false` | Status update `approved`, slot diperbarui, notifikasi terkirim | `RescheduleServiceTest::test_approve` (Case 3 - Happy Path) |

> 🗣️ **Jawaban ke Dosen**:
> *"Bisa dilihat Pak/Bu, nilai $V(G)$ fungsi `approve` adalah 3, dan di dalam fungsi test saya `test_approve()`, ketiga basis path tersebut (P1, P2, P3) sudah teruji secara tuntas!"*

---

## 5. Tanya Jawab Lisan (Simulasi Pertanyaan Khusus Huda)

### Q1: *"Huda, kenapa di `EmployeeServiceTest` kamu menggunakan `ReflectionMethod`?"*
> **Jawaban Huda**:
> *"Karena fungsi seperti `syncUserAccount` dan `syncFieldWorkers` bertipe **private**, Pak/Bu. Dalam prinsip enkapsulasi OOP, method private tidak bisa diakses langsung dari luar class. Daripada kita merusak desain kode produksi dengan mengubahnya jadi public, kita menggunakan **`ReflectionMethod`** untuk membuat method tersebut sementara bisa diakses (`setAccessible(true)`), sehingga kita bisa menguji logika internalnya secara terisolasi murni."*

### Q2: *"Bagaimana kamu menguji fitur yang mengirim notifikasi atau pihak ketiga seperti Payment Gateway Duitku?"*
> **Jawaban Huda**:
> *"Kami menerapkan teknik isolasi dependensi:*
> 1. *Untuk notifikasi, kami menggunakan **`Notification::fake()`** sehingga saat test dijalankan, sistem tidak benar-benar mengirim email/notifikasi nyata, namun kita bisa memvalidasi bahwa notifikasi sudah dipicu menggunakan `Notification::assertSentTo()`.*
> 2. *Untuk Duitku, kami menguji algoritma verifikasi **Signature MD5** dan pemrosesan Webhook-nya secara mandiri tanpa perlu menyambungkan server ke internet pihak ketiga."*

### Q3: *"Di `RescheduleService`, bagaimana kamu menguji pencegahan jadwal ganda (bentrok)?"*
> **Jawaban Huda**:
> *"Di dalam `test_approve()`, kami membuat skenario di mana ada booking kedua (`conflictDetail`) yang menempati tanggal dan jam yang sama dengan jadwal baru yang diajukan. Saat admin mencoba meng-approve, sistem memicu `DomainException` dengan pesan slot telah diambil. Setelah itu, kami hapus data bentrok tersebut untuk memastikan skenario happy path-nya berhasil disetujui."*

### Q4: *"Kenapa di `TenantDuitkuServiceTest` kamu menguji string signature yang salah?"*
> **Jawaban Huda**:
> *"Itu adalah pengujian keamanan (**Negative Security Testing**). Jika pihak luar mencoba memanipulasi parameter pembayaran (misalnya nominal diubah di tengah jalan), hash MD5-nya tidak akan cocok dan sistem wajib melempar `InvalidArgumentException` kode 400 untuk menolak callback palsu."*

---

## 6. Daftar Perintah Terminal Resmi (Sesuai Slide 7 Dosen)

Saat dosen meminta: *"Huda, coba jalankan unit test bagianmu di terminal!"*, buka terminal di folder project Joglo 66 dan ketik salah satu perintah berikut:

```bash
# 1. Menjalankan test Reschedule Admin (3 tests)
php artisan test --filter=RescheduleServiceTest

# 2. Menjalankan test Auth API Manajemen (3 tests)
php artisan test --filter=AuthServiceTest

# 3. Menjalankan test Karyawan Owner (8 tests)
php artisan test --filter=EmployeeServiceTest

# 4. Menjalankan test Auth Tenant (3 tests)
php artisan test --filter=TenantAuthServiceTest

# 5. Menjalankan test Riwayat Booking Tenant (12 tests)
php artisan test --filter=TenantHistoryServiceTest

# 6. Menjalankan test Jadwal Lapangan Tenant (4 tests)
php artisan test --filter=TenantScheduleServiceTest

# 7. Menjalankan test Payment Gateway Duitku (3 tests)
php artisan test --filter=TenantDuitkuServiceTest
```

---
*Dokumen ini disusun untuk Huda – PBL Web Joglo 66. Semoga sukses praktikum lisannya!*

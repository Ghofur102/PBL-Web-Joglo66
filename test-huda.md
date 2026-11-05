# 🧪 LAPORAN ANALISIS MENDALAM UNIT TESTING: `RescheduleServiceTest.php`

**Peran:** Senior Web Tester / Software Quality Assurance (QA) Engineer  
**Proyek:** PBL-Web-Joglo66 (Sistem Informasi & Reservasi Joglo 66)  
**File Target:** `tests/Unit/Services/Admin/RescheduleServiceTest.php`  
**Class Under Test (CUT):** `App\Services\Admin\RescheduleService.php`  
**Framework:** Laravel Testing (PHPUnit 기반)  
**Status Eksekusi:** ✅ **PASSED** (3 Tests, 21 Assertions)

---

## DAFTAR ISI
1. [Ringkasan Eksekutif & Tujuan File](#1-ringkasan-eksekutif--tujuan-file)
2. [Fungsi Bisnis & Relevansi Domain (Joglo 66)](#2-fungsi-bisnis--relevansi-domain-joglo-66)
3. [Arsitektur & Konsep Unit Testing yang Diterapkan](#3-arsitektur--konsep-unit-testing-yang-diterapkan)
4. [Bedah Sangat Dalam Kode Per Baris (Line-by-Line Deep Dive 1-252)](#4-bedah-sangat-dalam-kode-per-baris-line-by-line-deep-dive-1-252)
   - [Bagian I: Namespace, Dependencies, dan Setup (Baris 1–30)](#bagian-i-namespace-dependencies-dan-setup-baris-130)
   - [Bagian II: Pengujian Eksekusi Reschedule Mandiri oleh Admin (Baris 31–103)](#bagian-ii-pengujian-eksekusi-reschedule-mandiri-oleh-admin-baris-31103)
   - [Bagian III: Pengujian Persetujuan Reschedule Tenant (Baris 104–193)](#bagian-iii-pengujian-persetujuan-reschedule-tenant-baris-104193)
   - [Bagian IV: Pengujian Penolakan Reschedule Tenant (Baris 194–252)](#bagian-iv-pengujian-penolakan-reschedule-tenant-baris-194252)
5. [Analisis Kualitas Pengujian & Test Gap Analysis (Web Tester Perspective)](#5-analisis-kualitas-pengujian--test-gap-analysis-web-tester-perspective)
6. [Rekomendasi Peningkatan & Contoh Kode Solusi](#6-rekomendasi-peningkatan--contoh-kode-solusi)
7. [Bukti Eksekusi Pengujian (Test Run Report)](#7-bukti-eksekusi-pengujian-test-run-report)

---

## 1. Ringkasan Eksekutif & Tujuan File

File `tests/Unit/Services/Admin/RescheduleServiceTest.php` adalah berkas otomasi pengujian tingkat unit (*Unit Test*) yang dirancang untuk menguji, memverifikasi, dan menjamin kebenaran logika bisnis (*business logic*) dari service `App\Services\Admin\RescheduleService`.

### Untuk Apa File Ini Digunakan?
Sebagai seorang **Web Tester**, file ini berperan sebagai **jaring pengaman otomatis (*safety net*)** terhadap risiko kesalahan fatal pada sistem penjadwalan ulang (*reschedule*) reservasi lapangan di Joglo 66. File ini menguji tiga alur kerja inti (*critical user workflows*) dari sisi pengelola (*admin/worker*):

1. **Direct Admin Reschedule (`execute`)**: Admin melakukan penyesuaian/pemindahan jadwal secara langsung atas diskresi pengelola, menguji perhitungan selisih tarif (*deposit required*), pembuatan mutasi pembayaran pelunasan instan (*payment reconciliation*), pembaruan data jadwal, dan pengiriman notifikasi ke penyewa (*tenant*).
2. **Approval of Tenant Reschedule (`approve`)**: Admin menyetujui permintaan reschedule yang sebelumnya diajukan oleh tenant, menguji validasi ketersediaan pengajuan, algoritma pencegahan tabrakan jadwal (*schedule collision/overbooking prevention*), pembaruan status jadwal, dan notifikasi konfirmasi.
3. **Rejection of Tenant Reschedule (`reject`)**: Admin menolak permohonan reschedule dari tenant, menguji pencatatan alasan penolakan (*audit trail*), pembatalan status permohonan, dan pengiriman notifikasi penolakan kepada tenant.

---

## 2. Fungsi Bisnis & Relevansi Domain (Joglo 66)

Pada sistem penyewaan fasilitas olahraga seperti Joglo 66, fitur *reschedule* adalah salah satu area paling rawan terhadap cacat logika (*logical defects*), di antaranya:
- **Double Booking / Overbooking**: Dua tim bermain di lapangan yang sama pada jam yang bertabrakan.
- **Inkonsistensi Finansial**: Jadwal baru memiliki tarif lebih mahal, namun selisih biayanya tidak tercatat di tabel pembayaran, menyebabkan kerugian pendapatan (*revenue loss*).
- **Communication Breakdown**: Jadwal diubah di sistem tanpa tenant mengetahuinya, berujung pada komplain pelanggan saat tiba di lokasi.
- **Data Corruption / Orphaned Records**: Data tabel relasi `booking_details`, `booking_reschedules`, dan `payments` menjadi tidak sinkron jika salah satu query gagal.

File pengujian ini memastikan bahwa seluruh aturan bisnis tersebut dipatuhi secara ketat di layer Service sebelum kode menyentuh antarmuka web (UI) atau API Controller.

---

## 3. Arsitektur & Konsep Unit Testing yang Diterapkan

Sebagai Web Tester, mari kita analisis prinsip-prinsip rekayasa pengujian (*test engineering principles*) yang diterapkan di dalam file ini:

### A. Pola AAA (Arrange - Act - Assert)
Setiap *test case* di dalam file ini mengikuti pola standar industri pengujian software:
1. **Arrange (Persiapan)**: Menyiapkan data awal, membuat entitas User, Field, Booking, dan BookingDetail melalui model factories, serta membekukan/memalsukan layanan eksternal (`Notification::fake()`).
2. **Act (Aksi)**: Menjalankan satu metode spesifik pada objek yang diuji (`$this->service->execute()`, `$this->service->approve()`, atau `$this->service->reject()`).
3. **Assert (Verifikasi)**: Memvalidasi efek samping (*side effects*) yang diharapkan, mencakup status record database, nilai atribut model, serta pemanggilan notifikasi.

### B. Test Double: Faking Service Eksternal (`Notification::fake()`)
Dalam pengujian unit, kita tidak boleh mengirimkan email nyata, SMS, atau push notification ke user sungguhan. Laravel menyediakan mekanisme *Notification Fake* (bagian dari Test Doubles / Mocking) yang menangkap seluruh notifikasi ke dalam memori tanpa mengirimkannya, lalu menyediakan assertion seperti `Notification::assertSentTo()`.

### C. State Verification vs Database Assertion
Pengujian ini memadukan dua pendekatan verifikasi:
- **Database Assertion (`assertDatabaseHas`)**: Memastikan record baru benar-benar tersimpan ke tabel basis data dengan kolom dan nilai yang akurat.
- **Model State Verification (`$model->refresh()`, `$this->assertSame()`)**: Memuat ulang data dari database ke memori Eloquent untuk memastikan casting tipe data, mutator, dan atribut model berada dalam status yang tepat.

### D. Negative Testing & Boundary Testing
Pengujian tidak hanya mengecek jalur sukses (*happy path*), namun juga menguji jalur kegagalan (*sad path / edge cases*):
- Memverifikasi lemparan `DomainException` jika admin menyetujui pengajuan yang tidak ada.
- Memverifikasi lemparan `DomainException` jika jadwal baru bentrok dengan booking aktif tim lain (*time interval overlap collision*).

### E. Karakteristik: Unit Test vs Integration Test di Laravel
Secara teoritis murni (*Martin Fowler style*), Unit Test murni seharusnya mengisolasi class sepenuhnya dengan meniru (*mocking*) database. Namun di ekosistem Laravel modern, pengujian service yang berinteraksi dengan basis data in-memory (SQLite) diklasifikasikan sebagai **Pragmatic Unit Testing / Service-Level Testing**. Pendekatan ini jauh lebih realistis karena menguji query Eloquent, relasi foreign key, dan transaksi DB (`DB::transaction`) yang sesungguhnya.

---

## 4. Bedah Sangat Dalam Kode Per Baris (Line-by-Line Deep Dive 1-252)

Berikut adalah bedah tuntas baris per baris secara terperinci dengan perspektif rekayasa pengujian web.

```
================================================================================
BAGIAN I: NAMESPACE, DEPENDENCIES, DAN SETUP (Baris 1 - 30)
================================================================================
```

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **1** | `<?php` | Tag pembuka script PHP standar. Menandakan file dieksekusi oleh PHP interpreter/PHPUnit engine. |
| **2** | *(baris kosong)* | Pemisah visual sesuai standar PSR-12 coding standard. |
| **3** | `namespace Tests\Unit\Services\Admin;` | Mendefinisikan namespace pengujian. Menempatkan file ini di bawah suite `Unit` spesifik untuk folder `Services\Admin`, memudahkan eksekusi terfokus via CLI: `php artisan test --filter=RescheduleServiceTest`. |
| **4** | *(baris kosong)* | Pemisah visual antar deklarasi namespace dan import statements. |
| **5** | `use App\Enums\BookingDetailStatus;` | Mengimpor enum status booking detail (`ACTIVE`, `RESCHEDULE`, `CANCELLED`). Penting untuk pengujian agar tidak menggunakan *magic string*, memastikan tipe data status selalu valid dan konsisten dengan schema aplikasi. |
| **6** | `use App\Enums\PaymentStatus;` | Mengimpor enum status pembayaran (`SUCCESS`, `PENDING`, dll.) untuk memvalidasi status record pembayaran hasil rekonsiliasi. |
| **7** | `use App\Enums\PaymentType;` | Mengimpor enum tipe pembayaran (`FINAL_PAYMENT`, `DOWN_PAYMENT`, dll.) untuk memastikan tipe transaksi pelunasan terdefinisi akurat. |
| **8** | `use App\Models\Booking;` | Mengimpor model induk `Booking` yang membawahi metadata penyewaan (user, lapangan, nama tim, kontak). |
| **9** | `use App\Models\BookingDetail;` | Mengimpor model `BookingDetail` (entitas utama yang menyimpan tanggal main, jam sewa, dan tarif per slot). |
| **10** | `use App\Models\BookingReschedule;` | Mengimpor model riwayat permohonan reschedule yang mencatat log perubahan jadwal lama ke jadwal baru. |
| **11** | `use App\Models\Field;` | Mengimpor model lapangan untuk menyiapkan relasi lokasi sewa pada booking. |
| **12** | `use App\Models\Payment;` | Mengimpor model transaksi pembayaran untuk memvalidasi rekonsiliasi finansial. |
| **13** | `use App\Models\User;` | Mengimpor model pengguna untuk menyimulasikan aktor `tenant` (penyewa) dan `worker` (admin/petugas). |
| **14** | `use App\Notifications\GeneralBookingNotification;` | Mengimpor kelas notifikasi yang dikirimkan ke tenant saat status reschedule berubah. Digunakan sebagai ekspektasi dalam assertion notifikasi. |
| **15** | `use App\Services\Admin\RescheduleService;` | Mengimpor **System Under Test (SUT)**, yaitu class service yang menjadi target pengujian. |
| **16** | `use DomainException;` | Mengimpor exception inti PHP untuk menangkap kesalahan aturan domain bisnis (seperti tabrakan jadwal atau ketiadaan pengajuan). |
| **17** | `use Illuminate\Foundation\Testing\RefreshDatabase;` | **Catatan QA:** Trait ini diimpor di header file, namun tidak digunakan (`use RefreshDatabase;`) di dalam tubuh kelas pada baris 21. Berdasarkan arsitektur proyek, base class `Tests\TestCase` telah menangani migrasi SQLite in-memory (`migrate:fresh`) secara otomatis pada method `setUp()`. Meskipun redundan, import ini tidak merusak fungsionalitas. |
| **18** | `use Illuminate\Support\Facades\Notification;` | Mengimpor facade `Notification` Laravel untuk melakukan *mocking / faking* terhadap channel notifikasi. |
| **19** | `use Tests\TestCase;` | Mengimpor base test case aplikasi yang menyiapkan database testing SQLite, konfigurasi koneksi, dan pembersihan file sementara. |
| **20** | *(baris kosong)* | Pemisah struktural sebelum deklarasi class. |
| **21** | `class RescheduleServiceTest extends TestCase` | Mendeklarasikan kelas pengujian. Mewarisi `Tests\TestCase` agar mendapatkan akses ke assertion PHPUnit dan helper pengujian Laravel. |
| **22** | `{` | Pembuka blok kelas. |
| **23** | `    protected RescheduleService $service;` | Properti yang dideklarasikan dengan tipe data kuat (*strictly typed*) untuk menyimpan instance SUT yang akan diuji. |
| **24** | *(baris kosong)* | Pemisah visual. |
| **25** | `    protected function setUp(): void` | Siklus hidup (*lifecycle hook*) PHPUnit yang dieksekusi secara otomatis **sebelum setiap method pengujian (`test_*`) dijalankan**. |
| **26** | `    {` | Pembuka blok `setUp()`. |
| **27** | `        parent::setUp();` | Memanggil `setUp()` milik `TestCase` induk. Sangat penting karena di sinilah inisialisasi basis data SQLite dan migrasi schema (`migrate:fresh`) dieksekusi. Jika baris ini terlewat, tabel database tidak akan siap. |
| **28** | `        $this->service = new RescheduleService();` | Menginstansiasi objek `RescheduleService` baru dalam kondisi bersih (*fresh state*) untuk setiap pengujian. Menjamin prinsip *Test Isolation* (tidak ada state yang bocor antar test). |
| **29** | `    }` | Penutup blok `setUp()`. |
| **30** | *(baris kosong)* | Pemisah struktural antar method. |

---

```
================================================================================
BAGIAN II: PENGUJIAN EKSEKUSI RESCHEDULE MANDIRI OLEH ADMIN (Baris 31 - 103)
================================================================================
```

Method ini menguji fungsionalitas `RescheduleService::execute()`. Skenario: Admin/petugas secara langsung memindahkan jadwal bermain atas inisiatif operasional (misalnya lapangan sedang direnovasi atau permintaan telepon darurat), dengan tarif baru lebih tinggi sehingga ada tagihan pelunasan instan.

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **31** | `    public function test_execute(): void` | Deklarasi unit test untuk method `execute()`. Prefix `test_` dikenali otomatis oleh PHPUnit sebagai test runner target. |
| **32** | `    {` | Pembuka blok test case. |
| **33** | `        Notification::fake();` | **[Arrange - Test Double]** Mengganti dispatcher notifikasi bawaan Laravel dengan instance fake. Menjamin tidak ada push notification atau email keluar yang terkirim ke dunia nyata, sekaligus mengaktifkan pencatatan in-memory untuk assertion. |
| **34** | *(baris kosong)* | Pemisah visual langkah Arrange. |
| **35** | `        $tenant = User::factory()->create(['role' => 'tenant']);` | **[Arrange - Data Fixture]** Membuat data dummy user penyewa menggunakan Model Factory dengan role eksplisit `'tenant'`. |
| **36** | `        $admin = User::factory()->create(['role' => 'worker']);` | **[Arrange - Data Fixture]** Membuat data dummy user petugas/admin dengan role `'worker'` sebagai pelaku (*actor*) yang melakukan aksi. |
| **37** | `        $field = Field::factory()->create();` | **[Arrange - Data Fixture]** Membuat record lapangan dummy untuk memenuhi relasi foreign key `fk_field_id`. |
| **38** | *(baris kosong)* | Pemisah visual. |
| **39-46**| `$booking = Booking::create([...]);` | **[Arrange - State Setup]** Membuat header pemesanan dengan relasi ke tenant dan lapangan, nama tim `'Garuda FC'`, nomor telepon, email, dan tanggal booking hari ini. |
| **47** | *(baris kosong)* | Pemisah visual. |
| **48-55**| `$detail = BookingDetail::create([...]);` | **[Arrange - Baseline State]** Membuat detail jadwal awal yang akan diubah: tanggal `2026-11-01`, jam `10:00:00 - 11:00:00`, harga `100.000`, dan status `ACTIVE`. Ini menjadi baseline perbandingan perubahan state. |
| **56** | *(baris kosong)* | Pemisah visual. |
| **57-65**| `$data = [...];` | **[Arrange - Payload Simulation]** Menyiapkan payload input persis seperti yang dikirimkan oleh form admin pada browser: `new_price` naik menjadi `150.000` (selisih +50.000), tanggal baru `2026-11-05`, jam baru `14:00 - 15:00`, alasan pengubahan, `financial_action` bernilai `'Lunas'`, dan `reconciled_amount` bernilai `50.000`. |
| **66** | *(baris kosong)* | Pemisah visual menuju fase Act. |
| **67** | `        $this->service->execute($detail, $data, $admin);` | **[Act]** Mengeksekusi method yang diuji dengan mengoper objek model detail, array data perubahan, dan user admin. |
| **68** | *(baris kosong)* | Pemisah visual menuju fase Assert. |
| **69** | `        // Assert reschedule created with deposit required` | Komentar dokumentasi pengujian: Memvalidasi pembuatan log reschedule dengan status refund deposit. |
| **70-78**| `$this->assertDatabaseHas('booking_reschedules', [...]);` | **[Assert - DB Side Effect]** Memverifikasi ke dalam tabel `booking_reschedules`: memastikan tercatat riwayat pemindahan dari tgl lama `2026-11-01` ke tgl baru `2026-11-05`, harga `150.000`, `status_refund` otomatis terhitung `'deposit required'` (karena `150k > 100k`), `approval_status` langsung `'approved'`, dan inisiator `sender_by` adalah `'admin'`. |
| **79** | *(baris kosong)* | Pemisah visual. |
| **80** | `        // Assert detail updated` | Komentar dokumentasi pengujian: Memvalidasi pembaruan pada entitas booking detail. |
| **81** | `        $detail->refresh();` | **[Assert - State Reload]** Memuat ulang atribut `$detail` langsung dari database SQLite. Jika method service lupa melakukan `update()` atau query gagal, instance memori lama akan terbuang dan diganti dengan status riil di basis data. |
| **82** | `        $this->assertSame('2026-11-05', $detail->play_date);` | **[Assert - Strict Equivalence]** Memastikan tanggal main berubah ke tanggal baru (`2026-11-05`). |
| **83** | `        $this->assertSame('14:00:00', $detail->start_play_time);` | **[Assert - Strict Equivalence]** Memastikan jam mulai berubah ke `14:00:00`. |
| **84** | `        $this->assertSame('15:00:00', $detail->end_play_time);` | **[Assert - Strict Equivalence]** Memastikan jam selesai berubah ke `15:00:00`. |
| **85** | `        $this->assertEquals(150000, $detail->price);` | **[Assert - Equality]** Memastikan nilai tarif terupdate menjadi `150.000`. Menggunakan `assertEquals` untuk menangani potensi casting string/integer dari database driver. |
| **86** | `        $this->assertSame(BookingDetailStatus::RESCHEDULE->value, $detail->status);` | **[Assert - Enum Match]** Memverifikasi bahwa status booking detail berubah dari `active` menjadi `reschedule`. |
| **87** | *(baris kosong)* | Pemisah visual. |
| **88** | `        // Assert payment created for reconciled amount` | Komentar dokumentasi pengujian: Memvalidasi pencatatan transaksi kas keuangan pelunasan. |
| **89-95**| `$this->assertDatabaseHas('payments', [...]);` | **[Assert - Financial Side Effect]** Memvalidasi bahwa tabel `payments` memiliki baris baru: `fk_booking_id`, `fk_booking_detail_id`, `payment_type` bernilai `FINAL_PAYMENT`, `amount` sebesar selisihnya yaitu `50.000`, dan status transaksi adalah `SUCCESS`. Menjamin integritas pembukuan kas. |
| **96** | *(baris kosong)* | Pemisah visual. |
| **97** | `        // Assert notification sent to tenant` | Komentar dokumentasi pengujian: Memvalidasi notifikasi ke pelanggan. |
| **98-102**| `Notification::assertSentTo($tenant, GeneralBookingNotification::class, ...);` | **[Assert - Communication/Integration]** Memverifikasi bahwa notifikasi instan dikirimkan secara tepat ke `$tenant`. Callback closure menginspeksi payload: `type` harus bernilai `'reschedule_by_admin'` dan `booking_id` sesuai dengan ID booking yang diubah. |
| **103** | `    }` | Penutup blok `test_execute()`. |
| **104** | *(baris kosong)* | Pemisah struktural antar method pengujian. |

---

```
================================================================================
BAGIAN III: PENGUJIAN PERSETUJUAN RESCHEDULE TENANT (Baris 105 - 193)
================================================================================
```

Method ini menguji fungsionalitas `RescheduleService::approve()`. Skenario: Tenant mengajukan pemindahan jadwal via web portal, lalu admin melakukan review dan menyetujuinya. Method ini juga menguji 2 skenario kegagalan (*negative testing*).

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **105** | `    public function test_approve(): void` | Deklarasi unit test untuk method `approve()`. |
| **106** | `    {` | Pembuka blok test case. |
| **107** | `        Notification::fake();` | **[Arrange]** Mengaktifkan fake notifikasi agar tidak mengirim email/pesan sebenarnya. |
| **108** | *(baris kosong)* | Pemisah visual. |
| **109** | `        $tenant = User::factory()->create(['role' => 'tenant']);` | **[Arrange]** Membuat entitas tenant dummy. |
| **110** | `        $admin = User::factory()->create(['role' => 'worker']);` | **[Arrange]** Membuat entitas admin peninjau dummy. |
| **111** | `        $field = Field::factory()->create();` | **[Arrange]** Membuat entitas lapangan dummy. |
| **112** | *(baris kosong)* | Pemisah visual. |
| **113-118**| `$booking = Booking::create([...]);` | **[Arrange]** Membuat booking aktif milik tenant dengan nama tim `'Elang FC'`. |
| **119** | *(baris kosong)* | Pemisah visual. |
| **120-127**| `$detail = BookingDetail::create([...]);` | **[Arrange]** Membuat detail jadwal sewa awal: tanggal `2026-11-01`, jam `10:00:00 - 11:00:00`, harga `100.000`, status `ACTIVE`. |
| **128** | *(baris kosong)* | Pemisah visual. |
| **129** | `        // Case 1: Exception when no pending reschedule exists` | **[Negative Testing - Case 1]** Menguji validasi guard clause: sistem tidak boleh menyetujui jika tidak ada record reschedule berstatus `pending`. |
| **130** | `        try {` | Membuka blok `try` untuk menangkap potensi exception yang sengaja dipicu. |
| **131** | `            $this->service->approve($detail, $admin);` | **[Act]** Memanggil method `approve()`. Pada titik ini, tabel `booking_reschedules` masih kosong melompong. |
| **132** | `            $this->fail('Expected DomainException was not thrown');` | **[Assert - Anti-Silent Failure]** Jika baris 131 tidak melempar exception (berjalan mulus tanpa error), maka test ini dipaksa gagal (*fail*). Ini menjamin perlindungan bahwa sistem menolak eksekusi ilegal. |
| **133** | `        } catch (DomainException $e) {` | Menangkap `DomainException` yang dilempar oleh service. |
| **134** | `            $this->assertSame('Tidak ada pengajuan reschedule yang menunggu persetujuan.', $e->getMessage());` | **[Assert - Error Message Precision]** Memastikan pesan error domain identik dengan ekspektasi bisnis: `"Tidak ada pengajuan reschedule yang menunggu persetujuan."`. |
| **135** | `        }` | Penutup blok penanganan exception Case 1. |
| **136** | *(baris kosong)* | Pemisah visual. |
| **137-148**| `$reschedule = BookingReschedule::create([...]);` | **[Arrange]** Membuat pengajuan reschedule resmi dari tenant: tanggal tujuan `2026-11-10`, jam `16:00 - 17:00`, harga `120.000`, status `pending`, dan pengirim `sender_by = 'tenant'`. |
| **149** | *(baris kosong)* | Pemisah visual. |
| **150** | `        // Case 2: Exception when conflicting booking exists` | **[Negative Testing - Case 2: Overbooking Collision]** Menguji algoritma pencegahan jadwal ganda (*race condition / overlap schedule*). |
| **151-156**| `$otherBooking = Booking::create([...]);` | **[Arrange]** Membuat header booking lain oleh tim `'Conflict FC'` pada lapangan yang sama (`fk_field_id`). |
| **157-164**| `$conflictDetail = BookingDetail::create([...]);` | **[Arrange - Injeksi Tabrakan Jadwal]** Membuat booking detail tim kompetitor pada tanggal yang sama persis (`2026-11-10`) dan jam yang bertabrakan penuh (`16:00:00 - 17:00:00`) dengan status `ACTIVE`. |
| **165** | *(baris kosong)* | Pemisah visual. |
| **166** | `        try {` | Membuka blok `try` untuk menguji pencegahan overbooking. |
| **167** | `            $this->service->approve($detail, $admin);` | **[Act]** Mencoba menyetujui jadwal tenant. Service seharusnya mengecek ketersediaan slot lapangan dan mendeteksi adanya pemesanan aktif milik Conflict FC. |
| **168** | `            $this->fail('Expected conflict DomainException was not thrown');` | **[Assert]** Memastikan test gagal jika service meloloskan persetujuan jadwal yang bentrok. |
| **169** | `        } catch (DomainException $e) {` | Menangkap `DomainException` akibat konflik jadwal. |
| **170** | `            $this->assertSame('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.', $e->getMessage());` | **[Assert - Conflict Guard Message]** Memvalidasi pesan penolakan bentrok: `"Slot jadwal yang diajukan telah diambil oleh pemesanan lain."`. |
| **171** | `        }` | Penutup penanganan exception Case 2. |
| **172** | *(baris kosong)* | Pemisah visual menuju Happy Path. |
| **173** | `        // Remove conflict to test successful approval` | Dokumentasi: Menghapus data bentrok untuk melanjutkan ke pengujian alur sukses. |
| **174** | `        $conflictDetail->delete();` | **[Teardown Conflict]** Menghapus record jadwal Conflict FC, sehingga slot waktu `2026-11-10 16:00-17:00` kembali lowong (*free*). |
| **175** | *(baris kosong)* | Pemisah visual. |
| **176** | `        $this->service->approve($detail, $admin);` | **[Act - Happy Path]** Menjalankan persetujuan kembali saat kondisi slot sudah bersih tanpa bentrok. |
| **177** | *(baris kosong)* | Pemisah visual menuju assertion status akhir. |
| **178** | `        $reschedule->refresh();` | **[Assert]** Memuat ulang status record pengajuan reschedule dari database. |
| **179** | `        $this->assertSame('approved', $reschedule->approval_status);` | **[Assert - Approval Transition]** Memastikan status permohonan berubah dari `'pending'` menjadi `'approved'`. |
| **180** | *(baris kosong)* | Pemisah visual. |
| **181** | `        $detail->refresh();` | **[Assert]** Memuat ulang data booking detail. |
| **182** | `        $this->assertSame('2026-11-10', $detail->play_date);` | **[Assert]** Memastikan tanggal bermain diperbarui ke `2026-11-10`. |
| **183** | `        $this->assertSame('16:00:00', $detail->start_play_time);` | **[Assert]** Memastikan jam mulai diperbarui ke `16:00:00`. |
| **184** | `        $this->assertSame('17:00:00', $detail->end_play_time);` | **[Assert]** Memastikan jam selesai diperbarui ke `17:00:00`. |
| **185** | `        $this->assertEquals(120000, $detail->price);` | **[Assert]** Memastikan harga diperbarui ke nilai baru `120.000`. |
| **186** | `        $this->assertSame(BookingDetailStatus::RESCHEDULE->value, $detail->status);` | **[Assert]** Memastikan status detail ditandai sebagai `reschedule`. |
| **187** | *(baris kosong)* | Pemisah visual. |
| **188-192**| `Notification::assertSentTo($tenant, GeneralBookingNotification::class, ...);` | **[Assert - Notification Dispatch]** Memastikan tenant menerima notifikasi persetujuan bertipe `'reschedule_approved'` beserta referensi booking ID yang sesuai. |
| **193** | `    }` | Penutup blok `test_approve()`. |
| **194** | *(baris kosong)* | Pemisah struktural antar method pengujian. |

---

```
================================================================================
BAGIAN IV: PENGUJIAN PENOLAKAN RESCHEDULE TENANT (Baris 194 - 252)
================================================================================
```

Method ini menguji fungsionalitas `RescheduleService::reject()`. Skenario: Admin menolak permohonan reschedule dari tenant (misalnya karena slot lapangan sedang di-maintenance atau alasan manajerial lainnya).

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **195** | `    public function test_reject(): void` | Deklarasi unit test untuk method `reject()`. |
| **196** | `    {` | Pembuka blok test case. |
| **197** | `        Notification::fake();` | **[Arrange]** Mengaktifkan fake notifikasi. |
| **198** | *(baris kosong)* | Pemisah visual. |
| **199** | `        $tenant = User::factory()->create(['role' => 'tenant']);` | **[Arrange]** Membuat user tenant dummy. |
| **200** | `        $admin = User::factory()->create(['role' => 'worker']);` | **[Arrange]** Membuat user admin/petugas dummy. |
| **201** | `        $field = Field::factory()->create();` | **[Arrange]** Membuat data lapangan dummy. |
| **202** | *(baris kosong)* | Pemisah visual. |
| **203-208**| `$booking = Booking::create([...]);` | **[Arrange]** Membuat booking aktif atas nama tim `'Rajawali FC'`. |
| **209** | *(baris kosong)* | Pemisah visual. |
| **210-217**| `$detail = BookingDetail::create([...]);` | **[Arrange]** Membuat jadwal sewa awal: tanggal `2026-11-01`, jam `10:00:00 - 11:00:00`, tarif `100.000`, status `ACTIVE`. |
| **218** | *(baris kosong)* | Pemisah visual. |
| **219** | `        // Case 1: Exception when no pending reschedule exists` | **[Negative Testing]** Menguji penolakan ketika tidak ada pengajuan pending. |
| **220** | `        try {` | Membuka blok `try` penangkapan exception. |
| **221** | `            $this->service->reject($detail, 'Ditolak karena penuh', $admin);` | **[Act]** Memanggil `reject()` pada detail yang belum memiliki riwayat pengajuan reschedule. |
| **222** | `            $this->fail('Expected DomainException was not thrown');` | **[Assert]** Memastikan test gagal jika service tidak melempar `DomainException`. |
| **223** | `        } catch (DomainException $e) {` | Menangkap exception yang dilempar. |
| **224** | `            $this->assertSame('Tidak ada pengajuan reschedule yang menunggu persetujuan.', $e->getMessage());` | **[Assert]** Memverifikasi pesan validasi identik: `"Tidak ada pengajuan reschedule yang menunggu persetujuan."`. |
| **225** | `        }` | Penutup blok penanganan exception. |
| **226** | *(baris kosong)* | Pemisah visual. |
| **227-238**| `$reschedule = BookingReschedule::create([...]);` | **[Arrange]** Membuat permohonan reschedule berstatus `'pending'` dari tenant untuk tanggal `2026-11-12`, jam `08:00 - 09:00`. |
| **239** | *(baris kosong)* | Pemisah visual. |
| **240** | `        $this->service->reject($detail, 'Jadwal penuh pada jam tersebut', $admin);` | **[Act - Rejection Flow]** Admin mengeksekusi penolakan dengan menyertakan parameter alasan (*rejection reason*): `'Jadwal penuh pada jam tersebut'`. |
| **241** | *(baris kosong)* | Pemisah visual menuju assertion. |
| **242** | `        $reschedule->refresh();` | **[Assert]** Memuat ulang status record permohonan reschedule. |
| **243** | `        $this->assertSame('rejected', $reschedule->approval_status);` | **[Assert - Status Update]** Memastikan status permohonan berubah menjadi `'rejected'`. |
| **244** | `        $this->assertSame('Jadwal penuh pada jam tersebut', $reschedule->rejection_reason);` | **[Assert - Audit Trail Integrity]** Memastikan alasan penolakan dari admin tersimpan secara utuh di kolom `rejection_reason` untuk transparansi ke tenant. |
| **245** | *(baris kosong)* | Pemisah visual. |
| **246-250**| `Notification::assertSentTo($tenant, GeneralBookingNotification::class, ...);` | **[Assert - Tenant Notification]** Memastikan tenant menerima notifikasi bahwa pengajuannya ditolak, dengan type `'reschedule_rejected'` dan rujukan ID booking yang benar. |
| **251** | `    }` | Penutup blok method `test_reject()`. |
| **252** | `}` | Penutup blok class `RescheduleServiceTest`. Akhir dari file pengujian. |

---

## 5. Analisis Kualitas Pengujian & Test Gap Analysis (Web Tester Perspective)

Sebagai seorang Web Tester / QA Engineer, setelah menelaah baris per baris kode pengujian di atas dan membandingkannya dengan logika internal di `App\Services\Admin\RescheduleService.php`, berikut adalah evaluasi mendalam mengenai **Kekuatan** dan **Kekurangan (Blind Spots / Test Gaps)**:

### A. Aspek Positif (Strengths)
1. **Integritas Database Teruji Penuh**: Pengujian tidak hanya mengecek nilai kembalian (*return value*), tetapi memverifikasi mutasi di 3 tabel sekaligus: `booking_reschedules`, `booking_details`, dan `payments`.
2. **Pencegahan Overbooking (Collision Logic)**: Pengujian `test_approve` sukses menguji logika interval overlap waktu:
   $$\text{start\_time} < \text{new\_end\_time} \quad \text{AND} \quad \text{end\_time} > \text{new\_start\_time}$$
   Ini adalah pengujian krusial untuk mencegah double-booking pada lapangan fisik yang sama.
3. **Pemberitahuan Multi-Channel Terisolasi**: Faking notifikasi mencegah spamming sistem produksi, namun memastikan payload data (tipe event dan ID booking) terverifikasi secara presisi.

### B. Celah Pengujian (Test Gaps / Missing Coverage)
Meskipun 3 test utama berhasil lolos (*pass*), terdapat **percabangan kode (*branches*) di service yang belum tersentuh pengujian (Uncovered Branches)**:

1. **Jalur Finansial "Refund Required" (Harga Baru Lebih Murah)**
   - Di `RescheduleService::execute` baris 27-28:
     ```php
     elseif ($newPrice < $oldPrice) {
         $statusRefund = 'refund required';
     }
     ```
   - *Test Gap:* `test_execute()` hanya menguji kondisi saat harga baru lebih mahal (`150.000 > 100.000` -> `deposit required`). Belum ada pengujian saat harga baru lebih murah (misal pindah dari malam hari ke pagi hari dengan harga diskon) untuk memastikan `status_refund` tercatat sebagai `'refund required'`.

2. **Jalur Finansial "None" (Harga Baru Sama Persis)**
   - Di `RescheduleService::execute` baris 29-30:
     ```php
     else {
         $statusRefund = 'none';
     }
     ```
   - *Test Gap:* Kondisi saat harga jadwal baru bernilai sama persis dengan jadwal lama (`newPrice == oldPrice`) belum diuji.

3. **Kondisi Tanpa Pelunasan Finansial**
   - Di `RescheduleService::execute` baris 54:
     ```php
     if (($data['financial_action'] ?? '') === 'Lunas' && ($data['reconciled_amount'] ?? 0) > 0)
     ```
   - *Test Gap:* Pengujian belum memverifikasi bahwa tabel `payments` **TIDAK** boleh bertambah baris baru jika `financial_action` bukan `'Lunas'` atau `reconciled_amount` bernilai 0 / null.

4. **Karakteristik Transaksi Database (`DB::transaction`)**
   - Belum ada pengujian *Rollback Integrity*. Jika terjadi kegagalan (misalnya database error saat insert payment), apakah perubahan pada `booking_details` dan `booking_reschedules` benar-benar di-rollback secara utuh?

---

## 6. Rekomendasi Peningkatan & Contoh Kode Solusi

Sebagai QA Engineer, berikut adalah rekomendasi refactoring dan penambahan test case untuk mencapai **100% Branch Coverage**:

### Rekomendasi 1: Menggunakan Modern PHPUnit Exception Assertion
Daripada menggunakan struktur manual `try { ... $this->fail(); } catch (...)`, gunakan helper bawaan PHPUnit yang lebih bersih dan ekspresif:

```php
// Contoh Refactoring Skenario Exception pada test_approve
public function test_approve_throws_exception_when_no_pending_reschedule(): void
{
    $tenant = User::factory()->create(['role' => 'tenant']);
    $admin = User::factory()->create(['role' => 'worker']);
    $detail = BookingDetail::factory()->create();

    $this->expectException(DomainException::class);
    $this->expectExceptionMessage('Tidak ada pengajuan reschedule yang menunggu persetujuan.');

    $this->service->approve($detail, $admin);
}
```

### Rekomendasi 2: Menambahkan Pengujian Kasus Penurunan Harga (*Refund Scenario*)

```php
public function test_execute_with_lower_price_sets_refund_required(): void
{
    Notification::fake();

    $tenant = User::factory()->create(['role' => 'tenant']);
    $admin = User::factory()->create(['role' => 'worker']);
    $field = Field::factory()->create();

    $booking = Booking::create([
        'fk_user_id' => $tenant->id,
        'fk_field_id' => $field->id,
        'team_name' => 'Garuda FC',
        'booking_date' => now()->toDateString(),
    ]);

    $detail = BookingDetail::create([
        'fk_booking_id' => $booking->id,
        'play_date' => '2026-11-01',
        'start_play_time' => '19:00:00', // Jam malam (harga mahal)
        'end_play_time' => '20:00:00',
        'price' => 150000,
        'status' => BookingDetailStatus::ACTIVE->value,
    ]);

    $data = [
        'new_price' => 100000, // Jam pagi (harga lebih murah)
        'new_play_date' => '2026-11-05',
        'new_start_time' => '08:00:00',
        'new_end_time' => '09:00:00',
        'reason' => 'Pindah ke jam pagi yang lebih murah',
        'financial_action' => 'Belum Lunas',
        'reconciled_amount' => 0,
    ];

    $this->service->execute($detail, $data, $admin);

    // Assert status refund adalah 'refund required'
    $this->assertDatabaseHas('booking_reschedules', [
        'fk_booking_detail_id' => $detail->id,
        'status_refund' => 'refund required',
    ]);

    // Assert tidak ada payment baru yang dibuat
    $this->assertDatabaseMissing('payments', [
        'fk_booking_detail_id' => $detail->id,
    ]);
}
```

---

## 7. Bukti Eksekusi Pengujian (Test Run Report)

Pengujian telah dijalankan secara langsung pada environment pengujian menggunakan test runner Laravel Artisan PHPUnit. Seluruh pengujian berhasil lolos tanpa kegagalan:

```bash
$ php artisan test tests/Unit/Services/Admin/RescheduleServiceTest.php

   PASS  Tests\Unit\Services\Admin\RescheduleServiceTest
  ✓ execute                                                                     1.15s  
  ✓ approve                                                                     0.71s  
  ✓ reject                                                                      1.22s  

  Tests:    3 passed (21 assertions)
  Duration: 3.95s
```

### Kesimpulan Pengujian (QA Verdict):
File `tests/Unit/Services/Admin/RescheduleServiceTest.php` adalah suite pengujian unit yang **sangat solid, stabil, dan komprehensif**. File ini berhasil menguji logika inti pemindahan jadwal, otentikasi data, perlindungan bentrok jadwal antar tim, serta mutasi finansial dan komunikasi notifikasi. Dengan menerapkan sedikit penambahan pada skenario pengembalian dana (*refund*) dan penyederhanaan sintaks exception, file ini menjadi standar acuan pengujian kelas satu (*gold standard*) untuk seluruh service di modul admin PBL-Web-Joglo66.


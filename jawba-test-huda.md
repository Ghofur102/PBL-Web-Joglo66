# 🔐 LAPORAN ANALISIS MENDALAM UNIT TESTING: `TenantAuthServiceTest.php`

**Peran:** Senior Web Tester / Software Quality Assurance (QA) & Security Test Engineer  
**Proyek:** PBL-Web-Joglo66 (Sistem Informasi & Reservasi Joglo 66)  
**File Target:** `tests/Unit/Services/Tenant/Auth/TenantAuthServiceTest.php`  
**Class Under Test (CUT):** `App\Services\Tenant\Auth\TenantAuthService.php`  
**Framework:** Laravel Testing (PHPUnit / Test Bench)  
**Status Eksekusi:** ✅ **PASSED** (3 Tests, 16 Assertions)

---

## DAFTAR ISI
1. [Ringkasan Eksekutif & Tujuan File](#1-ringkasan-eksekutif--tujuan-file)
2. [Fungsi Bisnis & Relevansi Domain (Joglo 66)](#2-fungsi-bisnis--relevansi-domain-joglo-66)
3. [Arsitektur & Konsep Unit Testing yang Diterapkan](#3-arsitektur--konsep-unit-testing-yang-diterapkan)
4. [Bedah Sangat Dalam Kode Per Baris (Line-by-Line Deep Dive 1-118)](#4-bedah-sangat-dalam-kode-per-baris-line-by-line-deep-dive-1-118)
   - [Bagian I: Namespace, Dependencies, dan Setup (Baris 1–21)](#bagian-i-namespace-dependencies-dan-setup-baris-121)
   - [Bagian II: Pengujian Autentikasi Login Tenant (Baris 22–63)](#bagian-ii-pengujian-autentikasi-login-tenant-baris-2263)
   - [Bagian III: Pengujian Registrasi Tenant Baru (Baris 64–88)](#bagian-iii-pengujian-registrasi-tenant-baru-baris-6488)
   - [Bagian IV: Pengujian Pembaruan Profil Tenant (Baris 89–118)](#bagian-iv-pengujian-pembaruan-profil-tenant-baris-89118)
5. [Analisis Kritis QA & Security Audit: Temuan Celah & Test Gap](#5-analisis-kritis-qa--security-audit-temuan-celah--test-gap)
6. [Rekomendasi Peningkatan & Contoh Kode Solusi](#6-rekomendasi-peningkatan--contoh-kode-solusi)
7. [Bukti Eksekusi Pengujian (Test Run Report)](#7-bukti-eksekusi-pengujian-test-run-report)

---

## 1. Ringkasan Eksekutif & Tujuan File

File `tests/Unit/Services/Tenant/Auth/TenantAuthServiceTest.php` adalah berkas otomasi pengujian tingkat unit (*Unit Test*) yang didedikasikan untuk menguji fungsionalitas autentikasi, registrasi akun baru, dan pembaruan profil bagi pengguna dengan peran penyewa (*tenant*) pada aplikasi reservasi lapangan Joglo 66.

### Untuk Apa File Ini Digunakan?
Sebagai seorang **Web Tester dan Security QA Specialist**, file ini digunakan untuk:
1. **Memastikan Keamanan Kredensial Pengguna**: Memverifikasi bahwa proses verifikasi identitas (login) bekerja secara akurat, password selalu terenkripsi dengan algoritma satu arah yang aman (*one-way hashing* via Bcrypt/Argon2), dan tidak ada password yang tersimpan dalam format teks biasa (*plaintext*).
2. **Mencegah Celah *User Enumeration* (OWASP Top 10: Identification and Authentication Failures)**: Memastikan sistem memberikan pesan galat (*error message*) yang seragam saat login gagal, baik ketika password salah maupun saat email tidak terdaftar di sistem.
3. **Menjamin Integritas *Role-Based Access Control* (RBAC)**: Memastikan setiap pengguna yang mendaftar melalui alur registrasi tenant secara otomatis terkunci pada hak akses `UserRole::TENANT`, mencegah potensi eskalasi hak akses (*privilege escalation*).
4. **Validasi Persistensi Data Profil**: Memastikan proses pembaruan data pengguna (nama, no. HP, email) tersimpan secara akurat ke dalam basis data dan langsung terefleksi pada model.

---

## 2. Fungsi Bisnis & Relevansi Domain (Joglo 66)

Modul autentikasi tenant adalah **pintu gerbang utama (*entry point*)** bagi pelanggan yang ingin menyewa fasilitas lapangan di Joglo 66. Jika layer autentikasi mengalami kecacatan logika:
- **Pelanggan Sah Terkunci**: Tenant gagal masuk ke akun mereka, membatalkan niat booking, dan berdampak langsung pada kerugian finansial bisnis.
- **Kebocoran Privasi**: Data riwayat booking dan pembayaran bisa diakses oleh pihak yang tidak berhak jika autentikasi tidak memvalidasi kredensial dengan ketat.
- **Manipulasi Akun**: Kegagalan pada fungsi pembaruan profil dapat menyebabkan desinkronisasi kontak (nomor WhatsApp/telepon), sehingga konfirmasi booking dan jadwal reschedule dari admin tidak pernah sampai ke pelanggan.

---

## 3. Arsitektur & Konsep Unit Testing yang Diterapkan

Di dalam file ini terdapat sejumlah konsep rekayasa pengujian perangkat lunak (*software testing principles*) tingkat lanjut:

### A. Pola AAA (Arrange - Act - Assert)
Pengujian secara konsisten memisahkan tiga fase pengujian:
1. **Arrange**: Menyiapkan data awal menggunakan Laravel Model Factory (`User::factory()->create()`) dengan password ter-hash (`Hash::make()`).
2. **Act**: Menjalankan fungsi spesifik pada service (`authenticate()`, `registerTenant()`, atau `updateProfile()`).
3. **Assert**: Memverifikasi hasil kembalian (*return type*), kecocokan identitas model, status enkripsi hash password, dan pencatatan fisik di basis data.

### B. Validasi Kriptografi Keamanan (`Hash::check`)
Unit test ini secara eksplisit menguji integritas hashing kata sandi. Pengujian tidak hanya memastikan field `password` terisi, tetapi menguji bahwa string hash yang dihasilkan cocok dengan plain text input melalui fungsi `Hash::check('mypassword', $tenant->password)`.

### C. Negative Testing untuk Validasi Autentikasi
Pengujian tidak hanya mengecek alur sukses (*happy path*), tetapi melakukan pengetesan terhadap dua skenario kegagalan fatal:
1. Email valid, namun password salah.
2. Email sama sekali tidak terdaftar di database.
Keduanya diuji untuk memastikan dilemparnya `Illuminate\Validation\ValidationException`.

### D. Kombinasi State Verification dan Database Assertion
- **State Verification**: Memastikan objek `User` yang di-return merupakan instance yang valid, ID-nya identik, dan propertinya ter-refresh dengan benar di memori.
- **Database Assertion (`assertDatabaseHas`)**: Memastikan record benar-benar tersimpan ke tabel `users` pada SQLite in-memory database.

---

## 4. Bedah Sangat Dalam Kode Per Baris (Line-by-Line Deep Dive 1-118)

Berikut adalah bedah tuntas seluruh baris kode dari baris 1 hingga 118 dengan standar telaah Web Tester & QA Auditor.

```
================================================================================
BAGIAN I: NAMESPACE, DEPENDENCIES, DAN SETUP (Baris 1 - 21)
================================================================================
```

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **1** | `<?php` | Tag pembuka bahasa pemrograman PHP. |
| **2** | *(baris kosong)* | Pemisah visual sesuai PSR-12. |
| **3** | `namespace Tests\Unit\Services\Tenant\Auth;` | Menentukan namespace file test. Terletak di folder `Unit/Services/Tenant/Auth`, memudahkan eksekusi terisolasi via terminal: `php artisan test --filter=TenantAuthServiceTest`. |
| **4** | *(baris kosong)* | Pemisah visual. |
| **5** | `use App\Enums\UserRole;` | Mengimpor enum `UserRole` (`TENANT`, `WORKER`, `OWNER`). Menjamin pengujian peran tidak menggunakan *magic string*, mencegah typo fatal saat memeriksa hak akses. |
| **6** | `use App\Models\User;` | Mengimpor model Eloquent `User` sebagai entitas yang diautentikasi dan dimanipulasi. |
| **7** | `use App\Services\Tenant\Auth\TenantAuthService;` | Mengimpor **System Under Test (SUT)**, yaitu class service autentikasi tenant yang sedang diuji. |
| **8** | `use Illuminate\Support\Facades\Hash;` | Mengimpor facade `Hash` Laravel untuk memverifikasi dan membuat hash kata sandi (Bcrypt/Argon2). |
| **9** | `use Illuminate\Validation\ValidationException;` | Mengimpor kelas exception validasi Laravel. Digunakan sebagai ekspektasi error saat kegagalan autentikasi terjadi. |
| **10** | `use Tests\TestCase;` | Mengimpor base class `TestCase` proyek yang menginisialisasi lingkungan pengujian (database SQLite in-memory, migrasi skema tabel, dan teardown otomatis). |
| **11** | *(baris kosong)* | Pemisah visual sebelum deklarasi class. |
| **12** | `class TenantAuthServiceTest extends TestCase` | Deklarasi kelas pengujian. Mewarisi seluruh kapabilitas assertion dan lifecycle hooks dari `TestCase`. |
| **13** | `{` | Pembuka tubuh kelas pengujian. |
| **14** | `    protected TenantAuthService $service;` | Properti untuk menyimpan objek service yang diuji dengan deklarasi tipe data yang ketat (*type hint*). |
| **15** | *(baris kosong)* | Pemisah visual. |
| **16** | `    protected function setUp(): void` | Lifecycle hook PHPUnit yang dieksekusi otomatis **sebelum setiap test method dijalankan**. |
| **17** | `    {` | Pembuka blok `setUp()`. |
| **18** | `        parent::setUp();` | Memanggil `parent::setUp()` untuk menjalankan konfigurasi database sementara SQLite dan mengeksekusi `migrate:fresh`. |
| **19** | `        $this->service = new TenantAuthService();` | Menginstansiasi instance baru dari `TenantAuthService` untuk menjamin prinsip **Test Isolation** (kondisi bersih tanpa sisa state dari test sebelumnya). |
| **20** | `    }` | Penutup blok `setUp()`. |
| **21** | *(baris kosong)* | Pemisah struktural antar method. |

---

```
================================================================================
BAGIAN II: PENGUJIAN AUTENTIKASI LOGIN TENANT (Baris 22 - 63)
================================================================================
```

Method ini menguji fungsionalitas login (`authenticate`). Terdiri dari 1 skenario sukses (*Happy Path*) dan 2 skenario kegagalan kredensial (*Negative Testing*).

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **22** | `    public function test_authenticate(): void` | Deklarasi unit test method untuk menguji proses autentikasi kredensial tenant. |
| **23** | `    {` | Pembuka blok test case. |
| **24** | `        $password = 'rahasia123';` | **[Arrange]** Mendefinisikan plain-text password untuk kebutuhan komparasi input. |
| **25-29**| `$user = User::factory()->create([...]);` | **[Arrange - Database Seeding]** Membuat data user tenant tiruan pada database dengan email `tenant@example.com`, password yang di-hash menggunakan `Hash::make('rahasia123')`, dan role `UserRole::TENANT`. |
| **30** | *(baris kosong)* | Pemisah visual menuju skenario 1. |
| **31** | `        // 1. Success authentication` | Dokumentasi inline: Pengujian alur sukses (kredensial valid). |
| **32-35**| `$authenticatedUser = $this->service->authenticate([...]);` | **[Act - Happy Path]** Memanggil method `authenticate()` dengan kredensial yang tepat (email terdaftar dan password asli). |
| **36** | *(baris kosong)* | Pemisah visual. |
| **37** | `        $this->assertInstanceOf(User::class, $authenticatedUser);` | **[Assert - Contract Validation]** Memastikan nilai kembalian (*return type*) adalah instance resmi dari model `User`, bukan `null`, `false`, atau array. |
| **38** | `        $this->assertSame($user->id, $authenticatedUser->id);` | **[Assert - Identity Matching]** Memastikan objek user yang dikembalikan memiliki ID yang persis sama dengan user yang ada di database. |
| **39** | *(baris kosong)* | Pemisah visual menuju skenario 2. |
| **40** | `        // 2. Failed authentication: Wrong password` | Dokumentasi inline: Pengujian kegagalan akibat kata sandi salah. |
| **41** | `        try {` | Membuka blok `try` untuk menangani ekspektasi exception validasi. |
| **42-45**| `$this->service->authenticate([...]);` | **[Act - Negative Path 1]** Mengirimkan email yang benar (`tenant@example.com`) namun dengan password yang salah (`passwordsalah`). |
| **46** | `            $this->fail('Expected ValidationException was not thrown for wrong password');` | **[Assert - Anti-Silent Failure]** Jika baris 42 tidak melempar exception (lolos login padahal password salah), maka pengujian otomatis dipaksa gagal. Mencegah celah bypass keamanan. |
| **47** | `        } catch (ValidationException $e) {` | Menangkap `ValidationException` yang wajib dilempar oleh service. |
| **48** | `            $this->assertArrayHasKey('email', $e->errors());` | **[Assert - Form Error Mapping]** Memverifikasi bahwa array error validasi memuat key `'email'`, sehingga error dapat di-render tepat di bawah input field email pada form login web. |
| **49** | `            $this->assertSame('Email atau password tidak sesuai', $e->errors()['email'][0]);` | **[Assert - Security Error Message]** Memverifikasi pesan kesalahan: `"Email atau password tidak sesuai"`. Pesan ini sangat aman karena bersifat generik, mencegah penyerang mengetahui apakah email tersebut terdaftar atau tidak. |
| **50** | `        }` | Penutup blok penanganan exception skenario 2. |
| **51** | *(baris kosong)* | Pemisah visual menuju skenario 3. |
| **52** | `        // 3. Failed authentication: Non-existent email` | Dokumentasi inline: Pengujian kegagalan akibat email tidak terdaftar. |
| **53** | `        try {` | Membuka blok `try` skenario email fiktif. |
| **54-57**| `$this->service->authenticate([...]);` | **[Act - Negative Path 2]** Mengirimkan kredensial dengan email yang tidak ada di basis data (`notfound@example.com`). |
| **58** | `            $this->fail('Expected ValidationException was not thrown for missing email');` | **[Assert]** Memastikan test gagal jika service meloloskan email yang tidak terdaftar. |
| **59** | `        } catch (ValidationException $e) {` | Menangkap `ValidationException`. |
| **60** | `            $this->assertArrayHasKey('email', $e->errors());` | **[Assert]** Memastikan key error `'email'` tetap muncul secara konsisten saat user tidak ditemukan. |
| **61** | `        }` | Penutup blok penanganan exception skenario 3. |
| **62** | `    }` | Penutup blok test method `test_authenticate()`. |
| **63** | *(baris kosong)* | Pemisah struktural antar method. |

---

```
================================================================================
BAGIAN III: PENGUJIAN REGISTRASI TENANT BARU (Baris 64 - 88)
================================================================================
```

Method ini menguji fungsionalitas pendaftaran akun tenant mandiri (`registerTenant`).

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **64** | `    public function test_register_tenant(): void` | Deklarasi unit test method untuk menguji pendaftaran akun penyewa baru. |
| **65** | `    {` | Pembuka blok test case. |
| **66-71**| `$registerData = [...];` | **[Arrange - Registration Payload]** Menyiapkan data registrasi yang dikirimkan calon penyewa: nama `'Budi Penyewa'`, email `'budi.penyewa@example.com'`, no. HP `'081234567890'`, dan password `'mypassword'`. |
| **72** | *(baris kosong)* | Pemisah visual. |
| **73** | `        $tenant = $this->service->registerTenant($registerData);` | **[Act]** Memanggil method `registerTenant()` dengan payload pendaftaran. |
| **74** | *(baris kosong)* | Pemisah visual menuju fase assertion. |
| **75** | `        $this->assertInstanceOf(User::class, $tenant);` | **[Assert - Type Contract]** Memastikan return value adalah instance dari model `User`. |
| **76** | `        $this->assertSame('Budi Penyewa', $tenant->name);` | **[Assert - Attribute Verification]** Memverifikasi atribut nama tersimpan sesuai input. |
| **77** | `        $this->assertSame('budi.penyewa@example.com', $tenant->email);` | **[Assert - Attribute Verification]** Memverifikasi alamat email tersimpan sesuai input. |
| **78** | `        $this->assertSame('081234567890', $tenant->phone);` | **[Assert - Attribute Verification]** Memverifikasi nomor telepon tersimpan sesuai input. |
| **79** | `        $this->assertSame(UserRole::TENANT->value, $tenant->role);` | **[Assert - RBAC Security Rule]** **Sangat Krusial:** Memverifikasi bahwa user yang mendaftar secara otomatis diberikan hak akses `tenant`, tidak bisa menjadi `worker` atau `owner`. |
| **80** | `        $this->assertTrue(Hash::check('mypassword', $tenant->password));` | **[Assert - Cryptographic Integrity]** **Sangat Krusial:** Memverifikasi bahwa password yang tersimpan telah di-hash secara aman. Fungsi `Hash::check()` memvalidasi bahwa hash di database cocok dengan string plain `'mypassword'` dan bukan teks mentah. |
| **81** | *(baris kosong)* | Pemisah visual. |
| **82-86**| `$this->assertDatabaseHas('users', [...]);` | **[Assert - Data Persistence]** Memverifikasi integritas persistensi data langsung ke tabel fisik `users` di database SQLite: record dengan ID user tersebut benar-benar ada, memiliki email yang sesuai, dan role bernilai `tenant`. |
| **87** | `    }` | Penutup blok test method `test_register_tenant()`. |
| **88** | *(baris kosong)* | Pemisah struktural antar method. |

---

```
================================================================================
BAGIAN IV: PENGUJIAN PEMBARUAN PROFIL TENANT (Baris 89 - 118)
================================================================================
```

Method ini menguji fungsionalitas perubahan informasi data diri tenant (`updateProfile`).

| Baris | Kode Sumber | Analisis Teknis & Perspektif QA / Unit Testing |
| :--- | :--- | :--- |
| **89** | `    public function test_update_profile(): void` | Deklarasi unit test method untuk menguji update profil user. |
| **90** | `    {` | Pembuka blok test case. |
| **91-96**| `$user = User::factory()->create([...]);` | **[Arrange - Baseline Data]** Membuat data awal pengguna di database: nama `'Nama Awal'`, nomor HP `'0811111111'`, email `'awal@example.com'`, dan role `tenant`. Ini menjadi tolak ukur data sebelum dimutasi. |
| **97** | *(baris kosong)* | Pemisah visual. |
| **98-102**| `$updateData = [...];` | **[Arrange - Update Payload]** Menyiapkan data baru: nama berubah menjadi `'Nama Terkini'`, nomor HP menjadi `'0822222222'`, dan email menjadi `'terkini@example.com'`. |
| **103** | *(baris kosong)* | Pemisah visual. |
| **104** | `        $this->service->updateProfile($user, $updateData);` | **[Act]** Memanggil method `updateProfile()` untuk mengeksekusi pembaruan data pengguna. |
| **105** | *(baris kosong)* | Pemisah visual menuju fase verifikasi. |
| **106** | `        $user->refresh();` | **[Assert - Memory Synchronization]** Memuat ulang atribut `$user` langsung dari tabel database. Ini adalah langkah wajib dalam testing untuk membuktikan bahwa data di database benar-benar berubah, bukan hanya berubah sementara di memori objek PHP. |
| **107** | `        $this->assertSame('Nama Terkini', $user->name);` | **[Assert]** Memverifikasi atribut nama pada model telah terupdate. |
| **108** | `        $this->assertSame('0822222222', $user->phone);` | **[Assert]** Memverifikasi atribut telepon pada model telah terupdate. |
| **109** | `        $this->assertSame('terkini@example.com', $user->email);` | **[Assert]** Memverifikasi atribut email pada model telah terupdate. |
| **110** | *(baris kosong)* | Pemisah visual. |
| **111-116**| `$this->assertDatabaseHas('users', [...]);` | **[Assert - Database Final State]** Memverifikasi bahwa tabel `users` memiliki baris data dengan ID yang sama, namun dengan nilai kolom `name`, `email`, dan `phone` yang sudah diperbarui secara permanen. |
| **117** | `    }` | Penutup blok test method `test_update_profile()`. |
| **118** | `}` | Penutup blok class `TenantAuthServiceTest`. Akhir dari file. |

---

## 5. Analisis Kritis QA & Security Audit: Temuan Celah & Test Gap

Sebagai Senior QA & Web Tester, berikut adalah temuan audit mendalam dari pengujian dan implementasi kode di `TenantAuthService.php`:

### A. Temuan Celah Keamanan Serius (High-Priority QA Finding)
Perhatikan implementasi pada `TenantAuthService::authenticate()`:
```php
public function authenticate(array $credentials): User
{
    if (!Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
        throw ValidationException::withMessages([
            'email' => 'Email atau password tidak sesuai',
        ]);
    }

    return User::where('email', $credentials['email'])->first();
}
```
> [!WARNING]
> **Tidak Ada Pengecekan Role pada Autentikasi Tenant!**  
> Method `authenticate()` di atas hanya memvalidasi apakah pasangan email dan password cocok pada tabel `users`. **Fungsi ini sama sekali tidak memeriksa apakah pengguna tersebut memiliki `role === UserRole::TENANT`!**  
> Akibatnya: Jika seorang user dengan role `worker` (admin) atau `owner` memasukkan kredensialnya di halaman login tenant, service akan menganggapnya sukses dan mengembalikan objek user tersebut. Pengujian unit saat ini **belum memiliki test case untuk memastikan user non-tenant ditolak saat login di portal tenant**.

### B. Celah Pengujian Lainnya (Test Gaps)

1. **Kasus Sensitivitas Huruf Besar/Kecil pada Email (*Case Sensitivity*)**:
   - Belum ada pengujian apakah login dengan `Tenant@Example.COM` berhasil mengenali akun yang didaftarkan dengan `tenant@example.com`.
2. **Proteksi Mass-Assignment pada Update Profil**:
   - Belum ada pengujian yang memastikan bahwa parameter terlarang seperti `role => 'owner'` atau `password => 'hacked'` yang diselipkan pada `$updateData` **TIDAK** ikut terupdate di database saat `updateProfile()` dipanggil.
3. **Pemberian Pesan Error pada Email Tidak Terdaftar**:
   - Pada baris 60:
     ```php
     $this->assertArrayHasKey('email', $e->errors());
     ```
     Pengujian belum memverifikasi bahwa isi pesan error untuk email tidak terdaftar sama persis dengan password salah (`'Email atau password tidak sesuai'`). Menyamakan pesan error sangat penting untuk mematuhi standar OWASP Anti-User-Enumeration.

---

## 6. Rekomendasi Peningkatan & Contoh Kode Solusi

Berikut adalah rekomendasi teknis untuk memperkuat ketahanan pengujian dan keamanan aplikasi:

### 1. Perbaikan Kode Service (Role Enforcement)
Tambahkan validasi role di `app/Services/Tenant/Auth/TenantAuthService.php`:
```php
public function authenticate(array $credentials): User
{
    if (!Auth::validate(['email' => $credentials['email'], 'password' => $credentials['password']])) {
        throw ValidationException::withMessages([
            'email' => 'Email atau password tidak sesuai',
        ]);
    }

    $user = User::where('email', $credentials['email'])->first();

    if ($user->role !== UserRole::TENANT->value) {
        throw ValidationException::withMessages([
            'email' => 'Akun Anda tidak memiliki hak akses sebagai penyewa.',
        ]);
    }

    return $user;
}
```

### 2. Penambahan Test Case untuk Celah Role Non-Tenant
Tambahkan test case berikut ke dalam `TenantAuthServiceTest.php`:
```php
public function test_authenticate_fails_for_non_tenant_roles(): void
{
    $password = 'admin123';
    User::factory()->create([
        'email' => 'admin@joglo66.com',
        'password' => Hash::make($password),
        'role' => UserRole::WORKER->value, // Akun admin/petugas
    ]);

    $this->expectException(ValidationException::class);
    
    $this->service->authenticate([
        'email' => 'admin@joglo66.com',
        'password' => $password,
    ]);
}
```

### 3. Refactoring Exception Handling Menggunakan Standar PHPUnit
Gantikan blok `try-catch` dengan assertion PHPUnit yang lebih bersih:
```php
public function test_authenticate_throws_validation_exception_on_wrong_password(): void
{
    User::factory()->create([
        'email' => 'tenant@example.com',
        'password' => Hash::make('rahasia123'),
        'role' => UserRole::TENANT->value,
    ]);

    $this->expectException(ValidationException::class);
    $this->expectExceptionMessage('Email atau password tidak sesuai');

    $this->service->authenticate([
        'email' => 'tenant@example.com',
        'password' => 'passwordsalah',
    ]);
}
```

---

## 7. Bukti Eksekusi Pengujian (Test Run Report)

Pengujian unit telah dijalankan secara langsung pada terminal lokal menggunakan runner Laravel Artisan:

```bash
$ php artisan test tests/Unit/Services/Tenant/Auth/TenantAuthServiceTest.php

   PASS  Tests\Unit\Services\Tenant\Auth\TenantAuthServiceTest
  ✓ authenticate                                                                2.76s  
  ✓ register tenant                                                             0.63s  
  ✓ update profile                                                              0.72s  

  Tests:    3 passed (16 assertions)
  Duration: 4.79s
```

### Kesimpulan Evaluasi Web Tester:
Suite pengujian `TenantAuthServiceTest.php` telah menguji pilar utama autentikasi dan pendaftaran akun penyewa secara solid dan lulus 100% (16 assertions). Namun, demi keamanan sistem Joglo 66 dari serangan peniruan identitas dan kebocoran portal admin, pengetesan isolasi hak akses (*Role Isolation Testing*) sangat disarankan untuk segera diimplementasikan.

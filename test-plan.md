# Test Plan - Aplikasi Penyewaan Lapangan Joglo66

**Periode Testing:** Minggu ke-4  
**Status:** Draft untuk Review  
**Ruang Lingkup:** Testing pada modul Booking (Formulir Konfirmasi Data Penyewaan)

---

## 📋 Catatan Penting

- **Arsitektur Aplikasi:** Backend Laravel (PHP) ini hanya melayani API/Form Processing untuk Frontend Flutter (Dart) yang terpisah
- **Test Environment Split:**
  - **Laravel Backend (Ini):** Menangani logika booking, pricing calculation, slot validation, dan payment processing
  - **Flutter Frontend:** Menangani UI selection, slot presentation, dan data formatting sebelum mengirim ke Laravel
- **Critical Focus:** Karena keterbatasan waktu, testing difokuskan pada **business logic dan edge cases** di layer service & controller
- **Data Format Clarity:** Variabel `$playDate` = `$item['date']` harus berformat `Y-m-d` (contoh: `2025-08-27`) **tanpa jam**, sehingga valid sebagai array key untuk pengelompokan slot

---

## 📊 Test Plan - Modul Booking

| ID Uji | Modul / Fitur | Jenis Pengujian | Metode & Alat | Kriteria Lulus (Pass Criteria) | Keterangan |
|--------|---------------|-----------------|---------------|-------------------------------|-----------|
| **TP-01** | **Validasi Format Input JSON selected_slots** | Unit | Automatic (PHPUnit) | `selected_slots` parameter harus berbentuk JSON string valid dengan struktur: `[{"date":"Y-m-d", "jam":"H:i:s", "jam_akhir":"H:i:s"}, ...]`. Jika JSON invalid atau kosong, Laravel mengembalikan validation error 422 dengan pesan "selected_slots required, string". | **Laravel Backend.** Request FormRequest validation. Test dengan: valid JSON, invalid JSON, empty string, missing field. |
| **TP-02** | **Data Format Clarity - Pemeriksaan $playDate Uniqueness** | Unit | Automatic (PHPUnit) | `$playDate` (dari `$item['date']`) harus selalu berformat `Y-m-d` (tanggal saja, TANPA jam). Pastikan setiap slot dalam array `$slotsRaw` memiliki kombinasi `date` unik per pasangan jam (`jam`-`jam_akhir`). Jika duplikat date + jam ditemukan, slot akan ditimpa (overwrite) dalam `$groupedSlots` array. Test: kirim 2 slot dengan date sama, jam berbeda → harusnya 2 entry berbeda di `$groupedSlots[$playDate]`. Kirim 2 slot dengan date + jam SAMA → harusnya hanya 1 entry (terakhir menimpa). | **Laravel Backend, TenantBookingService::calculateAndGroupSlots()** line 32-57. Validasi di controller harus memastikan duplikasi tidak terjadi SEBELUM service dipanggil, atau service harus mendeteksi dan reject. **Pertanyaan Logic:** Apakah sistem memungkinkan user memilih slot identik berkali-kali (intent: apakah ini bug atau feature)? Jika bug, tambahkan validation di ConfirmBookingRequest untuk unique combination (date, jam, jam_akhir). |
| **TP-03** | **Perhitungan Harga Berdasarkan Day Type & Availability** | Unit | Automatic (PHPUnit) | Untuk setiap slot, `calculateAndGroupSlots()` harus: (1) extract `$dayType` dari `$playDate` menggunakan Carbon::parse()->format('l') (Senin-Minggu lowercase). (2) Query FieldPrice dengan filter: `day_type=$dayType`, `start_time <= jam`, `end_time >= jam_akhir`. (3) Jika record ditemukan, gunakan harga tersebut; jika tidak ada, set harga = 0. (4) Akumulasi totalPrice. Test: kirim slot untuk Hari Senin (harga Rp100.000) + Minggu (harga Rp150.000) → totalPrice harus Rp250.000. Jika FieldPrice tidak ada untuk day_type tertentu, harga harus 0 dan totalPrice berlaku akurat. | **Laravel Backend, TenantBookingService::calculateAndGroupSlots()** line 36-46, query FieldPrice. Test data: pre-populate FieldPrice dengan berbagai day_type. Gunakan PHPUnit + Database Seeding. |
| **TP-04** | **Grouping Slots by Play Date dan Sorting** | Unit | Automatic (PHPUnit) | `$groupedSlots` harus diorganisir sebagai associative array dengan key = `$playDate` (Y-m-d format). Setiap key berisi array slot dengan struktur: `['jam' => '...', 'jam_akhir' => '...', 'harga' => ...]`. Setelah loop, array harus di-sort menggunakan `ksort()` (key sort ascending). Test: kirim 3 slot: date 2025-08-29, 2025-08-27, 2025-08-28 → output harus urut: 2025-08-27, 2025-08-28, 2025-08-29. | **Laravel Backend, TenantBookingService::calculateAndGroupSlots()** line 59 (ksort). Assertion: sorted keys === array_keys in ascending order. |
| **TP-05** | **Validasi Ketersediaan Slot - Pengecekan Double Booking** | Integration | Automatic (PHPUnit + Database Transaction Test) | Sebelum membuat BookingDetail, `validateSlotsAvailability()` harus memastikan slot tidak sudah dipesan. Query `BookingDetail` harus: (1) filter field_id, (2) filter play_date, (3) exclude status CANCELLED/failed/expired, (4) cek time overlap: `WHERE start_play_time < $slot['jam_akhir'] AND end_play_time > $slot['jam']`. Jika overlap ditemukan dan status bukan CANCELLED, throw UnexpectedValueException. Test: book slot 10:00-11:00, kemudian attempt book 10:30-11:30 di date sama → harus error "Slot ... sudah dipesan orang lain". Concurrent attempt (race condition) harus di-lock dengan `lockForUpdate()`. | **Laravel Backend, TenantBookingService::checkBookingConflict()** line 143-159. Simulasi concurrent access dengan multiple PHPUnit processes atau manual lock testing. |
| **TP-06** | **Validasi Field Closure - Pengecekan Penutupan Lapangan** | Integration | Automatic (PHPUnit + Database Schema Check) | Jika tabel `field_closures` ada di database, sistem harus cek apakah slot booking overlap dengan field_closure. Query: `WHERE fk_field_id = $fieldId AND field_closure_start_time < $slotEndDT AND field_closure_end_time > $slotStartDT`. Format datetime: `$playDate . ' ' . $slot['jam'] . ':00'` (contoh: `2025-08-27 10:00:00`). Jika overlap, throw UnexpectedValueException. Test: set field_closure 10:00:00 - 12:00:00 pada 2025-08-27, attempt booking 10:30-11:30 → error "Lapangan sedang ditutup ...". Jika table tidak ada (Schema check return false), skip validasi. | **Laravel Backend, TenantBookingService::checkFieldClosureConflict()** line 161-179. Gunakan SQLite atau MySQL test DB; setup field_closures table untuk scenario positif. Test both cases: table exists + slot overlap, table exists + no overlap, table not exists. |
| **TP-07** | **Pembuatan Booking Record dengan Transactional Integrity** | Integration | Automatic (PHPUnit + DB Transaction) | `processBookingTransaction()` harus: (1) membuka transaction DB, (2) validate slots availability, (3) create Booking record dengan fields: fk_user_id, fk_field_id, team_name, customer_phone, customer_email, notes, booking_date (hari ini format Y-m-d). (4) return array dengan key 'booking' berisi Booking model. Jika ada exception, transaction rollback otomatis. Test: successful flow → Booking + Payment record created. Simulate validation failure mid-transaction → verify no data persisted. | **Laravel Backend, TenantBookingService::processBookingTransaction()** line 67-118. Use PHPUnit database transaction for rollback test. Assert Booking::count() before/after. |
| **TP-08** | **Pembuatan Booking Detail Records untuk Setiap Slot** | Integration | Automatic (PHPUnit + DB Transaction) | Untuk setiap slot dalam `$groupedSlots`, sistem harus membuat BookingDetail record dengan: fk_booking_id, start_play_time, end_play_time, play_date, price, status=WAITING. play_date harus sama dengan key dari groupedSlots (Y-m-d format). Test: booking dengan 3 slot → 3 BookingDetail records created dengan play_date unik atau repeated sesuai input. Verify no field mismatch antara slot data dan database record. | **Laravel Backend, TenantBookingService::processBookingTransaction()** line 82-98. Query BookingDetail::where('fk_booking_id', $bookingId)->get() dan assert count + data accuracy. |
| **TP-09** | **Cache Invalidation setelah Booking Dibuat** | Integration | Automatic (PHPUnit) | Setelah setiap BookingDetail created, sistem harus menjalankan `Cache::forget()` untuk 2 cache keys: (1) `tenant_nearest_bookings_field_{fieldId}`, (2) `tenant_slots_field_{fieldId}_{playDate}`. Tujuan: invalidate cached slot availability. Test: pre-populate cache dengan dummy data, create booking, verify cache keys dihapus (Cache::get() return null). | **Laravel Backend, TenantBookingService::processBookingTransaction()** line 95-96. Mock Cache facade atau use real cache (Redis/memcached) dalam test environment. Assert Cache::get($key) === null after operation. |
| **TP-10** | **Kalkulasi Amount to Pay Berdasarkan Payment Type** | Unit | Automatic (PHPUnit) | Jika `payment_type` = "down payment", amount = totalPrice / 2. Jika payment_type = "final payment", amount = totalPrice. Nilai `amountToPay` harus digunakan untuk create Payment record dan invoice generation. Test: totalPrice = Rp1.000.000, payment_type="down payment" → amount = Rp500.000. payment_type="final payment" → amount = Rp1.000.000. Jika payment_type invalid, validation request harus catch (rules enforce enum). | **Laravel Backend, TenantBookingService::processBookingTransaction()** line 100. StoreTenantBookingRequest::rules() line 23 enforce 'in:down payment,final payment'. Unit test: assert amount calculation formula. |
| **TP-11** | **Duitku Invoice Creation dan Payment Record Insertion** | Integration (API Mock) | Automatic (PHPUnit + Mock DuitkuService) | `createInvoice()` method dari DuitkuService harus dipanggil dengan Booking object dan calculated `amountToPay`. Method harus return object dengan properties: `reference`, `paymentUrl`. Payment record di-create dengan: fk_booking_id, fk_booking_detail_id=null, reference_id, payment_url, payment_type, method='transfer', amount, status=PENDING. Test: mock DuitkuService->createInvoice() return valid object, verify Payment record created dengan correct data. Test: mock return invalid/null → harus throw exception, transaction rollback. | **Laravel Backend, TenantBookingService::processBookingTransaction()** line 101-112. Use Mockery/PHPUnit Mock untuk DuitkuService. Verify Payment::latest()->first() match expected values. |
| **TP-12** | **Konfirmasi Form Render - View Data Accuracy (Laravel View)** | System | Manual (UI Browser Test) | Controller `confirmForm()` harus: (1) extract field dari database, (2) decode selected_slots JSON, (3) call calculateAndGroupSlots, (4) pass to view dengan keys: 'field', 'groupedSlots', 'totalPrice'. View 'tenant.booking.confirmation' harus display: field name, grouped slots by date, total price. User dapat review sebelum submit. Test: navigate confirmForm endpoint dengan valid selected_slots → view renders correctly, menampilkan semua slot dan total harga akurat. | **Laravel Backend + Blade View.** Manual test via browser (Chrome/Firefox). Verify HTML markup, slot grouping display, price calculation visual accuracy. Check responsive design. |
| **TP-13** | **Slot Lock Mechanism - Redis Atomic Lock (Concurrent Safety)** | System | Automatic (PHPUnit) + Manual (Stress Test) | Sebelum transaction, controller `store()` memanggil `acquireSlotLocks()` untuk lock setiap slot. Lock harus atomic dan time-limited (TTL ~5 menit). Jika lock acquire gagal (timeout/conflict), return error dan redirect dengan message "Slot ... sudah diproses orang lain, pilih slot lain." Multiple concurrent requests ke store() dengan slot identik harus hanya satu yang berhasil, lainnya conflict. Test: 2 concurrent POST requests dengan slot sama → 1 success, 1 fail dengan conflict message. Lock harus auto-release setelah transaksi selesai atau exception. | **Laravel Backend, BookingController::store()** line 74-80. Gunakan Redis untuk lock. PHPUnit async test atau manual concurrent curl requests. Verify lock acquisition + release. Monitor Redis keys. |
| **TP-14** | **Error Handling - Invalid Field ID atau Non-Existent Field** | Unit | Automatic (PHPUnit) | Jika `field_id` tidak ada di database, Controller `confirmForm()` atau `createForm()` harus throw 404 error (findOrFail()). Request FormRequest validation harus check 'exists:mysql_joglo66_app.fields,id'. Test: POST dengan field_id=9999 (tidak ada) → 404 response. Validasi error message clear. | **Laravel Backend, BookingController::createForm()** line 35, confirmForm() line 53. Laravel built-in validation + Eloquent findOrFail. PHPUnit assert response status 404. |
| **TP-15** | **User Authorization - Booking Access Control** | Unit | Automatic (PHPUnit) | Ketika penyimpanan success, `getBookingSuccessData()` method harus verify bahwa booking user_id match dengan authenticated user. Jika tidak match, throw AccessDeniedHttpException. Test: User A create booking, User B coba access → harus denied. | **Laravel Backend, TenantBookingService::getBookingSuccessData()** line 120-131. Auth::user() mock dalam PHPUnit, assert exception when user_id mismatch. |

---

## 🎯 Prioritas Testing

### 🔴 **CRITICAL (Must Test)**
1. **TP-02**: Data Format Clarity - $playDate uniqueness & date format validation
2. **TP-05**: Slot Double Booking Prevention
3. **TP-07**: Transaction Integrity (rollback scenarios)
4. **TP-13**: Concurrent Lock Mechanism

### 🟡 **HIGH (Should Test)**
5. **TP-01**: JSON Input Validation
6. **TP-03**: Pricing Calculation Logic
7. **TP-08**: BookingDetail Record Creation
8. **TP-10**: Payment Type Calculation

### 🟢 **MEDIUM (Nice to Test)**
9. **TP-04**: Slot Grouping & Sorting
10. **TP-06**: Field Closure Validation
11. **TP-11**: Duitku Integration (Mock)
12. **TP-12**: Confirmation Form Render (Manual)

### 🔵 **LOW (Edge Cases)**
13. **TP-09**: Cache Invalidation
14. **TP-14**: Error Handling (404)
15. **TP-15**: Authorization

---

## 🔍 Critical Business Logic Questions for Validation

### Issue #1: Date Format & Uniqueness in calculateAndGroupSlots()

**Q:** Variabel `$playDate = $item['date']` - Apa format eksak data ini?

**Current Understanding:**
- Format: `Y-m-d` (contoh: `2025-08-27`, **tanpa jam**)
- Digunakan sebagai array key di `$groupedSlots[$playDate][]`
- Untuk grouping multiple time slots dalam satu hari

**Potential Issue:**
Jika frontend mengirim format berbeda (misal dengan jam: `2025-08-27 10:00:00`), maka:
- Setiap slot akan jadi key unik (tidak ter-group by date)
- Array structure akan inconsistent
- View display bisa salah

**Recommendation:** Tambahkan validasi di `ConfirmBookingRequest::rules()`:
```php
'selected_slots' => [
    'required', 
    'string',
    'regex:/^\[\{.*"date":"[0-9]{4}-[0-9]{2}-[0-9]{2}".*\}\]$/'
]
```

---

### Issue #2: Duplicate Slot Detection

**Q:** Apakah sistem memungkinkan user memilih slot yang sama berkali-kali (duplikasi)?

**Current Behavior:**
- Jika 2 slot dengan `date` dan `jam` identik dikirim
- Yang terakhir akan **menimpa** (overwrite) slot sebelumnya di array
- `$groupedSlots['2025-08-27'][]` akan hanya berisi 1 entry, bukan 2

**Test Case:** 
```
Input:
[
  {"date": "2025-08-27", "jam": "10:00:00", "jam_akhir": "11:00:00"},
  {"date": "2025-08-27", "jam": "10:00:00", "jam_akhir": "11:00:00"}  // Duplikat!
]

Expected Output:
{
  "2025-08-27": [
    {"jam": "10:00:00", "jam_akhir": "11:00:00", "harga": 100000}
  ]
}

OR throw validation error "Duplikasi slot tidak diperbolehkan"
```

**Recommendation:** Tambahkan validation logic di `ConfirmBookingRequest`:
```php
public function after()
{
    $slots = json_decode($this->selected_slots, true);
    $combinations = array_map(fn($s) => "{$s['date']}_{$s['jam']}_{$s['jam_akhir']}", $slots);
    
    if (count($combinations) !== count(array_unique($combinations))) {
        $this->fail('Duplikasi slot tidak diperbolehkan.');
    }
}
```

---

### Issue #3: Field Closure DateTime Format

**Q:** Apakah field_closure datetime harus include seconds?

**Current Code (Line 164-165):**
```php
$slotStartDT = $playDate . ' ' . $slot['jam'] . ':00';
$slotEndDT = $playDate . ' ' . $slot['jam_akhir'] . ':00';
```

**Observation:**
- `$slot['jam']` format diasumsikan `H:i:s` atau `H:i`?
- Code append `:00` → jika jam sudah `H:i:s`, hasil jadi `H:i:s:00` (invalid)
- Jika jam format `H:i`, hasil jadi `H:i:00` (valid)

**Recommendation:** Clarify format & fix potential concatenation bug:
```php
// Safe approach - explicit formatting
$jamParsed = Carbon::parse($slot['jam'])->format('H:i:s');
$slotStartDT = Carbon::parse("{$playDate} {$jamParsed}")->toDateTimeString();
```

---

## 📝 Setup Test Environment

### Required Packages (PHPUnit)
```bash
composer require phpunit/phpunit --dev
composer require mockery/mockery --dev
composer require spatie/laravel-test-helpers --dev (optional)
```

### Test Database Configuration
```php
// phpunit.xml
<php>
    <env name="DB_CONNECTION" value="sqlite"/>
    <env name="DB_DATABASE" value=":memory:"/>
    <env name="CACHE_DRIVER" value="array"/>
</php>
```

### Mock DuitkuService Example
```php
public function test_duitku_invoice_creation()
{
    $mockDuitku = Mockery::mock(DuitkuService::class);
    $mockDuitku
        ->shouldReceive('createInvoice')
        ->with(Mockery::any(), 500000)
        ->andReturn((object)[
            'reference' => 'REF-123456',
            'paymentUrl' => 'https://pay.duitku.com/...'
        ]);
    
    $this->app->bind(DuitkuService::class, fn() => $mockDuitku);
    // Test akan gunakan mock ini
}
```

---

## 📅 Testing Schedule (Minggu ke-4)

| Hari | Fokus | Estimasi |
|------|-------|----------|
| **Senin** | TP-02, TP-01, TP-14 (Input Validation) | 2 jam |
| **Selasa** | TP-03, TP-10 (Pricing & Calculation) | 2 jam |
| **Rabu** | TP-05, TP-06 (Availability Validation) | 2.5 jam |
| **Kamis** | TP-07, TP-08 (Transaction & Records) | 2.5 jam |
| **Jumat** | TP-13, TP-12 (Concurrency & UI) + Buffer | 3 jam |

**Total:** ~12.5 jam (fokus pada CRITICAL & HIGH priority)

---

## ✅ Definition of Done

Setiap test dianggap **PASS** ketika:

1. ✅ Test code ditulis dengan asersi yang eksplisit (tidak generic)
2. ✅ Test case mencakup **happy path** + **at least 2 edge cases**
3. ✅ Semua assertions **GREEN** (no skipped tests)
4. ✅ Code coverage untuk tested method ≥ 80%
5. ✅ Jika menemukan bug, buat **Issue** dengan:
   - Deskripsi clear
   - Steps to reproduce
   - Expected vs Actual
   - Link ke test case yang mereproduksi
6. ✅ Jika logic ambiguity ditemukan (seperti Issue #1-3), **dokumentasikan** dan **discuss dengan tim**

---

## 📚 Referensi Dokumentasi

- **Laravel Testing:** https://laravel.com/docs/testing
- **PHPUnit Assertions:** https://phpunit.de/manual/current/en/appendixes.assertions.html
- **Carbon Date Formatting:** https://carbon.nesbot.com/docs/
- **Duitku Integration:** [Lihat app/Services/DuitkuService.php]
- **Database Testing:** https://laravel.com/docs/database-testing

---

## 🚀 Sign-Off

| Peran | Nama | Tanda Tangan | Tanggal |
|------|------|-------------|---------|
| QA Lead | - | _____ | __/__/__ |
| Dev Lead | - | _____ | __/__/__ |
| Pemilik Proyek | - | _____ | __/__/__ |

---

**Last Updated:** 2025-08-27  
**Version:** 1.0 (Draft)

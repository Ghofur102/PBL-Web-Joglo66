# Test Plan - Aplikasi Penyewaan Lapangan Joglo66

## 🔧 LARAVEL BACKEND (PHP) - Test Cases

| ID Uji | Modul / Fitur | Jenis Pengujian | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|--------|---------------|-----------------|---------------|-------------------------------|---------|
| **TP-01** | **Validasi Format Input JSON selected_slots** | Unit | Automatic (PHPUnit) | `selected_slots` parameter harus berbentuk JSON string valid dengan struktur: `[{"date":"Y-m-d", "jam":"H:i:s", "jam_akhir":"H:i:s"}, ...]`. Jika JSON invalid atau kosong, Laravel mengembalikan validation error 422 dengan pesan "selected_slots required, string". | **Controller:** `BookingController::confirmForm()` → **Request:** `ConfirmBookingRequest` line 17-19. Test dengan: valid JSON, invalid JSON, empty string, missing field. |
| **TP-02** | **Formulir Konfirmasi Data Penyewaan - Validasi Format & Uniqueness $playDate (CRITICAL BUSINESS LOGIC)** ⚠️ | Unit | Automatic (PHPUnit) | `$playDate` (dari `$item['date']`) HARUS selalu format `Y-m-d` (tanggal saja, TANPA jam) sesuai DATE_FORMAT constant line 25. Ini adalah array key di `$groupedSlots[$playDate][]` di TenantBookingService::calculateAndGroupSlots() line 48-50. **Pertanyaan Validasi Kritis:** (1) Apakah Flutter selalu mengirim format Y-m-d atau bisa Y-m-d H:i:s? (2) Jika duplikat date+jam dikirim, slot akan ter-overwrite (overwrite behavior). **Testing:** Kirim 2 slot: {"date":"2025-08-27","jam":"10:00","jam_akhir":"11:00"} + duplikat → harusnya hanya 1 entry di groupedSlots. Jika 2 slot: {"date":"2025-08-27","jam":"10:00"} + {"date":"2025-08-27","jam":"11:00"} → harusnya 2 entry berbeda di groupedSlots['2025-08-27'][]. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 27-65. Validasi di `ConfirmBookingRequest` harus enforce unique(date, jam, jam_akhir) combination. |
| **TP-03** | **Perhitungan Harga Berdasarkan Day Type & Availability** | Unit | Automatic (PHPUnit) | Untuk setiap slot, service harus: (1) extract `$dayType` dari `$playDate` menggunakan Carbon::parse()->format('l') (Senin-Minggu lowercase, line 36). (2) Query FieldPrice dengan filter: `day_type=$dayType`, `start_time <= jam`, `end_time >= jam_akhir` (line 38-43). (3) Jika record ditemukan, gunakan harga; jika tidak ada, set harga = 0. (4) Akumulasi totalPrice. Test: kirim slot Senin (harga Rp100.000) + Minggu (harga Rp150.000) → totalPrice Rp250.000. Jika FieldPrice tidak ada, harga harus 0. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 34-46. Pre-populate FieldPrice dengan berbagai day_type. PHPUnit + Database Seeding. |
| **TP-04** | **Grouping Slots by Play Date dan Sorting** | Unit | Automatic (PHPUnit) | `$groupedSlots` harus associative array dengan key = `$playDate` (Y-m-d format). Setiap key berisi array slot: `['jam' => '...', 'jam_akhir' => '...', 'harga' => ...]`. Setelah loop, array harus di-sort `ksort()` ascending by date (line 59). Test: kirim 3 slot dengan date 2025-08-29, 2025-08-27, 2025-08-28 → output urut: 2025-08-27, 2025-08-28, 2025-08-29. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 59. Assertion: sorted keys === array_keys in ascending order. |
| **TP-05** | **Validasi Ketersediaan Slot - Pengecekan Double Booking (CRITICAL)** | Integration | Automatic (PHPUnit + DB Transaction) | Sebelum membuat BookingDetail, `validateSlotsAvailability()` (line 133-141) harus memastikan slot tidak sudah dipesan. Query `BookingDetail` harus: (1) filter field_id via whereHas('booking'), (2) filter play_date (line 149), (3) exclude status CANCELLED/failed/expired (line 150), (4) cek time overlap: `WHERE start_play_time < jam_akhir AND end_play_time > jam` (line 151-152). Jika overlap ditemukan, throw UnexpectedValueException (line 157). Test: book slot 10:00-11:00, attempt book 10:30-11:30 same date → error "Slot ... sudah dipesan orang lain". Concurrent requests use `lockForUpdate()` (line 153) untuk prevent race condition. | **Service:** `TenantBookingService::checkBookingConflict()` line 143-159. Simulasi concurrent access dengan multiple PHPUnit processes. |
| **TP-06** | **Validasi Field Closure - Pengecekan Penutupan Lapangan (CRITICAL BUSINESS LOGIC - Potential DateTime Bug)** | Integration | Automatic (PHPUnit + DB Schema) | Jika tabel `field_closures` ada (Schema check line 163), sistem harus cek overlap dengan booking slot. Query: `WHERE fk_field_id AND field_closure_start_time < slotEndDT AND field_closure_end_time > slotStartDT` (line 167-173). **POTENTIAL BUG ALERT (Line 164-165):** Format datetime construction: `$slotStartDT = $playDate . ' ' . $slot['jam'] . ':00'`. **Pertanyaan Validasi:** Apakah `$slot['jam']` sudah format `H:i:s` atau `H:i` saja? Jika sudah `H:i:s`, concatenation `:00` akan create `H:i:s:00` (invalid). **Recommendation:** Use Carbon parsing: `Carbon::parse("{$playDate} {$slot['jam']}")->toDateTimeString()`. Test: set field_closure 10:00:00-12:00:00 pada 2025-08-27, attempt booking 10:30-11:30 → error "Lapangan sedang ditutup ...". Test: no overlap → booking allowed. Test: table not exist → skip validation (Schema::hasTable return false). | **Service:** `TenantBookingService::checkFieldClosureConflict()` line 161-179. Setup field_closures table in test. Test both: table exists + overlap, exists + no overlap, not exists. **FIX REQUIRED:** Clarify jam format + fix datetime concatenation. |
| **TP-07** | **Pembuatan Booking Record dengan Transactional Integrity (CRITICAL)** | Integration | Automatic (PHPUnit + DB Transaction) | `processBookingTransaction()` (line 67-118) harus: (1) open DB::transaction (line 69), (2) validate slots (line 70), (3) create Booking record (line 72-80) dengan: fk_user_id, fk_field_id, team_name, customer_phone, customer_email, notes, booking_date (hari ini Y-m-d format). (4) return array dengan key 'booking' (line 114-116). Jika exception thrown, transaction rollback otomatis. Test: successful flow → Booking + Payment records created. Simulate validation error mid-transaction → verify no data persisted (rollback). | **Service:** `TenantBookingService::processBookingTransaction()` line 67-118. Use PHPUnit database transaction, assert Booking::count() before/after. |
| **TP-08** | **Pembuatan Booking Detail Records untuk Setiap Slot** | Integration | Automatic (PHPUnit + DB Transaction) | Untuk setiap slot dalam `$groupedSlots` (line 83), sistem harus membuat BookingDetail (line 87-94) dengan: fk_booking_id, start_play_time, end_play_time, play_date, price, status=WAITING. play_date harus sama dengan groupedSlots key (Y-m-d format). Test: booking 3 slot → 3 BookingDetail records dengan correct data. Verify no field mismatch antara input dan database. | **Service:** `TenantBookingService::processBookingTransaction()` line 82-98. Query BookingDetail::where('fk_booking_id', $bookingId) dan assert count + accuracy. |
| **TP-09** | **Cache Invalidation setelah Booking Dibuat** | Integration | Automatic (PHPUnit) | Setelah setiap BookingDetail created (line 94-97), sistem harus `Cache::forget()` 2 keys: (1) `tenant_nearest_bookings_field_{fieldId}`, (2) `tenant_slots_field_{fieldId}_{playDate}`. Tujuan: invalidate cached slot availability. Test: pre-populate cache, create booking, verify Cache::get($key) === null. | **Service:** `TenantBookingService::processBookingTransaction()` line 95-96. Mock Cache atau use real cache (Redis/memcached). |
| **TP-10** | **Kalkulasi Amount to Pay Berdasarkan Payment Type** | Unit | Automatic (PHPUnit) | Jika `payment_type` = "down payment", amount = totalPrice / 2 (line 100). Jika "final payment", amount = totalPrice. Nilai amountToPay digunakan di invoice creation (line 101) dan Payment record (line 110). Test: totalPrice=1.000.000, payment_type="down payment" → amount=500.000. payment_type="final payment" → amount=1.000.000. | **Service:** `TenantBookingService::processBookingTransaction()` line 100-110. **Request:** `StoreTenantBookingRequest` line 23 validate payment_type enum. Unit test amount calculation. |
| **TP-11** | **Duitku Invoice Creation dan Payment Record Insertion** | Integration | Automatic (PHPUnit + Mock DuitkuService) | `createInvoice()` dari DuitkuService (line 101) harus dipanggil dengan Booking object dan calculated `amountToPay`. Method return object dengan: `reference`, `paymentUrl`. Payment record (line 103-112) created dengan: fk_booking_id, fk_booking_detail_id=null, reference_id, payment_url, payment_type, method='transfer', amount, status=PENDING. Test: mock DuitkuService return valid object → Payment created correct. Test: mock return invalid → exception + rollback. | **Service:** `TenantBookingService::processBookingTransaction()` line 101-112. Use Mockery untuk DuitkuService. Verify Payment::latest()->first() match expected. |
| **TP-12** | **Error Handling - Invalid Field ID atau Non-Existent Field** | Unit | Automatic (PHPUnit) | Jika `field_id` tidak ada, `confirmForm()` atau `createForm()` throw 404 (findOrFail, line 35, 53). `ConfirmBookingRequest` validate 'exists:mysql_joglo66_app.fields,id' (line 17). Test: field_id=9999 (tidak ada) → 404 response. Error message clear. | **Controller:** `BookingController::createForm()` line 35, `confirmForm()` line 53. **Request:** `ConfirmBookingRequest` line 17. Laravel validation + Eloquent. PHPUnit assert 404. |
| **TP-13** | **Slot Lock Mechanism - Redis Atomic Lock for Concurrent Safety (CRITICAL)** | System | Automatic (PHPUnit) + Manual (Stress Test) | Sebelum transaction (line 74), controller `store()` panggil `acquireSlotLocks()` untuk lock setiap slot. Lock harus atomic, TTL ~5 menit. Jika lock acquire gagal, return error + redirect "Slot sudah diproses orang lain, pilih slot lain" (line 78-81). Multiple concurrent POST ke store() dengan slot identik → 1 success, 1+ fail dengan conflict message. Lock harus auto-release after transaction atau exception. Test: 2 concurrent requests slot sama → 1 success, 1 conflict. Monitor Redis keys. | **Controller:** `BookingController::store()` line 74-96. Use Redis lock. PHPUnit async test atau manual concurrent curl. Verify lock acquire + release. |
| **TP-14** | **User Authorization - Booking Access Control** | Unit | Automatic (PHPUnit) | `getBookingSuccessData()` (line 120-131) verify booking user_id match dengan Auth::user(). Jika mismatch, throw AccessDeniedHttpException (line 127). Test: User A create booking, User B access → denied. | **Service:** `TenantBookingService::getBookingSuccessData()` line 120-131. Mock Auth::user() in PHPUnit, assert exception on mismatch. |
| **TP-15** | **Request Validation - Team Name Length & Payment Type Enum** | Unit | Automatic (PHPUnit) | `StoreTenantBookingRequest` (line 9-26) validate: team_name required + string + max:50, payment_type in:down payment,final payment. Test: team_name > 50 char → error. payment_type invalid → error. | **Request:** `StoreTenantBookingRequest` line 16-24. Laravel validation. PHPUnit test invalid inputs. |

---

## 📱 FLUTTER FRONTEND (Dart) - Test Cases

| ID Uji | Modul / Fitur | Jenis Pengujian | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|--------|---------------|-----------------|---------------|-------------------------------|---------|
| **TP-F01** | **Pemilihan Slot di UI - Format Data yang Dikirim ke Backend** | Unit | Automatic (Flutter test) | UI selection harus menghasilkan JSON dengan struktur: `[{"date":"Y-m-d", "jam":"HH:mm", "jam_akhir":"HH:mm"}]`. Format date harus Y-m-d, jam harus HH:mm (2-digit hour:minute). Test: select slot 10:00-11:00 pada 2025-08-27 → generated JSON format correct. Tidak ada jam di field date. | **Widget:** Slot selection screen (booking creation form). Test selection logic. Verify JSON serialization format sebelum POST ke Laravel. |
| **TP-F02** | **Validasi Duplikasi Slot pada UI** | Unit | Automatic (Flutter test) | UI harus prevent user select slot yang sama berkali-kali. Jika user attempt duplikat, show warning "Slot sudah dipilih" atau disable slot yang sudah selected. Test: select slot 10:00-11:00, attempt select lagi → warning atau disabled state. | **Widget:** Slot picker. Logic: maintain Set<String> selected slots dengan unique key (date_jam_jamAkhir). |
| **TP-F03** | **Display Confirmation Page - Show Grouped Slots by Date** | System | Manual (UI Test) | Setelah submit ke confirmForm endpoint, backend return `groupedSlots` (grouped by date). Flutter app harus display: lapangan name, slots grouped by date dengan harga per slot, total harga. Test: navigate confirmation, verify all slots displayed correctly, grouped by date, harga calculation match backend. | **Screen:** Confirmation booking screen. Receive grouped slots from Laravel API response. Display in ListView grouped by date. |
| **TP-F04** | **Payment Type Selection - Down Payment vs Final Payment** | Unit | Automatic (Flutter test) | UI radio button atau dropdown select payment_type: "down payment" atau "final payment". Amount displayed harus: down payment = totalPrice/2, final payment = totalPrice. Test: select down payment → amount 50% total. select final payment → 100% total. | **Widget:** Payment selection form. Logic: calculate display amount based on selection. Send correct payment_type value ke Laravel. |
| **TP-F05** | **Form Input Validation - Team Name Required** | Unit | Automatic (Flutter test) | team_name field harus: required, max 50 char. If empty, show error "Nama tim diperlukan". If > 50 char, show error "Maks 50 karakter". Test: empty → error. 51 char → error. Valid input → no error. | **Widget:** Team name text field in booking form. Validate length + required. Show error message inline. |
| **TP-F06** | **Error Handling - Backend Error Messages** | System | Manual (UI Test) | Jika Laravel return error (422, 400, 404), Flutter harus: (1) parse error message, (2) display user-friendly message, (3) allow retry atau navigate back. Test: send invalid selected_slots → show error. send non-existent field_id → show error. | **Screen:** Booking flow. Network error handling via try-catch, show SnackBar atau Dialog dengan error message. |
| **TP-F07** | **Confirmation Dialog - Submit Booking untuk Final Confirmation** | System | Manual (UI Test) | Before POST ke store(), show confirmation dialog: "Konfirmasi pemesanan: lapangan X, slot Y, total Rp Z, bayar (down/final)". User must confirm. Test: confirm → POST execute. cancel → dismiss dialog, stay on confirmation page. | **Widget:** Confirmation dialog. Display booking summary. Action buttons: Konfirmasi (POST) / Batal (dismiss). |
| **TP-F08** | **Success Page - Display Booking Reference & Payment URL** | System | Manual (UI Test) | After successful store(), navigate to success page showing: booking reference, payment URL, payment status (PENDING), print/share options. Test: successful POST → success page display correct booking data from response. | **Screen:** Booking success screen. Receive booking data + payment reference from Laravel response. Display and allow print/share. |
| **TP-F09** | **Network Request - POST to confirmForm & store endpoints** | Integration | Automatic (Flutter test + Mock HTTP) | Flutter HTTP client harus POST: (1) confirmForm dengan field_id + selected_slots (JSON string). (2) store dengan field_id + team_name + notes + booking_data (JSON string) + payment_type. Headers: Content-Type: application/json. Auth: include token if required. Test: mock HTTP response 200 → success. mock 422 → validation error. mock 500 → server error. | **Service:** API service / Http client. Test with Mock HTTP package (http.ClientResponse mock). Verify endpoint URL, method, headers, body structure. |
| **TP-F10** | **Date/Time Picker - Format Selection Output** | Unit | Automatic (Flutter test) | Date picker harus output: Y-m-d format (2025-08-27). Time picker harus output: HH:mm format (10:00). Test: pick 2025-08-27 10:00-11:00 → output "2025-08-27", "10:00", "11:00". | **Widget:** Date/Time picker. Verify picker library (showDatePicker, showTimePicker) output format. Format using intl package or DateTime.format(). |
| **TP-F11** | **Slot Availability Display - Real-time Fetch dari Backend** | System | Manual (UI Test) | Booking creation form harus fetch available slots dari backend API (bukan hardcoded). Display only slots with status available. Test: navigate creation form → fetch slots for selected field → display available times. | **Screen:** Booking creation form. API call: GET available-slots?field_id=X&date=Y. Parse response, display in time picker. |
| **TP-F12** | **Loading State - Show Spinner during API Call** | System | Manual (UI Test) | During POST confirmForm & store, show loading indicator (spinner/progress). Disable buttons to prevent multiple submit. After response, hide loading + navigate or show error. Test: click submit → loading appears → after response, loading disappears. | **Widget:** Loading state management. Use FutureBuilder atau Provider state management. Show CircularProgressIndicator during API call. |

---

## 🎯 Prioritas Testing

### 🔴 **CRITICAL (Must Test)**
- **TP-02** (Laravel): Data Format Clarity - $playDate uniqueness & date format validation + duplikasi slot detection
- **TP-05** (Laravel): Slot Double Booking Prevention dengan lockForUpdate
- **TP-06** (Laravel): Field Closure DateTime parsing bug fix validation
- **TP-07** (Laravel): Transaction Integrity (rollback scenarios)
- **TP-13** (Laravel): Concurrent Lock Mechanism
- **TP-F01** (Flutter): Slot data format yang dikirim ke backend

### 🟡 **HIGH (Should Test)**
- **TP-01** (Laravel): JSON Input Validation
- **TP-03** (Laravel): Pricing Calculation Logic
- **TP-08** (Laravel): BookingDetail Record Creation
- **TP-10** (Laravel): Payment Type Calculation
- **TP-F03** (Flutter): Display Confirmation dengan grouped slots
- **TP-F04** (Flutter): Payment type selection
- **TP-F09** (Flutter): Network request structure

### 🟢 **MEDIUM (Nice to Test)**
- **TP-04** (Laravel): Slot Grouping & Sorting
- **TP-11** (Laravel): Duitku Integration (Mock)
- **TP-F02** (Flutter): Duplikasi slot prevention
- **TP-F05** (Flutter): Form validation
- **TP-F10** (Flutter): Date/Time picker format

### 🔵 **LOW (Edge Cases)**
- **TP-09** (Laravel): Cache Invalidation
- **TP-12** (Laravel): Error handling 404
- **TP-14** (Laravel): Authorization
- **TP-15** (Laravel): Request validation
- **TP-F06, TP-F07, TP-F08, TP-F11, TP-F12** (Flutter): UI/UX testing

---

## 📅 Testing Schedule (Minggu ke-4)

| Hari | Backend (Laravel) Focus | Frontend (Flutter) Focus | Estimasi |
|------|------------------------|------------------------|----------|
| **Senin** | TP-02, TP-01 (Validation) | TP-F01, TP-F02 (Slot data format) | 2.5 jam |
| **Selasa** | TP-03, TP-10 (Pricing) | TP-F04, TP-F05 (Form validation) | 2.5 jam |
| **Rabu** | TP-05, TP-06 (Availability & Closure) | TP-F09, TP-F10 (API & picker format) | 3 jam |
| **Kamis** | TP-07, TP-08 (Transaction & Records) | TP-F03 (Confirmation display) | 2.5 jam |
| **Jumat** | TP-13, TP-12, TP-14 (Concurrency & Auth) | TP-F06, TP-F07, TP-F08 (Error & Success flow) | 3 jam |

**Total:** ~13.5 jam (fokus CRITICAL & HIGH priority)

---

## ✅ Definition of Done

1. ✅ Test code dengan asersi eksplisit (bukan generic)
2. ✅ Test case mencakup happy path + min 2 edge cases
3. ✅ Semua assertions GREEN (no skipped)
4. ✅ Code coverage ≥ 80% untuk method tested
5. ✅ Bug ditemukan → create GitHub Issue dengan steps to reproduce
6. ✅ Logic ambiguity → dokumentasi + discuss dengan tim (terutama TP-02, TP-06, TP-F01)


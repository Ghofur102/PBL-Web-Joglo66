# Test Plan - Aplikasi Penyewaan Lapangan Joglo66

## 🔧 LARAVEL BACKEND (PHP) - Test Plan

### Unit Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-L-U01** | **Validasi Format Input JSON selected_slots** | Automatic (PHPUnit) | `selected_slots` parameter harus berbentuk JSON string valid dengan struktur: `[{"date":"Y-m-d", "jam":"H:i:s", "jam_akhir":"H:i:s"}, ...]`. Jika JSON invalid atau kosong, Laravel mengembalikan validation error 422 dengan pesan "selected_slots required, string". | **Request:** `ConfirmBookingRequest` line 17-19. Test dengan: valid JSON, invalid JSON, empty string, missing field. |
| **TP-L-U02** | **Formulir Konfirmasi Data Penyewaan - Validasi Format & Uniqueness $playDate (CRITICAL BUSINESS LOGIC)** ⚠️ | Automatic (PHPUnit) | `$playDate` (dari `$item['date']`) HARUS selalu format `Y-m-d` (tanggal saja, TANPA jam) sesuai DATE_FORMAT constant line 25. Ini adalah array key di `$groupedSlots[$playDate][]`. **Pertanyaan Validasi Kritis:** (1) Apakah Flutter selalu mengirim format Y-m-d atau bisa Y-m-d H:i:s? (2) Jika duplikat date+jam dikirim, slot akan ter-overwrite. **Testing:** Kirim 2 slot duplikat → harusnya hanya 1 entry. Kirim 2 slot berbeda di date sama → harusnya 2 entry berbeda. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 27-65. Validasi di `ConfirmBookingRequest` harus enforce unique(date, jam, jam_akhir). |
| **TP-L-U03** | **Perhitungan Harga Berdasarkan Day Type & Availability** | Automatic (PHPUnit) | Untuk setiap slot, service harus: (1) extract `$dayType` dari `$playDate` menggunakan Carbon::parse()->format('l') (Senin-Minggu lowercase). (2) Query FieldPrice dengan filter: day_type, start_time <= jam, end_time >= jam_akhir. (3) Jika record ditemukan, gunakan harga; jika tidak ada, set harga = 0. (4) Akumulasi totalPrice. Test: slot Senin (Rp100K) + Minggu (Rp150K) → totalPrice Rp250K. Jika FieldPrice tidak ada → harga 0. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 34-46. PHPUnit + Database Seeding dengan berbagai day_type. |
| **TP-L-U04** | **Grouping Slots by Play Date dan Sorting** | Automatic (PHPUnit) | `$groupedSlots` harus associative array dengan key = `$playDate` (Y-m-d format). Setiap key berisi array slot dengan struktur: `['jam', 'jam_akhir', 'harga']`. Setelah loop, array harus di-sort ascending by date menggunakan `ksort()`. Test: kirim 3 slot date 2025-08-29, 2025-08-27, 2025-08-28 → output urut: 2025-08-27, 2025-08-28, 2025-08-29. | **Service:** `TenantBookingService::calculateAndGroupSlots()` line 59. Assertion: sorted keys === array in ascending order. |
| **TP-L-U05** | **Kalkulasi Amount to Pay Berdasarkan Payment Type** | Automatic (PHPUnit) | Jika `payment_type` = "down payment", amount = totalPrice / 2. Jika "final payment", amount = totalPrice. Nilai amountToPay digunakan di invoice creation dan Payment record. Test: totalPrice=1.000.000, payment_type="down payment" → amount=500.000. payment_type="final payment" → amount=1.000.000. | **Service:** `TenantBookingService::processBookingTransaction()` line 100. **Request:** `StoreTenantBookingRequest` line 23 validate payment_type enum. Unit test amount calculation formula. |
| **TP-L-U06** | **Request Validation - Team Name Length & Required Fields** | Automatic (PHPUnit) | `StoreTenantBookingRequest` validate: team_name required + string + max:50, field_id required + exists, notes nullable + string, booking_data required + json, payment_type required + enum. Test: team_name empty → error. team_name > 50 char → error. payment_type invalid → error. field_id tidak ada → error. | **Request:** `StoreTenantBookingRequest` line 16-24. Laravel validation + Illuminate\Validation\Rules\Enum. |

### Integration Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-L-I01** | **Validasi Ketersediaan Slot - Pengecekan Double Booking (CRITICAL)** | Automatic (PHPUnit + DB Transaction) | Sebelum membuat BookingDetail, `validateSlotsAvailability()` harus memastikan slot tidak sudah dipesan. Query BookingDetail harus: (1) filter field_id via whereHas('booking'), (2) filter play_date, (3) exclude status CANCELLED/failed/expired, (4) cek time overlap: `WHERE start_play_time < jam_akhir AND end_play_time > jam`. Jika overlap ditemukan, throw UnexpectedValueException. Test: book slot 10:00-11:00, attempt book 10:30-11:30 same date → error. Concurrent requests use `lockForUpdate()` untuk prevent race condition. | **Service:** `TenantBookingService::checkBookingConflict()` line 143-159. Simulasi concurrent access dengan multiple PHPUnit processes. |
| **TP-L-I02** | **Validasi Field Closure - Pengecekan Penutupan Lapangan (CRITICAL BUSINESS LOGIC - Potential DateTime Bug)** | Automatic (PHPUnit + DB Schema) | Jika tabel `field_closures` ada (Schema check), sistem harus cek overlap dengan booking slot. Query: `WHERE fk_field_id AND field_closure_start_time < slotEndDT AND field_closure_end_time > slotStartDT`. **POTENTIAL BUG (Line 164-165):** Format datetime: `$slotStartDT = $playDate . ' ' . $slot['jam'] . ':00'`. **Pertanyaan Validasi:** Apakah `$slot['jam']` sudah format `H:i:s` atau `H:i`? Jika sudah `H:i:s`, concatenation `:00` akan create `H:i:s:00` (invalid). **Recommendation:** Use Carbon parsing. Test: set field_closure 10:00:00-12:00:00 pada 2025-08-27, attempt booking 10:30-11:30 → error. No overlap → booking allowed. Table not exist → skip. | **Service:** `TenantBookingService::checkFieldClosureConflict()` line 161-179. Setup field_closures table in test. Test: exists+overlap, exists+no overlap, not exists. **FIX REQUIRED:** Clarify jam format + fix datetime concatenation. |
| **TP-L-I03** | **Pembuatan Booking Record dengan Transactional Integrity (CRITICAL)** | Automatic (PHPUnit + DB Transaction) | `processBookingTransaction()` harus: (1) open DB::transaction, (2) validate slots, (3) create Booking record dengan: fk_user_id, fk_field_id, team_name, customer_phone, customer_email, notes, booking_date (Y-m-d format). (4) return array dengan key 'booking'. Jika exception thrown, transaction rollback otomatis. Test: successful flow → Booking + Payment records created. Simulate validation error mid-transaction → verify no data persisted (rollback). | **Service:** `TenantBookingService::processBookingTransaction()` line 67-118. Use PHPUnit database transaction, assert Booking::count() before/after. |
| **TP-L-I04** | **Pembuatan Booking Detail Records untuk Setiap Slot** | Automatic (PHPUnit + DB Transaction) | Untuk setiap slot dalam `$groupedSlots`, sistem harus membuat BookingDetail dengan: fk_booking_id, start_play_time, end_play_time, play_date, price, status=WAITING. play_date harus sama dengan groupedSlots key (Y-m-d format). Test: booking 3 slot → 3 BookingDetail records dengan correct data. Verify no field mismatch. | **Service:** `TenantBookingService::processBookingTransaction()` line 82-98. Query BookingDetail dan assert count + accuracy. |
| **TP-L-I05** | **Cache Invalidation setelah Booking Dibuat** | Automatic (PHPUnit) | Setelah setiap BookingDetail created, sistem harus `Cache::forget()` 2 keys: (1) `tenant_nearest_bookings_field_{fieldId}`, (2) `tenant_slots_field_{fieldId}_{playDate}`. Test: pre-populate cache, create booking, verify Cache::get($key) === null. | **Service:** `TenantBookingService::processBookingTransaction()` line 95-96. Mock Cache atau use real cache (Redis/memcached). |
| **TP-L-I06** | **Duitku Invoice Creation dan Payment Record Insertion** | Automatic (PHPUnit + Mock DuitkuService) | `createInvoice()` dari DuitkuService harus dipanggil dengan Booking object dan calculated `amountToPay`. Method return object dengan: `reference`, `paymentUrl`. Payment record created dengan correct fields. Test: mock DuitkuService return valid → Payment created correct. mock return invalid → exception + rollback. | **Service:** `TenantBookingService::processBookingTransaction()` line 101-112. Use Mockery untuk DuitkuService. Verify Payment record. |
| **TP-L-I07** | **Konfirmasi Form Endpoint - Full Flow dari Request ke Response** | Automatic (PHPUnit + HTTP Test) | Controller `confirmForm()` harus: (1) validate request, (2) extract field, (3) decode selected_slots, (4) call calculateAndGroupSlots, (5) return view dengan correct data. Test: POST confirmForm dengan valid field_id + selected_slots → response status 200 + view display correct data. Invalid field_id → 404. Invalid JSON → 422. | **Controller:** `BookingController::confirmForm()` line 48-57. PHPUnit HTTP test dengan `$this->post()`. Assert response status, data in view. |

### System Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-L-S01** | **Slot Lock Mechanism - Redis Atomic Lock for Concurrent Safety (CRITICAL)** | Automatic (PHPUnit async) + Manual (Stress Test) | Sebelum transaction, controller `store()` panggil `acquireSlotLocks()` untuk lock setiap slot. Lock harus atomic, TTL ~5 menit. Jika lock acquire gagal, return error + redirect "Slot sudah diproses orang lain". Multiple concurrent POST ke store() dengan slot identik → 1 success, 1+ fail dengan conflict message. Lock auto-release after transaction atau exception. Test: 2 concurrent requests slot sama → 1 success, 1 conflict. Monitor Redis keys. | **Controller:** `BookingController::store()` line 74-96. Use Redis lock. PHPUnit async test atau manual concurrent curl. Verify lock acquire + release. |
| **TP-L-S02** | **Error Handling - Invalid Field ID atau Non-Existent Field** | Automatic (PHPUnit + HTTP Test) | Jika `field_id` tidak ada, Controller throw 404 (findOrFail). `ConfirmBookingRequest` validate 'exists:mysql_joglo66_app.fields,id'. Test: field_id=9999 (tidak ada) → 404 response. Error message clear. | **Controller:** `BookingController::createForm()` line 35, `confirmForm()` line 53. **Request:** `ConfirmBookingRequest` line 17. PHPUnit assert 404 status. |
| **TP-L-S03** | **User Authorization - Booking Access Control** | Automatic (PHPUnit) | `getBookingSuccessData()` verify booking user_id match dengan Auth::user(). Jika mismatch, throw AccessDeniedHttpException. Test: User A create booking, User B access → denied. User A access own booking → allowed. | **Service:** `TenantBookingService::getBookingSuccessData()` line 120-131. Mock Auth::user() in PHPUnit, assert exception on mismatch. |
| **TP-L-S04** | **Booking Flow End-to-End - Dari Create Form sampai Success Page** | Automatic (PHPUnit + HTTP Test) | Full flow: (1) GET createForm dengan field_id → view rendered. (2) POST confirmForm dengan selected_slots → confirmation view. (3) POST store dengan booking_data → success redirect. Verify setiap step menghasilkan expected state: view data, booking record, payment record, response redirect. | **Controller:** Integrated test across createForm, confirmForm, store methods. Follow request flow dengan assertions di setiap endpoint. |
| **TP-L-S05** | **Confirmation Page Render - View Data Accuracy dengan Grouped Slots** | Manual (Browser Test) | View 'tenant.booking.confirmation' harus display: field name, grouped slots by date, harga per slot, total price. Test: navigate confirmation endpoint → view renders correctly, menampilkan semua slot grouped by date, price calculation match backend. | **View:** Blade template confirmation view. Manual test via browser. Verify HTML markup, slot grouping, price display, responsive design. |

### Performance Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-L-P01** | **calculateAndGroupSlots() - Processing Time untuk Large Slot Arrays** | Automatic (PHPUnit + Performance Benchmark) | Function harus handle array dengan 100 slots dalam < 100ms. Test: prepare 100 slots (various dates/times) → measure execution time dengan `microtime(true)`. Assert execution time < 100ms. Memory usage < 5MB. | **Service:** `TenantBookingService::calculateAndGroupSlots()`. Use PHPUnit performance test atau wrench package. Benchmark multiple runs. |
| **TP-L-P02** | **Database Query Performance - Slot Validation dengan Large BookingDetail Records** | Automatic (PHPUnit + Query Counting) | Query untuk cek double booking (checkBookingConflict) harus <= 2 queries. Jika field_closures exist, max 3 queries total. Test: 10.000 existing BookingDetail records → validation check completion < 500ms. Assert query count menggunakan QueryBuilder. | **Service:** `TenantBookingService::checkBookingConflict()` + `checkFieldClosureConflict()`. Setup large dataset seeding. Count queries dengan DB::listen(). Verify execution time. |
| **TP-L-P03** | **Transaction Performance - Booking Create dengan Multiple BookingDetail** | Automatic (PHPUnit + Benchmark) | `processBookingTransaction()` harus complete (create Booking + 10 BookingDetail records + Payment record) dalam < 500ms. Test: 10 iterations × 10 slots/iteration → measure average time. Assert < 500ms per transaction. | **Service:** `TenantBookingService::processBookingTransaction()`. Benchmark with Timer/Stopwatch. Measure full transaction time including DB writes. |
| **TP-L-P04** | **Cache Performance - Cache Hit/Miss pada Slot Availability Queries** | Automatic (PHPUnit + Cache Benchmark) | Query FieldPrice dengan cache harus return dalam < 50ms (cache hit). Without cache, harus < 200ms. Test: pre-populate cache, query 100x → measure cache vs non-cache time. Assert cache provides 3x+ speed improvement. | **Service:** `TenantBookingService::calculateAndGroupSlots()` + cache layer. Setup cache warm/cold scenarios. Measure with PHP timer. |
| **TP-L-P05** | **Concurrent Lock Performance - Redis Lock Acquire/Release Time** | Automatic (PHPUnit + Stress Test) | `acquireSlotLocks()` untuk 5 slots harus acquire lock dalam < 100ms per lock (total < 500ms). Release harus < 50ms. Test: 100 concurrent requests competing for same slots → measure lock contention time. Assert average lock time < 150ms. | **Controller:** `BookingController` lock methods. Use Redis benchmark tools atau ApacheBench. Measure lock overhead. Verify no deadlock. |
| **TP-L-P06** | **API Response Time - confirmForm & store Endpoints** | Automatic (PHPUnit HTTP Benchmark) | `POST /booking/confirm` harus respond < 300ms. `POST /booking/store` harus respond < 1000ms (including payment creation). Test: 50 requests ke masing-masing endpoint → measure average response time. Assert within SLA. | **Controller:** Integrated HTTP test. Use Apache Bench atau custom timer. Measure full request-response cycle. |
| **TP-L-P07** | **Memory Usage - Processing Large Grouped Slots Array** | Automatic (PHPUnit + Memory Profiling) | `calculateAndGroupSlots()` dengan 100 slots harus use < 2MB memory. `processBookingTransaction()` dengan 50 BookingDetail harus use < 5MB. Test: measure peak memory dengan `memory_get_peak_usage()`. Assert within limits. | **Service:** Memory profiling. Monitor peak usage di start + end. Assert no memory leak. Use XDebug profiler optional. |

---

## 📱 FLUTTER FRONTEND (Dart) - Test Plan

### Unit Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-F-U01** | **Pemilihan Slot di UI - Format Data yang Dikirim ke Backend** | Automatic (Flutter test) | UI selection harus menghasilkan JSON dengan struktur: `[{"date":"Y-m-d", "jam":"HH:mm", "jam_akhir":"HH:mm"}]`. Format date Y-m-d, jam HH:mm (2-digit hour:minute). Test: select slot 10:00-11:00 pada 2025-08-27 → generated JSON format correct. Tidak ada jam di field date. | **Widget:** Slot selection screen. Test selection logic. Verify JSON serialization format sebelum POST. |
| **TP-F-U02** | **Validasi Duplikasi Slot pada UI** | Automatic (Flutter test) | UI harus prevent user select slot yang sama berkali-kali. Jika attempt duplikat, show warning atau disable. Test: select slot 10:00-11:00, attempt select lagi → warning atau disabled state. | **Widget:** Slot picker. Logic: maintain Set<String> selected slots dengan unique key. |
| **TP-F-U03** | **Payment Type Selection - Down Payment vs Final Payment Calculation** | Automatic (Flutter test) | Radio button atau dropdown select payment_type: "down payment" atau "final payment". Amount displayed: down payment = totalPrice/2, final payment = totalPrice. Test: select down payment → amount 50% total. select final payment → 100% total. | **Widget:** Payment selection form. Logic calculate display amount. Send correct payment_type value ke Laravel. |
| **TP-F-U04** | **Form Input Validation - Team Name Required & Max Length** | Automatic (Flutter test) | team_name field: required, max 50 char. If empty → error "Nama tim diperlukan". If > 50 char → error "Maks 50 karakter". Test: empty → error. 51 char → error. Valid input → no error. | **Widget:** Team name text field. Validate length + required. Show error message inline. |
| **TP-F-U05** | **Date/Time Picker - Format Selection Output** | Automatic (Flutter test) | Date picker output: Y-m-d format (2025-08-27). Time picker output: HH:mm format (10:00). Test: pick 2025-08-27 10:00-11:00 → output "2025-08-27", "10:00", "11:00". | **Widget:** Date/Time picker. Verify picker library output format. Format using intl package. |
| **TP-F-U06** | **Slot Data Model Serialization** | Automatic (Flutter test) | Slot model harus serialize ke JSON correct structure. Test: create Slot(date: '2025-08-27', jam: '10:00', jamAkhir: '11:00') → toJson() return valid JSON. fromJson() deserialize back ke object correct. | **Model:** Slot data model. Test toJson/fromJson methods. |

### Integration Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-F-I01** | **Network Request - POST ke confirmForm & store Endpoints** | Automatic (Flutter test + Mock HTTP) | Flutter HTTP client harus POST: (1) confirmForm dengan field_id + selected_slots (JSON string). (2) store dengan field_id + team_name + notes + booking_data + payment_type. Headers: Content-Type: application/json. Auth: include token if required. Test: mock HTTP 200 → success. mock 422 → validation error. mock 500 → server error. | **Service:** API service / Http client. Test with Mock HTTP. Verify endpoint URL, method, headers, body structure. |
| **TP-F-I02** | **Response Parsing - Parse groupedSlots dari confirmForm Response** | Automatic (Flutter test) | Setelah confirmForm POST success, backend return `groupedSlots` grouped by date. Flutter harus parse response → deserialize ke Dart objects → validate structure. Test: response dengan 3 slots berbeda date → parse correct structure. Handle error response correct. | **Service:** API response parsing. Mock API response. Assert deserialized data struktur correct. |
| **TP-F-I03** | **Local State Management - Maintain Selected Slots List** | Automatic (Flutter test + Provider/Riverpod) | App harus maintain state: selected slots, total price, payment type across screens. Test: select slot screen 1 → navigate to confirmation → verify slots persisted. Add slot → update total price. Change payment type → update amount. | **State:** BLoC/Provider/Riverpod. Test state mutations. Assert state changes propagate correct. |
| **TP-F-I04** | **Navigation Flow - Route Management between Booking Screens** | Automatic (Flutter test) | Navigation: createForm → confirmForm → successPage. Test: navigate createForm → open date picker, select date → confirmForm submit → response 200 redirect to confirmation view → display grouped slots → submit booking → response 200 redirect success page. | **Navigation:** Router/Navigation service. Mock routing. Assert route transitions correct. |
| **TP-F-I05** | **Error Handling - Display Backend Error Messages** | Automatic (Flutter test) | Jika Laravel return error (422, 400, 404), Flutter harus: (1) parse error message, (2) display user-friendly message, (3) allow retry atau navigate back. Test: send invalid selected_slots → show error SnackBar. send non-existent field_id → show error Dialog. | **Screen/Widget:** Error handling UI. Mock error response. Assert error message display correct. |

### System Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-F-S01** | **Slot Availability Display - Real-time Fetch dari Backend** | Manual (UI Test) | Booking creation form harus fetch available slots dari backend API. Display only slots dengan status available. Test: navigate creation form → fetch slots for selected field → display available times in picker. | **Screen:** Booking creation form. API call: GET available-slots?field_id=X&date=Y. Display in time picker. |
| **TP-F-S02** | **Display Confirmation Page - Show Grouped Slots by Date** | Manual (UI Test) | Setelah submit confirmForm, display confirmation screen dengan: lapangan name, slots grouped by date, harga per slot, total harga. Test: navigate confirmation, verify all slots displayed correctly grouped by date, price calculation match backend. | **Screen:** Confirmation booking screen. Receive grouped slots dari API. Display in ListView grouped by date. |
| **TP-F-S03** | **Confirmation Dialog - Submit Booking untuk Final Confirmation** | Manual (UI Test) | Before POST store(), show confirmation dialog: "Konfirmasi pemesanan: lapangan X, slot Y, total Rp Z, bayar (down/final)". User must confirm. Test: confirm → POST execute. cancel → dismiss dialog, stay on page. | **Widget:** Confirmation dialog. Display summary. Buttons: Konfirmasi / Batal. |
| **TP-F-S04** | **Success Page - Display Booking Reference & Payment URL** | Manual (UI Test) | After successful store(), navigate success page showing: booking reference, payment URL, payment status (PENDING), print/share options. Test: successful POST → success page display correct data. Allow print/share. | **Screen:** Success screen. Display booking data + payment reference. Include print/share. |
| **TP-F-S05** | **Loading State - Show Spinner during API Call** | Manual (UI Test) | During POST confirmForm & store, show loading indicator. Disable buttons to prevent multiple submit. After response, hide loading + navigate or show error. Test: click submit → loading appears → after response, loading disappears. | **Widget:** Loading state. Use FutureBuilder atau Provider. Show CircularProgressIndicator during API call. |
| **TP-F-S06** | **Full Booking Flow End-to-End - Dari Select Slot sampai Success** | Manual (UI Test) | Complete flow: (1) Select field → (2) Select date/time → (3) Review confirmation → (4) Enter team name + payment type → (5) Confirm booking → (6) Success page with reference. Test: end-to-end flow tanpa error → success. Test: cancel di step 3 → back to step 2. Test: error response at step 4 → show error, allow retry. | **App:** Full integration test. Navigate through all screens. Verify state persistence, error handling, navigation. |
| **TP-F-S07** | **Responsive Design - Display Correct pada Various Screen Sizes** | Manual (UI Test) | App harus display correct pada: small phone (5" 720p), medium phone (6" 1080p), large phone (6.5" 1440p). Widgets harus not overflow, text readable, buttons clickable. Test: render each screen on multiple device sizes, verify layout consistency. | **UI:** Responsive design. Test on different screen sizes (emulator). Check widget sizes, spacing, text overflow. |

### Performance Testing

| ID Test | Modul / Fitur | Metode & Alat | Kriteria Lulus (Pass Criteria) | Catatan |
|---------|---------------|---------------|-------------------------------|---------|
| **TP-F-P01** | **Slot Selection Performance - Large Slot List Rendering** | Automatic (Flutter test + Benchmark) | ListView dengan 500 available slots harus render smooth (60 FPS). Test: create ListView 500 items → measure frame rate. Assert jammed frame rate <= 50ms per frame (60 FPS). Use DevTools performance profiler. | **Widget:** Slot list screen. Implement virtualization/lazy loading. Measure frame rate dengan Flutter DevTools. |
| **TP-F-P02** | **JSON Serialization Performance** | Automatic (Dart test + Benchmark) | JSON encode/decode 100 slots harus complete dalam < 50ms. Test: encode 100 slots → measure time. decode 100 slots → measure time. Assert < 50ms each operation. | **Service:** JSON serialization. Benchmark toJson/fromJson. Use Stopwatch. |
| **TP-F-P03** | **HTTP Request Performance - API Call Response Time** | Automatic (Flutter test + Mock HTTP Benchmark) | confirmForm POST harus complete (send + receive) dalam < 2 seconds. store POST harus < 3 seconds. Test: 50 requests → measure average response time. Assert within SLA. Include network latency simulation. | **Service:** API client. Mock HTTP dengan delay simulation. Measure total time. |
| **TP-F-P04** | **Local State Management Performance - State Update Propagation** | Automatic (Flutter test + Benchmark) | Update selected slots state harus propagate ke widgets dalam < 100ms. Test: modify state 100x → measure average update time. Assert UI rebuild < 100ms per update. Use DevTools performance monitoring. | **State:** Provider/BLoC. Benchmark state updates. Monitor rebuild time. |
| **TP-F-P05** | **Memory Usage - Slots List & Confirmation State** | Automatic (Dart test + Memory Profiling) | Displaying 500 slots harus use < 50MB RAM. Confirmation screen dengan 50 grouped slots < 20MB. Test: measure memory via DevTools. Assert within limits. Check for memory leak (after dispose). | **App:** Memory profiling. Use DevTools memory tab. Monitor before/after navigation. |
| **TP-F-P06** | **Navigation Performance - Route Transition Smoothness** | Manual (Device Test) | Route transition (screen A → screen B) harus complete dalam < 300ms smooth animation. Test: navigate each screen transition, verify smooth animation tanpa jank. Measure dengan DevTools timeline. | **Navigation:** Route transition. Test on physical device. Record performance. |
| **TP-F-P07** | **Image/Asset Loading Performance** | Manual (Device Test) | If app load field images, loading harus complete < 2 seconds untuk 10 field cards. Test: scroll field list, measure image load time. Assert images load fast tanpa blocking UI. | **Widget:** Image loading. Implement lazy loading/caching. Test on real device with network throttling. |

---

## 🎯 Prioritas Testing

### 🔴 **CRITICAL (Must Test)**
- **TP-L-U02** (Laravel): $playDate format & uniqueness + duplikasi detection
- **TP-L-I01** (Laravel): Double booking prevention dengan lock
- **TP-L-I02** (Laravel): Field closure DateTime parsing bug fix
- **TP-L-S01** (Laravel): Concurrent lock mechanism
- **TP-L-I03** (Laravel): Transaction integrity + rollback
- **TP-F-U01** (Flutter): Slot data format ke backend

### 🟡 **HIGH (Should Test)**
- **TP-L-U01, TP-L-U03, TP-L-U05** (Laravel): Validation + pricing
- **TP-L-I04** (Laravel): BookingDetail creation
- **TP-F-I01** (Flutter): Network requests
- **TP-F-S02** (Flutter): Confirmation display
- **TP-L-P01, TP-L-P02** (Laravel): Performance critical paths

### 🟢 **MEDIUM (Nice to Test)**
- **TP-L-U04, TP-L-U06, TP-L-I05, TP-L-I06** (Laravel): Grouping, cache, Duitku
- **TP-F-U02-U05** (Flutter): Validation, picker
- **TP-F-S01, TP-F-S03-S05** (Flutter): UI flows
- **TP-L-P03-P07** (Laravel): Performance non-critical
- **TP-F-P01-P07** (Flutter): Performance

### 🔵 **LOW (Edge Cases)**
- **TP-L-S02, TP-L-S03** (Laravel): Error handling, auth
- **TP-L-I07, TP-L-S04, TP-L-S05** (Laravel): E2E, UI render
- **TP-F-I02-I05** (Flutter): Response parsing, error handling
- **TP-F-S06, TP-F-S07** (Flutter): E2E, responsive

---

## 📅 Testing Schedule (Minggu ke-4)

| Hari | Backend Focus | Frontend Focus | Estimasi |
|------|---------------|---------------|----------|
| **Senin** | **Unit:** TP-L-U01-U06 (Validation, calculation) | **Unit:** TP-F-U01-U06 (Data format, validation) | 3 jam |
| **Selasa** | **Integration:** TP-L-I01-I04 (Double booking, closure, transaction, records) | **Integration:** TP-F-I01-I04 (Network, parsing, state, navigation) | 3.5 jam |
| **Rabu** | **System:** TP-L-S01-S05 (Lock, error handling, auth, E2E, render) | **System:** TP-F-S01-S07 (Display, flow, responsive) | 3.5 jam |
| **Kamis** | **Performance:** TP-L-P01-P04 (Processing, queries, transaction, cache) | **Performance:** TP-F-P01-P04 (Rendering, serialization, HTTP, state) | 3 jam |
| **Jumat** | **Performance:** TP-L-P05-P07 (Lock, API, memory) | **Performance:** TP-F-P05-P07 (Memory, navigation, assets) + **Buffer** | 3 jam |

**Total:** ~16.5 jam (fokus CRITICAL & HIGH priority, minimal buffer)

---

## ✅ Definition of Done

1. ✅ Test plan ditulis dengan asersi eksplisit (bukan generic)
2. ✅ Setiap test plan mencakup happy path + min 2 edge cases
3. ✅ Unit/Integration/System/Performance terpisah jelas
4. ✅ Performance test include benchmark + threshold
5. ✅ Code coverage untuk tested method ≥ 80%
6. ✅ Bug ditemukan → create GitHub Issue dengan steps to reproduce
7. ✅ Logic ambiguity → dokumentasi + discuss (TP-L-U02, TP-L-I02)

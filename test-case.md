# Test Cases

## LARAVEL - Unit Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-L-U01-1 | TP-L-U01 | selected_slots valid JSON single slot | Fresh DB; Field id=101 exists | Panggil fungsi/endpoint yang memproses `selected_slots` dengan string: `'[{"date":"2026-09-01","jam":"18:00:00","jam_akhir":"19:00:00","field_id":101}]'` | Fungsi mengembalikan parsed array; `json_decode` != null; `count(parsed) == 1`; `parsed[0].date == "2026-09-01"` |
| TC-L-U01-2 | TP-L-U01 | selected_slots malformed JSON → validasi error | Fresh DB | Panggil fungsi dengan payload `'[{"date":"2026-09-01","jam":"18:00:00"'` (missing ]) | Lempar JsonException atau validation error; PHPUnit assert throws JsonException atau error message contains "selected_slots" |
| TC-L-U02-1 | TP-L-U02 | $playDate format valid (happy path) | Unit test env | Panggil parser/validator dengan `$item['date']="2026-09-01"` | `Carbon::parse("2026-09-01")` sukses; no exception; output format `Y-m-d` |
| TC-L-U02-2 | TP-L-U02 | Duplicate playDate rejected (business rule) | DB seeded: BookingDetail(field_id=101, play_date='2026-09-01') exists | Jalankan validation yang memeriksa uniqueness untuk slot yang diinput dengan `play_date='2026-09-01'` dan `field_id=101` | Validation gagal; DuplicateSlotException atau response validation errors termasuk pesan 'duplicate' dan tidak ada booking baru dibuat |
| TC-L-U03-1 | TP-L-U03 | Perhitungan harga untuk weekend (day type) | Tarif: weekday=100000, weekend=150000 | Panggil price calculation dengan slot date '2026-09-05' (Minggu) | Returned price == 150000 (exact integer) |
| TC-L-U03-2 | TP-L-U03 | Total harga untuk beberapa slot & breakdown | Dua slot, masing-masing 100000 | Panggil fungsi kalkulasi | `totalPrice == 200000`; returned breakdown array length == 2 dengan per-slot price |
| TC-L-U04-1 | TP-L-U04 | Grouping dan sorting slots by date ascend | Unordered slots: dates '2026-09-02' dan '2026-09-01' | Panggil `calculateAndGroupSlots()` dengan array tersebut | Returned associative array keys order: '2026-09-01','2026-09-02'; setiap grup terurut by `jam` ascending |
| TC-L-U05-1 | TP-L-U05 | Down payment amount calculation | `totalPrice=200000`, `payment_type='down payment'` | Panggil `calcAmount(200000, 'down payment')` | Returned amount == 100000 (exact) |
| TC-L-U05-2 | TP-L-U05 | Final payment amount calculation | `totalPrice=200000`, `payment_type='final payment'` | Panggil `calcAmount(200000, 'final payment')` | Returned amount == 200000 |
| TC-L-U06-1 | TP-L-U06 | Request validation: team_name required | None | Jalankan `StoreTenantBookingRequest->validate()` dengan input `{team_name:'', field_id:101}` | Validation gagal; `errors['team_name']` contains "The team_name field is required." (controller returns 422) |
| TC-L-U06-2 | TP-L-U06 | Request validation: team_name max length exceeded | Input `team_name` 51 chars, `field_id=101` | Jalankan validator | Validation gagal; `errors['team_name']` contains "may not be greater than 50 characters." |

## LARAVEL - Integration Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-L-I01-1 | TP-L-I01 | Double booking prevention (two concurrent requests) | DB: no booking for field_id=101 at 2026-09-01T18:00; Redis available | Kirim 2 parallel POST /api/bookings dengan body: `{"field_id":101,"start":"2026-09-01T18:00:00+07:00","end":"2026-09-01T19:00:00+07:00","team_name":"QA Team"}` | Satu response HTTP 201 Created; satu response HTTP 409 Conflict with `{"error":"Slot not available"}`; DB only 1 booking row for that slot |
| TC-L-I01-2 | TP-L-I01 | Double booking prevention (sequential race) | Sama seperti di atas | Kirim POST pertama, tunggu 50ms, kirim POST kedua | First returns 201; second returns 409; DB single booking |
| TC-L-I02-1 | TP-L-I02 | Field closure prevents booking | DB seeded `field_closures` row {field_id:101, date:'2026-09-01'} | POST /api/bookings with slot date '2026-09-01' payload | Response HTTP 422 JSON `{"error":"Field closed on selected date"}`; no booking created |
| TC-L-I03-1 | TP-L-I03 | Transaction rollback when BookingDetail insertion fails | Mock BookingDetail::create to throw after Booking created | POST /api/bookings valid payload | Response HTTP 500 (or controlled error); DB bookings table unchanged (no persisted booking) |
| TC-L-I04-1 | TP-L-I04 | BookingDetail created for each submitted slot | Payload includes 3 slots | POST /api/bookings | Response HTTP 201 JSON `{"booking_id":<int>,"details_count":3}`; DB `booking_details` contains 3 rows linked to `booking_id` |
| TC-L-I05-1 | TP-L-I05 | Cache invalidation after booking created | Cache has key `tenant_nearest_bookings_field_101` before | Create booking for field 101 (POST) | After success, `Cache::has('tenant_nearest_bookings_field_101') == false` or key updated; PHPUnit assert false |
| TC-L-I06-1 | TP-L-I06 | Duitku invoice created and payment record inserted (mock) | Mock DuitkuService; payments table empty | Complete booking flow that triggers `createInvoice()` | Assert mock `createInvoice()` called once with Booking model; DB `payments` table has row with `payment_gateway='duitku'` and matching `booking_id` |
| TC-L-I07-1 | TP-L-I07 | confirmForm endpoint returns groupedSlots & total_price | Server running; field_id=101 exists | POST /booking/confirm body: `{"field_id":101,"selected_slots":"[{\"date\":\"2026-09-01\",\"jam\":\"18:00:00\"}]"}` | Response HTTP 200 JSON contains `groupedSlots` object with key '2026-09-01' array length 1 and numeric `total_price` |

## LARAVEL - System Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-L-S01-1 | TP-L-S01 | Redis atomic slot lock under 10 concurrent requests | Redis up; no booking for target slot | Fire 10 parallel POST /booking/store identical payload | Exactly 1 request HTTP 201; others HTTP 409; Redis lock keys `slot_lock_{field}_{date}_{jam}` present during processing and removed after |
| TC-L-S02-1 | TP-L-S02 | 404 for non-existent field_id | POST /api/bookings with `field_id=999999` | Request body valid | Response HTTP 404 Not Found; body contains 'No query results for model' or equivalent NotFound message |
| TC-L-S03-1 | TP-L-S03 | Authorization denies viewing others' bookings | DB booking id=600 owner user_id=201; authenticate as user_id=202 | GET /bookings/600 with auth cookie/token of user_id=202 | Response HTTP 403 Forbidden with JSON `{"message":"This action is unauthorized."}` |
| TC-L-S04-1 | TP-L-S04 | End-to-end booking flow (browser) create → confirm → success | Browser session; field_id=101 available | 1) GET /booking/create?field_id=101 → assert HTTP 200 and form present; 2) POST /booking/confirm → HTTP 200; 3) POST /booking/store → HTTP 302 redirect to /booking/success/{id} | Success page shows text `Booking ID: {id}` and DB has booking with that id and expected status |
| TC-L-S05-1 | TP-L-S05 | Confirmation page renders grouped slots correctly | Session contains `groupedSlots` for '2026-09-01' | GET /booking/confirmation | Rendered HTML contains header '2026-09-01' and an element with text '18:00' under that section (assert via DomCrawler/CSS) |

## LARAVEL - Performance Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-L-P01-1 | TP-L-P01 | `calculateAndGroupSlots()` handles 100 slots under 100ms (p95) | Generate synthetic 100-slot array | Run function 20x capturing execution time via `microtime(true)` | p95 measuredSeconds < 0.1 (100ms) |
| TC-L-P02-1 | TP-L-P02 | Slot validation query count with 10k BookingDetail rows | Seed `booking_details` 10,000 rows; DB warmed | Execute `checkBookingConflict()` for candidate slot and capture query count/time | Query count ≤ 3 and execution time < 0.2s |
| TC-L-P06-1 | TP-L-P06 | `POST /booking/confirm` responds < 300ms | Warm cache; valid payload | `curl -w '%{time_total}' -o /dev/null -s -X POST https://dev/api/booking/confirm -d '<payload>'` run 10x | 95th percentile `time_total` < 0.3s |

## FLUTTER - Unit Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-F-U01-1 | TP-F-U01 | Slot selection serializes to backend JSON structure | Flutter unit test env | Create `Slot(date:'2026-09-01', jam:'18:00', jamAkhir:'19:00', fieldId:101)` → call `toJson()` | JSON contains keys `date`,`jam`,`jam_akhir`,`field_id` with matching values (exact match or key order-insensitive) |
| TC-F-U02-1 | TP-F-U02 | Prevent duplicate slot selection in UI state | Widget test with Provider/Riverpod | Simulate tapping same slot twice | `state.selectedSlots.length == 1`; widget warning with key `slot_duplicate_warning` visible |
| TC-F-U03-1 | TP-F-U03 | Payment type selection computes down payment | Unit test | `payment_type='down payment'`, `totalPrice=200000` | Computed displayed amount == 100000 |
| TC-F-U04-1 | TP-F-U04 | team_name required & max length validation | Widget/form test | Submit with `team_name=''` then with 51-char string | For empty: show error text 'Nama tim diperlukan' in widget key `team_name_error`; for >50: show 'Nama tim maksimal 50 karakter' |
| TC-F-U05-1 | TP-F-U05 | Date/time picker outputs Y-m-d and HH:mm | Widget test | Pick date 2026-09-01 and time 18:00 via pickers | Formatter returns '2026-09-01' and '18:00' passed to Slot model |
| TC-F-U06-1 | TP-F-U06 | Slot model JSON serialization roundtrip | Unit test | Create model → `jsonEncode` → `jsonDecode` → fromJson | Deserialized model equals original field-by-field |

## FLUTTER - Integration Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-F-I01-1 | TP-F-I01 | `confirmForm` POST contract from Flutter HTTP client (mocked) | Mock server expects POST /api/confirm | Send POST body: `{"field_id":101,"selected_slots":[{"date":"2026-09-01","jam":"18:00"}]}` | Mock responds HTTP 200 `{"groupedSlots":{"2026-09-01":[{"jam":"18:00"}]},"total_price":100000}`; client parses `groupedSlots` and `total_price` into state |
| TC-F-I02-1 | TP-F-I02 | Parse `groupedSlots` from confirmForm response | Mock server returns above | Run parsing logic | `state.groupedSlots` contains key '2026-09-01' with list length 1; UI displays grouped list |
| TC-F-I03-1 | TP-F-I03 | Local state persists selected slots across navigation | App with Provider/Riverpod | Select slot → navigate to confirm → navigate back | `state.selectedSlots` still contains previously selected slot |
| TC-F-I04-1 | TP-F-I04 | Navigation flow createForm → confirmForm → successPage | Integration test with mocked network | Simulate selection, mock POST confirm (200), mock POST store (201) | Final route is `successPage`; widget shows booking reference Text key `booking_ref` containing numeric id |
| TC-F-I05-1 | TP-F-I05 | Display backend validation errors (422) | Mock server returns 422 `{"errors":{"end":["The end field is required."]}}` | Submit form | App displays inline error 'The end field is required.' associated to end field widget key `end_error` |

## FLUTTER - System Testing (Manual / UI)

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-F-S01-1 | TP-F-S01 | Slot availability fetched and displayed from backend | Dev API reachable; `field_id=101` has available slots | Open create booking screen → wait for GET /api/fields/101/slots | UI displays slots equal to API response count; visible labels equal times from API |
| TC-F-S02-1 | TP-F-S02 | Confirmation page shows grouped slots by date | After confirmForm success | Open confirmation screen | Screen shows field name and section '2026-09-01' with slot '18:00' |
| TC-F-S03-1 | TP-F-S03 | Confirmation dialog before final store displays exact text | On confirm screen tap 'Booking Sekarang' | Modal should show `Konfirmasi pemesanan: Lapangan A, 2026-09-01 18:00 - 19:00, Total: 100000` and buttons 'Batal'/'Konfirmasi' |
| TC-F-S04-1 | TP-F-S04 | Success page shows booking reference & payment URL | After store returns `{"booking_id":5001,"payment_url":"https://pay.test/5001"}` | Open success page | Page shows text `Booking ID: 5001` (key `booking_ref`) and a clickable link with href `https://pay.test/5001` |
| TC-F-S05-1 | TP-F-S05 | Loading spinner visible & submit disabled during API calls | While POST confirmForm/store running | Trigger submit | Submit button disabled; spinner with key `loading_spinner` visible; duplicate taps ignored |
| TC-F-S06-1 | TP-F-S06 | Full booking flow end-to-end (manual) | User logged in; clean app state | Select field → select slots → confirm → complete store with test payment | Success page shown and backend contains booking record matching displayed Booking ID |
| TC-F-S07-1 | TP-F-S07 | Responsive UI across screen sizes | Devices: 5" (720p), 6" (1080p), 6.5" | Open pages on each device | No horizontal scrollbars; CTA visible; text not truncated |

## FLUTTER - Performance Testing

| id test case | id test plan | deskripsi skenario uji | kondisi awal | langkah uji & data input | hasil yang diharapkan |
|---|---|---|---|---|---|
| TC-F-P01-1 | TP-F-P01 | Render 500 slots list at ~60 FPS | Widget with 500 slot items | Scroll list and measure frame times | Average FPS ≥ 55; no frame drop > 100ms |
| TC-F-P02-1 | TP-F-P02 | JSON encode/decode 100 slots < 50ms | Unit benchmark | Encode 100 slots and decode back measuring time | Execution time < 50ms |
| TC-F-P03-1 | TP-F-P03 | confirmForm network roundtrip < 2s | Mock network or real API | Trigger confirmForm and measure end-to-end time | Time < 2.0s |
| TC-F-P04-1 | TP-F-P04 | State update propagation < 100ms | Update selectedSlots state | Dependent widgets updated within 100ms |
| TC-F-P05-1 | TP-F-P05 | Memory usage showing 500 slots < 50MB | Instrumented emulator/device | Open slots list and measure RSS | RSS < 50MB |
| TC-F-P06-1 | TP-F-P06 | Route transition < 300ms | Device/emulator | Navigate between screens and measure animation duration | Transition time < 0.3s |
| TC-F-P07-1 | TP-F-P07 | Image load for 10 field cards < 2s | Network simulated | Scroll field list of 10 images | Each image loads within 2s and placeholder replaced |

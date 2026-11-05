# 🗺️ PANDUAN LENGKAP & ALUR END-TO-END: RescheduleServiceTest.php

> 🆘 **PANDUAN KHUSUS HUDA (ALUR EKSEKUSI & HUBUNGAN ANTAR-FILE)**:
> File ini menjelaskan secara detail **bagaimana alur pengujian berjalan**, **file mana saja yang dipanggil**, serta **bedah baris per baris dari 3 fungsi test utama** beserta jejak eksekusinya ke kode produksi `RescheduleService.php`.

---

## 🧭 PETA EKOSISTEM: FILE APA SAJA YANG TERLIBAT?

Saat kamu menjalankan `RescheduleServiceTest.php`, berikut adalah peta file yang saling berkomunikasi di belakang layar:

```
[ 1. FILE PENGUJI ]
  tests/Unit/Services/Admin/RescheduleServiceTest.php
        │
        ▼ (Memanggil Method execute / approve / reject)
[ 2. FILE LOGIKA PRODUKSI (SERVICE) ]
  app/Services/Admin/RescheduleService.php
        │
        ├──► [ 3. MODEL ELOQUENT & DATABASE ]
        │     ├── app/Models/BookingDetail.php     -> Mengubah jam, tanggal, harga, status jadi RESCHEDULE
        │     ├── app/Models/BookingReschedule.php -> Menyimpan riwayat pengajuan & status approval
        │     ├── app/Models/Payment.php           -> Menerbitkan tagihan selisih harga (jika lebih mahal)
        │     └── app/Models/Booking.php           -> Menghubungkan tenant dan lapangan untuk cek bentrok
        │
        ├──► [ 4. NOTIFIKASI ]
        │     └── app/Notifications/GeneralBookingNotification.php -> Mengirim info ke tenant (ditangkap oleh Fake)
        │
        └──► [ 5. EXCEPTION SYSTEM ]
              └── DomainException (PHP Core)       -> Melempar error jika data kosong / jadwal bentrok
```

---

## 🔍 BEDAH ALUR EKSEKUSI 3 FUNGSI (BARIS PER BARIS KE FILE ASLI)

---

### ⭐ FUNGSI 1: `test_execute()` (Baris 31 – 103)
> **Tujuan**: Menguji admin mengubah jadwal main secara sepihak dan langsung lunas.

#### 1. Persiapan Data (Arrange) di File Test:
* **Baris 33 (`Notification::fake()`)**: Membekukan class `GeneralBookingNotification.php` agar tidak mengirim email/WA keluar.
* **Baris 35–46**: Membuat record `User` (tenant & admin), `Field` (lapangan), dan `Booking` (header pemesanan).
* **Baris 48–55**: Membuat `BookingDetail` seharga **Rp 100.000** di tanggal `2026-11-01` jam `10:00 - 11:00`.
* **Baris 57–65**: Menyiapkan array `$data` baru seharga **Rp 150.000**, tanggal `2026-11-05`, jam `14:00 - 15:00`, dengan selisih harga `reconciled_amount = 50000`.

#### 2. Alur Saat Baris 67 Dipanggil (`$this->service->execute`):
Eksekusi langsung berpindah ke **`app/Services/Admin/RescheduleService.php` (baris 19–80)**:
1. **Ke `RescheduleService.php` Baris 22–31**:
   * Sistem menghitung: `newPrice (150rb) > oldPrice (100rb)`.
   * Maka `$statusRefund` otomatis diset ke `'deposit required'` (butuh bayar tambahan).
2. **Ke `RescheduleService.php` Baris 33–44 (Panggil `BookingReschedule.php`)**:
   * Melakukan `BookingReschedule::create([...])` untuk mencatat log reschedule dengan `approval_status = 'approved'` dan `sender_by = 'admin'`.
3. **Ke `RescheduleService.php` Baris 46–52 (Panggil `BookingDetail.php`)**:
   * Menjalankan `$detail->update([...])` untuk mengubah tanggal, jam mulai, jam selesai, harga (150rb), dan mengubah statusnya menjadi `BookingDetailStatus::RESCHEDULE`.
4. **Ke `RescheduleService.php` Baris 54–65 (Panggil `Payment.php`)**:
   * Mengecek kondisi: `financial_action === 'Lunas'` dan `reconciled_amount > 0`.
   * Karena terpenuhi, sistem memanggil `Payment::create([...])` dengan nominal **Rp 50.000** dan status `PaymentStatus::SUCCESS`.
5. **Ke `RescheduleService.php` Baris 67–79 (Panggil `GeneralBookingNotification.php`)**:
   * Sistem memanggil `$tenant->notify(new GeneralBookingNotification(...))`.

#### 3. Kembali ke File Test untuk Memeriksa Hasil (Assert):
* **Baris 70–78**: `$this->assertDatabaseHas('booking_reschedules')` $\rightarrow$ Memeriksa tabel database apakah baris 33–44 tadi berhasil tersimpan.
* **Baris 81–86**: `$detail->refresh()` lalu `$this->assertSame()` $\rightarrow$ Mengambil data dari tabel `booking_details` dan memastikan statusnya sudah `RESCHEDULE` dan harganya sudah 150.000.
* **Baris 89–95**: `$this->assertDatabaseHas('payments')` $\rightarrow$ Memastikan record pembayaran Rp 50.000 benar-benar ada di database.
* **Baris 98–102**: `Notification::assertSentTo` $\rightarrow$ Memastikan tenant menerima notifikasi bertipe `'reschedule_by_admin'`.

---

### ⭐ FUNGSI 2: `test_approve()` (Baris 105 – 193) [FUNGSI UTAMA]
> **Tujuan**: Menguji persetujuan admin, penolakan saat data kosong, dan pencegahan jadwal bentrok.

#### ALUR SKENARIO 1: Gagal Karena Tidak Ada Pengajuan (Baris 129–135)
1. **Di Test (Baris 131)**: Panggil `$this->service->approve($detail, $admin);`.
2. **Lompat ke `RescheduleService.php` Baris 86–93**:
   * Sistem menjalankan query: `BookingReschedule::where('fk_booking_detail_id', $detail->id)->where('approval_status', 'pending')->first();`.
   * Karena belum ada data reschedule yang dibuat, hasilnya `null` (`!$reschedule`).
   * **Pemicu Error**: Sistem melempar `throw new DomainException('Tidak ada pengajuan reschedule yang menunggu persetujuan.');`.
3. **Kembali ke Test (Baris 133–134)**:
   * Blok `catch (DomainException $e)` menangkap error tersebut.
   * `$this->assertSame('Tidak ada pengajuan...', $e->getMessage())` $\rightarrow$ Membuktikan pesan error valid.

---

#### ALUR SKENARIO 2: Gagal Karena JADWAL BENTROK (Baris 150–171)
1. **Di Test (Baris 137–148)**: Penyewa mengajukan reschedule ke tanggal **`2026-11-10` jam `16:00 - 17:00`**.
2. **Di Test (Baris 150–164)**: Kita sengaja membuat pemesanan lain (`Conflict FC`) pada lapangan yang sama di jam yang sama persis: tanggal **`2026-11-10` jam `16:00 - 17:00`**.
3. **Di Test (Baris 167)**: Admin mencoba menyetujui dengan memanggil `$this->service->approve($detail, $admin);`.
4. **Lompat ke `RescheduleService.php` Baris 95–105 (Logika Query Deteksi Bentrok)**:
   * Service menjalankan query cerdas pada model `BookingDetail`:
     ```php
     BookingDetail::whereHas('booking', fn ($q) => $q->where('fk_field_id', $detail->booking->fk_field_id))
         ->where('id', '!=', $detail->id)
         ->where('play_date', $reschedule->new_play_date)
         ->whereNotIn('status', ['cancelled', 'failed', 'expired'])
         ->where('start_play_time', '<', $reschedule->new_end_play_time) // 16:00 < 17:00 (True)
         ->where('end_play_time', '>', $reschedule->new_start_play_time)   // 17:00 > 16:00 (True)
         ->exists();
     ```
   * Karena jadwal bertabrakan, variabel `$conflict` bernilai `true`!
   * **Pemicu Error**: Sistem melempar `throw new DomainException('Slot jadwal yang diajukan telah diambil oleh pemesanan lain.');`.
5. **Kembali ke Test (Baris 169–170)**:
   * Blok `catch (DomainException $e)` menangkap error bentrok tersebut.
   * `$this->assertSame('Slot jadwal yang diajukan telah diambil...', $e->getMessage())` $\rightarrow$ **Terbukti sistem sukses menolak jadwal ganda!**

---

#### ALUR SKENARIO 3: Sukses Menyetujui / Happy Path (Baris 173–193)
1. **Di Test (Baris 174)**: Hapus jadwal yang bentrok tadi (`$conflictDetail->delete()`) agar slot jam kembali kosong.
2. **Di Test (Baris 176)**: Panggil kembali `$this->service->approve($detail, $admin);`.
3. **Lompat ke `RescheduleService.php` Baris 107–130**:
   * Cek pengajuan ada? $\rightarrow$ Ada.
   * Cek bentrok? $\rightarrow$ Tidak bentrok (`$conflict = false`).
   * Panggil `BookingReschedule::update(['approval_status' => 'approved'])`.
   * Panggil `BookingDetail::update([...])` $\rightarrow$ Jam dan tanggal resmi dipindahkan, status jadi `RESCHEDULE`.
   * Panggil `$tenant->notify(new GeneralBookingNotification(...))` dengan tipe `'reschedule_approved'`.
4. **Kembali ke Test (Baris 178–192)**:
   * `$reschedule->refresh()` $\rightarrow$ Pastikan approval status di database sudah `'approved'`.
   * `$detail->refresh()` $\rightarrow$ Pastikan jam main resmi pindah ke `16:00 - 17:00`.
   * `Notification::assertSentTo()` $\rightarrow$ Memastikan notifikasi persetujuan sampai ke tenant.

---

### ⭐ FUNGSI 3: `test_reject()` (Baris 195 – 251)
> **Tujuan**: Menguji penolakan reschedule oleh admin dan pencatatan alasan penolakannya.

#### 1. Skenario Error (Baris 219–225):
* Sama seperti approve, jika tidak ada permohonan pending, memanggil `reject()` akan melempar `DomainException`.

#### 2. Skenario Sukses Menolak (Baris 227–250):
1. **Di Test (Baris 227–238)**: Buat permohonan reschedule pending di tanggal `2026-11-12` jam `08:00 - 09:00`.
2. **Di Test (Baris 240)**: Panggil `$this->service->reject($detail, 'Jadwal penuh pada jam tersebut', $admin);`.
3. **Lompat ke `RescheduleService.php` Baris 135–164**:
   * Service mengambil data reschedule yang pending.
   * Menjalankan update pada `BookingReschedule.php`:
     ```php
     $reschedule->update([
         'approval_status'  => 'rejected',
         'rejection_reason' => $reason, // 'Jadwal penuh pada jam tersebut'
     ]);
     ```
   * Mengirim notifikasi penolakan via `GeneralBookingNotification` bertipe `'reschedule_rejected'`.
4. **Kembali ke Test (Baris 242–250)**:
   * `$reschedule->refresh()` $\rightarrow$ Memeriksa kolom `approval_status` bernilai `'rejected'`.
   * `$this->assertSame('Jadwal penuh pada jam tersebut', $reschedule->rejection_reason)` $\rightarrow$ Memastikan alasan penolakan tersimpan utuh di database.
   * `Notification::assertSentTo()` $\rightarrow$ Memastikan tenant menerima kabar bahwa pengajuannya ditolak.

---

## 🎯 RANGKUMAN TANYA JAWAB CEPAT DOSEN:

| Pertanyaan Dosen | Jawaban Singkat & Tepat |
| :--- | :--- |
| *Method apa di Service yang diuji oleh `test_execute()`?* | Method `execute()` di `app/Services/Admin/RescheduleService.php` baris 19. |
| *Ke tabel mana saja method `execute()` menyimpan data?* | Ke 3 tabel: `booking_reschedules` (log permohonan), `booking_details` (update waktu), dan `payments` (tagihan selisih harga). |
| *Bagaimana method `approve()` tahu kalau ada jadwal bentrok?* | Di `RescheduleService.php` baris 95–101, ada query ke model `BookingDetail` yang mencocokkan tanggal, lapangan yang sama, dan kondisi jam mulai lebih kecil dari jam akhir jadwal baru (`start_play_time < new_end_play_time`). |
| *Mengapa notifikasi tidak dikirim beneran ke email saat testing?* | Karena di baris awal test dipasang `Notification::fake()` yang memotong proses kirim email, tetapi kita bisa memverifikasi payload-nya lewat `Notification::assertSentTo()`. |

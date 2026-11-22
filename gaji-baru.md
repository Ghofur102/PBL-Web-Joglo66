# Penjelasan File Tes Pembaruan Gaji

> Bagian ini membahas `tests/Unit/Services/Treasure/SalaryUpdateTest.php` berdasarkan susunan kode saat ini. Nomor baris mengacu pada file tersebut. Baris kosong hanya pemisah agar kode mudah dibaca dan tidak menjalankan instruksi.

## Gambaran umum

File ini menguji endpoint pembaruan gaji melalui empat skenario:

1. Bendahara mengirim nominal valid dan perubahan berhasil disimpan.
2. Bendahara mengirim nominal negatif dan validasi menolak permintaan.
3. Pengguna yang bukan bendahara mencoba mengubah gaji dan mendapat penolakan akses.
4. Simulasi tombol batal: request perubahan tidak dikirim sehingga data awal tetap ada.

Tes mengikuti pola **Arrange, Act, Assert**: menyiapkan data, menjalankan request, lalu memeriksa respons dan database.

## Penjelasan baris demi baris

### Baris 1â€“14: pembuka file dan import

| Baris | Penjelasan |
|---|---|
| 1 | `<?php` menandai bahwa file berisi kode PHP. |
| 2 | Baris kosong sebagai pemisah; tidak menjalankan kode. |
| 3 | `namespace Tests\Unit\Services\Treasure;` menempatkan kelas tes dalam namespace tersebut agar terorganisir dan tidak bentrok dengan nama kelas lain. |
| 4 | Baris kosong sebagai pemisah. |
| 5 | Mengimpor enum `GeneralStatus`, yang dipakai untuk menetapkan status karyawan aktif. |
| 6 | Mengimpor enum `UserRole`, yang dipakai untuk menetapkan peran bendahara dan tenant. |
| 7 | Mengimpor model `Employee` untuk membuat data karyawan. |
| 8 | Mengimpor model `EmployeeSalary` untuk membuat dan menguji data gaji. |
| 9 | Mengimpor model `Expense` untuk membuat pengeluaran terkait pembayaran gaji. |
| 10 | Mengimpor model `Field` untuk menyiapkan lapangan yang diperlukan oleh data pengeluaran. |
| 11 | Mengimpor model `User` untuk menyiapkan akun yang melakukan request. |
| 12 | Mengimpor trait `InteractsWithDatabase`, yang menyediakan assertion untuk memeriksa keberadaan data di database dalam tes. |
| 13 | Mengimpor trait `MakesHttpRequests`, yang menyediakan helper seperti `postJson()` dan `getJson()`. |
| 14 | Mengimpor `Tests\TestCase`, kelas dasar tes Laravel yang menyediakan lingkungan pengujian aplikasi. |

### Baris 15â€“24: dokumentasi kelas, deklarasi kelas, dan trait

| Baris | Penjelasan |
|---|---|
| 15 | Baris kosong sebagai pemisah. |
| 16 | `/**` memulai blok PHPDoc. Isinya dokumentasi untuk alat bantu pengembangan, bukan perintah yang dijalankan. |
| 17 | `@mixin ...TestCase` memberi tahu IDE agar mengenali fitur dari kelas tes Laravel pada kelas ini. |
| 18 | `@mixin MakesHttpRequests` membantu IDE mengenali helper HTTP yang digunakan di tes. |
| 19 | `@mixin InteractsWithDatabase` membantu IDE mengenali assertion database. |
| 20 | `*/` menutup blok PHPDoc. |
| 21 | Mendeklarasikan kelas `SalaryUpdateTest` sebagai turunan dari `TestCase`, sehingga kelas ini dapat memakai fasilitas tes Laravel. |
| 22 | `{` membuka isi kelas. |
| 23 | Memasukkan trait `InteractsWithDatabase` agar tes dapat memakai assertion database. |
| 24 | Memasukkan trait `MakesHttpRequests` agar tes dapat mengirim request ke aplikasi. |

### Baris 25â€“38: properti dan awal persiapan tes

| Baris | Penjelasan |
|---|---|
| 25 | Baris kosong sebagai pemisah. |
| 26 | Mendeklarasikan properti terlindungi `$treasurer` dengan tipe `User`, untuk menyimpan akun bendahara yang dipakai tes. |
| 27 | Baris kosong antarproperti. |
| 28 | Mendeklarasikan `$field` bertipe `Field`, untuk menyimpan lapangan uji. |
| 29 | Baris kosong sebagai pemisah. |
| 30 | Mendeklarasikan `$employee` bertipe `Employee`, untuk menyimpan karyawan uji. |
| 31 | Baris kosong sebagai pemisah. |
| 32 | Mendeklarasikan `$expense` bertipe `Expense`, untuk menyimpan pengeluaran gaji uji. |
| 33 | Baris kosong sebagai pemisah. |
| 34 | Mendeklarasikan `$salary` bertipe `EmployeeSalary`, untuk menyimpan data gaji awal. |
| 35 | Baris kosong sebagai pemisah. |
| 36 | Mendeklarasikan method `setUp()` yang menyiapkan data sebelum setiap method tes dijalankan. `: void` artinya method ini tidak mengembalikan nilai. |
| 37 | `{` membuka isi method `setUp()`. |
| 38 | Memanggil persiapan milik kelas induk `TestCase` agar lingkungan Laravel untuk tes siap. |

### Baris 39â€“55: menyiapkan bendahara, lapangan, dan karyawan

| Baris | Penjelasan |
|---|---|
| 39 | Baris kosong untuk memisahkan langkah persiapan. |
| 40 | Komentar bahwa baris berikut membuat akun bendahara. Komentar tidak dieksekusi. |
| 41 | Membuat record `User` melalui factory dan menyimpannya langsung ke properti `$this->treasurer`. |
| 42 | Mengatur kolom `role` menggunakan nilai enum `TREASURER`, sehingga akun ini dikenali sebagai bendahara. |
| 43 | `]);` menutup array atribut dan pemanggilan factory. |
| 44 | Baris kosong sebagai pemisah. |
| 45 | Komentar tentang kebutuhan data lapangan untuk relasi pengeluaran. |
| 46 | Membuat data lapangan dengan factory, menyimpannya ke database, lalu menaruh model hasilnya di `$this->field`. |
| 47 | Baris kosong sebagai pemisah. |
| 48 | Komentar bahwa data karyawan aktif akan disiapkan. |
| 49 | Membuat dan menyimpan record karyawan melalui `Employee::create()`, lalu menyimpan modelnya ke `$this->employee`. |
| 50 | Mengisi nama karyawan uji. |
| 51 | Mengisi posisi/jabatan karyawan. |
| 52 | Mengisi gaji pokok awal sebesar 2.000.000. |
| 53 | Mengisi tanggal mulai bekerja. |
| 54 | Mengisi status dengan nilai enum `ACTIVE`. |
| 55 | `]);` menutup array data karyawan dan pemanggilan `create()`. |

### Baris 56â€“81: menyiapkan pengeluaran dan gaji awal

| Baris | Penjelasan |
|---|---|
| 56 | Baris kosong sebagai pemisah. |
| 57 | Komentar tentang pembuatan pengeluaran awal untuk gaji. |
| 58 | Membuat record `Expense` dan menyimpan model hasilnya ke `$this->expense`. |
| 59 | Menghubungkan pengeluaran ke lapangan yang dibuat sebelumnya memakai ID lapangan. |
| 60 | Menghubungkan pengeluaran ke bendahara yang membuat/mencatatnya memakai ID pengguna. |
| 61 | Membuat nama pengeluaran dari teks `Gaji ` yang digabung dengan nama karyawan. Titik (`.`) adalah operator penggabungan string PHP. |
| 62 | Menetapkan kategori pengeluaran sebagai `Gaji`. |
| 63 | Menetapkan kuantitas pengeluaran sebanyak satu. |
| 64 | Menetapkan harga satuan sebesar 2.000.000. |
| 65 | Menetapkan tanggal pengeluaran. |
| 66 | Menetapkan tanggal pencatatan/pembuatan pengeluaran. |
| 67 | `]);` menutup data pengeluaran dan pemanggilan `create()`. |
| 68 | Baris kosong sebagai pemisah. |
| 69 | Komentar tentang pembuatan gaji awal untuk periode Januari 2026 sebesar 2.000.000. |
| 70 | Membuat record gaji dengan `EmployeeSalary::create()` dan menyimpan modelnya ke `$this->salary`. |
| 71 | Menghubungkan record gaji ke karyawan uji. |
| 72 | Menghubungkan record gaji ke pengeluaran gaji. |
| 73 | Menetapkan nominal gaji awal sebesar 2.000.000. |
| 74 | Menetapkan bulan periode gaji sebagai `january`. |
| 75 | Menetapkan tahun periode gaji sebagai 2026. |
| 76 | Menetapkan tanggal pembayaran gaji. |
| 77 | Menetapkan bonus awal sebesar nol. |
| 78 | Menetapkan potongan awal sebesar nol. |
| 79 | Memberi catatan untuk data gaji awal. |
| 80 | `]);` menutup array dan pemanggilan pembuatan gaji. |
| 81 | `}` menutup method `setUp()`. Data ini disiapkan sebelum setiap tes agar tiap skenario mulai dari kondisi awal. |

### Baris 82â€“118: tes nominal valid berhasil disimpan

| Baris | Penjelasan |
|---|---|
| 82 | Baris kosong sebagai pemisah. |
| 83 | Memulai blok PHPDoc untuk mendokumentasikan tes berikutnya. |
| 84 | Memberi ID skenario pengujian `TC-TRS-01.1`. |
| 85 | Menjelaskan tujuan: nominal valid dapat diperbarui oleh bendahara. |
| 86 | Menutup PHPDoc. |
| 87 | Mendeklarasikan method tes dengan nama deskriptif. PHPUnit mengenalinya sebagai tes karena namanya diawali `test_`. `: void` berarti tidak mengembalikan nilai. |
| 88 | `{` membuka isi tes. |
| 89 | Memulai array `$payload`, data yang akan dikirim ke endpoint. |
| 90 | Mengirim ID karyawan yang gajinya akan diperbarui. |
| 91 | Mengirim nomor bulan 1 (Januari); komentar menjelaskan arti nilainya. |
| 92 | Mengirim tahun periode gaji 2026. |
| 93 | Mengirim nominal baru sebesar 2.500.000; nilainya memenuhi batas minimal nol. |
| 94 | Mengirim bonus sebesar 100.000. |
| 95 | Mengirim potongan sebesar nol. |
| 96 | Mengirim catatan perubahan gaji. |
| 97 | `];` menutup array payload. |
| 98 | Baris kosong yang memisahkan persiapan dan request. |
| 99 | Mengatur pengguna yang sedang login menjadi bendahara dan memakai guard `sanctum` untuk request tes. |
| 100 | Mengirim request HTTP POST berbentuk JSON ke endpoint update gaji bersama payload. |
| 101 | Baris kosong sebagai pemisah. |
| 102 | Komentar bahwa respons HTTP dan JSON akan diperiksa. |
| 103 | Memastikan status HTTP respons adalah 200, lalu melanjutkan pemeriksaan respons dengan method berantai. |
| 104 | Memulai assertion terhadap isi JSON respons. |
| 105 | Memastikan JSON memiliki `success` bernilai `true`. |
| 106 | Memastikan pesan sukses sesuai teks yang diharapkan. |
| 107 | `]);` menutup array assertion JSON. |
| 108 | Baris kosong sebagai pemisah. |
| 109 | Komentar bahwa perubahan juga diperiksa langsung di database. |
| 110 | Memastikan tabel `employee_salaries` memiliki record yang cocok dengan kondisi berikut. |
| 111 | Mencocokkan record berdasarkan ID karyawan uji. |
| 112 | Memastikan periode bulan tetap `january`. |
| 113 | Memastikan periode tahun 2026. |
| 114 | Memastikan nominal gaji berubah menjadi 2.500.000. |
| 115 | Memastikan bonus tersimpan sebesar 100.000. |
| 116 | Memastikan catatan perubahan tersimpan. |
| 117 | `]);` menutup kondisi assertion database. |
| 118 | `}` menutup method tes skenario nominal valid. |

### Baris 119â€“155: tes nominal negatif ditolak

| Baris | Penjelasan |
|---|---|
| 119 | Baris kosong sebagai pemisah. |
| 120 | Memulai PHPDoc untuk skenario tes kedua. |
| 121 | Memberi ID skenario `TC-TRS-01.2`. |
| 122 | Menjelaskan bahwa tes memastikan nominal negatif ditolak. |
| 123 | Menutup PHPDoc. |
| 124 | Mendeklarasikan method tes untuk kasus nominal negatif. |
| 125 | `{` membuka isi tes. |
| 126 | Memulai array payload request. |
| 127 | Mengirim ID karyawan uji. |
| 128 | Mengirim bulan Januari. |
| 129 | Mengirim tahun 2026. |
| 130 | Mengirim nominal `-1`, nilai negatif yang seharusnya gagal validasi. |
| 131 | Mengirim bonus nol. |
| 132 | Mengirim potongan nol. |
| 133 | Mengirim catatan yang menandai pengujian nominal negatif. |
| 134 | `];` menutup payload. |
| 135 | Baris kosong sebagai pemisah. |
| 136 | Mengautentikasi request sebagai bendahara dengan guard Sanctum. |
| 137 | Mengirim payload sebagai JSON ke endpoint pembaruan gaji. |
| 138 | Baris kosong sebagai pemisah. |
| 139 | Komentar yang menjelaskan respons validasi yang diharapkan. |
| 140 | Memastikan status HTTP adalah 422, yang menandakan input tidak valid. |
| 141 | Memastikan kesalahan validasi terjadi pada field `amount_paid`. |
| 142 | Baris kosong sebagai pemisah. |
| 143 | Komentar bahwa nominal awal harus tetap ada karena update ditolak. |
| 144 | Memastikan data gaji cocok masih ada di tabel `employee_salaries`. |
| 145 | Mencocokkan karyawan uji. |
| 146 | Mencocokkan periode Januari. |
| 147 | Mencocokkan tahun 2026. |
| 148 | Memastikan nominal yang tersimpan tetap 2.000.000. |
| 149 | `]);` menutup assertion bahwa data awal masih ada. |
| 150 | Baris kosong sebagai pemisah. |
| 151 | Memastikan tidak ada record gaji karyawan tersebut dengan nominal negatif. |
| 152 | Membatasi pencarian ke karyawan uji. |
| 153 | Mencari nominal negatif `-1`. |
| 154 | `]);` menutup assertion bahwa data dengan nominal negatif tidak tersimpan. |
| 155 | `}` menutup method tes nominal negatif. |

### Baris 156â€“198: pengguna selain bendahara ditolak

| Baris | Penjelasan |
|---|---|
| 156 | Baris kosong sebagai pemisah. |
| 157 | Memulai PHPDoc untuk skenario tes ketiga. |
| 158 | Memberi ID skenario `TC-TRS-01.3`. |
| 159 | Menjelaskan bahwa pengguna non-bendahara harus mendapat status akses ditolak 403. |
| 160 | Menutup PHPDoc. |
| 161 | Mendeklarasikan method tes untuk akses oleh pengguna non-bendahara. |
| 162 | `{` membuka isi tes. |
| 163 | Komentar yang menjelaskan pembuatan akun tenant untuk simulasi peran selain bendahara. |
| 164 | Membuat objek `User` secara eksplisit dari atribut yang dihasilkan factory. `new User(...)` memastikan ekspresi ini jelas menghasilkan model `User`. |
| 165 | Mengatur atribut `role` menjadi nilai enum `TENANT`. |
| 166 | `]));` menutup array, pemanggilan `raw()`, dan pembuatan objek `User`. `raw()` menghasilkan atribut tanpa menyimpan model ke database. |
| 167 | Menyimpan objek tenant ke database agar dapat dipakai untuk autentikasi request. |
| 168 | Baris kosong sebagai pemisah. |
| 169 | Memulai payload yang akan dikirim tenant. |
| 170 | Mengisi ID karyawan target percobaan update. |
| 171 | Mengisi bulan Januari. |
| 172 | Mengisi tahun 2026. |
| 173 | Mencoba mengubah nominal menjadi 3.000.000. |
| 174 | `];` menutup payload. |
| 175 | Baris kosong sebagai pemisah. |
| 176 | Menetapkan tenant sebagai pengguna yang sedang login pada guard Sanctum. |
| 177 | Mengirim request POST JSON ke endpoint update gaji dengan payload tenant. |
| 178 | Baris kosong sebagai pemisah. |
| 179 | Komentar yang menjelaskan bahwa status 403 dan pesan akses ditolak diharapkan. |
| 180 | Memastikan respons memiliki status HTTP 403, lalu melanjutkan pemeriksaan JSON. |
| 181 | Memulai assertion isi JSON. |
| 182 | Memastikan `success` bernilai `false`. |
| 183 | Memastikan pesan respons menyatakan `Akses ditolak.` |
| 184 | `]);` menutup assertion JSON. |
| 185 | Baris kosong sebagai pemisah. |
| 186 | Komentar bahwa database diperiksa untuk memastikan gaji tidak berubah. |
| 187 | Memastikan record gaji awal masih ada di tabel. |
| 188 | Mencocokkan ID karyawan. |
| 189 | Mencocokkan periode Januari. |
| 190 | Mencocokkan tahun 2026. |
| 191 | Memastikan nominal awal 2.000.000 tetap tersimpan. |
| 192 | `]);` menutup assertion keberadaan data awal. |
| 193 | Baris kosong sebagai pemisah. |
| 194 | Memastikan tidak ada data gaji karyawan tersebut dengan nominal percobaan 3.000.000. |
| 195 | Membatasi pencarian ke ID karyawan uji. |
| 196 | Mencari nominal yang dicoba tenant. |
| 197 | `]);` menutup assertion data perubahan tidak tersimpan. |
| 198 | `}` menutup method tes akses non-bendahara. |

### Baris 199â€“235: simulasi pembatalan perubahan

| Baris | Penjelasan |
|---|---|
| 199 | Baris kosong sebagai pemisah. |
| 200 | Memulai PHPDoc untuk skenario tes keempat. |
| 201 | Memberi ID skenario `TC-TRS-01.4`. |
| 202 | Menjelaskan bahwa skenario menguji keadaan ketika bendahara membatalkan perubahan. |
| 203 | Menutup PHPDoc. |
| 204 | Mendeklarasikan method tes untuk skenario pembatalan. |
| 205 | `{` membuka isi tes. |
| 206 | Komentar bahwa nominal baru hanya dimasukkan sebagai draf, belum dikirim. |
| 207 | Menyimpan nilai draf 3.500.000 ke variabel `$draftNominal`. |
| 208 | Baris kosong sebagai pemisah. |
| 209 | Komentar bahwa tombol batal berarti request update tidak dikirim ke server. |
| 210 | Menjelaskan bahwa pembatalan disimulasikan dengan tidak memanggil endpoint update; tes ini tidak mengklik UI/browser sungguhan. |
| 211 | Baris kosong sebagai pemisah. |
| 212 | Komentar bahwa database harus masih memuat nominal awal. |
| 213 | Memastikan record gaji karyawan untuk periode tertentu masih ada. |
| 214 | Mencocokkan ID karyawan. |
| 215 | Mencocokkan periode Januari. |
| 216 | Mencocokkan tahun 2026. |
| 217 | Memastikan nominal awal masih 2.000.000. |
| 218 | `]);` menutup assertion data awal. |
| 219 | Baris kosong sebagai pemisah. |
| 220 | Memastikan nominal draf tidak tersimpan sebagai gaji. |
| 221 | Membatasi pencarian ke karyawan uji. |
| 222 | Menggunakan nilai draf 3.500.000 sebagai nominal yang tidak boleh ditemukan. |
| 223 | `]);` menutup assertion bahwa perubahan draf tidak tersimpan. |
| 224 | Baris kosong sebagai pemisah. |
| 225 | Komentar bahwa tes kemudian meminta data gaji melalui endpoint baca untuk memastikan data tetap nominal awal. |
| 226 | Mengautentikasi request sebagai bendahara dengan guard Sanctum. |
| 227 | Mengirim GET JSON ke endpoint daftar gaji untuk bulan 1 dan tahun 2026. |
| 228 | Baris kosong sebagai pemisah. |
| 229 | Memastikan endpoint baca merespons status HTTP 200 dan memulai assertion fragmen JSON. |
| 230 | Memulai pemeriksaan fragmen JSON, yaitu sebagian isi respons yang harus ditemukan. |
| 231 | Memastikan fragmen merujuk ke karyawan uji. |
| 232 | Memastikan nominal yang ditampilkan tetap 2.000.000. |
| 233 | `]);` menutup assertion JSON. |
| 234 | `}` menutup method tes pembatalan. |
| 235 | `}` terakhir menutup kelas `SalaryUpdateTest`. |

## Istilah penting untuk ujian lisan

- **Factory**: cara Laravel membuat model/data uji dengan nilai contoh.
- **Model**: representasi tabel database melalui kelas PHP seperti `User` atau `EmployeeSalary`.
- **`$this->...`**: properti objek tes; nilainya bisa digunakan oleh method tes di kelas yang sama.
- **`actingAs()`**: mensimulasikan pengguna yang sudah login.
- **Guard `sanctum`**: mekanisme autentikasi yang dipakai request API dalam tes ini.
- **Payload**: kumpulan data yang dikirim bersama request.
- **HTTP 200**: request berhasil; **422**: input gagal validasi; **403**: pengguna tidak memiliki izin.
- **`assertDatabaseHas()`**: memastikan data dengan nilai tertentu ditemukan di database.
- **`assertDatabaseMissing()`**: memastikan data dengan nilai tertentu tidak ditemukan.
- **`assertJson()`**: memeriksa pasangan nilai JSON; **`assertJsonFragment()`** memeriksa sebagian isi JSON.
- **`raw()`** mengembalikan atribut factory sebagai array. `new User(...)` membentuk model dengan tipe jelas, lalu `save()` menyimpannya.
- Skenario keempat bukan pengujian tombol UI secara langsung. Pembatalan disimulasikan dengan tidak mengirim request update.

## Skrip singkat untuk menjelaskan ke dosen

> â€œFile ini menguji endpoint pembaruan gaji dalam empat kondisi. Sebelum tiap tes, `setUp()` menyiapkan bendahara, lapangan, karyawan, pengeluaran, dan gaji awal. Tes pertama memastikan nominal valid tersimpan dan respons berhasil. Tes kedua memastikan nominal negatif ditolak validasi dengan status 422 dan data lama tidak berubah. Tes ketiga memastikan tenant tidak berhak mengubah gaji, mendapat status 403, dan perubahan tidak tersimpan. Tes keempat mensimulasikan pembatalan dengan tidak mengirim request update, lalu memastikan data awal masih tersimpan dan tetap ditampilkan oleh endpoint baca.â€

## Menjalankan tes

Jalankan tes ini dari root proyek:

```powershell
php artisan test tests/Unit/Services/Treasure/SalaryUpdateTest.php
```

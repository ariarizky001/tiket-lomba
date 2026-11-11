# PRD Fitur Latber (Latihan Bersama)

> Instruksi kerja untuk agent. Fitur baru yang **berdiri sendiri**, tidak mengubah modul Event yang sudah ada. Kerjakan HANYA scope di sini. Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Aplikasi inti (autentikasi, layout admin, modul Event) sudah berjalan. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell. Pola otorisasi mengikuti modul Event yang ada (grup `auth:sanctum` dan aturan role yang sudah dipakai di sana).

## 2. Tujuan
Latber mirip Event (dibuat per tanggal dan punya sesi), tetapi isinya **jauh lebih sederhana**: tidak ada peserta atau invoice. Penjualan dicatat sebagai **angka jumlah per sesi**:
- **Tiket Terjual**: jumlah tiket yang sudah dibayar. `pemasukan = jumlah terjual × harga sesi`.
- **Tiket Terpesan**: jumlah tiket yang sudah dipesan tetapi **belum lunas**. **Tidak dihitung sebagai pemasukan**, hanya angkanya yang dicatat dan dijumlahkan (tidak ada nilai Rupiah).

## 3. Scope
1. Menu **Latber** di navigasi utama: daftar latber, tambah/edit/hapus (nama, tanggal, lokasi)
2. Halaman **Ruang Latber** `/latbers/:id` dengan tiga tab: **Sesi**, **Tiket Terjual**, **Tiket Terpesan**
3. CRUD sesi latber (nama sesi, harga, nomor urut)
4. Grid input jumlah terjual dan jumlah terpesan per sesi
5. Ringkasan di Ruang Latber dan di daftar latber

## 4. Di Luar Scope
Peserta/invoice/pembuatan invoice, template invoice, kuota sesi, pemindahan otomatis terpesan ke terjual, hadiah, pengeluaran, laporan/dashboard khusus latber, ekspor, perubahan pada modul Event.

## 5. Database
**latbers** (model `Latber`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| nama | string(150) | wajib |
| tanggal | date | wajib |
| lokasi | string(150) nullable | |
| timestamps | | |

**latber_sessions** (model `LatberSession`; jangan memakai nama tabel `sessions`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| latber_id | FK → latbers.id | `cascadeOnDelete` |
| nomor_urut | unsignedSmallInteger | wajib, 1 sampai 999 |
| nama | string(150) | wajib |
| harga | unsignedInteger | Rupiah, ≥ 0 |
| terjual | unsignedInteger | default 0 |
| terpesan | unsignedInteger | default 0 |
| timestamps | | |

Index unik `(latber_id, nama)` dan `(latber_id, nomor_urut)`.

**Aturan urutan (satu sumber):** `nomor_urut` naik, lalu `id` naik. Buat local scope `urut` pada `LatberSession` dan pakai di semua query. Nomor boleh berlubang; tidak ada renumber otomatis saat hapus.

## 6. Aturan Bisnis (WAJIB di server)
1. **Pemasukan sesi** = `terjual × harga` (harga sesi saat ini, dihitung server, **bukan** dari klien). **Pemasukan latber** = jumlah pemasukan seluruh sesi.
2. **Terpesan tidak pernah masuk pemasukan** dan tidak ditampilkan dalam Rupiah di mana pun. Hanya angka jumlahnya yang ditampilkan dan dijumlahkan.
3. Mengubah `harga` sesi yang `terjual > 0` mengubah pemasukan sesi itu. Frontend wajib menampilkan peringatan konfirmasi saat hal ini terjadi (bagian 9).
4. Jumlah (`terjual`, `terpesan`) integer 0 sampai 99999.
5. Sesi yang `terjual > 0` atau `terpesan > 0` **tidak bisa dihapus**; latber yang memiliki sesi dengan data tersebut **tidak bisa dihapus** (422).
6. Nomor urut sesi: unik per latber, default `max + 1`, boleh diatur ulang lewat endpoint reorder (sama dengan perilaku sesi Event).
7. Penyimpanan jumlah memakai satu DB transaction; hanya sesi yang dikirim yang diubah, sehingga perubahan admin lain pada sesi lain tidak tertimpa.

## 7. Routes & API
Di dalam grup `auth:sanctum`:

**Latber**
- `GET /api/latbers` → daftar, urut `tanggal` desc lalu `id` desc, setiap elemen memuat `ringkasan`: `{ jumlah_sesi, total_terjual, pemasukan, total_terpesan }` (integer, 0 bila kosong). Gunakan query agregat, **tanpa N+1**
- `POST /api/latbers`, `GET /api/latbers/{latber}`, `PUT /api/latbers/{latber}`, `DELETE /api/latbers/{latber}`. Objek latber memuat `ringkasan` yang sama
- Validasi: `nama` required|string|max:150; `tanggal` required|date; `lokasi` nullable|string|max:150

**Sesi latber**
- `GET /api/latbers/{latber}/sessions` → array sesi terurut. Tiap sesi: `id, nomor_urut, nama, harga, terjual, terpesan, pemasukan` (integer; `pemasukan = terjual × harga`)
- `POST /api/latbers/{latber}/sessions` (`nomor_urut` opsional, default max + 1)
- `PUT /api/latber-sessions/{latberSession}`, `DELETE /api/latber-sessions/{latberSession}`
- `PUT /api/latbers/{latber}/sessions/reorder` body `{ "ids": [3,1,2] }`: `ids` harus tepat semua id sesi latber itu tanpa duplikat, server memberi nomor 1..n dalam satu transaction (geser dulu semua nomor, mis. `+1000`, agar tidak bentrok index unik); daftarkan sebelum resource route
- Validasi: `nama` required|string|max:150, unik per latber; `harga` required|integer|min:0|max:999999999; `nomor_urut` integer|min:1|max:999, unik per latber (pesan: `Nomor urut sudah dipakai sesi lain`)

**Input jumlah**
- `PUT /api/latbers/{latber}/terjual` body `{ "sesi": [ { "id": 1, "jumlah": 10 }, { "id": 2, "jumlah": 4 } ] }`
- `PUT /api/latbers/{latber}/terpesan` body sama
- Aturan: `sesi` required|array|min:1; `sesi.*.id` required|integer|distinct dan **harus milik latber di URL**; `sesi.*.jumlah` required|integer|between:0,99999. Hanya kolom yang dimaksud (`terjual` atau `terpesan`) yang diubah. Respons: sama dengan `GET /api/latbers/{latber}/sessions` (urut dan lengkap)

Semua endpoint tanpa login → 401 JSON. Error validasi format standar Laravel (422). Pesan error hapus: `Sesi sudah memiliki data penjualan dan tidak dapat dihapus` dan `Latber sudah memiliki data penjualan dan tidak dapat dihapus`.

## 8. Struktur Frontend
```
resources/js/client/admin/components/latber/
├── LatberList.js        # /latbers : tabel + drawer form
├── LatberRoom.js        # /latbers/:id : header, ringkasan, Tabs
├── LatberSessionTab.js  # tabel sesi + drawer form + tombol urutan
├── CountGrid.js         # grid input jumlah (dipakai Terjual dan Terpesan)
├── SoldTab.js           # tab Tiket Terjual
└── OrderedTab.js        # tab Tiket Terpesan
```
Daftarkan route `/latbers` dan `/latbers/:id` di `AppLayout`, dan tambahkan item **Latber** di navigasi utama (di samping Dashboard dan Event). Redux tetap hanya untuk auth.

## 9. Kebutuhan Frontend
**LatberList (`/latbers`)**
- `Table` antd (`scroll={{ x: true }}`): Tanggal (`DD/MM/YYYY`), Nama, Lokasi, Sesi, Tiket Terjual, Pemasukan (Rupiah), Tiket Terpesan, Aksi (**Kelola**, Edit, Hapus dengan `Popconfirm`)
- `Input.Search` (nama) dan `DatePicker.RangePicker` (filter tanggal), diterapkan di sisi klien; tombol **Tambah Latber** membuka `Drawer` (nama, tanggal default hari ini, lokasi). Tekan **Kelola** → `/latbers/:id`
- Kosong → `Empty` "Belum ada latber"

**LatberRoom (`/latbers/:id`)**
- Header: tombol kembali, nama, tanggal, lokasi
- Kartu `Statistic` (xs=24, sm=8): **Total Tiket Terjual**, **Pemasukan** (Rupiah), **Total Tiket Terpesan** (angka saja, tanpa Rupiah). Diperbarui setiap data tersimpan
- `Tabs`: **Sesi**, **Tiket Terjual**, **Tiket Terpesan**. Latber tidak ditemukan (404) → `Result` "Latber tidak ditemukan"

**Tab Sesi (`LatberSessionTab`)**
- Tabel: No Urut, Nama Sesi, Harga (Rupiah), Terjual, Terpesan, Urutan (tombol ↑ ↓ memanggil `reorder`, nonaktif di ujung daftar dan saat request), Aksi (Edit, Hapus dengan `Popconfirm`)
- `Drawer` form: Nomor Urut (`InputNumber`, default nomor berikutnya), Nama Sesi, Harga (`InputNumber`, format ribuan, min 0, step 1000). Error 422 tampil di field terkait
- **Saat edit harga sesi yang `terjual > 0`**: sebelum menyimpan, tampilkan `Modal.confirm` "Mengubah harga akan mengubah pemasukan sesi ini dari Rp X menjadi Rp Y. Lanjutkan?" dengan nilai terhitung
- Sesi kosong → `Empty` "Belum ada sesi, tambahkan sesi pertama"

**Tab Tiket Terjual (`SoldTab` + `CountGrid`)**
- Menampilkan **semua sesi** (urut nomor). Kolom: No Urut, Sesi, Harga (Rupiah), **Jumlah Terjual** (`InputNumber` min 0, max 99999, `precision={0}`), **Pemasukan** (= jumlah × harga, terhitung langsung saat mengetik, Rupiah)
- Baris ringkasan (`Table.Summary`): total jumlah dan total pemasukan
- Satu tombol **Simpan** (loading, mencegah klik ganda) dan **Batal Perubahan**; `Tag` kuning "Ada perubahan belum disimpan" selama isian berbeda dari data server; Simpan nonaktif bila tidak ada perubahan
- Simpan memanggil `PUT .../terjual` dengan **hanya baris yang berubah**; sukses → `message.success("Tersimpan")`, perbarui tabel dan kartu ringkasan dari respons. Gagal 422 → `message.error` dan isian tidak hilang

**Tab Tiket Terpesan (`OrderedTab` + `CountGrid`)**
- Struktur sama dengan Tiket Terjual, tetapi kolom hanya: No Urut, Sesi, **Jumlah Terpesan**. **Tidak ada kolom harga atau pemasukan**
- Baris ringkasan hanya menampilkan **total tiket terpesan**
- Keterangan kecil di atas grid: "Tiket terpesan belum dihitung sebagai pemasukan."
- Simpan memanggil `PUT .../terpesan` dengan hanya baris yang berubah

**UX:** mobile-first (375px), teks UI berbahasa Indonesia, uang format Rupiah (`formatRupiah`); kolom Sesi dikunci di kiri (`fixed: 'left'`) pada grid agar terlihat saat digeser.

## 10. Tes Otomatis (pola Tahap 5)
Tambahkan `tests/Feature/LatberTest.php` dan `tests/Feature/LatberSessionTest.php` (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`); `php artisan test` tetap hijau. Cakupan minimal:
- CRUD latber dan validasi (nama dan tanggal wajib, tanggal tidak valid → 422); urutan daftar `tanggal` desc
- CRUD sesi; nama kembar dalam satu latber ditolak, di latber lain boleh; harga negatif ditolak; nomor urut kembar ditolak, default `max + 1`
- `reorder` menukar nomor dengan benar (termasuk menukar dua sesi pertama), atomic, menolak `ids` yang tidak lengkap, ganda, atau milik latber lain
- `terjual` dan `terpesan` tersimpan benar; hanya kolom yang dimaksud berubah; jumlah negatif, > 99999, bukan angka, `id` milik latber lain, atau `id` ganda → 422 dan **tidak ada data yang berubah**
- `pemasukan = terjual × harga`; **terpesan tidak memengaruhi `pemasukan`** dan tidak muncul dalam Rupiah di respons mana pun
- Mengubah harga mengubah `pemasukan` sesuai aturan bagian 6.3
- Hapus sesi yang `terjual > 0` atau `terpesan > 0` ditolak; sesi kosong terhapus; hapus latber yang punya data ditolak; latber kosong terhapus beserta sesinya
- `ringkasan` pada daftar dan detail sama dengan jumlah seluruh sesi; nilai kosong bernilai 0 (bukan `null`)
- Simpan jumlah sebagian sesi tidak menimpa sesi lain
- Semua endpoint tanpa login → 401 JSON; tidak ada N+1 pada `GET /api/latbers` dan `GET .../sessions`

## 11. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `latbers` dan `latber_sessions` ada dengan FK, index unik, dan kolom `terjual`/`terpesan`
- [ ] Navigasi utama memiliki menu **Latber**; modul Event tidak berubah dan tetap berfungsi
- [ ] Tambah, edit, dan hapus latber berfungsi; filter nama dan rentang tanggal bekerja
- [ ] Ruang Latber memiliki tiga tab (Sesi, Tiket Terjual, Tiket Terpesan) dan tiga kartu ringkasan
- [ ] CRUD sesi dan tombol urutan ↑ ↓ berfungsi; nomor kembar ditolak dengan pesan di field
- [ ] **Uji angka:** Latber "Latber Minggu" tanggal 2026-11-15, Sesi 1 "Murai Batu A" harga Rp 50.000 dan Sesi 2 "Cucak Hijau" harga Rp 75.000. Isi **Terjual** 10 dan 4 → pemasukan Rp 500.000 dan Rp 300.000, **total pemasukan Rp 800.000**, total terjual 14
- [ ] Lanjutan uji: isi **Terpesan** 5 dan 2 → total terpesan 7; **pemasukan tetap Rp 800.000**; tab Terpesan tidak menampilkan Rupiah sama sekali
- [ ] Mengubah harga Sesi 1 menjadi Rp 60.000 memunculkan konfirmasi; setelah lanjut, pemasukan Sesi 1 menjadi Rp 600.000
- [ ] Hanya baris yang berubah yang dikirim saat Simpan; mengubah sesi A di satu layar tidak menimpa sesi B yang diubah di layar lain
- [ ] Hapus sesi atau latber yang sudah berisi angka ditolak dengan pesan jelas; yang kosong bisa dihapus
- [ ] Ringkasan di daftar latber sama dengan ringkasan di Ruang Latber
- [ ] Klik ganda Simpan tidak menyebabkan error atau data ganda
- [ ] Endpoint tanpa login → 401; tidak ada N+1; nyaman di lebar 375px; tidak ada error di Console
- [ ] `php artisan test` hijau; tidak ada library baru; fitur lain tetap berjalan

## 12. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, model dan scope, controller, route, komponen React, test)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `php artisan route:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

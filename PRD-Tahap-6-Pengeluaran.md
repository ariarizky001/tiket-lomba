# PRD Tahap 6 — Menu Pengeluaran (Pembukuan)

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 6 di atas hasil Tahap 1 sampai 5. Jangan ubah yang sudah berjalan kecuali disebut di sini. Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 sampai 5 selesai (termasuk input hadiah berbentuk tabel dan tes otomatis). Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru** (untuk tanggal pakai `moment` yang sudah ikut terpasang bersama antd 4). Lingkungan Windows PowerShell.

## 2. Tujuan
Admin mencatat pengeluaran event (gaji, reimburse, dan sejenisnya) di **menu tersendiri, terpisah dari Laporan**, lalu melihat pembukuan sederhana: uang masuk dari penjualan tiket dikurangi pengeluaran = saldo akhir.

## 3. Scope Tahap 6
1. Tabel dan API `expenses` (CRUD, per event)
2. Tab baru **Pengeluaran** di Ruang Event (urutan tab: Sesi, Peserta & Invoice, Laporan, **Pengeluaran**)
3. Ringkasan pembukuan di bagian atas tab Pengeluaran
4. Daftar kategori pengeluaran
5. Proteksi: event yang sudah punya pengeluaran tidak bisa dihapus

## 4. Di Luar Scope
Upload bukti/nota, kategori custom (dikelola user), anggaran (budget), pemasukan lain di luar tiket (sponsor, donasi), ekspor Excel/PDF, laporan lintas event, alur persetujuan, pembedaan role, perubahan pada tab Laporan, grafik.

## 5. Database
**expenses** (model `Expense`, memakai `SoftDeletes`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| event_id | FK → events.id | `cascadeOnDelete` |
| tanggal | date | tanggal pengeluaran, wajib |
| kategori | string(30) | salah satu dari daftar di bawah |
| keterangan | string(255) | wajib, contoh "Gaji juri sesi A" |
| penerima | string(150) nullable | dibayarkan ke siapa |
| nominal | unsignedInteger | Rupiah, ≥ 1 |
| created_by | FK → users.id nullable | `nullOnDelete`, diisi otomatis dari user login |
| timestamps, deleted_at | | |

Index `(event_id, tanggal)`. Hapus pengeluaran = **soft delete** (data tetap ada di database untuk keperluan pembukuan, tidak tampil di aplikasi, dan tidak dihitung di total mana pun). Tidak perlu fitur pulihkan.

**Daftar kategori** (satu sumber di backend, `config/expense.php`, key `categories`):
`Gaji`, `Reimburse`, `Konsumsi`, `Sewa Tempat`, `Perlengkapan`, `Transportasi`, `Lain-lain`.

## 6. Definisi Pembukuan (WAJIB konsisten di server dan UI)
- **Pemasukan** = jumlah `subtotal` item dari invoice berstatus **lunas** (angka yang sama persis dengan `ringkasan.pemasukan` pada tab Laporan). Gunakan **satu method/service bersama** untuk menghitungnya agar kedua halaman tidak pernah berbeda.
- **Belum lunas (info)** = jumlah subtotal invoice belum lunas. Ditampilkan sebagai catatan, **tidak** masuk pemasukan maupun saldo.
- **Hadiah** = total hadiah event (angka yang sama dengan `ringkasan.total_hadiah` pada tab Laporan), ditampilkan sebagai baris tambahan hanya-baca, tanpa input di menu ini (diinput tetap di tab Laporan).
- **Total pengeluaran** = jumlah `nominal` seluruh pengeluaran event (tidak termasuk yang di-soft-delete).
- **Saldo akhir** = `pemasukan − hadiah − total pengeluaran`. Boleh negatif.
- **Per kategori** = total `nominal` dikelompokkan per kategori.

Tab Laporan **tidak diubah**: istilah "Laba Bersih" di sana tetap = pemasukan − hadiah (belum memperhitungkan pengeluaran).

## 7. Routes & API
Di dalam grup `auth:sanctum`:
- `GET /api/expense-categories` → array nama kategori dari config
- `GET /api/events/{event}/expenses` → array pengeluaran event, urut `tanggal` desc lalu `id` desc. Setiap elemen: `id, tanggal (YYYY-MM-DD), kategori, keterangan, penerima, nominal` (integer)
- `POST /api/events/{event}/expenses` → buat (201 + objek)
- `PUT /api/expenses/{expense}` → ubah (200 + objek)
- `DELETE /api/expenses/{expense}` → soft delete (204)
- `GET /api/events/{event}/bookkeeping`:
```json
{
  "pemasukan": 350000,
  "belum_lunas": 0,
  "hadiah": 80000,
  "pengeluaran": 150000,
  "saldo": 120000,
  "per_kategori": [
    { "kategori": "Gaji", "total": 100000 },
    { "kategori": "Reimburse", "total": 50000 }
  ]
}
```
Semua nilai berupa **integer** (0 bila kosong, bukan `null`). `per_kategori` hanya memuat kategori yang punya total > 0, urut total terbesar. Gunakan query agregat, **tanpa N+1**.

**Validasi (POST/PUT):** `tanggal` required|date; `kategori` required|in:(daftar config); `keterangan` required|string|max:255; `penerima` nullable|string|max:150; `nominal` required|integer|min:1|max:999999999. Error format standar Laravel (422). `event_id` dan `created_by` tidak boleh diisi dari body (ditentukan server).

**Perubahan pada Tahap 1:** `EventController@destroy` juga menolak penghapusan bila event punya pengeluaran → 422 `Event sudah memiliki data pembukuan dan tidak dapat dihapus` (pesan untuk invoice yang sudah ada tetap).

## 8. Kebutuhan Frontend
**Struktur tambahan**
```
resources/js/client/admin/components/expense/
├── ExpenseTab.js          # memuat data, menyusun bagian-bagian
├── BookkeepingSummary.js  # ringkasan pembukuan
├── ExpenseTable.js        # filter + tabel
└── ExpenseDrawer.js       # form tambah/edit
```
Daftarkan tab **Pengeluaran** pada `EventRoom`. Redux tetap hanya untuk auth.

**Bagian 1: Ringkasan Pembukuan (`BookkeepingSummary`)**
- `Statistic` antd dalam `Row/Col` (xs=12, md=6), berurutan: **Pemasukan (Lunas)**, **Hadiah**, **Total Pengeluaran**, **Saldo Akhir** (hijau bila ≥ 0, merah bila negatif)
- Teks kecil abu-abu di bawahnya: "Belum lunas Rp X (belum dihitung sebagai pemasukan)" bila `belum_lunas > 0`
- Rincian per kategori ditampilkan sebagai deretan `Tag` (`Gaji · Rp 100.000`), disembunyikan bila kosong

**Bagian 2: Daftar Pengeluaran (`ExpenseTable`)**
- Toolbar: tombol **Tambah Pengeluaran**; `Select` filter kategori (Semua kategori + daftar dari API); `DatePicker.RangePicker` filter tanggal; `Input.Search` mencari pada keterangan dan penerima. Filter diterapkan **di sisi klien** pada data yang sudah dimuat
- `Table` antd, `scroll={{ x: true }}`, kolom: Tanggal (format `DD/MM/YYYY`), Kategori (`Tag`), Keterangan, Penerima (kosong tampil `-`), Nominal (Rupiah, rata kanan), Aksi (Edit, Hapus dengan `Popconfirm` "Hapus pengeluaran ini?")
- Baris ringkasan di bawah tabel (`Table.Summary`): **total sesuai filter yang sedang aktif**. Ringkasan di Bagian 1 tidak terpengaruh filter
- Kosong → `Empty` "Belum ada pengeluaran"

**ExpenseDrawer** (`Drawer`, lebar penuh di mobile)
- Field: Tanggal (`DatePicker`, default hari ini, format `DD/MM/YYYY`, dikirim `YYYY-MM-DD`), Kategori (`Select`, wajib), Keterangan (`Input`, wajib), Penerima (`Input`, opsional), Nominal (`InputNumber`, min 1, format ribuan `Rp`, wajib)
- Tombol **Simpan** dengan state loading (cegah klik ganda); mode edit mengisi form dari baris
- Sukses → `message.success`, tutup drawer, muat ulang daftar **dan** ringkasan. Error 422 tampil di field terkait; isian tidak hilang

**UX:** mobile-first (375px), teks UI berbahasa Indonesia, uang format Rupiah (`formatRupiah`).

## 9. Tes Otomatis (mengikuti pola Tahap 5)
Tambahkan `tests/Feature/ExpenseTest.php` (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`), dan `php artisan test` tetap hijau. Cakupan minimal:
- CRUD pengeluaran; validasi (kategori di luar daftar, nominal < 1, keterangan kosong, tanggal tidak valid → 422)
- `event_id` dan `created_by` dari body diabaikan; `created_by` terisi user login
- Hapus = soft delete: tidak muncul di daftar dan tidak dihitung di total, tetapi barisnya masih ada di database
- `bookkeeping`: pemasukan hanya dari invoice lunas dan **sama dengan** `ringkasan.pemasukan` di endpoint laporan; hadiah sama dengan `ringkasan.total_hadiah`; saldo = pemasukan − hadiah − pengeluaran; nilai kosong bernilai 0 (bukan `null`); `per_kategori` benar
- Hapus event yang punya pengeluaran → 422
- Semua endpoint baru tanpa login → 401 JSON
- Anti N+1: jumlah query `expenses` dan `bookkeeping` konstan terhadap jumlah data

## 10. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `expenses` ada dengan FK, index, dan kolom `deleted_at`
- [ ] Ruang Event memiliki tab Sesi, Peserta & Invoice, Laporan, Pengeluaran; tab Laporan tidak berubah dan tetap berfungsi
- [ ] Tambah, edit, dan hapus pengeluaran berfungsi; validasi tampil per field
- [ ] Uji angka: pemasukan lunas Rp 350.000, hadiah Rp 80.000, pengeluaran Gaji Rp 100.000 + Reimburse Rp 50.000 → Total Pengeluaran Rp 150.000, **Saldo Akhir Rp 120.000**, rincian kategori tampil benar
- [ ] Angka Pemasukan dan Hadiah di tab Pengeluaran sama dengan di tab Laporan
- [ ] Menandai invoice lunas mengubah Pemasukan dan Saldo setelah ringkasan dimuat ulang; invoice belum lunas hanya tampil sebagai catatan
- [ ] Saldo negatif tampil merah
- [ ] Filter kategori, rentang tanggal, dan pencarian bekerja; total di footer tabel mengikuti filter, ringkasan atas tidak
- [ ] Setelah hapus, pengeluaran hilang dari daftar dan ringkasan, tetapi barisnya masih ada di database (soft delete)
- [ ] Hapus event yang punya pengeluaran ditolak dengan pesan yang jelas
- [ ] Klik ganda tombol Simpan tidak membuat pengeluaran kembar
- [ ] Semua endpoint baru tanpa login → 401; tidak ada N+1
- [ ] Nyaman di lebar layar 375px; tidak ada error di Console
- [ ] `php artisan test` hijau; tidak ada library baru; fitur Tahap 1 sampai 5 tetap berjalan

## 11. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, config, model, controller, route, komponen React, test)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `php artisan config:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

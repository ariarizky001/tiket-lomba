# PRD Tahap 3 — Peserta & Invoice

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 3 di atas hasil Tahap 1 dan 2 (jangan ubah yang sudah berjalan kecuali disebut di sini). Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 dan 2 selesai dan lolos (login, dashboard, CRUD event, Ruang Event, CRUD sesi). Tech stack dan larangan library sama persis dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). Lingkungan Windows PowerShell (perintah satu baris). **Tidak boleh menambah library baru** (cetak memakai `window.print()` + CSS print).

## 2. Tujuan
Admin mencatat peserta yang membeli tiket (satu atau beberapa sesi) dalam sebuah event, sistem menghitung subtotal/total otomatis, membuat invoice berkode pendek, dan admin dapat menandai lunas/belum lunas, melihat, mencetak, atau menyalin invoice untuk dikirim lewat WhatsApp.

## 3. Scope Tahap 3
1. Tabel dan API `invoices` + `invoice_items`
2. Tab **Peserta & Invoice** di Ruang Event (aktifkan tab yang sebelumnya nonaktif)
3. Form tambah/edit peserta dengan banyak sesi + hitung subtotal dan total otomatis
4. Validasi kuota sesi, tampilan terjual/sisa per sesi
5. Status lunas/belum lunas
6. Tampilan invoice (lihat, cetak, salin teks WhatsApp) dan pengaturan teks invoice per event
7. Proteksi data (hapus sesi/event yang sudah punya invoice, kuota tidak boleh di bawah terjual)

## 4. Di Luar Scope
Hadiah, dashboard laporan, grid gantangan/cetak bagan, halaman public, pembayaran sebagian/cicilan, ekspor Excel/PDF, pengiriman WhatsApp otomatis, manajemen user.

## 5. Database
**invoices** (model `Invoice`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| event_id | FK → events.id | `cascadeOnDelete` |
| nomor | unsignedInteger | urutan per event, mulai 1 |
| kode | string(20) | contoh `LBPB-0001` |
| nama_peserta | string(150) | wajib |
| total | unsignedInteger | Rupiah, dihitung server |
| status | string(15) | `belum_lunas` (default) \| `lunas` |
| paid_at | timestamp nullable | terisi saat lunas, null saat belum lunas |
| timestamps | | |

Index unik `(event_id, nomor)`.

**invoice_items** (model `InvoiceItem`)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| invoice_id | FK → invoices.id | `cascadeOnDelete` |
| event_session_id | FK → event_sessions.id | `cascadeOnDelete` |
| qty | unsignedSmallInteger | ≥ 1 |
| harga | unsignedInteger | **snapshot** harga sesi saat transaksi |
| subtotal | unsignedInteger | `qty × harga` |
| timestamps | | |

Satu invoice tidak boleh memuat sesi yang sama dua kali (validasi `distinct`).

**events** (tambah kolom nullable untuk template invoice): `invoice_judul` string(100), `invoice_catatan` text. Nilai default tampilan bila kosong: judul "INVOICE", catatan kosong.

Relasi: `Event hasMany Invoice`, `Invoice belongsTo Event`, `Invoice hasMany InvoiceItem`, `InvoiceItem belongsTo EventSession`, `EventSession hasMany InvoiceItem`.

## 6. Aturan Bisnis (WAJIB, dijalankan di server)
1. **Server menghitung harga.** Abaikan harga/subtotal/total dari klien. `harga` diambil dari `event_sessions.harga` saat simpan, `subtotal = qty × harga`, `total = jumlah subtotal`.
2. **Kode invoice**: `{INISIAL}-{nomor 4 digit}`. INISIAL = huruf pertama tiap kata nama event, kapital, hanya huruf/angka, maksimal 4 karakter (contoh "Lomba Burung Piala Bupati" → `LBPB`). `nomor` = nomor terbesar di event itu + 1 (mulai 1). Nomor tidak dipakai ulang bila invoice dihapus. Pembuatan nomor dan kode dilakukan di dalam DB transaction dengan `lockForUpdate` pada event agar tidak bentrok saat dua admin menyimpan bersamaan.
3. **Kuota**: jumlah `qty` seluruh invoice (lunas + belum lunas) pada satu sesi tidak boleh melebihi `kuota`. Saat edit, qty invoice itu sendiri tidak dihitung ganda. Pelanggaran → HTTP 422 dengan pesan per sesi, contoh `Sesi "Kelas A" tersisa 2 tiket`. Pengecekan dilakukan dalam transaction dengan `lockForUpdate` pada baris sesi terkait.
4. **Status**: `lunas` mengisi `paid_at = now()`, kembali ke `belum_lunas` mengosongkan `paid_at`. Invoice baru default `belum_lunas`, boleh dibuat langsung `lunas`.
5. **Hapus invoice** mengembalikan kuota otomatis (item ikut terhapus).
6. **Sesi tidak boleh dihapus** jika sudah punya invoice item → 422 `Sesi sudah memiliki invoice dan tidak dapat dihapus`.
7. **Kuota sesi tidak boleh diturunkan di bawah jumlah terjual** → 422 `Kuota tidak boleh kurang dari tiket terjual (N)`. (Perubahan pada `EventSessionController@update` Tahap 2.)
8. **Event tidak boleh dihapus** jika sudah punya invoice → 422 `Event sudah memiliki invoice dan tidak dapat dihapus`. (Perubahan pada `EventController@destroy` Tahap 1.)
9. Perubahan harga sesi **tidak** mengubah invoice lama (karena snapshot).

## 7. Routes & API
Di dalam grup `auth:sanctum`:
- `GET /api/events/{event}/invoices` → daftar invoice event beserta `items` (dengan `session_nama`). Query opsional: `status` (`lunas`|`belum_lunas`), `q` (cari nama peserta/kode). Urut `id` desc.
- `POST /api/events/{event}/invoices`
- `GET /api/invoices/{invoice}`
- `PUT /api/invoices/{invoice}`
- `PATCH /api/invoices/{invoice}/status` body `{status}`
- `DELETE /api/invoices/{invoice}` → 204
- `PUT /api/events/{event}/invoice-template` body `{invoice_judul, invoice_catatan}` → kembalikan event

**Body POST/PUT invoice**
```json
{
  "nama_peserta": "Budi",
  "status": "belum_lunas",
  "items": [
    { "event_session_id": 3, "qty": 1 },
    { "event_session_id": 4, "qty": 2 }
  ]
}
```
**Validasi:** `nama_peserta` required|string|max:150; `status` in:lunas,belum_lunas (default belum_lunas); `items` required|array|min:1; `items.*.event_session_id` required|exists dan **harus milik event yang sama**; `items.*.qty` required|integer|min:1|max:100; `items.*.event_session_id` distinct. `invoice_judul` nullable|string|max:100; `invoice_catatan` nullable|string|max:1000.

**Perubahan pada endpoint sesi (Tahap 2):** respons `GET /api/events/{event}/sessions` ditambah field `terjual` (jumlah qty seluruh invoice) dan `sisa` (`kuota − terjual`), dihitung dengan `withSum`/query agregat (tanpa N+1).

## 8. Kebutuhan Frontend
**Struktur tambahan**
```
resources/js/client/admin/components/invoice/
├── InvoiceTab.js          # tabel + filter + tombol tambah
├── InvoiceForm.js         # drawer tambah/edit
├── InvoiceView.js         # modal tampilan invoice (cetak/salin)
└── InvoiceTemplateModal.js
resources/js/client/common/helpers/Utils.js   # formatRupiah (Intl.NumberFormat id-ID)
```
Aktifkan tab **Peserta & Invoice** pada `EventRoom`; tab Hadiah dan Laporan tetap nonaktif. Tab Sesi: tambahkan kolom **Terjual** dan **Sisa**.

**InvoiceTab**
- Toolbar: `Input.Search` (nama/kode), `Select` filter status (Semua/Lunas/Belum Lunas), tombol **Tambah Peserta**, tombol **Pengaturan Invoice**
- `Table`, `scroll={{ x: true }}`, kolom: Kode, Nama Peserta, Sesi Dibeli (`Tag` per item, format `Nama Sesi ×qty`), Total (Rupiah), Status (`Tag` hijau "Lunas", merah "Belum Lunas"), Aksi
- Aksi: **Lihat Invoice**, **Edit**, **Tandai Lunas / Tandai Belum Lunas** (`Popconfirm`), **Hapus** (`Popconfirm`)
- Teks nama peserta berwarna merah bila belum lunas, hitam bila lunas
- Setelah aksi apa pun: muat ulang daftar + `message.success`; error 422 ditampilkan dengan `message.error` berisi pesan dari API

**InvoiceForm** (`Drawer`, lebar penuh di mobile)
- Field: Nama Peserta; daftar item dengan `Form.List` (Select sesi + `InputNumber` qty + subtotal tampil otomatis + tombol hapus baris); tombol "Tambah Sesi"; **Total** tampil otomatis dan diperbarui langsung; Select Status (default Belum Lunas)
- Opsi Select sesi menampilkan `Nama — Rp harga — sisa N`; sesi dengan `sisa = 0` dinonaktifkan (kecuali sesi yang sudah ada di invoice yang sedang diedit)
- Sesi yang sudah dipilih di baris lain tidak muncul lagi di baris berikutnya
- Tombol **Simpan & Buat Invoice**. Setelah buat baru sukses, tutup drawer dan **langsung buka InvoiceView** invoice tersebut
- Perhitungan di klien hanya untuk tampilan; nilai final dari server

**InvoiceView** (`Modal`)
- Isi: judul (dari `invoice_judul`, default "INVOICE"), nama event, kode, tanggal dibuat, nama peserta, tabel item (sesi, qty, harga, subtotal), total, status (Lunas/Belum Lunas), catatan (`invoice_catatan`)
- Tombol **Cetak** → `window.print()` dengan CSS `@media print` yang hanya menampilkan area invoice (sembunyikan header, sidebar, tombol, overlay lain)
- Tombol **Salin untuk WhatsApp** → salin teks polos ke clipboard (`navigator.clipboard.writeText`), lalu `message.success("Tersalin")`. Format:
```
*INVOICE {kode}*
Event: {nama event}
Nama: {nama peserta}

{Nama Sesi} x{qty} = Rp {subtotal}
...
*Total: Rp {total}*
Status: {LUNAS / BELUM LUNAS}

{catatan}
```

**InvoiceTemplateModal**: form dua field (judul invoice, catatan) → simpan ke event.

**UX:** mobile-first (375px), semua teks UI berbahasa Indonesia, harga selalu format Rupiah, Redux tetap hanya untuk auth.

## 9. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `invoices`, `invoice_items`, kolom template di `events` ada
- [ ] Tab Peserta & Invoice aktif dan hanya menampilkan invoice milik event tersebut
- [ ] Tambah peserta dengan 2 sesi (qty berbeda): subtotal dan total benar di form dan di tabel
- [ ] Kode invoice berformat `INISIAL-0001`, berurutan per event, tidak dipakai ulang setelah hapus
- [ ] Mengubah `harga` sesi setelah transaksi tidak mengubah total invoice lama
- [ ] Membeli melebihi sisa kuota ditolak (422) dengan pesan jelas; sesi `sisa = 0` tidak bisa dipilih di form
- [ ] Mengirim harga/total palsu dari klien tidak berpengaruh (server menghitung ulang)
- [ ] Mengirim `event_session_id` milik event lain ditolak
- [ ] Edit invoice menghitung ulang total dan kuota tanpa menghitung ganda qty invoice itu sendiri
- [ ] Toggle lunas/belum lunas mengisi/mengosongkan `paid_at`; tag warna dan warna teks nama sesuai status
- [ ] Hapus invoice mengembalikan kuota; kolom Terjual/Sisa di tab Sesi ikut berubah
- [ ] Hapus sesi yang sudah punya invoice ditolak; menurunkan kuota di bawah terjual ditolak
- [ ] Hapus event yang sudah punya invoice ditolak
- [ ] Cari nama/kode dan filter status berfungsi
- [ ] Cetak hanya menampilkan area invoice; Salin WhatsApp menghasilkan teks sesuai format
- [ ] Judul dan catatan invoice yang diubah di pengaturan tampil di invoice
- [ ] Semua endpoint tanpa login → 401
- [ ] Nyaman di lebar layar 375px
- [ ] Tidak ada library baru; fitur Tahap 1 dan 2 tetap berjalan

## 10. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, model, controller, route, komponen React)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `npm run dev`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

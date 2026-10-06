# PRD Tahap 2 — Ruang Event & Master Sesi

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 2 di atas hasil Tahap 1 (jangan ubah yang sudah berjalan kecuali disebut di sini). Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 selesai dan lolos: login/logout, dashboard event aktif, CRUD Event. Tech stack dan larangan library sama persis dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Laravel 10, Sanctum cookie, Laravel Mix 6). Lingkungan Windows PowerShell (perintah satu baris).

## 2. Tujuan
Admin dapat masuk ke satu event ("Ruang Event") dan mengelola sesi di dalamnya: nama sesi, harga tiket, dan jumlah peserta (kuota).

## 3. Scope Tahap 2
1. Tabel dan API `event_sessions` (CRUD, terikat ke event)
2. Halaman **Ruang Event** `/events/:id` dengan tab; tab **Sesi** aktif
3. CRUD sesi (tabel + drawer form)
4. Navigasi masuk ke Ruang Event dari dashboard dan dari daftar event

## 4. Di Luar Scope
Peserta, invoice, hadiah, laporan, penghitungan tiket terjual/sisa, proteksi hapus sesi yang sudah punya pesanan (menyusul di Tahap 3), halaman public, realtime.

## 5. Database
**event_sessions** (nama model `EventSession`; jangan pakai nama tabel `sessions` karena bentrok dengan tabel session Laravel)
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| event_id | FK → events.id | `cascadeOnDelete` |
| nama | string(150) | wajib |
| harga | unsignedInteger | Rupiah, ≥ 0 |
| kuota | unsignedSmallInteger | jumlah peserta/tiket, ≥ 1, default 24 |
| timestamps | | |

Index unik `(event_id, nama)`: nama sesi tidak boleh kembar dalam satu event.

Relasi: `Event hasMany EventSession`, `EventSession belongsTo Event`.

## 6. Routes & API
Di dalam grup `auth:sanctum` pada `routes/api.php`:
```php
Route::apiResource('events.sessions', EventSessionController::class)
    ->shallow()
    ->parameters(['sessions' => 'eventSession']);
```
Menghasilkan:
- `GET /api/events/{event}/sessions` (urut `id` asc)
- `POST /api/events/{event}/sessions`
- `GET|PUT|DELETE /api/sessions/{eventSession}`

**Validasi (store & update)**
- `nama`: required|string|max:150, unik per event (saat update abaikan record sendiri)
- `harga`: required|integer|min:0
- `kuota`: required|integer|min:1|max:1000

Error validasi memakai format standar Laravel (422). Hapus sukses → 204.

`EventController@show` mengembalikan data event; `EventController@index` tidak berubah.

## 7. Kebutuhan Frontend
**Struktur tambahan**
```
resources/js/client/admin/components/
├── event/EventRoom.js        # halaman /events/:id (header + Tabs)
└── session/SessionTab.js     # tabel + drawer form sesi
```
**Routing (tambah di `AppLayout`)**
- `/events` → `EventList` (sudah ada)
- `/events/:id` → `EventRoom`

**Navigasi**
- Kartu event di Dashboard menjadi klik-able → `/events/:id`
- Di tabel `EventList` tambah tombol **Kelola** per baris → `/events/:id`

**EventRoom**
- Ambil event via `GET /api/events/{id}`; tampilkan `PageHeader`/judul berisi nama event, tanggal, lokasi, dan tombol Kembali ke daftar event
- `Tabs` antd: **Sesi** (aktif), **Peserta & Invoice**, **Hadiah**, **Laporan** (tiga tab terakhir `disabled`)
- Event tidak ditemukan (404) → tampilkan `Result` "Event tidak ditemukan" dengan tombol kembali

**SessionTab**
- `Table` antd, kolom: No (nomor urut tampilan), Nama Sesi, Harga (format Rupiah `Rp 50.000`, via `Intl.NumberFormat('id-ID')`), Kuota, Aksi; `scroll={{ x: true }}`
- Tombol "Tambah Sesi" → `Drawer` berisi `Form`: nama (Input), harga (`InputNumber` dengan format ribuan, min 0, step 1000), kuota (`InputNumber`, min 1, default 24)
- Aksi edit (isi form dari baris) dan hapus (`Popconfirm`)
- Setelah simpan/hapus: muat ulang daftar + `message.success`
- Error validasi dari API tampil di field terkait
- Kosong → `Empty` "Belum ada sesi, tambahkan sesi pertama"

**Perubahan kecil di Tahap 1**
- `Popconfirm` hapus event: teks "Menghapus event juga menghapus seluruh sesinya."

**UX:** mobile-first, semua teks UI berbahasa Indonesia, Redux tetap hanya untuk auth (data sesi cukup state lokal komponen).

## 8. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses; tabel `event_sessions` ada dengan FK dan index unik
- [ ] Klik kartu event di dashboard dan tombol Kelola membuka `/admin/events/:id`
- [ ] Tab Sesi menampilkan sesi milik event itu saja (tidak bercampur event lain)
- [ ] Tambah, edit, hapus sesi berfungsi; harga tampil format Rupiah
- [ ] Nama sesi kembar dalam satu event ditolak dengan pesan error; nama sama di event berbeda diperbolehkan
- [ ] Harga negatif atau kuota < 1 ditolak
- [ ] Menghapus event ikut menghapus sesinya
- [ ] Tab Peserta & Invoice, Hadiah, Laporan tampil tapi nonaktif
- [ ] `/api/events/{id}/sessions` tanpa login → 401
- [ ] Event ID tidak valid menampilkan halaman "Event tidak ditemukan"
- [ ] Nyaman di lebar layar 375px
- [ ] Tidak ada library baru di luar tech stack Tahap 1

## 9. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (migration, model, controller, route, komponen React)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `npm run dev`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

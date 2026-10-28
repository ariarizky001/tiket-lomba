# PRD Revisi — Nomor Urut Sesi

> Instruksi kerja untuk agent. Revisi pada fitur Sesi (Tahap 2) dan semua tempat yang mengurutkan atau menampilkan sesi. Kerjakan HANYA yang tertulis di sini. Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 1. Masalah
Sesi saat ini diurutkan menurut waktu dibuat (`id`), sehingga sesi yang dibuat pertama otomatis menjadi "Sesi 1". Padahal sesi yang dibuat lebih dulu bisa saja berposisi sesi 2. Admin ingin **menetapkan sendiri nomor urut sesi**, dan nomor itu menjadi urutan di seluruh aplikasi, terutama dropdown pilihan sesi saat menambah peserta:
```
1. Sesi A
2. Sesi B
3. Sesi C
```

## 2. Scope
1. Kolom `nomor_urut` pada sesi, ditetapkan admin saat tambah/edit sesi
2. Tombol naik/turun untuk mengatur ulang urutan dengan cepat
3. Seluruh daftar dan tampilan sesi memakai urutan `nomor_urut`
4. Migrasi data sesi yang sudah ada

## 3. Di Luar Scope
Drag and drop, nomor urut peserta, perubahan harga/kuota, library baru.

## 4. Database
Pada tabel `event_sessions` tambahkan:
| kolom | tipe | catatan |
|---|---|---|
| nomor_urut | unsignedSmallInteger | wajib, 1 sampai 999 |

Index **unik** `(event_id, nomor_urut)`: nomor tidak boleh kembar di dalam satu event; event berbeda boleh memakai nomor sama.

**Migrasi (WAJIB aman untuk data yang ada):** (1) tambah kolom `nullable`; (2) isi nilai awal untuk sesi yang sudah ada: per event, nomor 1, 2, 3, … sesuai urutan `id` naik; (3) ubah kolom menjadi NOT NULL dan pasang index unik. Tulis dalam satu migration yang bisa di-`rollback`.

## 5. Aturan Urutan (satu sumber)
Urutan sesi di **seluruh** backend dan frontend: **`nomor_urut` naik, lalu `id` naik**. Buat satu local scope di model `EventSession` (mis. `scopeUrut`) dan pakai di semua query agar tidak ada yang tertinggal. Nomor boleh berlubang (mis. 1, 2, 4 setelah sesi 3 dihapus); tidak ada renumber otomatis saat hapus.

## 6. API
Di dalam grup `auth:sanctum`:

**Perubahan pada endpoint sesi (Tahap 2)**
- Setiap objek sesi pada respons (`index`, `store`, `update`, `show`) memuat `nomor_urut` (integer)
- `GET /api/events/{event}/sessions` diurutkan sesuai bagian 5
- `POST /api/events/{event}/sessions`: `nomor_urut` **opsional**; bila kosong, server mengisi `max(nomor_urut) + 1` event itu (1 bila belum ada sesi)
- `PUT /api/sessions/{eventSession}`: `nomor_urut` boleh dikirim; bila dikirim harus valid
- Validasi: `nomor_urut` nullable (POST) | integer | min:1 | max:999, **unik per event** (saat update abaikan record sendiri). Pesan bila kembar: `Nomor urut sudah dipakai sesi lain`

**Endpoint baru: atur ulang urutan**
`PUT /api/events/{event}/sessions/reorder` (daftarkan **sebelum** `apiResource`), body:
```json
{ "ids": [3, 1, 2] }
```
- `ids` harus berisi **tepat semua** id sesi event itu tanpa duplikat (kurang, lebih, atau milik event lain → 422)
- Server memberi nomor 1, 2, 3, … sesuai urutan `ids`, dalam **satu DB transaction**. Untuk menghindari bentrok index unik saat menukar nomor, geser dulu semua nomor event itu (mis. `+ 1000`) sebelum menetapkan nomor akhir
- Respons: daftar sesi terurut, sama dengan `GET .../sessions`

**Pemakaian urutan di endpoint lain (semua wajib memakai scope urutan)**
- `GET /api/events/{event}/invoices`: `items` tiap invoice diurutkan menurut `nomor_urut` sesinya; setiap item memuat tambahan `session_nomor` (integer, selain `session_nama`)
- `GET /api/events/{event}/report`: `sesi[]` terurut; setiap sesi memuat `nomor_urut`
- `GET /api/events/{event}/rekap-peserta`: `sesi[]` terurut; field `urutan` kini berisi `nomor_urut` sesi (bukan posisi berdasarkan `id`)
- Renderer template invoice (`InvoiceRenderer`): baris bagian *Kelas Pesanan* diurutkan menurut `nomor_urut` sesi (bukan `event_session_id`); `{no}` tetap nomor baris 1, 2, 3; tambahkan placeholder baris **`{nomor_sesi}`** (= `nomor_urut` sesi) dan daftarkan di `PlaceholderHelp` dan metadata placeholder

## 7. Kebutuhan Frontend
**Tab Sesi (`SessionTab`)**
- Tabel diurutkan sesuai bagian 5; kolom pertama **No Urut** menampilkan `nomor_urut` (bukan indeks baris)
- Kolom **Urutan** berisi dua tombol kecil naik (↑) dan turun (↓) (`@ant-design/icons` `ArrowUpOutlined`/`ArrowDownOutlined`, tanpa teks). Klik memanggil `PUT .../sessions/reorder` dengan susunan `ids` yang sudah ditukar, lalu memuat ulang. Tombol naik nonaktif di baris pertama, tombol turun nonaktif di baris terakhir; tombol nonaktif saat request berjalan
- Form tambah/edit sesi (`Drawer`) menambah field **Nomor Urut** (`InputNumber`, min 1, max 999). Saat **tambah**, terisi otomatis nomor berikutnya (maks + 1). Error 422 ("Nomor urut sudah dipakai sesi lain") tampil di field itu
- Sesi baru dengan nomor yang dipilih tampil di posisi sesuai nomornya setelah simpan

**Tempat lain yang menampilkan sesi (wajib mengikuti urutan baru dan memakai `nomor_urut`)**
- **Dropdown sesi di form peserta**: urut nomor, label `{nomor_urut}. {nama} — Rp harga — sisa N` (contoh `1. SESI A — Rp 100.000 — sisa 24`)
- **Filter sesi** (bila ada) di tabel peserta dan invoice: urut nomor dengan label `{nomor_urut}. {nama}`
- **Rekap Peserta**: header tabel `Sesi {nomor_urut} {nama}`; tabel berjajar dari nomor terkecil
- **Laporan** (tabel sesi dan grid hadiah): baris berurutan menurut nomor
- Kolom nama sesi pada baris item invoice/peserta menampilkan `{nomor_urut}. {nama}` bila ruang mencukupi

Semua pengurutan di frontend memakai urutan dari API (jangan diurutkan ulang sendiri berdasarkan `id`). Redux tetap hanya untuk auth.

## 8. Tes Otomatis (pola Tahap 5)
Tambahkan atau perluas test (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`); `php artisan test` tetap hijau:
- Migrasi mengisi nomor 1..n per event sesuai `id`, dan event berbeda tidak saling memengaruhi
- Nomor kembar dalam satu event ditolak (422), event berbeda boleh sama; `nomor_urut` 0, 1000, atau bukan angka → 422
- `POST` tanpa `nomor_urut` → terisi maks + 1 (1 untuk sesi pertama)
- **Sesi dibuat pertama dengan nomor 2, sesi dibuat kedua dengan nomor 1** → `GET sessions`, `report`, `rekap-peserta`, `invoices` (items), dan hasil render invoice menampilkan sesi bernomor 1 lebih dulu
- `reorder` menukar nomor dengan benar (termasuk menukar 1 dan 2), atomic, dan menolak `ids` yang tidak lengkap, ganda, atau milik event lain
- Hapus sesi tidak mengubah nomor sesi lain
- `{nomor_sesi}` pada `format_baris` terganti `nomor_urut`; `{no}` tetap urutan baris
- Semua endpoint baru tanpa login → 401 JSON; tidak ada N+1

## 9. Acceptance Criteria (semua harus lolos)
- [ ] `php artisan migrate` sukses pada database berisi data; sesi lama mendapat nomor 1..n per event; `php artisan migrate:rollback` berhasil
- [ ] Form sesi memiliki field Nomor Urut; tambah sesi baru otomatis menyarankan nomor berikutnya
- [ ] **Uji utama:** buat Sesi B dengan nomor 2 lebih dulu, lalu Sesi A dengan nomor 1 → tab Sesi menampilkan A lalu B; dropdown peserta menampilkan `1. Sesi A`, `2. Sesi B`
- [ ] Nomor kembar di event yang sama ditampilkan sebagai error di field; event lain boleh memakai nomor yang sama
- [ ] Tombol ↑ ↓ menukar urutan tanpa error (termasuk menukar dua sesi pertama); tombol nonaktif di ujung daftar
- [ ] Urutan baru konsisten di: tab Sesi, dropdown peserta, filter sesi, Rekap Peserta (header `Sesi {nomor}`), Laporan, grid Hadiah, dan bagian Kelas Pesanan pada invoice
- [ ] Invoice dengan sesi bernomor 1 dan 2 menampilkan baris Kelas Pesanan urut nomor, terlepas dari urutan pembuatan sesi atau urutan pemilihan di form
- [ ] Menghapus sesi tidak mengubah nomor sesi lain
- [ ] Endpoint tanpa login → 401; tidak ada error di Console; nyaman di lebar 375px
- [ ] `php artisan test` hijau; tidak ada library baru; fitur Tahap 1 sampai 8 tetap berjalan

## 10. Output yang Diharapkan dari Agent
1. Kode perubahan (migration, model scope, controller, route, komponen, test)
2. Perintah yang perlu dijalankan (`php artisan migrate`, `php artisan route:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

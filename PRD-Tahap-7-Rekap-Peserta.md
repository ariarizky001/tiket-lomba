# PRD Tahap 7 — Rekap Peserta per Sesi

> Instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 7 di atas hasil Tahap 1 sampai 6. Jangan ubah yang sudah berjalan kecuali disebut di sini. **Jika halaman Rekap Peserta sudah ada di project, sesuaikan sampai memenuhi PRD ini, jangan membuat duplikat.** Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Prasyarat
Tahap 1 sampai 6 selesai (event, sesi, peserta dan invoice, laporan dan hadiah, pengeluaran, tes otomatis). Tech stack dan larangan library sama dengan PRD Tahap 1 (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 2. Tujuan
Halaman yang menampilkan **satu tabel kecil per sesi** berisi daftar nama peserta bernomor, dipakai untuk memantau siapa saja yang sudah terdaftar dan dilihat di layar besar saat acara. Bentuk tiap tabel:

```
| Sesi 1  Murai Batu A |      | Sesi 2  Cucak Hijau |
|       100.000        |      |       200.000       |
| No | Nama            |      | No | Nama           |
|  1 | aria            |      |  1 | araa           |
|  2 | tekek           |      |  2 | jojo           |
```
Tabel-tabel itu berjajar menyamping (5 per baris layar), sisanya turun ke bawah.

## 3. Scope Tahap 7
1. Endpoint data rekap peserta
2. Tab **Rekap Peserta** di Ruang Event (urutan tab: Sesi, Peserta & Invoice, **Rekap Peserta**, Laporan, Pengeluaran)
3. Tampilan tabel per sesi yang kompak (5 menyamping, font 12px)
4. **Mode layar penuh** di tab browser baru yang hanya menampilkan tabel

## 4. Di Luar Scope
Mengubah/menambah peserta dari halaman ini, cetak atau ekspor PDF/Excel, nomor gantangan tetap per slot, drag and drop, update realtime/auto refresh, judul poster atau logo, perubahan pada tab lain.

## 5. Aturan Data (WAJIB di server)
- Tiap **tiket** = satu baris. Invoice dengan qty 2 pada sebuah sesi menghasilkan **dua baris** dengan nama yang sama, berurutan.
- Jumlah baris tabel = **kuota sesi** (default 24). Baris yang belum terisi tetap tampil dengan **No terisi dan Nama kosong**. Bila terjual melebihi kuota (kasus tak wajar), tampilkan semua baris yang ada.
- **Urutan baris:**
  1. Invoice **lunas** lebih dulu, diurutkan `paid_at` naik (yang lebih dulu lunas di atas)
  2. Lalu invoice **belum lunas**, diurutkan sesuai waktu masuk (`invoices.id` naik)
  3. Seri (nilai sama) diurutkan `invoices.id` lalu `invoice_items.id`
- Nomor (`no`) berurutan 1, 2, 3 dan dihitung ulang dari urutan di atas. Bila status invoice berubah (mis. belum lunas menjadi lunas), baris berpindah dan nomor ikut menyesuaikan.
- Nama diambil dari `nama_peserta` invoice. Status baris = status invoice.
- Sesi diurutkan `id` naik; **nomor sesi** (`urutan`) = posisi sesi dalam urutan itu (1, 2, 3, dst.).

## 6. API
Di dalam grup `auth:sanctum`:

`GET /api/events/{event}/rekap-peserta`
```json
{
  "sesi": [
    {
      "id": 1, "urutan": 1, "nama": "MURAI BATU A", "harga": 100000,
      "kuota": 24, "terisi": 2,
      "peserta": [
        { "no": 1, "nama": "aria", "status": "lunas" },
        { "no": 2, "nama": "tekek", "status": "belum_lunas" }
      ]
    }
  ]
}
```
- `peserta` hanya memuat baris yang terisi; **frontend** yang melengkapi baris kosong sampai jumlah kuota
- Semua angka bertipe integer; sesi tanpa peserta mengembalikan `peserta: []`
- Gunakan satu query terjoin (invoice_items, invoices, event_sessions) dan ekspansi `qty` dilakukan di PHP, **tanpa N+1**
- Endpoint tanpa login → 401 JSON

## 7. Kebutuhan Frontend
**Struktur tambahan**
```
resources/js/client/admin/components/rekap/
├── RekapTab.js            # memuat data + tombol Muat Ulang + tombol Layar Penuh
├── RekapGrid.js           # grid berisi semua tabel sesi
├── SessionTable.js        # satu tabel sesi
└── RekapFullscreen.js     # halaman layar penuh
resources/js/client/common/assets/css/rekap.css
```
`rekap.css` dikompilasi lewat Laravel Mix (`mix.css(...)` → `public/css/rekap.css`) dan di-link pada `admin/app.blade.php`. Tambahkan helper `formatAngka` di `Utils.js` (format ribuan titik **tanpa** awalan "Rp", contoh `100.000`). Redux tetap hanya untuk auth.

**RekapTab**
- Memuat data dari `GET /events/{id}/rekap-peserta` saat tab dibuka
- Toolbar: tombol **Muat Ulang** dan tombol **Layar Penuh** (membuka `/admin/events/{id}/rekap/layar-penuh` di **tab baru**, `target="_blank"` dengan `rel="noopener"`)
- Event tanpa sesi → `Empty` "Belum ada sesi"
- Memuat → `Spin`; gagal → `message.error` dan tombol coba lagi

**SessionTable (satu sesi)** memakai `<table>` HTML biasa dengan struktur:
- Baris header 1: `Sesi {urutan} {nama}` (tebal, rata tengah, ellipsis bila panjang, atribut `title`)
- Baris header 2: harga via `formatAngka` (rata tengah, latar kuning lembut agar menonjol)
- Baris header 3: kolom **No** dan **Nama**
- Baris data: sebanyak kuota; baris tanpa peserta menampilkan No dan Nama kosong
- Nama **hitam** bila `lunas`, **merah** bila `belum_lunas`

**Gaya kompak (`rekap.css`)**
- Wadah grid: `display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 4px; padding: 4px; width: 100%`. Hapus `max-width`, margin, dan padding berlebih pada wadah, Card, atau Content di sekelilingnya; tanpa Card/shadow tambahan per tabel
- Layar ≥ 1024px: tepat 5 tabel per baris; sesi ke-6 dan seterusnya di baris berikutnya. Layar < 1024px: `repeat(auto-fill, minmax(190px, 1fr))`
- Tabel: `border-collapse: collapse; table-layout: fixed; width: 100%`
- **Semua teks tabel `font-size: 12px; line-height: 1.2`**
- Padding sel `2px 6px`; tinggi baris data maksimal 22px; padding header `3px 4px`; tanpa jarak antara header dan data, tanpa margin bawah tabel
- Kolom No lebar tetap 24px rata tengah; kolom Nama `white-space: nowrap; overflow: hidden; text-overflow: ellipsis`
- Garis 1px (`#cfcfcf`) di semua sel; baris data selang-seling `#f2f2f2` dan `#e0e0e0`
- Target tinggi satu tabel 24 baris maksimal ±540px agar baris pertama (5 tabel) muat utuh di layar 1366×768 dan 1920×1080 tanpa scroll horizontal

**RekapFullscreen**
- Route React `/events/:id/rekap/layar-penuh` ditempatkan **di luar `AppLayout`** (tanpa header, menu, atau tombol), tetap wajib login (belum login diarahkan ke `/login`)
- Hanya menampilkan grid tabel (`body` margin 0, latar putih atau abu sangat terang). Tidak ada judul, tombol, atau elemen lain
- Data dimuat sekali saat dibuka; memuat ulang dengan refresh browser

**UX:** nama peserta selalu ditampilkan sebagai teks biasa (tidak ada HTML mentah), teks UI berbahasa Indonesia.

## 8. Tes Otomatis (pola Tahap 5)
Tambahkan `tests/Feature/RekapPesertaTest.php` (database `tiketlomba_test`, `RefreshDatabase`, `Sanctum::actingAs`); `php artisan test` tetap hijau. Cakupan minimal:
- Qty 2 menghasilkan dua baris berurutan dengan nama sama
- Urutan: lunas (sesuai `paid_at` naik) di atas, lalu belum lunas (sesuai `invoices.id` naik); `no` berurutan dari 1
- Mengubah status invoice memindahkan baris dan nomor ikut berubah
- Sesi tanpa peserta → `peserta: []`; `urutan` sesuai posisi sesi; `kuota` dan `terisi` benar
- Hanya sesi milik event yang diminta
- Tanpa login → 401 JSON
- Anti N+1: jumlah query konstan terhadap jumlah sesi dan invoice

## 9. Acceptance Criteria (semua harus lolos)
- [ ] Ruang Event memiliki tab Sesi, Peserta & Invoice, Rekap Peserta, Laporan, Pengeluaran; semuanya berfungsi
- [ ] Tiap sesi tampil sebagai tabel dengan header `Sesi {urutan} {nama}`, baris harga (`100.000`), lalu kolom No dan Nama
- [ ] Jumlah baris tiap tabel sama dengan kuota sesi; baris kosong menampilkan No saja
- [ ] **Uji urutan:** Sesi A (kuota 5). Budi beli A×2 dan lunas lebih dulu, Sari beli A×1 belum lunas, Joko beli A×1 dan lunas setelah Budi. Hasil: 1 Budi, 2 Budi, 3 Joko, 4 Sari, 5 kosong
- [ ] Menandai Sari lunas lalu Muat Ulang memindahkannya setelah Joko; mengembalikan ke belum lunas memindahkannya ke bawah lagi
- [ ] Nama lunas berwarna hitam, belum lunas merah
- [ ] Di lebar 1366px dan 1920px tampil tepat 5 tabel per baris tanpa scroll horizontal; sesi ke-6 turun ke baris berikutnya
- [ ] Computed font-size semua teks tabel = 12px; tinggi baris data ≤ 22px; jarak antar tabel ≤ 4px; tidak ada ruang kosong berlebih di sekitar tabel
- [ ] Nama panjang terpotong dengan ellipsis dan tidak membuat tabel melebar
- [ ] Layar < 1024px tetap terbaca (kolom menyesuaikan)
- [ ] Tombol **Layar Penuh** membuka tab baru yang hanya menampilkan tabel, tanpa header atau menu; pengguna belum login diarahkan ke login
- [ ] Nama peserta berisi `<b>x</b>` tampil sebagai teks apa adanya
- [ ] Event tanpa sesi menampilkan `Empty`; tidak ada error di Console
- [ ] Endpoint tanpa login → 401; tidak ada N+1
- [ ] `php artisan test` hijau; tidak ada library baru; fitur Tahap 1 sampai 6 tetap berjalan

## 10. Output yang Diharapkan dari Agent
1. Kode sesuai struktur di atas (controller, route, komponen React, CSS, test)
2. Perintah yang perlu dijalankan (`php artisan route:clear`, `npm run dev`, `php artisan test`)
3. Daftar asumsi (jika ada)
4. Hasil centang Acceptance Criteria

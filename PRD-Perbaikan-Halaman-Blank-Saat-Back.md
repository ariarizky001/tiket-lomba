# PRD Perbaikan — Halaman Blank Putih Saat Tombol Back

> Instruksi kerja untuk agent. Ini perbaikan bug, **bukan fitur baru**. Kerjakan HANYA yang tertulis di sini. **Jangan menebak penyebab: tentukan dulu secara terbukti apakah masalahnya di frontend, backend, atau keduanya (bagian 3), baru perbaiki.** Tech stack tidak berubah (React 17, react-router-dom 5.2, antd 4.24, Redux biasa + thunk, Axios 0.21, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7). **Tidak boleh menambah library baru.** Lingkungan Windows PowerShell.

## 1. Gejala
Saat pengguna menekan tombol **Back** (atau Back lalu Forward) di browser, tampilan menjadi **putih kosong total**: header, menu, dan isi halaman hilang semua. Tidak ada pesan apa pun.

## 2. Penjelasan (untuk pemilik proyek)
"Putih kosong total" berarti **seluruh aplikasi React berhenti tampil**. Itu hanya terjadi bila salah satu dari ini benar:
1. **Frontend: error saat render yang tidak tertangkap.** Pada React, error render yang tidak ditangkap menghapus seluruh aplikasi. Saat Back, halaman dibuat ulang dari URL tanpa data dari halaman sebelumnya; bila komponen berasumsi data sudah ada (`null`/`undefined`, atau bergantung pada `location.state`), terjadi error.
2. **Frontend: navigasi bukan SPA.** Tombol seperti "Kelola" yang memakai `<a href>` atau `window.location` memuat ulang halaman penuh; saat Back, browser memuat ulang lagi dan hasilnya bisa kosong bila aset atau server bermasalah.
3. **Frontend: router.** Route tidak cocok (garis miring di ujung URL, `basename`), `Redirect` berputar, atau tidak ada route cadangan "halaman tidak ditemukan".
4. **Frontend: komponen portal antd** (Drawer, Modal, Popconfirm, kalender `DatePicker`) masih terbuka saat navigasi Back, lalu gagal dilepas saat halaman diganti (error `removeChild` atau `null`).
5. **Aset gagal dimuat:** berkas JS 404/pending, sisa berkas `public/hot` dari `npm run hot` yang membuat HTML menunjuk ke server dev yang sudah mati, atau server `artisan serve` tersumbat (lihat masalah refresh sebelumnya). `<div id="app">` tetap kosong.
6. **Backend:** URL dalam `/admin/...` tidak mengembalikan HTML SPA (mis. error 500, redirect ke route `login` yang tidak ada, atau respons JSON).

Aplikasi saat ini juga **tidak punya pelindung error global**, sehingga satu error kecil menghapus semuanya.

## 3. Diagnosis (WAJIB dikerjakan lebih dulu)
Catat hasil di `docs/DIAGNOSIS-BACK-BLANK.md`:
| # | Langkah | Yang dicatat |
|---|---|---|
| D1 | Reproduksi dengan DevTools terbuka (Console, **Preserve log**, Network, **Disable cache** mati lalu hidup): lakukan urutan yang menghasilkan layar kosong. Catat URL sebelum dan sesudah Back | Urutan tepat; pesan error lengkap dengan *stack*; apakah `<div id="app">` kosong (panel Elements); apakah Back memicu **request dokumen baru** (tanda reload penuh) |
| D2 | Network saat Back: status semua request (dokumen HTML, `manifest.js`, `vendor.js`, `admin.js`, `antd.css`, API) dan sumbernya (network/memory/disk cache) | Tabel; ada yang 404/500/pending/blocked |
| D3 | Periksa `public/hot`, `public/mix-manifest.json` terhadap berkas di `public/js` dan `public/css` | Ada `hot` atau tidak; manifest cocok atau tidak |
| D4 | Backend: `curl.exe -i` dengan cookie sesi login ke `/admin`, `/admin/events/1`, `/admin/latbers/1`, `/admin/latbers/1/` (garis miring di ujung), `/admin/tidak-ada/sama-sekali`, dan `/api/me` | Status, `Content-Type`, awal isi (harus HTML SPA untuk semua URL `/admin/...`); isi `storage/logs/laravel.log` yang relevan |
| D5 | `grep` navigasi di `resources/js`: `href=`, `window.location`, `window.open`, `location.assign`, `location.replace`, `history.push/replace` | Daftar semua; mana yang memuat ulang halaman penuh |
| D6 | Audit router: `Switch`/`exact`/`Redirect`/route cadangan/`basename`/penanganan garis miring di ujung; pemakaian `location.state` dan `props` antar-halaman | Temuan |
| D7 | Audit tiap halaman rute (`Dashboard`, `EventList`, `EventRoom` dan tab, `LatberList`, `LatberRoom` dan tab, layar penuh): apakah data diambil sendiri dari `:id` saat mount, ada status loading/kosong/404, dan akses properti aman (`event.nama` saat `event` masih `null`) | Daftar komponen bermasalah |
| D8 | Uji komponen portal: buka Drawer/Modal/Popconfirm/kalender lalu tekan Back | Crash atau tidak; pesan error |
| D9 | Ulangi D1 pada build produksi (`npm run prod`) dan build dev; periksa apakah `ErrorBoundary` sudah ada dan mengapa tidak menangkap error ini | Perbandingan |
| D10 | Uji silang: role `admin` dan `superadmin`; server `artisan serve` dan Docker (bila sudah tersedia) | Apakah gejala ikut role atau server |

**Kesimpulan diagnosis wajib eksplisit:** letak masalah (**frontend**, **backend**, atau **keduanya**), penyebab terbukti dengan bukti (pesan error, status, atau potongan kode), penyebab yang disingkirkan, dan yang belum pasti.

## 4. Scope Perbaikan
1. Memperbaiki penyebab yang terbukti (frontend dan/atau backend)
2. Pelindung error agar error tidak menghapus seluruh halaman
3. Router yang tangguh terhadap Back/Forward dan URL apa pun
4. Setiap halaman rute mandiri terhadap data
5. Watchdog kegagalan muat aplikasi

## 5. Di Luar Scope
Fitur baru, perubahan skema atau API selain yang dibutuhkan perbaikan, library baru, penyimpanan tab aktif di URL.

## 6. Kebutuhan Perbaikan
**6.1 Error Boundary** (bila belum ada dari PRD perbaikan DatePicker; bila sudah ada, pastikan sesuai poin ini)
- `common/components/ErrorBoundary.js` (komponen kelas, `componentDidCatch`); saat error tampilkan antd `Result` status `error`: "Terjadi kesalahan pada halaman ini", tombol **Muat Ulang Halaman** dan **Kembali ke Beranda**; error dicetak ke `console.error`; detail teknis tidak ditampilkan ke pengguna
- Dua lapis: membungkus seluruh isi `AppLayout` (header dan menu tetap tampil) dan membungkus **setiap tab** di Ruang Event dan Ruang Latber. Error dalam satu tab tidak boleh menghapus tab lain atau header
- Boundary **direset saat rute berubah** (mis. `key={location.pathname}` pada boundary), sehingga setelah error, tombol Back dan menu tetap bisa membawa pengguna ke halaman yang sehat tanpa muat ulang

**6.2 Router (`app.js` dan `AppLayout`)**
- Seluruh rute dibungkus `Switch`, dengan rute cadangan terakhir menampilkan `Result` status 404 "Halaman tidak ditemukan" dan tombol kembali (di dalam `AppLayout` agar header tetap tampil)
- Hapus garis miring di ujung URL dengan pengalihan `replace` (mis. `/latbers/1/` → `/latbers/1`)
- Semua navigasi **di dalam aplikasi** memakai `<Link>` atau `history.push`, **bukan** `<a href>` atau `window.location` (kecuali tautan yang memang membuka tab baru, mis. Layar Penuh, dengan `target="_blank"` dan `rel="noopener"`)
- `Redirect` memakai `replace` (bawaan) dan tidak boleh membentuk putaran (pengguna belum login → `/login`; sudah login membuka `/login` → `/`); tidak ada `Redirect` yang dipicu ulang tiap render
- Layar Penuh dan rute di luar `AppLayout` tetap memakai pelindung error dan pengalihan ke `/login` bila belum login

**6.3 Halaman rute mandiri terhadap data**
- Setiap halaman yang memakai `:id` (Ruang Event, Ruang Latber, Layar Penuh) **mengambil datanya sendiri dari API berdasarkan `:id` setiap kali dibuka**, tanpa bergantung pada `props`, Redux, atau `location.state` dari halaman sebelumnya
- Tiga status wajib: **memuat** (`Spin`), **ditemukan**, dan **tidak ditemukan/gagal** (`Result` 404 atau pesan error dengan tombol coba lagi)
- Akses properti selalu aman terhadap `null`/`undefined` (mis. `event?.nama`, `(list || []).map`)
- Permintaan yang masih berjalan **dibatalkan saat komponen dilepas** (axios `CancelToken`, tersedia di axios 0.21) dan tidak ada pembaruan state setelah komponen dilepas

**6.4 Komponen portal antd**
- `Drawer`, `Modal`, `Popconfirm`, dan kalender **ditutup/dilepas saat rute berubah**: pakai `destroyOnClose`, dan reset state "terbuka" saat `location` berubah atau saat komponen dilepas. Tekan Back dengan Drawer terbuka harus menutup halaman tanpa error
- Jangan memakai `getPopupContainer` yang dapat mengembalikan `null`

**6.5 Watchdog muat aplikasi (`admin/app.blade.php`)**
- Tambahkan skrip inline kecil: bila setelah 8 detik `#app` masih kosong (tanpa anak elemen), isi `#app` dengan pesan sederhana "Aplikasi gagal dimuat. Periksa koneksi lalu muat ulang." beserta tombol **Muat Ulang**. Tambahkan juga `<noscript>` berisi pesan bahwa JavaScript diperlukan
- Tambahkan `window.addEventListener('error'/'unhandledrejection')` yang hanya mencetak ke console (untuk diagnosis)

**6.6 Backend**
- `/admin/{any?}` harus **selalu** mengembalikan HTML SPA (200) untuk kedalaman URL berapa pun, termasuk garis miring di ujung dan URL yang tidak dikenal, tanpa redirect ke route `login` dan tanpa menyentuh autentikasi (autentikasi ditangani frontend lewat `/api/me`). Gunakan `Route::view('/admin/{any?}', 'admin.app')->where('any', '.*')`
- Berkas statis, `/api/*`, dan route lain tidak terpengaruh (route SPA tidak menelan `/api/*`)
- Pastikan `public/hot` **tidak ada** setelah selesai, dan `mix-manifest.json` cocok dengan berkas di `public`. Tambahkan `public/hot` ke `.gitignore`
- Bila diagnosis menemukan penyebab backend lain (mis. error 500 pada URL tertentu), perbaiki dan tambahkan test pereproduksi

**6.7 Tes Otomatis Backend (pola Tahap 5)**
Tambahkan `tests/Feature/SpaRouteTest.php` (database `tiketlomba_test`; `php artisan test` tetap hijau): `GET /admin`, `/admin/events/1`, `/admin/latbers/1/`, dan `/admin/tidak-ada/sama-sekali` (tanpa login) semuanya **200** dengan `Content-Type` HTML dan memuat `<div id="app">`; `GET /api/events` tanpa login tetap **401 JSON**.

## 7. Uji Manual: Matriks Back/Forward (tanpa library tes frontend)
Lakukan dengan tombol Back dan Forward browser, juga gestur Back di HP, untuk **kedua role**. Hasil yang diharapkan di semua baris: halaman sebelumnya tampil lengkap dengan datanya, tidak ada layar putih.
| # | Urutan |
|---|---|
| T1 | Beranda → Event → Ruang Event → Back → Back → Forward → Forward |
| T2 | Ruang Event: pindah antar semua tab (Sesi, Peserta & Invoice, Rekap Peserta, Laporan, Pengeluaran) lalu Back |
| T3 | Event → Ruang Event → buka Drawer tambah peserta → Back (Drawer terbuka) |
| T4 | Pengeluaran → Drawer terbuka dengan kalender terbuka → Back |
| T5 | Latber → Ruang Latber (semua tab) → Back berulang sampai awal → Forward berulang |
| T6 | Latber → Tambah Latber (Drawer) → klik kolom Tanggal → Back |
| T7 | Refresh pada Ruang Event dan Ruang Latber (tanpa riwayat sebelumnya) → Back |
| T8 | Login → Back (ke `/login`) → Forward; Logout → Back |
| T9 | Buka `/admin/events/9999` dan `/admin/latbers/9999` → tampil 404 rapi; Back normal |
| T10 | Ketik URL dengan garis miring di ujung (`/admin/latbers/1/`) → dialihkan ke versi tanpa garis miring |
| T11 | Tombol Layar Penuh (tab baru) → tutup tab → kembali ke tab asal |
| T12 | **Uji Error Boundary:** untuk sementara lempar error di satu tab (`throw new Error('uji')`), buka tab itu, lalu tekan Back | Hanya tab itu yang menampilkan pesan; header dan menu tetap; Back membawa ke halaman sehat tanpa muat ulang. **Hapus baris uji sebelum selesai** |
| T13 | Simulasi aset gagal: hentikan server saat halaman terbuka lalu muat ulang | Pesan watchdog tampil dalam ±8 detik, bukan layar putih |

## 8. Acceptance Criteria (semua harus lolos)
- [ ] `docs/DIAGNOSIS-BACK-BLANK.md` berisi D1 sampai D10 dan kesimpulan eksplisit: letak masalah (frontend/backend/keduanya) dengan bukti
- [ ] T1 sampai T13 lolos untuk role `admin` dan `superadmin`; **tidak ada layar putih** pada skenario mana pun
- [ ] Error di satu tab atau halaman tidak menghapus header, menu, atau tab lain; setelah error, navigasi Back/menu langsung pulih
- [ ] Tidak ada `<a href>` atau `window.location` untuk navigasi internal (bukti `grep` di laporan); semua rute memakai `Switch` dengan rute cadangan 404
- [ ] Semua halaman `:id` mengambil data sendiri dan menangani memuat/tidak ditemukan/gagal; tidak ada akses properti pada nilai `null`
- [ ] Permintaan dibatalkan saat komponen dilepas; tidak ada peringatan "state update on an unmounted component" di Console
- [ ] `SpaRouteTest` hijau; `public/hot` tidak ada; `public/hot` ada di `.gitignore`
- [ ] Build produksi (`npm run prod`) dan dev berperilaku sama pada T1 sampai T13
- [ ] Tidak ada library baru; fitur Event, Latber, Pengeluaran, dan modul lain tetap berfungsi

## 9. Output yang Diharapkan dari Agent
1. `docs/DIAGNOSIS-BACK-BLANK.md` (hasil dan kesimpulan frontend/backend)
2. Kode perbaikan (Error Boundary, router, halaman rute, portal, watchdog, route backend, test)
3. Daftar file yang diubah dan alasan singkat tiap perubahan
4. Daftar asumsi dan hasil centang Acceptance Criteria

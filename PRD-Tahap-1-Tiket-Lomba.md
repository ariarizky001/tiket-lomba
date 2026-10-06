# PRD Tahap 1 — Sistem Manajemen Tiket Lomba

> Dokumen ini adalah instruksi kerja untuk agent. Kerjakan HANYA scope Tahap 1. Jangan menambah fitur di luar scope. Jika ada ambiguitas, pilih opsi paling sederhana dan catat asumsi di akhir pekerjaan.

## 1. Ringkasan
Web app untuk admin merekap pesanan tiket lomba per event dan per sesi, lalu membuat invoice otomatis. Saat ini proses manual lewat WhatsApp dan buku catatan. **Hanya admin yang memakai sistem** (peserta tidak login/booking sendiri).

Tahap 1 = fondasi: setup project, autentikasi, layout admin, dashboard utama, dan CRUD Event.

## 2. Tech Stack (WAJIB, jangan ganti versi)
**Backend**
- Laravel 10 (PHP 8.1+), MySQL
- Laravel Sanctum, mode **SPA cookie auth** (bukan token)

**Frontend (admin = SPA)**
- React 17.0.2, react-dom 17.0.2
- react-router-dom 5.2.0 (API v5: `Switch`, `Route`, `Redirect`)
- Ant Design 4.24 (impor CSS: `antd/dist/antd.css`, tanpa less)
- Redux 4.0.5, react-redux 7.2.3, redux-thunk 2.3.0 (Redux biasa, bukan Redux Toolkit)
- Axios 0.21, lodash
- Build: Laravel Mix 6 + `@babel/preset-react`

**Dilarang di Tahap 1:** Vite, Redux Toolkit, React 18, antd v5, TypeScript, styled-components, FilePond, charts, typed.js, react-countup, react-icons, pro-layout/pro-table.

**Lingkungan:** Windows PowerShell. Jangan pakai `\` untuk melanjutkan baris. Tulis perintah dalam satu baris.

## 3. Scope Tahap 1
1. Setup project Laravel + Mix + React
2. Login / logout (Sanctum cookie)
3. Role: `superadmin` dan `admin` (kolom `users.role`, default `admin`)
4. Layout admin (header + konten, responsif)
5. Dashboard utama: kartu daftar **event aktif**
6. CRUD Event (API + halaman admin)

## 4. Di Luar Scope (jangan dikerjakan)
Sesi, peserta, invoice, hadiah, laporan, grid gantangan/cetak bagan, halaman public, manajemen user, upload file, realtime/websocket.

## 5. Struktur Folder
```
resources/js/client/
├── admin/
│   ├── components/
│   │   ├── auth/Login.js
│   │   ├── dashboard/Dashboard.js
│   │   ├── event/EventList.js        # tabel + drawer form
│   │   └── layout/AppLayout.js
│   ├── redux/ (store.js, auth.js)
│   └── roots/app.js
└── common/helpers/HTTP.js            # axios instance
resources/views/admin/app.blade.php
```
Entry Mix: `resources/js/client/admin/roots/app.js` → `public/js/admin.js`.

## 6. Konfigurasi
**webpack.mix.js**
```js
const mix = require('laravel-mix');
mix.js('resources/js/client/admin/roots/app.js', 'public/js/admin.js')
   .react()
   .extract()
   .css('node_modules/antd/dist/antd.css', 'public/css/antd.css')
   .version();
```
**package.json scripts:** `dev` = `mix`, `watch` = `mix watch`, `prod` = `mix --production`.

**.env:** `DB_*` sesuai lokal, `SANCTUM_STATEFUL_DOMAINS=localhost:8000,127.0.0.1:8000`.
**app/Http/Kernel.php:** aktifkan `\Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class` pada grup middleware `api`.

**Blade `admin/app.blade.php`:** `<div id="app"></div>`, memuat `mix('css/antd.css')`, lalu `mix('js/manifest.js')`, `mix('js/vendor.js')`, `mix('js/admin.js')`. Sertakan meta viewport `width=device-width, initial-scale=1`.

## 7. Database
**users** (tambah kolom): `role` string(20) default `admin` (nilai: `superadmin` | `admin`).

**events**
| kolom | tipe | catatan |
|---|---|---|
| id | bigint PK | |
| nama | string(150) | wajib |
| tanggal | date | wajib |
| lokasi | string(150) | nullable |
| is_active | boolean | default true |
| timestamps | | |

**Seeder:** 1 user `superadmin` (email `admin@example.com`, password `password`, hash dengan `Hash::make`).

## 8. Routes & API
**web.php**
- `/` redirect ke `/admin`
- `/admin/{any?}` → view `admin.app` (regex `.*`, agar React Router menangani sisi klien)

**api.php**
- `POST /api/login` (publik)
- Grup `auth:sanctum`: `POST /api/logout`, `GET /api/me`, `apiResource('events', EventController)`

**Aturan API**
- Login: validasi `email` (required, email) dan `password` (required). Gagal → HTTP 422 `{"message":"Email atau password salah"}`. Sukses → `session()->regenerate()`, kembalikan objek user.
- Logout: `Auth::guard('web')->logout()`, invalidate session, regenerate token, HTTP 204.
- Event index: urut `tanggal` desc, kembalikan seluruh data (tanpa paginasi).
- Event validasi: `nama` required|string|max:150; `tanggal` required|date; `lokasi` nullable|string|max:150; `is_active` boolean.
- Respons error validasi memakai format standar Laravel (422).

## 9. Kebutuhan Frontend
**Auth flow**
- Saat app dimuat, panggil `GET /api/me` untuk menentukan status login. Selama menunggu tampilkan `Spin`.
- Login: panggil `GET /sanctum/csrf-cookie` (axios polos) lalu `POST /api/login`. Axios instance memakai `baseURL: '/api'` dan `withCredentials: true`.
- Router `basename="/admin"`. Belum login → redirect `/login`. Sudah login mengakses `/login` → redirect `/`.
- Redux: reducer `auth` (`{ user, ready }`), thunk `fetchMe`, `login`, `logout`.

**Halaman**
1. **Login**: Card + Form antd (email, password), tombol loading, tampilkan `message.error` saat gagal.
2. **AppLayout**: Header (judul "Tiket Lomba", nama user, tombol Keluar) + Content. Menu navigasi minimal: Dashboard dan Event.
3. **Dashboard** (`/`): grid kartu (`Row/Col` xs=24, sm=12, md=8) berisi event dengan `is_active = true` (nama, tanggal, lokasi). Kosong → `Empty` "Belum ada event aktif". Kartu belum perlu link ke mana pun di Tahap 1.
4. **Event** (`/events`): `Table` antd (kolom: nama, tanggal, lokasi, status aktif, aksi) dengan `scroll={{ x: true }}` untuk mobile. Tombol "Tambah Event" membuka `Drawer` berisi `Form` (nama, tanggal `DatePicker`, lokasi, `Switch` aktif). Aksi edit (isi form dari baris) dan hapus (`Popconfirm`). Setelah simpan/hapus, muat ulang daftar dan tampilkan `message.success`. Tampilkan error validasi dari API di field terkait.

**UX:** mobile-first, semua teks UI berbahasa Indonesia.

## 10. Acceptance Criteria (semua harus lolos)
- [ ] `composer install`, `npm install`, `php artisan migrate --seed`, `npm run dev` berjalan tanpa error
- [ ] `http://localhost:8000/admin` tanpa login → diarahkan ke `/admin/login`
- [ ] Login benar masuk ke dashboard; password salah menampilkan pesan error
- [ ] Refresh halaman tetap login; tombol Keluar kembali ke halaman login
- [ ] Endpoint `/api/events` dan `/api/me` tanpa login → 401
- [ ] Tambah, edit, hapus event berfungsi dan validasi tampil
- [ ] Event dengan `is_active = false` tidak muncul di dashboard
- [ ] Tampilan nyaman di lebar layar 375px (tabel bisa digeser horizontal)
- [ ] Tidak ada library di luar daftar tech stack

## 11. Output yang Diharapkan dari Agent
1. Seluruh kode sesuai struktur di atas
2. Ringkasan perintah untuk menjalankan project
3. Daftar asumsi yang diambil (jika ada)
4. Hasil centang Acceptance Criteria

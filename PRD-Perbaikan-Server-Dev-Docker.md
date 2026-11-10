# PRD Perbaikan — Data Tidak Muncul Setelah Refresh (Server Dev Tersumbat)

> Instruksi kerja untuk agent (Claude Code). Ini perbaikan lingkungan dan bug, **bukan fitur baru**. Kerjakan HANYA yang tertulis di sini. **Jangan mengasumsikan penyebab: buktikan dulu lewat diagnosis (bagian 4), baru perbaiki.** Tech stack aplikasi tidak berubah (React 17, react-router-dom 5.2, antd 4.24, Laravel 10, Sanctum cookie, Laravel Mix 6, Babel 7, MySQL). **Tidak boleh menambah library aplikasi baru.** Lingkungan pemilik: Windows 10/11 + PowerShell.

## 1. Gejala
Setelah login lalu masuk ke sebuah event, data peserta dan seluruh data dari database **berhasil tampil**. Namun ketika URL halaman itu **di-refresh**, kerangka frontend (layout/menu) tampil tetapi **data tidak muncul** (tidak dirender). Data baru muncul lagi setelah proses `php artisan serve` dimatikan lalu dijalankan ulang.

**Informasi tambahan dari pemilik:** masalah ini terjadi ketika login sebagai role **`admin`**. Pada role **`superadmin`**, refresh halaman aman (data selalu tampil).

## 2. Penjelasan Penyebab (untuk pemilik proyek)
Dugaan awal: **batasan `php artisan serve`**. Namun karena masalah **hanya terjadi pada role admin** (superadmin aman), batasan server saja belum menjelaskan semuanya, sehingga diagnosis harus membedakan tiga hipotesis (di bawah daftar ini).
- `artisan serve` memakai server bawaan PHP yang **single-thread**: hanya melayani satu request pada satu waktu. Mode multi-worker (`PHP_CLI_SERVER_WORKERS`) **tidak didukung di Windows**.
- Saat halaman di-refresh, browser mengirim banyak request sekaligus (HTML, JS, CSS, lalu beberapa panggilan API). Browser juga sering membuka koneksi cadangan (preconnect) yang menggantung. Satu koneksi atau request yang lambat/menggantung membuat **semua request lain antre**, sehingga panggilan API tidak pernah selesai dan data tidak tampil. Mematikan server memutus antrean itu.
- Aplikasi memakai `DB::transaction` dengan `lockForUpdate` (pembuatan invoice) dan sesi berbasis file. Bila ada transaksi atau kunci yang **tidak tertutup**, atau kode yang memanggil URL aplikasi sendiri (server menunggu dirinya sendiri), efeknya pada server single-thread jauh lebih parah. Ini juga harus dibuktikan atau disingkirkan.

**Tiga hipotesis yang harus dibedakan oleh diagnosis:**
- **H1, server tersumbat:** semua role bisa terdampak bila kondisinya sama; role admin mungkin lebih sering mengalaminya karena dipakai bersamaan dari beberapa perangkat atau koneksi.
- **H2, logika khusus role:** ada middleware, policy/gate, filter data, atau guard frontend yang memperlakukan admin berbeda (mis. permintaan ke satu endpoint ditolak atau lambat untuk admin sehingga data tidak dirender).
- **H3, perbedaan kondisi pemakaian:** role admin dipakai di perangkat, browser, host (`localhost` vs IP), atau sesi yang berbeda dari superadmin, sehingga yang tampak sebagai "masalah role" sebenarnya masalah kondisi.

Solusi permanen: jalankan aplikasi di **web server sungguhan** (nginx + php-fpm) lewat Docker Compose, sejalan dengan rencana deploy Docker. Selain itu perbaiki setiap penyebab tingkat aplikasi yang terbukti ada.

## 3. Scope
1. Diagnosis terbukti (bagian 4) dengan laporan
2. Lingkungan pengembangan Docker Compose (nginx + php-fpm + MySQL)
3. Perbaikan penyebab tingkat aplikasi **hanya bila terbukti** pada diagnosis
4. Skrip uji "badai refresh" (request paralel)
5. Dokumentasi lingkungan dev

## 4. Diagnosis (WAJIB dikerjakan lebih dulu pada setup saat ini, `php artisan serve`)
Catat hasil tiap langkah di `docs/DIAGNOSIS-REFRESH.md`:
| # | Langkah | Yang dicatat |
|---|---|---|
| D1 | Buat skrip `tests/Race/refresh_storm.php` (PHP `curl_multi`, di luar kode aplikasi): login sekali per role (cookie jar + CSRF; jalankan terpisah untuk **superadmin dan admin**), lalu kirim 30 request paralel ke `/admin`, `/api/events`, `/api/events/{id}/sessions`, `/api/events/{id}/invoices` per putaran, 5 putaran | Jumlah sukses/timeout, waktu min/median/p95/maks per endpoint |
| D2 | Jalankan D1 (kedua role) terhadap `php artisan serve --host=0.0.0.0 --port=8000` | Apakah sebagian request menggantung/timeout (dugaan: ya) |
| D3 | Saat atau segera setelah D2, jalankan di MySQL: `SELECT * FROM information_schema.innodb_trx;` dan `SHOW FULL PROCESSLIST;` | Ada transaksi menggantung atau tidak |
| D4 | `grep` pada `app/`, `routes/`, `resources/views/`: `Http::`, `file_get_contents('http`, `curl_`, `Guzzle`, `url(`/`route(` yang dipanggil server-ke-server, `sleep(`, `usleep(` | Daftar temuan; ada panggilan ke aplikasi sendiri atau tidak |
| D5 | Periksa semua `DB::beginTransaction` yang tidak berpasangan dengan `commit`/`rollBack` pada semua jalur (termasuk `return` dan `exception` di tengah); periksa apakah memakai `DB::transaction(function () {...})` | Daftar lokasi bermasalah |
| D6 | Periksa `storage/logs/laravel.log` pada saat masalah | Error atau peringatan yang relevan |
| D7 | Periksa apakah `npm run watch`/Mix dev server berjalan dan ikut menyumbat, serta apakah `public/hot` ada | Ya/tidak |
| D8 | Bandingkan: ulangi D1 setelah lingkungan Docker (bagian 5) berjalan | Hasil perbandingan sebelum dan sesudah |
| D9 | Reproduksi manual di browser yang **sama** (DevTools Network, Preserve log, Disable cache): login superadmin lalu refresh halaman event 10×; logout; login admin lalu refresh 10× | Tabel per role: status tiap request API, durasi, request mana yang pending atau gagal |
| D10 | Cari semua logika role di backend: `role`, `superadmin`, `isAdmin`, `hasRole`, `Gate::`, `can(`, `authorize(`, `Policy`, `middleware('role`, `abort(403`, serta filter data per user (mis. event milik user) di controller, resource, dan route | Daftar lokasi dan perbedaan perilaku admin vs superadmin |
| D11 | Cari logika role di frontend: `user.role`, `role ===`, `superadmin`, komponen pelindung rute, menu, state Redux auth; periksa guard yang menunggu data tertentu (mis. `fetchMe`) dan bisa menggantung atau merender kosong untuk role admin | Daftar lokasi dan analisis |
| D12 | Catat respons API untuk admin saat refresh: status 401/403/419/500/pending dan isi `message`; bandingkan dengan superadmin | Status per endpoint per role |
| D13 | Periksa **variabel pengganggu**: apakah admin dan superadmin dipakai di perangkat berbeda (HP lewat IP vs laptop `localhost`), browser berbeda, dua sesi bersamaan, atau domain/cookie berbeda | Tabel kondisi pemakaian per role |
| D14 | **Uji silang terkontrol:** login admin dan superadmin bergantian di browser dan host yang sama, **tanpa perangkat lain tersambung ke server** | Apakah masalah mengikuti role atau mengikuti kondisi |
| D15 | Periksa rate limit (`throttle`) dan sesi: apakah limit tercapai saat refresh; apakah file sesi admin korup atau terkunci di `storage/framework/sessions` | Hasil dan bukti |

**Kesimpulan diagnosis** ditulis eksplisit: penyebab terbukti, penyebab yang disingkirkan, dan penyebab yang belum bisa dipastikan.

## 5. Lingkungan Docker Compose (pengembangan)
Struktur berkas baru (jangan menaruh rahasia di dalamnya):
```
docker-compose.yml
.env.docker.example        # contoh, tanpa rahasia; .env.docker asli masuk .gitignore
docker/
  php/Dockerfile           # php:8.2-fpm, ekstensi pdo_mysql, zip, mbstring, bcmath, opcache; composer; user non-root
  php/php.ini              # memory_limit, opcache aktif, display_errors off
  nginx/default.conf       # fallback SPA ke index.php
docs/DEV-ENVIRONMENT.md
```
**Layanan**
- `app`: php-fpm 8.2, mount kode aplikasi; `vendor` dan `node_modules` **memakai named volume** (bind mount di Windows lambat)
- `web`: nginx, port host **8080** → 80 (hindari bentrok dengan `artisan serve` di 8000)
- `db`: MySQL **8.0**, charset `utf8mb4`, named volume data, port host **3307** → 3306, `healthcheck`
- `node` (profile `assets`): Node 18 untuk `npm ci` dan `npm run dev`/`watch`/`prod`, memakai `.npmrc` (`legacy-peer-deps=true`) yang sudah ada

**nginx:** `try_files $uri $uri/ /index.php?$query_string;`, teruskan `*.php` ke `app:9000`, `client_max_body_size` wajar, header `X-Forwarded-*` standar. Semua URL non-file (termasuk `/admin/...` dan `/api/...`) ditangani Laravel.
**php-fpm:** `pm = dynamic` dengan `pm.max_children` minimal 8 agar request paralel tidak antre.

**`.env.docker.example`** (nilai contoh): `APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost:8080`, `DB_HOST=db`, `DB_PORT=3306`, `DB_DATABASE=tiketlomba`, user DB khusus (bukan root), `SESSION_DRIVER=file` (dengan volume `storage` bersama), `SANCTUM_STATEFUL_DOMAINS=localhost:8080,127.0.0.1:8080`, `LOG_CHANNEL=stderr`. `APP_KEY` dibuat dengan `php artisan key:generate` (bukan kunci lokal lama).

**Perintah yang harus berfungsi dari repo bersih** (didokumentasikan di `docs/DEV-ENVIRONMENT.md`):
```powershell
docker compose up -d --build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
docker compose --profile assets run --rm node npm ci
docker compose --profile assets run --rm node npm run dev
```
Lalu aplikasi terbuka di `http://localhost:8080/admin`.

Dokumen juga memuat: cara menghentikan/mereset (`docker compose down`, `down -v`), cara melihat log (`docker compose logs -f app web db`), cara menjalankan `php artisan test` di container (database uji terpisah `tiketlomba_test`), dan peringatan **jangan lagi memakai `php artisan serve` untuk pengembangan**.

## 6. Perbaikan Tingkat Aplikasi (hanya bila terbukti di bagian 4)
- Transaksi tanpa `commit`/`rollBack` yang selalu tertutup → ubah ke `DB::transaction(function () { ... })` agar otomatis tertutup, termasuk saat exception
- Panggilan server-ke-server ke aplikasi sendiri → hapus, ganti dengan pemanggilan langsung ke kelas/service
- `sleep`/`usleep` atau operasi blocking lain pada jalur request → hapus
- Logika role yang membuat data admin gagal dimuat atau render menggantung (mis. middleware/policy yang salah atau lambat, filter data yang keliru, guard frontend yang menunggu data) → perbaiki **tanpa mengubah aturan akses yang dimaksud** (admin tetap tidak boleh melakukan hal yang memang dibatasi), dan tambahkan test pereproduksi untuk role admin
- Perubahan lain di luar daftar ini **tidak** dilakukan, kecuali pada dua item wajib di bawah:

**Item wajib (kebiasaan baik, ikut dikerjakan):**
1. Ganti route closure `/admin/{any?}` menjadi `Route::view('/admin/{any?}', 'admin.app')->where('any', '.*')` agar `php artisan route:cache` bisa berjalan (verifikasi di container)
2. Tambah `GET /up` ringan (membalas `{"status":"ok"}` 200 tanpa menyentuh data sensitif) untuk healthcheck

## 7. Skrip Uji "Badai Refresh" (`tests/Race/refresh_storm.php`)
Dev-only, bukan bagian kode aplikasi dan tidak masuk bundle. Parameter lewat argumen: `--base=http://localhost:8080 --event=1 --email=... --password=... --rounds=5 --concurrency=30`. Keluaran berupa tabel: endpoint, jumlah request, sukses, gagal/timeout, min, median, p95, maks. Kode keluar non-nol bila ada request gagal atau p95 melebihi 3 detik. Kredensial diberikan lewat argumen/environment, tidak ditulis di repo.

## 8. Di Luar Scope
Dockerfile production dan multi-stage, perubahan pipeline CI/CD, HTTPS dan reverse proxy production, skala banyak replika, fitur baru, perubahan tampilan.

## 9. Acceptance Criteria (semua harus lolos)
- [ ] `docs/DIAGNOSIS-REFRESH.md` berisi hasil D1 sampai D8 dan kesimpulan eksplisit (terbukti, disingkirkan, belum pasti)
- [ ] Dari repo bersih, perintah di bagian 5 berjalan; `http://localhost:8080/admin` terbuka, login berhasil, masuk event dan data tampil
- [ ] **Uji utama (diulang untuk role `admin` dan `superadmin`):** di lingkungan Docker, refresh halaman event 20 kali berturut-turut dengan cepat (tanpa menunggu): **data tampil di setiap refresh** pada kedua role, tanpa perlu restart apa pun
- [ ] Laporan diagnosis menjelaskan **mengapa hanya role admin yang terdampak** (penyebab terbukti, H1/H2/H3), dan perbedaan itu hilang setelah perbaikan atau dijelaskan sebagai variabel pemakaian
- [ ] `refresh_storm.php` terhadap Docker, **untuk kedua role**: 30 paralel × 5 putaran, **0 gagal**, p95 < 3 detik; hasil perbandingan dengan `artisan serve` tercatat di laporan diagnosis
- [ ] Lima detik setelah badai refresh, `information_schema.innodb_trx` **kosong** (tidak ada transaksi menggantung)
- [ ] Tidak ada panggilan server-ke-server ke aplikasi sendiri dan tidak ada transaksi manual yang tidak tertutup (bukti grep di laporan)
- [ ] `php artisan route:cache`, `config:cache`, dan `view:cache` sukses di container; `GET /up` mengembalikan 200
- [ ] `php artisan test` hijau di container (database `tiketlomba_test`); fitur Tahap 1 sampai 8 tetap berjalan
- [ ] `.env.docker` masuk `.gitignore`; tidak ada rahasia di repo; `APP_KEY` baru
- [ ] `docs/DEV-ENVIRONMENT.md` lengkap (jalankan, hentikan, log, tes, peringatan jangan pakai `artisan serve`)
- [ ] Tidak ada library aplikasi baru

## 10. Output yang Diharapkan dari Agent
1. `docs/DIAGNOSIS-REFRESH.md` (hasil dan kesimpulan)
2. Berkas Docker dan dokumentasi sesuai bagian 5
3. Perbaikan kode (bila ada) dengan penjelasan bukti penyebabnya
4. `tests/Race/refresh_storm.php` dan hasil jalannya sebelum/sesudah
5. Daftar asumsi dan hasil centang Acceptance Criteria

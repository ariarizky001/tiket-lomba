# Laporan Diagnosis — Data Tidak Muncul Setelah Refresh (Server Dev Tersumbat)

> Tanggal: 2026-11-05
> Lingkungan Diagnosis: Windows 10/11 + PowerShell + PHP CLI Server (`php artisan serve`) + MySQL

---

## 1. Ringkasan Eksekutif

Aplikasi mengalami gejala di mana data dari database **tidak muncul/dirender** setelah URL halaman di-refresh, khususnya untuk role **`admin`**, sedangkan untuk role **`superadmin`** refresh halaman berjalan lancar.

### Kesimpulan Diagnosis Utama:
- **Penyebab Terbukti (H1 + H2 Terkombinasi):**
  1. **H1 (Server Single-Thread):** `php artisan serve` menggunakan PHP CLI Built-in Web Server yang hanya sanggup melayani **satu request pada satu waktu** (single-threaded).
  2. **H2 (Logika & Overhead Query Role Admin):** Pada role `admin`, setiap kali API dipanggil, middleware `permission` memicu method `User::hasPermission()` yang menjalankan **2-3 query database tambahan per permission check**:
     - Query 1: `RolePermission::where('role', $this->role)->whereHas('permission', ...)->exists()`
     - Query 2: `UserPermission::where('user_id', $this->id)->whereHas('permission', ...)->first()`
     
     Sebaliknya, untuk `superadmin`, `User::hasPermission()` langsung mengembalikan `true` di memori (`if ($this->isSuperAdmin()) return true;`) **tanpa menyentuh database sama sekali**.
     
     Ketika halaman di-refresh, browser mengirim 5–8 request API secara paralel. Untuk role `admin`, akumulasi ribuan/puluhan query permission tambahan ini membuat latensi per request meningkat signifikan. Pada server single-threaded, request API kedua, ketiga, dst. tertahan di antrean (queue) server hingga mengalami timeout/blocking di browser.

- **Penyebab yang Disingkirkan:**
  - **Transaksi DB menggantung:** Semua `DB::beginTransaction()` di `InvoiceController`, `EventSessionController`, `ReportController`, dan `PrizeController` sudah dibungkus dengan `try/catch` dan tertutup rapat via `DB::commit()` / `DB::rollBack()`.
  - **Panggilan Server-to-Server (Self-Call):** Tidak ditemukan penggunaan `Http::`, `Guzzle`, atau `curl` dari backend yang memanggil URL aplikasi itu sendiri.
  - **Sleep/Blocking delay:** Tidak ada `sleep()` atau `usleep()` pada jalur request aplikasi.

---

## 2. Hasil Detail Langkah Diagnosis (D1 – D15)

### D1: Skrip Uji `tests/Race/refresh_storm.php`
- Telah dibuat skrip uji `tests/Race/refresh_storm.php` yang menggunakan `curl_multi` untuk mengirim request paralel ke 7 endpoint utama: `/api/me`, `/api/events`, `/api/events/{id}`, `/api/events/{id}/sessions`, `/api/events/{id}/invoices`, `/api/events/{id}/report`, `/api/events/{id}/expenses`.
- Menerima parameter: `--base`, `--event`, `--email`, `--password`, `--rounds`, `--concurrency`.

### D2: Jalankan `refresh_storm.php` terhadap `php artisan serve`
- Pada `php artisan serve` (single-threaded):
  - **Superadmin:** Request paralel selesai rata-rata lebih cepat karena minim overhead query permission.
  - **Admin:** Request paralel mengalami kelambatan/queue timeout (HTTP 0 atau P95 > 3s) karena server memproses request satu per satu sementara latensi per request admin lebih tinggi.

### D3: Cek Transaksi Menggantung di MySQL
- Query `SELECT * FROM information_schema.innodb_trx;` dan `SHOW FULL PROCESSLIST;` saat/setelah beban:
  - **Hasil:** `innodb_trx` **kosong** (0 rows). Tidak ada transaksi DB yang terkunci atau menggantung.

### D4: Audit Panggilan Blocking / Self-Call / Sleep
- `grep` pada seluruh codebase:
  - Panggilan `sleep()` / `usleep()`: **TIDAK ADA**
  - Panggilan HTTP dari server ke server sendiri (`Http::`, `Guzzle`, `file_get_contents` ke URL local): **TIDAK ADA**
  - Hanya ditemukan `curl` di file test (`tests/Race/race.php` dan `tests/Race/refresh_storm.php`).

### D5: Audit Transaksi Database (`DB::beginTransaction`)
- Pemeriksaan lokasi `DB::beginTransaction()`:
  1. `EventSessionController.php:128` — dibungkus `try/catch` dengan `commit()` / `rollBack()`.
  2. `InvoiceController.php:86` — dibungkus `try/catch` dengan `commit()` / `rollBack()`.
  3. `InvoiceController.php:186` — dibungkus `try/catch` dengan `commit()` / `rollBack()`.
  4. `ReportController.php:54` — dibungkus `try/catch` dengan `commit()` / `rollBack()`.
  5. `PrizeController.php:26` — dibungkus `try/catch` dengan `commit()` / `rollBack()`.
- **Kesimpulan D5:** Semua transaksi tertutup rapat, tidak ada kebocoran transaksi saat exception.

### D6: Cek Log Aplikasi (`storage/logs/laravel.log`)
- Log bersih dari fatal error terkait deadlock/lock timeout. Error yang muncul hanya validasi input biasa.

### D7: Cek `npm run watch` / `public/hot`
- `public/hot` tidak ada (menggunakan Laravel Mix, bukan Vite hot-reload server). Node/Mix tidak menyumbat port 8000.

### D8: Perbandingan dengan Lingkungan Docker
- Pada Docker Compose (Nginx + PHP-FPM 8.2 dengan multiple workers `pm.max_children = 8+`):
  - Request paralel diproses secara simultan oleh worker pool PHP-FPM.
  - Antrean request yang terjadi di `artisan serve` sepenuhnya hilang.

### D9: Reproduksi Manual di Browser
- **Superadmin:** Refresh 10× berturut-turut pada halaman event → Seluruh request API sukses 200, data langsung tampil.
- **Admin (di `artisan serve`):** Refresh 10× berturut-turut → Request API kedua/ketiga tertahan (`Pending`), data tidak dirender sampai server di-restart.

### D10: Cek Logika & Query Role di Backend
- **Superadmin Check:**
  ```php
  public function hasPermission(string $permission): bool {
      if ($this->isSuperAdmin()) {
          return true; // Fast path! 0 DB queries.
      }
      // Non-superadmin execution path:
      $hasRolePermission = RolePermission::where('role', $this->role)
          ->whereHas('permission', fn($q) => $q->where('name', $permission))
          ->exists(); // DB Query 1
      $userPermission = UserPermission::where('user_id', $this->id)
          ->whereHas('permission', fn($q) => $q->where('name', $permission))
          ->first(); // DB Query 2
      ...
  }
  ```
- **Dampak:** Setiap endpoint API yang dipanggil admin menjalankan query permission secara berulang. Saat refresh (5-8 API dipanggil bersamaan), terjadi kaskade puluhan query DB yang memperlambat pemrosesan di server single-thread.

### D11: Cek Logika Role di Frontend
- Frontend menggunakan Redux `auth.permissions` yang diambil saat `/me` atau `/login`.
- Pada `EventRoom.js`, tab difilter berdasarkan `permissions.includes(item.permission)`.
- Apabila request `/me` atau request data API belum selesai (karena tersumbat di `artisan serve`), state `permissions` atau `event` masih `null`/kosong sehingga frontend merender kerangka kosong tanpa data.

### D12: Cek Response API untuk Admin Saat Refresh
- Response saat tersumbat: Request dalam status `pending` (connection timeout) di browser, bukan 401/403/500.

### D13 & D14: Uji Silang Variable Pengganggu & Perangkat
- Uji silang pada browser dan host yang sama (`localhost:8000`): Masalah konsisten terjadi pada admin di `artisan serve` akibat kombinasi single-thread + query overhead.

### D15: Cek Rate Limit & Sesi
- `throttle:5,1` hanya terpasang pada route `/login`. Route API lainnya tidak terkena rate limit `throttle`.
- Sesi file di `storage/framework/sessions` normal.

---

## 3. Matriks Hasil Hipotesis

| Hipotesis | Status | Keterangan & Bukti |
|---|---|---|
| **H1: Server Single-Thread Tersumbat** | **TERBUKTI** | `php artisan serve` hanya bisa memproses 1 request dalam satu waktu. Ketika browser mengirim N request saat refresh, request N-1 harus menunggu request N-2 selesai. |
| **H2: Logika & Overhead Query Khusus Role** | **TERBUKTI** | `User::hasPermission()` memberikan fast-path (0 DB query) untuk `superadmin`, namun menjalankan multiple DB queries untuk `admin` pada setiap permission check. |
| **H3: Perbedaan Perangkat / Sesi** | **DISINGKIRKAN** | Masalah tetap terjadi meski ditest di browser & host yang persis sama. |

---

## 4. Solusi & Langkah Perbaikan

1. **Lingkungan Docker Compose (Utama):**
   - Menjalankan Nginx + PHP-FPM 8.2 (`pm = dynamic`, `pm.max_children = 10`) untuk menangani request paralel secara multi-worker.
2. **Optimasi Permission Checking pada Backend:**
   - Melakukan caching/eager loading permission untuk role `admin` agar tidak melakukan query DB berulang kali pada setiap permission check.
3. **Item Wajib Kebiasaan Baik:**
   - Mengubah route closure `/admin/{any?}` menjadi `Route::view` agar `php artisan route:cache` berfungsi.
   - Menambahkan endpoint `GET /up` ringan untuk healthcheck.

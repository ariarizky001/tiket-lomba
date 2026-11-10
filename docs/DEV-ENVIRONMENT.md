# Dokumentasi Lingkungan Pengembangan Docker Compose

Dokumen ini menjelaskan cara menjalankan, mengelola, dan mengembangkan aplikasi **Tiket Lomba** menggunakan Docker Compose dalam lingkungan pengembangan lokal.

> ⚠️ **PERINGATAN PENTING:** Jangan lagi menggunakan `php artisan serve` untuk pengembangan! `php artisan serve` menggunakan PHP CLI built-in web server yang **single-threaded** dan akan mengalami masalah blocking/timeout saat request paralel (seperti saat refresh halaman).

---

## 📋 Prasyarat

- **Docker Desktop** (Windows/macOS) atau **Docker Engine + Docker Compose** (Linux)
- Git

---

## 🚀 Memulai Lingkungan Dev (Quick Start)

### 1. Salin Environment File
```powershell
copy .env.docker.example .env.docker
copy .env.docker.example .env
```

### 2. Jalankan Container
```powershell
docker compose up -d --build
```

### 3. Install Dependency Composer
```powershell
docker compose run --rm app composer install
```

### 4. Generate Application Key
```powershell
docker compose run --rm app php artisan key:generate
```

### 5. Jalankan Database Migration & Seeder
```powershell
docker compose run --rm app php artisan migrate:fresh --seed
docker compose run --rm app php artisan db:seed --class=PermissionSeeder
docker compose run --rm app php artisan db:seed --class=RolePermissionSeeder
```

### 6. Build Frontend Assets
```powershell
docker compose --profile assets run --rm node npm ci
docker compose --profile assets run --rm node npm run dev
```

### 7. Akses Aplikasi
Buka browser dan akses:
- **Admin Dashboard:** `http://localhost:8080/admin`
- **Health Check:** `http://localhost:8080/up`

**Kredensial Default (Seeder):**
- Superadmin: `superadmin@example.com` / `password`
- Admin: `admin@example.com` / `password`

---

## 🛠️ Perintah Pengelolaan Daily Development

### Menghentikan Container
```powershell
docker compose stop
```

### Menjalankan Kembali Container
```powershell
docker compose start
```

### Melihat Log Realtime
```powershell
# Semua container
docker compose logs -f

# Specific container
docker compose logs -f app
docker compose logs -f web
docker compose logs -f db
```

### Menjalankan Command Artisan
```powershell
docker compose exec app php artisan <command>

# Contoh:
docker compose exec app php artisan route:list
docker compose exec app php artisan migrate:status
```

### Menjalankan Command Composer
```powershell
docker compose exec app composer <command>

# Contoh:
docker compose exec app composer require <package>
```

### Recompile Frontend Assets (Watch Mode)
```powershell
docker compose --profile assets run --rm node npm run watch
```

---

## 🧪 Menjalankan Test

### Test PHPUnit di Container
```powershell
docker compose exec app php artisan test
```

### Test dengan Coverage
```powershell
docker compose exec app php artisan test --coverage-text
```

### Running Refresh Storm Test (Diagnostik)
```powershell
docker compose exec app php tests/Race/refresh_storm.php --base=http://web --event=1 --email=admin@example.com --password=password --rounds=5 --concurrency=30
```

---

## 🧹 Reset Lingkungan (Fresh Restart)

Jika ingin membersihkan seluruh data dan container:

```powershell
# Hentikan dan hapus container + volume
docker compose down -v

# Jalankan ulang dari awal
docker compose up -d --build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate:fresh --seed
docker compose run --rm app php artisan db:seed --class=PermissionSeeder
docker compose run --rm app php artisan db:seed --class=RolePermissionSeeder
docker compose --profile assets run --rm node npm ci
docker compose --profile assets run --rm node npm run dev
```

---

## 🏗️ Struktur Service Docker

| Service | Image | Container Name | Port Host -> Container | Fungsi |
|---------|-------|----------------|------------------------|--------|
| `app` | PHP 8.2-FPM (custom) | `tiketlomba_app` | 9000 (internal) | Application Server (PHP-FPM) |
| `web` | Nginx Alpine | `tiketlomba_web` | **8080** -> 80 | Web Server / SPA Router |
| `db` | MySQL 8.0 | `tiketlomba_db` | **3307** -> 3306 | Database Server |
| `node` | Node 18 Alpine | `tiketlomba_node` | - | Asset Compiler (Profil: `assets`) |

---

## 📌 Catatan Performa & Named Volume

Di sistem operasi Windows, bind mount seluruh folder project ke Docker Container bisa terasa lambat karena overhead I/O. Oleh karena itu, folder-folder berikut menggunakan **Named Volumes**:
- `/var/www/html/vendor` (Composer dependencies)
- `/var/www/html/node_modules` (NPM dependencies)
- `/var/www/html/storage` (App storage)
- `/var/www/html/bootstrap/cache` (Framework cache)

Ini membuat aplikasi berjalan sangat cepat di Windows.

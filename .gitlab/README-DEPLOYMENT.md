# Deployment Guide - Tiket Lomba

Dokumentasi lengkap untuk deployment aplikasi Tiket Lomba menggunakan GitLab CI/CD.

## Daftar Isi

- [Prerequisites](#prerequisites)
- [Server Setup](#server-setup)
- [GitLab Configuration](#gitlab-configuration)
- [Deployment Process](#deployment-process)
- [Rollback Procedure](#rollback-procedure)
- [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Server Requirements
- OS: Ubuntu 22.04 atau 20.04 LTS
- RAM: Minimal 1GB (Recommended 2GB)
- Storage: Minimal 20GB
- PHP 8.2+
- MySQL 8.0+ atau MariaDB 10.5+
- Nginx atau Apache
- Composer
- Node.js 18+
- Git

### Local Requirements
- SSH client
- Git
- Access ke GitLab repository

---

## Server Setup

### 1. Jalankan Setup Script

```bash
# SSH ke server sebagai root
ssh root@your-server-ip

# Download dan jalankan setup script
curl -o setup-server.sh https://your-repo/scripts/setup-server.sh
chmod +x setup-server.sh
sudo bash setup-server.sh
```

Atau manual:

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.2
sudo apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
  php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl \
  php8.2-zip php8.2-gd php8.2-intl

# Install Nginx
sudo apt install -y nginx

# Install MySQL
sudo apt install -y mysql-server
sudo mysql_secure_installation

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js 18
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Install Git
sudo apt install -y git
```

### 2. Create Database

```bash
sudo mysql
```

```sql
CREATE DATABASE tiket_lomba CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tiket_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON tiket_lomba.* TO 'tiket_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Setup Deploy User

```bash
# Create user
sudo adduser deploy
sudo usermod -aG sudo deploy

# Setup SSH directory
sudo -u deploy mkdir -p /home/deploy/.ssh
sudo -u deploy chmod 700 /home/deploy/.ssh

# Setup project directory
sudo mkdir -p /var/www/tiket-lomba
sudo chown -R deploy:deploy /var/www/tiket-lomba
sudo chmod -R 775 /var/www/tiket-lomba

# Setup backup directory
sudo mkdir -p /var/www/tiket-lomba-backups
sudo chown -R deploy:deploy /var/www/tiket-lomba-backups
```

### 4. Setup SSH Key untuk Deploy

**Di local machine:**

```bash
# Generate SSH key pair
bash scripts/generate-ssh-key.sh

# Atau manual
ssh-keygen -t ed25519 -C "gitlab-deploy" -f ~/.ssh/gitlab-deploy

# Copy public key ke server
ssh-copy-id -i ~/.ssh/gitlab-deploy.pub deploy@your-server-ip

# Test connection
ssh -i ~/.ssh/gitlab-deploy deploy@your-server-ip
```

**Di server (alternative):**

```bash
# Login sebagai deploy user
sudo su - deploy

# Add public key
nano ~/.ssh/authorized_keys
# Paste public key content

# Set permissions
chmod 600 ~/.ssh/authorized_keys
```

### 5. Setup Nginx Virtual Host

```bash
sudo nano /etc/nginx/sites-available/tiket-lomba
```

```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/tiket-lomba/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
# Enable site
sudo ln -s /etc/nginx/sites-available/tiket-lomba /etc/nginx/sites-enabled/

# Test configuration
sudo nginx -t

# Reload Nginx
sudo systemctl reload nginx
```

### 6. Setup SSL (Certbot)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo systemctl enable certbot.timer
```

---

## GitLab Configuration

### 1. Push Repository ke GitLab

```bash
# Add GitLab remote
git remote add gitlab git@gitlab.com:username/tiket-lomba.git

# Push ke GitLab
git push gitlab main
```

### 2. Set CI/CD Variables

Go to **GitLab → Settings → CI/CD → Variables** dan tambahkan variables berikut:

| Variable | Type | Value | Protected | Masked |
|----------|------|-------|-----------|--------|
| `SSH_PRIVATE_KEY` | File | `[content dari ~/.ssh/gitlab-deploy]` | ✅ | ❌ |
| `SSH_USER` | Variable | `deploy` | ✅ | ❌ |
| `SSH_HOST` | Variable | `your-server-ip` atau `your-domain.com` | ✅ | ❌ |
| `DEPLOY_PATH` | Variable | `/var/www/tiket-lomba` | ✅ | ❌ |
| `BACKUP_PATH` | Variable | `/var/www/tiket-lomba-backups` | ✅ | ❌ |
| `APP_URL` | Variable | `https://your-domain.com` | ✅ | ❌ |
| `APP_KEY` | Variable | `base64:...` | ✅ | ✅ |
| `DB_HOST` | Variable | `127.0.0.1` | ✅ | ❌ |
| `DB_DATABASE` | Variable | `tiket_lomba` | ✅ | ❌ |
| `DB_USERNAME` | Variable | `tiket_user` | ✅ | ❌ |
| `DB_PASSWORD` | Variable | `[database password]` | ✅ | ✅ |
| `GOOGLE_CREDENTIALS_BASE64` | Variable | `[base64 encoded json]` | ✅ | ✅ |

### 3. Generate APP_KEY

```bash
# Generate Laravel APP_KEY
php artisan key:generate --show

# Output: base64:random-string...
```

### 4. Encode Google Credentials

```bash
# Encode service account JSON to Base64
bash scripts/encode-google-credentials.sh path/to/service-account.json

# Copy output dan set sebagai GOOGLE_CREDENTIALS_BASE64
```

### 5. Setup .env di Server

```bash
# SSH ke server
ssh deploy@your-server-ip

# Create .env file
cd /var/www/tiket-lomba
nano .env
```

Copy dari `.env.production.example` dan sesuaikan:

```env
APP_NAME="Tiket Lomba"
APP_ENV=production
APP_KEY=base64:your-app-key
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tiket_lomba
DB_USERNAME=tiket_user
DB_PASSWORD=your_database_password

# Decode Google credentials (akan di-handle oleh aplikasi)
# atau set path ke file JSON
```

---

## Deployment Process

### Automatic Deployment

1. Push ke branch `main`:
   ```bash
   git add .
   git commit -m "Your commit message"
   git push gitlab main
   ```

2. Pipeline otomatis berjalan:
   - **Test Stage**: PHPUnit tests
   - **Build Stage**: Compile frontend assets

3. Deploy manual:
   - Go to **GitLab → CI/CD → Pipelines**
   - Klik pipeline yang sedang berjalan
   - Setelah test dan build success, klik **"Play"** button pada job `deploy_production`

4. Monitor deployment progress di job logs

### Manual Deployment (Alternative)

Jika ingin deploy tanpa GitLab CI/CD:

```bash
# Set environment variables
export SSH_USER=deploy
export SSH_HOST=your-server-ip
export DEPLOY_PATH=/var/www/tiket-lomba
export BACKUP_PATH=/var/www/tiket-lomba-backups

# Run deploy script
bash deploy.sh production
```

### Post-Deployment Checklist

- [ ] Aplikasi bisa diakses di `https://your-domain.com`
- [ ] Login berhasil
- [ ] Database migration berjalan dengan baik
- [ ] Assets (CSS/JS) loaded dengan benar
- [ ] Storage link sudah dibuat
- [ ] Permissions sudah benar (775 untuk storage dan bootstrap/cache)

---

## Rollback Procedure

### Via GitLab CI/CD

1. Go to **GitLab → CI/CD → Pipelines**
2. Cari pipeline yang berhasil sebelumnya
3. Klik pipeline tersebut
4. Klik **"Play"** button pada job `rollback_production`
5. Monitor rollback progress

### Via Manual Script

```bash
# Set environment variables
export SSH_USER=deploy
export SSH_HOST=your-server-ip
export DEPLOY_PATH=/var/www/tiket-lomba
export BACKUP_PATH=/var/www/tiket-lomba-backups

# List available backups
ssh $SSH_USER@$SSH_HOST "ls -lt $BACKUP_PATH/"

# Rollback ke backup tertentu
bash rollback.sh backup_20261105_123456

# Atau rollback ke backup terbaru
bash rollback.sh latest
```

### Backup Retention

Backups disimpan di `/var/www/tiket-lomba-backups/` dengan format:
```
backup_YYYYMMDD_HHMMSS/
```

**Rekomendasi:** Setup cron job untuk auto-cleanup old backups:

```bash
# SSH ke server
ssh deploy@your-server-ip

# Edit crontab
crontab -e

# Add line (keep last 10 backups)
0 2 * * * cd /var/www/tiket-lomba-backups && ls -t | tail -n +11 | xargs rm -rf
```

---

## Troubleshooting

### Permission Denied

```bash
# SSH ke server
ssh deploy@your-server-ip

# Fix permissions
cd /var/www/tiket-lomba
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 500 Internal Server Error

```bash
# Check logs
tail -f /var/www/tiket-lomba/storage/logs/laravel.log

# Check Nginx error log
sudo tail -f /var/log/nginx/error.log

# Check PHP-FPM log
sudo tail -f /var/log/php8.2-fpm/error.log

# Clear cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Migration Error

```bash
# SSH ke server
ssh deploy@your-server-ip
cd /var/www/tiket-lomba

# Check migration status
php artisan migrate:status

# Run migration manually
php artisan migrate --force

# Rollback last migration
php artisan migrate:rollback --step=1
```

### Assets Not Loading

```bash
# Rebuild assets
npm ci
npm run prod

# Check mix-manifest.json
cat public/mix-manifest.json

# Check file permissions
ls -la public/js/
ls -la public/css/
```

### Database Connection Error

```bash
# Test database connection
mysql -u tiket_user -p tiket_lomba

# Check .env
cat /var/www/tiket-lomba/.env | grep DB_

# Restart MySQL
sudo systemctl restart mysql
```

### SSH Connection Failed

```bash
# Test SSH connection
ssh -v deploy@your-server-ip

# Check SSH service
sudo systemctl status ssh

# Check firewall
sudo ufw status

# Allow SSH
sudo ufw allow 22
```

### GitLab Pipeline Failed

**Test Stage Failed:**
- Check PHPUnit output
- Verify database service running
- Check test coverage reports

**Build Stage Failed:**
- Check npm install errors
- Verify Node.js version (18+)
- Check package.json dependencies

**Deploy Stage Failed:**
- Check SSH connection
- Verify SSH_PRIVATE_KEY variable
- Check server permissions
- Verify .env file exists

---

## Useful Commands

### Server Management

```bash
# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Restart Nginx
sudo systemctl restart nginx

# Check service status
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status mysql

# View logs
tail -f /var/www/tiket-lomba/storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log
```

### Laravel Artisan

```bash
# Clear all cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Optimize
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Maintenance mode
php artisan down --message="Maintenance mode" --retry=60
php artisan up

# Check Laravel version
php artisan --version
```

### Database

```bash
# Backup database
mysqldump -u tiket_user -p tiket_lomba > backup_$(date +%Y%m%d).sql

# Restore database
mysql -u tiket_user -p tiket_lomba < backup_20261105.sql

# Check database size
mysql -u tiket_user -p -e "SELECT table_schema AS 'Database', SUM(data_length + index_length) / 1024 / 1024 AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'tiket_lomba' GROUP BY table_schema;"
```

---

## Security Checklist

- [ ] `APP_DEBUG=false` di production
- [ ] `APP_KEY` sudah diset dan unique
- [ ] Database password kuat
- [ ] SSH key menggunakan ED25519 atau RSA 4096
- [ ] Firewall aktif (UFW)
- [ ] SSL certificate valid
- [ ] `.env` tidak accessible dari web
- [ ] `storage/` dan `bootstrap/cache/` tidak accessible dari web
- [ ] PHP file execution disabled di `storage/` dan `public/upload/`

---

## Support

Jika mengalami masalah, hubungi:
- Developer: [your-email@example.com]
- Documentation: `/docs/`
- Issues: GitLab Issues

---

**Last Updated:** 2026-11-05

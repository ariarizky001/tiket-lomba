# CI/CD Configuration Files - Tiket Lomba

File-file konfigurasi untuk deployment dengan GitLab CI/CD telah dibuat di folder ini.

## 📁 File yang Telah Dibuat

### Root Directory
- ✅ `.gitlab-ci.yml` - Pipeline configuration
- ✅ `deploy.sh` - Deployment script
- ✅ `rollback.sh` - Rollback script
- ✅ `.env.production.example` - Template environment variables production
- ✅ `.dockerignore` - Docker ignore patterns
- ✅ `.gitignore` - Updated dengan CI/CD artifacts

### Scripts Directory (`scripts/`)
- ✅ `setup-server.sh` - Server setup automation
- ✅ `generate-ssh-key.sh` - SSH key generator
- ✅ `encode-google-credentials.sh` - Google credentials encoder

### Documentation (`.gitlab/`)
- ✅ `README-DEPLOYMENT.md` - Dokumentasi lengkap deployment

---

## 🚀 Quick Start Guide

### 1. Persiapan Lokal

```bash
# Generate SSH key untuk deployment
bash scripts/generate-ssh-key.sh

# Encode Google Service Account JSON
bash scripts/encode-google-credentials.sh path/to/your-service-account.json
```

### 2. Setup Server (Saat VPS Sudah Siap)

```bash
# SSH ke server sebagai root
ssh root@your-server-ip

# Jalankan setup script
sudo bash scripts/setup-server.sh
```

### 3. Konfigurasi GitLab

**Push repository ke GitLab:**
```bash
git remote add gitlab git@gitlab.com:username/tiket-lomba.git
git push gitlab main
```

**Set CI/CD Variables di GitLab:**

Go to: **Settings → CI/CD → Variables**

| Variable Name | Type | Value |
|--------------|------|-------|
| `SSH_PRIVATE_KEY` | File | Private key dari `~/.ssh/gitlab-deploy` |
| `SSH_USER` | Variable | `deploy` |
| `SSH_HOST` | Variable | IP/domain server |
| `DEPLOY_PATH` | Variable | `/var/www/tiket-lomba` |
| `BACKUP_PATH` | Variable | `/var/www/tiket-lomba-backups` |
| `APP_URL` | Variable | `https://your-domain.com` |
| `APP_KEY` | Variable | Generate: `php artisan key:generate --show` |
| `DB_HOST` | Variable | `127.0.0.1` |
| `DB_DATABASE` | Variable | `tiket_lomba` |
| `DB_USERNAME` | Variable | `tiket_user` |
| `DB_PASSWORD` | Variable | Database password |
| `GOOGLE_CREDENTIALS_BASE64` | Variable | Base64 encoded JSON |

### 4. Deploy

1. Push ke main branch
2. Go to GitLab → CI/CD → Pipelines
3. Wait for test & build stages to complete
4. Click **"Play"** button pada `deploy_production` job
5. Monitor deployment logs

### 5. Rollback (Jika Diperlukan)

1. Go to GitLab → CI/CD → Pipelines
2. Click **"Play"** button pada `rollback_production` job

---

## 📋 Pipeline Stages

```
┌─────────┐
│  TEST   │ - PHPUnit tests with code coverage
└────┬────┘
     │
┌────▼────┐
│  BUILD  │ - Compile frontend assets (npm run prod)
└────┬────┘
     │
┌────▼────┐
│ DEPLOY  │ - Manual trigger, sync to server + migration
└────┬────┘
     │
┌────▼────┐
│ROLLBACK │ - Manual trigger, restore from backup
└─────────┘
```

---

## 🔧 Manual Deployment (Alternative)

Jika tidak menggunakan GitLab CI/CD:

```bash
# Set environment variables
export SSH_USER=deploy
export SSH_HOST=your-server-ip
export DEPLOY_PATH=/var/www/tiket-lomba
export BACKUP_PATH=/var/www/tiket-lomba-backups

# Deploy
bash deploy.sh production

# Rollback
bash rollback.sh latest
```

---

## 📖 Dokumentasi Lengkap

Baca dokumentasi lengkap di: [.gitlab/README-DEPLOYMENT.md](.gitlab/README-DEPLOYMENT.md)

---

## ✅ Checklist Persiapan

### Sebelum Setup Server
- [ ] VPS sudah tersedia (Ubuntu 22.04/20.04)
- [ ] Domain sudah pointing ke IP server
- [ ] SSH access ke server (port 22)

### Setup Server
- [ ] Jalankan `setup-server.sh`
- [ ] Setup database & user
- [ ] Setup Nginx virtual host
- [ ] Setup SSL certificate (Certbot)
- [ ] Create `.env` file di server
- [ ] Setup SSH key untuk deploy user

### Setup GitLab
- [ ] Push repository ke GitLab
- [ ] Set semua CI/CD Variables
- [ ] Enable GitLab Runner (biasanya sudah default)
- [ ] Test pipeline dengan push ke main

### Post-Deployment
- [ ] Verify aplikasi bisa diakses
- [ ] Test login & fitur utama
- [ ] Check logs: `tail -f storage/logs/laravel.log`
- [ ] Setup monitoring (optional)
- [ ] Setup backup cron job (optional)

---

## 🆘 Troubleshooting

### Pipeline Failed

**Test stage failed:**
```bash
# Check PHPUnit output di GitLab logs
# Biasanya karena database connection atau test setup
```

**Build stage failed:**
```bash
# Check npm install errors
# Verify package.json dependencies
```

**Deploy stage failed:**
```bash
# Check SSH connection
ssh deploy@your-server-ip

# Verify SSH_PRIVATE_KEY variable
# Check server permissions
```

### Server Issues

**500 Error:**
```bash
# Check logs
tail -f /var/www/tiket-lomba/storage/logs/laravel.log

# Fix permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

**Assets not loading:**
```bash
# Check public/js/ and public/css/ exists
ls -la public/js/
ls -la public/css/

# Verify mix-manifest.json
cat public/mix-manifest.json
```

---

## 📝 Notes

- Backup otomatis dibuat setiap deployment di `/var/www/tiket-lomba-backups/`
- Format backup: `backup_YYYYMMDD_HHMMSS/`
- Rollback akan restore ke backup terbaru
- Scripts harus dijalankan dari root directory project
- Chmod +x tidak diperlukan di Windows, tapi akan otomatis executable di Linux server

---

## 🔐 Security Notes

- **NEVER commit** `.env` file
- **NEVER commit** Google Service Account JSON
- **NEVER commit** SSH private keys
- Gunakan GitLab CI/CD Variables untuk secrets
- Set variables sebagai **Protected** dan **Masked**
- Gunakan strong password untuk database
- Enable firewall di server (UFW)
- Setup SSL certificate untuk HTTPS

---

## 📞 Support

Untuk bantuan lebih lanjut:
- Baca dokumentasi lengkap di `.gitlab/README-DEPLOYMENT.md`
- Check GitLab pipeline logs untuk error details
- SSH ke server untuk check application logs

---

**Created:** 2026-11-05  
**Status:** Ready for deployment  
**Next Step:** Setup VPS dan jalankan deployment

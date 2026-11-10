#!/bin/bash

# Server Setup Script for Tiket Lomba
# Run this script on a fresh Ubuntu 22.04/20.04 server
# Usage: sudo bash setup-server.sh

set -e

echo "=========================================="
echo "  SERVER SETUP - Tiket Lomba"
echo "  OS: Ubuntu 22.04/20.04"
echo "=========================================="

# Check if running as root
if [ "$EUID" -ne 0 ]; then
  echo "Please run as root: sudo bash $0"
  exit 1
fi

# Update system
echo ""
echo "[1/9] Updating system packages..."
apt update && apt upgrade -y

# Install PHP 8.2
echo ""
echo "[2/9] Installing PHP 8.2 and extensions..."
apt install -y software-properties-common
add-apt-repository -y ppa:ondrej/php
apt update
apt install -y php8.2 php8.2-cli php8.2-fpm php8.2-mysql \
  php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl \
  php8.2-zip php8.2-gd php8.2-intl php8.2-readline

# Install Nginx
echo ""
echo "[3/9] Installing Nginx..."
apt install -y nginx

# Install MySQL
echo ""
echo "[4/9] Installing MySQL Server..."
apt install -y mysql-server
systemctl enable mysql
systemctl start mysql

# Install Composer
echo ""
echo "[5/9] Installing Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
chmod +x /usr/local/bin/composer

# Install Node.js 18
echo ""
echo "[6/9] Installing Node.js 18..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs

# Install Git
echo ""
echo "[7/9] Installing Git..."
apt install -y git

# Create deploy user
echo ""
echo "[8/9] Creating deploy user..."
if ! id -u deploy &>/dev/null; then
  adduser --disabled-password --gecos "" deploy
  usermod -aG sudo deploy
  echo "Deploy user created: deploy"
else
  echo "Deploy user already exists"
fi

# Setup directories
echo ""
echo "[9/9] Setting up project directories..."
PROJECT_PATH="/var/www/tiket-lomba"
BACKUP_PATH="/var/www/tiket-lomba-backups"

mkdir -p $PROJECT_PATH
mkdir -p $BACKUP_PATH
mkdir -p $PROJECT_PATH/storage/framework/{sessions,views,cache}
mkdir -p $PROJECT_PATH/storage/logs
mkdir -p $PROJECT_PATH/bootstrap/cache

chown -R deploy:deploy $PROJECT_PATH
chown -R deploy:deploy $BACKUP_PATH
chmod -R 775 $PROJECT_PATH/storage
chmod -R 775 $PROJECT_PATH/bootstrap/cache

echo ""
echo "=========================================="
echo "  SERVER SETUP COMPLETED!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Run: mysql_secure_installation"
echo "2. Create database: mysql -u root -p"
echo "   CREATE DATABASE tiket_lomba;"
echo "   CREATE USER 'tiket_user'@'localhost' IDENTIFIED BY 'your_password';"
echo "   GRANT ALL PRIVILEGES ON tiket_lomba.* TO 'tiket_user'@'localhost';"
echo "   FLUSH PRIVILEGES;"
echo "3. Setup SSH key for deploy user"
echo "4. Configure Nginx virtual host"
echo "5. Setup SSL certificate (Certbot)"
echo ""

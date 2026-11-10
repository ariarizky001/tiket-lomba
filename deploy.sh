#!/bin/bash

set -e

DEPLOY_ENV="${1:-production}"
SSH_USER="${SSH_USER:-deploy}"
SSH_HOST="${SSH_HOST:-your-server-ip}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/tiket-lomba}"
BACKUP_PATH="${BACKUP_PATH:-/var/www/tiket-lomba-backups}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo "=========================================="
echo "  DEPLOYMENT SCRIPT - Tiket Lomba"
echo "  Environment: $DEPLOY_ENV"
echo "  Target: $SSH_USER@$SSH_HOST:$DEPLOY_PATH"
echo "  Time: $(date)"
echo "=========================================="

echo ""
echo "[1/5] Building frontend assets..."
npm ci
npm run prod

echo ""
echo "[2/5] Creating backup on server..."
ssh $SSH_USER@$SSH_HOST "mkdir -p $BACKUP_PATH && cp -r $DEPLOY_PATH $BACKUP_PATH/backup_$TIMESTAMP 2>/dev/null || true"

echo ""
echo "[3/5] Syncing files to server..."
rsync -avz --delete \
  --exclude='.env' \
  --exclude='.env.local' \
  --exclude='.env.*.local' \
  --exclude='storage/' \
  --exclude='node_modules/' \
  --exclude='vendor/' \
  --exclude='.git/' \
  --exclude='.github/' \
  --exclude='.gitlab/' \
  --exclude='tests/' \
  --exclude='phpunit.xml' \
  --exclude='.phpunit.result.cache' \
  --exclude='*.md' \
  --exclude='*.log' \
  --exclude='deploy.sh' \
  --exclude='rollback.sh' \
  ./ $SSH_USER@$SSH_HOST:$DEPLOY_PATH

echo ""
echo "[4/5] Running post-deploy commands..."
ssh $SSH_USER@$SSH_HOST "cd $DEPLOY_PATH && \
  composer install --no-dev --optimize-autoloader --no-interaction && \
  php artisan down --message 'Maintenance mode - deploying...' && \
  php artisan migrate --force && \
  php artisan config:cache && \
  php artisan route:cache && \
  php artisan view:cache && \
  php artisan storage:link 2>/dev/null || true && \
  php artisan up && \
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true && \
  chmod -R 775 storage bootstrap/cache 2>/dev/null || true"

echo ""
echo "[5/5] Verifying deployment..."
ssh $SSH_USER@$SSH_HOST "cd $DEPLOY_PATH && \
  echo 'Laravel version:' && php artisan --version && \
  echo 'File permissions:' && ls -ld storage bootstrap/cache"

echo ""
echo "=========================================="
echo "  DEPLOYMENT COMPLETED SUCCESSFULLY!"
echo "  Backup: $BACKUP_PATH/backup_$TIMESTAMP"
echo "  URL: Check your APP_URL"
echo "=========================================="

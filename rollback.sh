#!/bin/bash

set -e

SSH_USER="${SSH_USER:-deploy}"
SSH_HOST="${SSH_HOST:-your-server-ip}"
DEPLOY_PATH="${DEPLOY_PATH:-/var/www/tiket-lomba}"
BACKUP_PATH="${BACKUP_PATH:-/var/www/tiket-lomba-backups}"
BACKUP_NAME="${1:-latest}"

echo "=========================================="
echo "  ROLLBACK SCRIPT - Tiket Lomba"
echo "  Target: $SSH_USER@$SSH_HOST:$DEPLOY_PATH"
echo "=========================================="

echo ""
echo "Available backups:"
echo "---"
ssh $SSH_USER@$SSH_HOST "ls -lt $BACKUP_PATH/ | head -20"
echo "---"

if [ "$BACKUP_NAME" = "latest" ]; then
  BACKUP_NAME=$(ssh $SSH_USER@$SSH_HOST "ls -t $BACKUP_PATH/ | head -1")
  echo ""
  echo "Auto-selecting latest backup: $BACKUP_NAME"
fi

if [ -z "$BACKUP_NAME" ]; then
  echo ""
  echo "ERROR: No backup found!"
  echo "Make sure backups exist in: $BACKUP_PATH/"
  exit 1
fi

echo ""
read -p "Are you sure you want to rollback to '$BACKUP_NAME'? (yes/no): " CONFIRM

if [ "$CONFIRM" != "yes" ]; then
  echo "Rollback cancelled."
  exit 0
fi

echo ""
echo "[1/3] Putting application in maintenance mode..."
ssh $SSH_USER@$SSH_HOST "cd $DEPLOY_PATH && php artisan down --message 'Rollback in progress...' 2>/dev/null || true"

echo ""
echo "[2/3] Restoring from backup: $BACKUP_NAME"
ssh $SSH_USER@$SSH_HOST "rm -rf $DEPLOY_PATH &&
  cp -r $BACKUP_PATH/$BACKUP_NAME $DEPLOY_PATH &&
  cd $DEPLOY_PATH &&
  php artisan migrate --force &&
  php artisan config:cache &&
  php artisan route:cache &&
  php artisan view:cache"

echo ""
echo "[3/3] Bringing application back online..."
ssh $SSH_USER@$SSH_HOST "cd $DEPLOY_PATH &&
  php artisan up &&
  chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true &&
  chmod -R 775 storage bootstrap/cache 2>/dev/null || true"

echo ""
echo "=========================================="
echo "  ROLLBACK COMPLETED SUCCESSFULLY!"
echo "  Restored from: $BACKUP_NAME"
echo "  Time: $(date)"
echo "=========================================="

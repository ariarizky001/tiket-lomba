#!/bin/bash

# Generate SSH Key Pair for GitLab CI/CD Deployment
# Usage: bash generate-ssh-key.sh

set -e

KEY_NAME="gitlab-deploy"
KEY_PATH="$HOME/.ssh/$KEY_NAME"

echo "=========================================="
echo "  SSH KEY GENERATOR"
echo "=========================================="

if [ -f "$KEY_PATH" ]; then
  echo ""
  echo "Warning: Key pair already exists at $KEY_PATH"
  read -p "Overwrite? (yes/no): " OVERWRITE
  if [ "$OVERWRITE" != "yes" ]; then
    echo "Cancelled."
    exit 0
  fi
  rm -f "$KEY_PATH" "$KEY_PATH.pub"
fi

echo ""
echo "[1/3] Generating ED25519 SSH key pair..."
ssh-keygen -t ed25519 -C "gitlab-deploy@$HOSTNAME" -f "$KEY_PATH" -N ""

echo ""
echo "[2/3] Setting permissions..."
chmod 700 ~/.ssh
chmod 600 "$KEY_PATH"
chmod 644 "$KEY_PATH.pub"

echo ""
echo "[3/3] Displaying keys..."
echo ""
echo "=========================================="
echo "  PUBLIC KEY (Add to server ~/.ssh/authorized_keys)"
echo "=========================================="
cat "$KEY_PATH.pub"
echo ""
echo "=========================================="
echo "  PRIVATE KEY (Add to GitLab CI/CD Variables as SSH_PRIVATE_KEY)"
echo "=========================================="
cat "$KEY_PATH"
echo "=========================================="

echo ""
echo "Next steps:"
echo "1. Copy the PUBLIC KEY above"
echo "2. SSH to your server and add to ~/.ssh/authorized_keys:"
echo "   ssh deploy@your-server-ip"
echo "   echo 'PUBLIC_KEY_HERE' >> ~/.ssh/authorized_keys"
echo ""
echo "3. Copy the PRIVATE KEY above"
echo "4. Go to GitLab → Settings → CI/CD → Variables"
echo "5. Add variable: SSH_PRIVATE_KEY (type: File)"
echo "6. Paste the private key content"
echo ""

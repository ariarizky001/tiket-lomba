#!/bin/bash

# Encode Google Service Account JSON to Base64
# Usage: bash encode-google-credentials.sh path/to/service-account.json

set -e

if [ -z "$1" ]; then
  echo "Usage: bash encode-google-credentials.sh path/to/service-account.json"
  exit 1
fi

INPUT_FILE="$1"

if [ ! -f "$INPUT_FILE" ]; then
  echo "Error: File not found: $INPUT_FILE"
  exit 1
fi

echo "=========================================="
echo "  GOOGLE CREDENTIALS ENCODER"
echo "=========================================="

echo ""
echo "[1/2] Encoding $INPUT_FILE to Base64..."

if command -v base64 &> /dev/null; then
  # Linux
  ENCODED=$(base64 -w 0 "$INPUT_FILE")
elif command -v b64 &> /dev/null; then
  # macOS alternative
  ENCODED=$(b64 -i "$INPUT_FILE")
else
  # Fallback for Windows Git Bash or other systems
  ENCODED=$(cat "$INPUT_FILE" | base64 | tr -d '\n')
fi

echo ""
echo "[2/2] Base64 encoded string:"
echo ""
echo "=========================================="
echo "$ENCODED"
echo "=========================================="

echo ""
echo "Next steps:"
echo "1. Copy the Base64 string above"
echo "2. Go to GitLab → Settings → CI/CD → Variables"
echo "3. Add variable: GOOGLE_CREDENTIALS_BASE64"
echo "4. Paste the Base64 string as value"
echo ""
echo "To decode on server (for testing):"
echo "  echo \$GOOGLE_CREDENTIALS_BASE64 | base64 -d > credentials.json"
echo ""

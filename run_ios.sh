#!/bin/bash
set -e

# API host production (tanpa suffix /api/v1 — ApiClient tambah sendiri)
API_BASE_URL="https://mywap.my"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FLUTTER_DIR="$SCRIPT_DIR/mywap_mobile"

if ! command -v flutter >/dev/null 2>&1; then
  echo "✗ flutter tidak ditemui dalam PATH."
  exit 1
fi

echo "→ Mencari simulator iOS yang booted..."

BOOTED="$(
  xcrun simctl list devices booted 2>/dev/null \
    | sed -n 's/^[[:space:]]*//; s/([^)]*)[[:space:]]*(Booted).*//p'
)"

if [ -z "$BOOTED" ]; then
  echo "→ Tiada simulator booted. Membuka Simulator..."
  open -a Simulator
  echo "⚠  Tunggu setakat simulator iOS boot, kemudian run ./run_ios.sh sekali lagi."
  exit 1
fi

DEVICE_ID="$(
  xcrun simctl list devices -j booted 2>/dev/null \
    | python3 -c 'import sys,json;d=json.load(sys.stdin);print(next(x["udid"] for k in d["devices"] for x in d["devices"][k] if "udid" in x))'
)"

if [ -z "$DEVICE_ID" ]; then
  echo "✗ Tidak dapat dapatkan ID simulator. Buka satu dan cuba lagi."
  exit 1
fi

echo "→ Menyemak backend $API_BASE_URL reachable..."
if ! curl -sSfI --max-time 10 "$API_BASE_URL" >/dev/null 2>&1; then
  echo "⚠  Amaran: $API_BASE_URL tidak menjawab HTTP HEAD. Terus juga?"
fi

echo "→ Run app di simulator dengan API_BASE_URL=$API_BASE_URL"
cd "$FLUTTER_DIR"
flutter run -d "$DEVICE_ID" --dart-define=API_BASE_URL="$API_BASE_URL"

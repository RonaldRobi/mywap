#!/bin/bash
set -e

# myWAP — build AAB release untuk Google Play.
#
# Guna: ./build_android_release.sh [--no-bump]
#   --no-bump  Jangan naikkan versionCode (pubspec sudah disediakan oleh ship.sh).

API_BASE_URL="${API_BASE_URL:-https://mywap.my}"

NO_BUMP=0
for arg in "$@"; do
  case "$arg" in
    --no-bump) NO_BUMP=1 ;;
    -h|--help)
      echo "Penggunaan: ./build_android_release.sh [--no-bump]"
      echo "  --no-bump  Jangan naikkan versionCode (guna pubspec semasa)."
      exit 0
      ;;
    *)
      echo "✗ Argumen tidak dikenali: $arg"
      exit 1
      ;;
  esac
done

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FLUTTER_DIR="$SCRIPT_DIR/mywap_mobile"
MANIFEST="$FLUTTER_DIR/build/app/intermediates/merged_manifests/release/processReleaseManifest/AndroidManifest.xml"
AAB="$FLUTTER_DIR/build/app/outputs/bundle/release/app-release.aab"

export PATH="/opt/homebrew/opt/ruby/bin:$PATH"

if ! command -v flutter >/dev/null 2>&1; then
  echo "✗ flutter tidak ditemui dalam PATH."
  exit 1
fi

if [ "$NO_BUMP" -eq 0 ]; then
  echo "→ Semak versionCode (App Store Connect + Play)..."
  (cd "$FLUTTER_DIR" && ruby tool/version.rb bump-build --write) || {
    echo "✗ Gagal bump versionCode. Betulkan rangkaian/kunci, atau guna --no-bump jika pubspec memang sudah betul."
    exit 1
  }
fi

PUB_VERSION="$(sed -n 's/^version:[[:space:]]*//p' "$FLUTTER_DIR/pubspec.yaml" | head -1)"
PUB_MARKETING="${PUB_VERSION%%+*}"
PUB_BUILD="${PUB_VERSION##*+}"
echo "→ Versi: $PUB_VERSION"

cd "$FLUTTER_DIR"

echo "→ flutter pub get..."
flutter pub get

echo "→ build_runner (jana semula kod generated)..."
dart run build_runner build --delete-conflicting-outputs

echo "→ Build AAB (release, API_BASE_URL=$API_BASE_URL)"
flutter build appbundle --release --dart-define=API_BASE_URL="$API_BASE_URL"

if [ ! -f "$AAB" ]; then
  echo "✗ AAB tidak dijana: $AAB"
  exit 1
fi

if [ -f "$MANIFEST" ]; then
  AAB_CODE="$(ruby -e 'm = File.read(ARGV[0]).match(/android:versionCode="(\d+)"/); puts m ? m[1] : ""' "$MANIFEST")"
  AAB_NAME="$(ruby -e 'm = File.read(ARGV[0]).match(/android:versionName="([^"]*)"/); puts m ? m[1] : ""' "$MANIFEST")"

  if [ "$AAB_CODE" != "$PUB_BUILD" ] || [ "$AAB_NAME" != "$PUB_MARKETING" ]; then
    echo "✗ AAB $AAB_NAME ($AAB_CODE) tidak sepadan dengan pubspec $PUB_MARKETING ($PUB_BUILD)."
    echo "  Jalankan semula tanpa --no-bump, atau betulkan pubspec."
    exit 1
  fi
  echo "→ AAB disahkan: $AAB_NAME ($AAB_CODE)"
else
  echo "! Manifest gabungan tidak dijumpai; langkau pengesahan versionCode."
fi

echo "✓ Siap. AAB untuk Play Console:"
ls -lah "$AAB"

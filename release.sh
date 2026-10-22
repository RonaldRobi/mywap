#!/bin/bash
set -euo pipefail

# myWAP — build + hantar terus ke TestFlight dan Play Console (internal track).
#
# Guna:
#   ./release.sh                 # bump build, build dua-dua, upload dua-dua
#   ./release.sh --no-bump       # guna build number semasa
#   ./release.sh --ios-only      # iOS sahaja
#   ./release.sh --android-only  # Android sahaja
#   ./release.sh --upload-only   # upload artifact sedia ada, tak build
#
# Prasyarat (sekali sahaja):
#   cd mywap_mobile && bundle install
#   fastlane/asc_key.json              (App Store Connect API key)
#   fastlane/play_service_account.json (Play Console service account)
#
# Kredensial TIDAK di-commit — lihat .gitignore.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
MOBILE="$ROOT/mywap_mobile"
PUBSPEC="$MOBILE/pubspec.yaml"
API_BASE_URL="https://mywap.my"

DO_BUMP=1
DO_IOS=1
DO_ANDROID=1
DO_BUILD=1

for arg in "$@"; do
  case "$arg" in
    --no-bump) DO_BUMP=0 ;;
    --ios-only) DO_ANDROID=0 ;;
    --android-only) DO_IOS=0 ;;
    --upload-only) DO_BUILD=0 ;;
    -h|--help)
      sed -n '3,20p' "$0"
      exit 0
      ;;
    *)
      echo "✗ Argumen tidak dikenali: $arg"
      exit 1
      ;;
  esac
done

die() { echo "✗ $1"; exit 1; }

# Homebrew Ruby (3.x) diutamakan; system Ruby 2.6 terlalu lama untuk fastlane.
if [ -x /opt/homebrew/opt/ruby/bin/bundle ]; then
  export PATH="/opt/homebrew/opt/ruby/bin:$PATH"
fi

command -v flutter >/dev/null 2>&1 || die "flutter tidak ditemui dalam PATH."

if [ "$DO_IOS" = 1 ] && [ "$DO_BUILD" = 1 ]; then
  [ -x "$ROOT/build_ios_release.sh" ] || die "build_ios_release.sh tiada/tidak executable."
fi

if [ "$DO_BUILD" = 1 ] && [ "$DO_BUMP" = 1 ]; then
  line="$(grep -E '^version:' "$PUBSPEC")"
  semver="${line#version: }"
  semver="${semver%%+*}"
  current="${line##*+}"
  next=$((current + 1))
  perl -0pi -e "s/^version: .*/version: ${semver}+${next}/m" "$PUBSPEC"
  echo "→ Build number: ${current} → ${next} (${semver}+${next})"
fi

if [ "$DO_BUILD" = 1 ]; then
  if [ "$DO_IOS" = 1 ]; then
    echo "→ Build iOS…"
    "$ROOT/build_ios_release.sh"
  fi
  if [ "$DO_ANDROID" = 1 ]; then
    echo "→ Build Android AAB…"
    (cd "$MOBILE" && flutter build appbundle --release --dart-define=API_BASE_URL="$API_BASE_URL")
  fi
fi

cd "$MOBILE"
command -v bundle >/dev/null 2>&1 || die "bundler tiada. Jalankan: gem install bundler"

if [ "$DO_IOS" = 1 ]; then
  echo "→ Upload ke TestFlight…"
  bundle exec fastlane ios beta
fi

if [ "$DO_ANDROID" = 1 ]; then
  echo "→ Upload ke Play Console…"
  bundle exec fastlane android beta
fi

echo "✓ Selesai."

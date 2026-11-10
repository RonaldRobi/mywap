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
#   ./release.sh --publish       # selepas upload: submit iOS review + promote Android production
#   ./release.sh --no-store-check # JANGAN semak store; guna nombor tempatan sahaja (tidak disyorkan)
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
DO_PUBLISH=0
DO_STORE_CHECK=1

for arg in "$@"; do
  case "$arg" in
    --no-bump) DO_BUMP=0 ;;
    --ios-only) DO_ANDROID=0 ;;
    --android-only) DO_IOS=0 ;;
    --upload-only) DO_BUILD=0 ;;
    --publish) DO_PUBLISH=1 ;;
    --no-store-check) DO_STORE_CHECK=0 ;;
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
command -v bundle >/dev/null 2>&1 || die "bundler tiada. Jalankan: cd mywap_mobile && gem install bundler && bundle install"

if [ "$DO_IOS" = 1 ] && [ "$DO_BUILD" = 1 ]; then
  [ -x "$ROOT/build_ios_release.sh" ] || die "build_ios_release.sh tiada/tidak executable."
fi

if [ "$DO_BUILD" = 1 ] && [ "$DO_BUMP" = 1 ]; then
  line="$(grep -E '^version:' "$PUBSPEC")"
  semver="${line#version: }"
  semver="${semver%%+*}"
  current="${line##*+}"

  # Jamin build number melebihi build terakhir di KEDUA-DUA store, bukan
  # sekadar nombor tempatan. Ini elak penolakan "bundle version must be higher".
  ios_latest=0
  android_latest=0
  store_check_failed=""

  if [ "$DO_IOS" = 1 ]; then
    echo "→ Semak build TestFlight terkini…"
    ios_out="$( (cd "$MOBILE" && bundle exec fastlane ios latest_build 2>/dev/null) || true )"
    parsed="$(printf '%s\n' "$ios_out" | grep -oE 'LATEST_IOS_BUILD=[0-9]+' | tail -1 | cut -d= -f2 || true)"
    if [ -n "${parsed:-}" ]; then
      ios_latest="$parsed"
    else
      store_check_failed="${store_check_failed} TestFlight"
      echo "  ⚠ Build TestFlight tidak dapat dibaca."
    fi
  fi

  if [ "$DO_ANDROID" = 1 ]; then
    echo "→ Semak versionCode Play terkini…"
    android_out="$( (cd "$MOBILE" && bundle exec fastlane android latest_version_code 2>/dev/null) || true )"
    parsed="$(printf '%s\n' "$android_out" | grep -oE 'LATEST_ANDROID_CODE=[0-9]+' | tail -1 | cut -d= -f2 || true)"
    if [ -n "${parsed:-}" ]; then
      android_latest="$parsed"
    else
      store_check_failed="${store_check_failed} Play"
      echo "  ⚠ VersionCode Play tidak dapat dibaca."
    fi
  fi

  if [ "$DO_STORE_CHECK" = 1 ] && [ -n "$store_check_failed" ]; then
    die "Tidak dapat sahkan build number dari:${store_check_failed}. Build dibatalkan untuk elak penolakan store. Betulkan kredensial/network, atau guna --no-store-check untuk paksa (tidak disyorkan)."
  fi

  next="$current"
  if [ "$ios_latest" -gt "$next" ]; then next="$ios_latest"; fi
  if [ "$android_latest" -gt "$next" ]; then next="$android_latest"; fi
  next=$((next + 1))

  perl -0pi -e "s/^version: .*/version: ${semver}+${next}/m" "$PUBSPEC"
  echo "→ Build number: tempatan ${current}, TestFlight ${ios_latest}, Play ${android_latest} → ${next} (${semver}+${next})"
fi

if [ "$DO_BUILD" = 1 ]; then
  if [ "$DO_IOS" = 1 ]; then
    echo "→ Build iOS…"
    "$ROOT/build_ios_release.sh"
  fi
  if [ "$DO_ANDROID" = 1 ]; then
    echo "→ Build Android AAB…"
    # Kod generated (*.g.dart / *.freezed.dart) di-ignore oleh git, jadi ia
    # mesti dijana semula sebelum build.
    (cd "$MOBILE" && flutter pub get \
      && dart run build_runner build --delete-conflicting-outputs \
      && flutter build appbundle --release --dart-define=API_BASE_URL="$API_BASE_URL")
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

if [ "$DO_PUBLISH" = 1 ]; then
  if [ "$DO_IOS" = 1 ]; then
    echo "→ Hantar build ke App Store untuk review…"
    bundle exec fastlane ios submit_review
  fi
  if [ "$DO_ANDROID" = 1 ]; then
    echo "→ Promote build Play ke production…"
    bundle exec fastlane android promote_production
  fi
fi

echo "✓ Selesai."

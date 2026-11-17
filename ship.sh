#!/bin/bash
set -euo pipefail

# myWAP — satu command: build iOS + Android, naikkan version dengan selamat,
# kemudian hantar ke TestFlight + Play.
#
# Guna:
#   ./ship.sh                 Build dua-dua → TestFlight + Play internal
#   ./ship.sh --release       Build + submit App Store review + Play production
#   ./ship.sh --ios-only      iOS sahaja
#   ./ship.sh --android-only  Android sahaja
#   ./ship.sh --dry-run       Tunjuk plan tanpa build/upload
#
# Pilihan lain: --track internal|alpha|beta|production, --skip-tests,
#               --test, --no-wait, --yes, --help

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FLUTTER_DIR="$ROOT/mywap_mobile"
IPA="$FLUTTER_DIR/build/ios/ipa/myWAP.ipa"
AAB="$FLUTTER_DIR/build/app/outputs/bundle/release/app-release.aab"
LOG_DIR="$FLUTTER_DIR/build/ship"

IOS=1
ANDROID=1
RELEASE=0
TRACK=""
SKIP_TESTS=0
RUN_TESTS=0
DRY_RUN=0
NO_WAIT=0
ASSUME_YES=0

usage() {
  cat <<'TEXT'
myWAP — satu command: build iOS + Android, naikkan version dengan selamat,
kemudian hantar ke TestFlight + Play.

Guna:
  ./ship.sh                 Build dua-dua → TestFlight + Play internal
  ./ship.sh --release       Build + submit App Store review + Play production
  ./ship.sh --ios-only      iOS sahaja
  ./ship.sh --android-only  Android sahaja
  ./ship.sh --dry-run       Tunjuk plan tanpa build/upload

Pilihan lain:
  --track internal|alpha|beta|production
  --skip-tests   Langkau flutter analyze
  --test         Jalankan flutter test
  --no-wait      Jangan tunggu pengesahan Apple/Google
  --yes          Jangan minta pengesahan untuk --release
TEXT
}

while [ $# -gt 0 ]; do
  case "$1" in
    --ios-only) IOS=1; ANDROID=0 ;;
    --android-only) ANDROID=1; IOS=0 ;;
    --release) RELEASE=1 ;;
    --track) shift; TRACK="${1:-}" ;;
    --skip-tests) SKIP_TESTS=1 ;;
    --test) RUN_TESTS=1 ;;
    --dry-run) DRY_RUN=1 ;;
    --no-wait) NO_WAIT=1 ;;
    --yes|-y) ASSUME_YES=1 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "✗ Argumen tidak dikenali: $1"; echo; usage; exit 1 ;;
  esac
  shift
done

if [ -z "$TRACK" ]; then
  if [ "$RELEASE" -eq 1 ]; then TRACK="production"; else TRACK="internal"; fi
fi

case "$TRACK" in
  internal|alpha|beta|production) ;;
  *) echo "✗ Track tidak sah: $TRACK (internal|alpha|beta|production)"; exit 1 ;;
esac

if [ "$IOS" -eq 0 ] && [ "$ANDROID" -eq 0 ]; then
  echo "✗ Tiada platform dipilih."
  exit 1
fi

export PATH="/opt/homebrew/opt/ruby/bin:$PATH"
export FASTLANE_SKIP_UPDATE_CHECK=1
export FASTLANE_HIDE_CHANGELOG=1
export FASTLANE_DISABLE_COLORS=1

step() { printf '\n→ %s\n' "$*"; }
ok() { printf '✓ %s\n' "$*"; }
fail() { printf '✗ %s\n' "$*"; exit 1; }
section() { printf '\n── %s ──\n' "$*"; }

section "Preflight"

[ "$(uname -s)" = "Darwin" ] || fail "Ship perlu macOS (iOS build)."

command -v flutter >/dev/null 2>&1 || fail "flutter tidak ditemui dalam PATH."
command -v ruby >/dev/null 2>&1 || fail "ruby tidak ditemui dalam PATH."
command -v bundle >/dev/null 2>&1 || fail "bundler tidak ditemui. Pasang: brew install ruby"
ruby -e 'exit(RUBY_VERSION.split(".").first.to_i >= 3 ? 0 : 1)' \
  || fail "Ruby $($(command -v ruby) -v 2>/dev/null) terlalu lama. Pastikan Homebrew Ruby di hadapan PATH."

if [ "$IOS" -eq 1 ]; then
  command -v xcodebuild >/dev/null 2>&1 || fail "xcodebuild tidak ditemui."
  [ -f "$FLUTTER_DIR/fastlane/asc_key.json" ] || fail "Tiada $FLUTTER_DIR/fastlane/asc_key.json"
fi
if [ "$ANDROID" -eq 1 ]; then
  [ -f "$FLUTTER_DIR/fastlane/play_service_account.json" ] \
    || fail "Tiada $FLUTTER_DIR/fastlane/play_service_account.json"
fi

AVAIL_KB="$(df -k "$ROOT" | awk 'NR==2 {print $4}')"
if [ -n "$AVAIL_KB" ] && [ "$AVAIL_KB" -lt 10485760 ]; then
  fail "Ruang disk kurang 10GB (${AVAIL_KB}KB). Kosongkan ~/Library/Developer/Xcode/DerivedData dan ~/.gradle/caches."
elif [ -n "$AVAIL_KB" ] && [ "$AVAIL_KB" -lt 20971520 ]; then
  printf '! Ruang disk %sGB — build mungkin perlukan lebih.\n' "$((AVAIL_KB / 1048576))"
fi

( cd "$FLUTTER_DIR" && bundle check >/dev/null 2>&1 ) \
  || fail "Gem fastlane belum lengkap. Jalankan: cd mywap_mobile && bundle install"

if ! git -C "$ROOT" diff --quiet 2>/dev/null || ! git -C "$ROOT" diff --cached --quiet 2>/dev/null; then
  printf '! Ada perubahan git belum commit; itu OK, cuma ambil perhatian.\n'
fi

LOCK_DIR="${TMPDIR:-/tmp}/mywap-ship.lock"
if ! mkdir "$LOCK_DIR" 2>/dev/null; then
  fail "Ship lain sedang berjalan (lock: $LOCK_DIR). Buang direktori itu jika tiada proses lain."
fi
trap 'rmdir "$LOCK_DIR" 2>/dev/null || true' EXIT INT TERM

mkdir -p "$LOG_DIR"
LOG="$LOG_DIR/$(date +%Y%m%d-%H%M%S).log"
exec > >(tee -a "$LOG") 2>&1
printf 'Log: %s\n' "$LOG"

section "Versi"

if [ "$DRY_RUN" -eq 1 ]; then
  step "Dry-run (tiada perubahan ditulis)"
  if [ "$RELEASE" -eq 1 ] && [ "$IOS" -eq 1 ]; then
    if ! RELEASE_ENV="$(cd "$FLUTTER_DIR" && ruby tool/version.rb next-release --env)"; then
      fail "Release tidak boleh diteruskan. Semak: ruby tool/version.rb status"
    fi
    eval "$RELEASE_ENV"
  fi
  (cd "$FLUTTER_DIR" && ruby tool/version.rb bump-build --env)
  echo
  echo "Sasaran: iOS=$([ "$IOS" -eq 1 ] && echo "ya" || echo "tidak"), Android=$([ "$ANDROID" -eq 1 ] && echo "ya" || echo "tidak"), track=$TRACK, release=$RELEASE"
  echo "Tiada build/upload dijalankan (--dry-run)."
  exit 0
fi

CURRENT_MARKETING="$(sed -n 's/^version:[[:space:]]*//p' "$FLUTTER_DIR/pubspec.yaml" | head -1)"
CURRENT_MARKETING="${CURRENT_MARKETING%%+*}"
PLANNED_MARKETING="$CURRENT_MARKETING"

if [ "$RELEASE" -eq 1 ] && [ "$IOS" -eq 1 ]; then
  step "Semak version App Store (next-release)"
  if ! RELEASE_ENV="$(cd "$FLUTTER_DIR" && ruby tool/version.rb next-release --env)"; then
    fail "Release tidak boleh diteruskan. Semak: ruby tool/version.rb status"
  fi
  eval "$RELEASE_ENV"
  PLANNED_MARKETING="$MARKETING"
fi

if ! PLAN="$(cd "$FLUTTER_DIR" && ruby tool/version.rb bump-build --env)"; then
  fail "Gagal tentukan build number. Betulkan rangkaian/kunci, kemudian cuba lagi."
fi
eval "$PLAN"
PLANNED_VERSION="${PLANNED_MARKETING}+${NEXT_BUILD}"
printf 'Versi akan dibina: %s\n' "$PLANNED_VERSION"

if [ "$RELEASE" -eq 1 ] && [ "$ASSUME_YES" -eq 0 ]; then
  printf '\nHantar %s ke App Store review + Play %s? [y/N] ' "$PLANNED_VERSION" "$TRACK"
  ans=""
  read -r ans < /dev/tty || true
  case "$ans" in
    y|Y|ya|YA) ;;
    *) echo "Dibatalkan; pubspec tidak diubah."; exit 0 ;;
  esac
fi

if [ "$RELEASE" -eq 1 ] && [ "$IOS" -eq 1 ]; then
  if ! RELEASE_ENV="$(cd "$FLUTTER_DIR" && ruby tool/version.rb next-release --write --env)"; then
    fail "Release tidak boleh diteruskan. Semak: ruby tool/version.rb status"
  fi
  eval "$RELEASE_ENV"
fi

B=""
if B="$(cd "$FLUTTER_DIR" && ruby tool/version.rb bump-build --write --env)"; then
  eval "$B"
else
  fail "Gagal bump build. Betulkan rangkaian/kunci, kemudian cuba lagi."
fi
VERSION="${MARKETING}+${BUILD}"
printf 'Versi dibina: %s\n' "$VERSION"

section "Ujian"

if [ "$SKIP_TESTS" -eq 0 ]; then
  step "flutter analyze"
  ( cd "$FLUTTER_DIR" && flutter analyze ) || fail "flutter analyze gagal."
fi
if [ "$RUN_TESTS" -eq 1 ]; then
  step "flutter test"
  ( cd "$FLUTTER_DIR" && flutter test ) || fail "flutter test gagal."
fi
if [ "$SKIP_TESTS" -eq 1 ] && [ "$RUN_TESTS" -eq 0 ]; then
  echo "Dilangkau (--skip-tests)."
fi

section "Build"

if [ "$IOS" -eq 1 ]; then
  step "Build iOS IPA"
  "$ROOT/build_ios_release.sh" --no-bump || fail "Build iOS gagal."
  [ -f "$IPA" ] || fail "IPA tidak dijumpai: $IPA"
  ok "IPA: $IPA"
fi

if [ "$ANDROID" -eq 1 ]; then
  step "Build Android AAB"
  "$ROOT/build_android_release.sh" --no-bump || fail "Build Android gagal."
  [ -f "$AAB" ] || fail "AAB tidak dijumpai: $AAB"
  ok "AAB: $AAB"
fi

section "Upload"

if [ "$IOS" -eq 1 ]; then
  if [ "$RELEASE" -eq 1 ]; then
    step "App Store review (manual release selepas lulus)"
    ( cd "$FLUTTER_DIR" && bundle exec fastlane ios submit_review ) || fail "Upload/submit iOS gagal."
  else
    step "TestFlight"
    ( cd "$FLUTTER_DIR" && bundle exec fastlane ios beta ) || fail "Upload iOS gagal."
  fi
  ok "iOS dihantar ($VERSION)"
fi

if [ "$ANDROID" -eq 1 ]; then
  step "Play Store track: $TRACK"
  ( cd "$FLUTTER_DIR" && PLAY_TRACK="$TRACK" bundle exec fastlane android beta ) || fail "Upload Android gagal."
  ok "Android dihantar ($VERSION, track $TRACK)"
fi

if [ "$NO_WAIT" -eq 0 ]; then
  section "Pengesahan"
  VERIFY_ARGS=(--build "$BUILD" --minutes 20)
  [ "$IOS" -eq 0 ] && VERIFY_ARGS+=(--android-only)
  [ "$ANDROID" -eq 0 ] && VERIFY_ARGS+=(--ios-only)
  if ! ( cd "$FLUTTER_DIR" && ruby tool/version.rb verify "${VERIFY_ARGS[@]}" ); then
    printf '! Build dihantar tetapi belum disahkan. Semak App Store Connect / Play Console, atau jalankan: ruby mywap_mobile/tool/version.rb verify --build %s\n' "$BUILD"
    exit 1
  fi
fi

section "Ringkasan"
printf 'Versi    %s\n' "$VERSION"
if [ "$IOS" -eq 1 ]; then
  if [ "$RELEASE" -eq 1 ]; then
    printf 'iOS      App Store review (release manual selepas lulus)\n'
  else
    printf 'iOS      TestFlight\n'
  fi
fi
if [ "$ANDROID" -eq 1 ]; then
  printf 'Android  Play %s\n' "$TRACK"
fi
printf 'Log      %s\n' "$LOG"
ok "Selesai."

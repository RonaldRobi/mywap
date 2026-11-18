#!/bin/bash
set -e

# myWAP — build IPA App Store (TestFlight/Transporter) untuk team Cyberocket.
#
# Guna: ./build_ios_release.sh [--no-bump]
#   --no-bump  Jangan naikkan build number (pubspec sudah disediakan oleh ship.sh).
#
# Nota signing: team ini tiada device berdaftar, jadi automatic signing untuk
# `archive` gagal (Xcode minta development profile). Strategi:
#   1. archive TANPA signing,
#   2. export dengan -allowProvisioningUpdates (Xcode cipta profile App Store),
#   3. re-sign app dengan entitlements dari profile (kekalkan aps-environment).
# Hasil: IPA sah untuk App Store Connect / Transporter.

API_BASE_URL="${API_BASE_URL:-https://mywap.my}"
TEAM_ID="BW4B5LCN9S"
BUNDLE_ID="com.mywap.mywapMobile"
SIGN_IDENTITY="Apple Distribution: Cyberocket Tech ($TEAM_ID)"

NO_BUMP=0
for arg in "$@"; do
  case "$arg" in
    --no-bump) NO_BUMP=1 ;;
    -h|--help)
      echo "Penggunaan: ./build_ios_release.sh [--no-bump]"
      echo "  --no-bump  Jangan naikkan build number (guna pubspec semasa)."
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
IOS_DIR="$FLUTTER_DIR/ios"
ARCHIVE_PATH="$FLUTTER_DIR/build/ios/Runner.xcarchive"
EXPORT_DIR="$FLUTTER_DIR/build/ios/ipa"
EXPORT_OPTIONS="$IOS_DIR/ExportOptions.plist"
IPA="$EXPORT_DIR/myWAP.ipa"

export PATH="/opt/homebrew/opt/ruby/bin:$PATH"

if ! command -v flutter >/dev/null 2>&1; then
  echo "✗ flutter tidak ditemui dalam PATH."
  exit 1
fi

if ! command -v xcodebuild >/dev/null 2>&1; then
  echo "✗ xcodebuild tidak ditemui dalam PATH."
  exit 1
fi

XCODE_VERSION="$(xcodebuild -version | awk 'NR == 1 {print $2}')"
XCODE_MAJOR="${XCODE_VERSION%%.*}"
if [ "$(date +%Y%m%d)" -ge 20270401 ] && [ "$XCODE_MAJOR" -lt 27 ]; then
  echo "✗ Apple memerlukan iOS 27 SDK dari Xcode 27 atau lebih baharu."
  echo "  Xcode aktif: $XCODE_VERSION. Kemas kini Xcode sebelum membina IPA baharu."
  exit 1
fi
if [ "$XCODE_MAJOR" -lt 27 ]; then
  echo "! Xcode $XCODE_VERSION menggunakan iOS SDK sebelum 27. Apple akan mewajibkan Xcode 27 mulai April 2027."
fi

if [ "$NO_BUMP" -eq 0 ]; then
  echo "→ Semak build number (App Store Connect + Play)..."
  (cd "$FLUTTER_DIR" && ruby tool/version.rb bump-build --write) || {
    echo "✗ Gagal bump build number. Betulkan rangkaian/kunci, atau guna --no-bump jika pubspec memang sudah betul."
    exit 1
  }
fi

PUB_VERSION="$(sed -n 's/^version:[[:space:]]*//p' "$FLUTTER_DIR/pubspec.yaml" | head -1)"
echo "→ Versi: $PUB_VERSION"

if ! security find-identity -v -p codesigning | grep -q "$SIGN_IDENTITY"; then
  echo "✗ Tiada cert '$SIGN_IDENTITY' dalam keychain."
  echo "  Xcode → Settings → Accounts → team Cyberocket → Manage Certificates → + → Apple Distribution."
  exit 1
fi

echo "→ flutter pub get..."
cd "$FLUTTER_DIR"
flutter pub get

# Kod generated (*.g.dart / *.freezed.dart) di-ignore oleh git, jadi ia mesti
# dijana semula di sini. Jika tidak, build boleh guna kod generated yang lapuk
# dan pembetulan dalam fail sumber tidak masuk ke dalam app.
echo "→ build_runner (jana semula kod generated)..."
dart run build_runner build --delete-conflicting-outputs

echo "→ pod install..."
cd "$IOS_DIR"
pod install

echo "→ Build Flutter (release, no codesign) API_BASE_URL=$API_BASE_URL"
cd "$FLUTTER_DIR"
flutter build ios --release --no-codesign --dart-define=API_BASE_URL="$API_BASE_URL"

echo "→ Archive tanpa signing..."
rm -rf "$ARCHIVE_PATH"
cd "$IOS_DIR"
xcodebuild -workspace Runner.xcworkspace \
  -scheme Runner \
  -configuration Release \
  -destination 'generic/platform=iOS' \
  -archivePath "$ARCHIVE_PATH" \
  CODE_SIGNING_ALLOWED=NO \
  CODE_SIGNING_REQUIRED=NO \
  CODE_SIGN_IDENTITY="" \
  PROVISIONING_PROFILE_SPECIFIER="" \
  archive

echo "→ Export IPA (cipta/guna profile App Store)..."
rm -rf "$EXPORT_DIR"
xcodebuild -exportArchive \
  -archivePath "$ARCHIVE_PATH" \
  -exportPath "$EXPORT_DIR" \
  -exportOptionsPlist "$EXPORT_OPTIONS" \
  -allowProvisioningUpdates

echo "→ Re-sign dengan entitlements profile..."
TMP="$(mktemp -d)"
unzip -q "$IPA" -d "$TMP"
security cms -D -i "$TMP/Payload/Runner.app/embedded.mobileprovision" > "$TMP/profile.plist"
plutil -extract Entitlements xml1 -o "$TMP/entitlements.plist" "$TMP/profile.plist"
codesign -f -s "$SIGN_IDENTITY" --entitlements "$TMP/entitlements.plist" "$TMP/Payload/Runner.app"
codesign --verify --deep --strict "$TMP/Payload/Runner.app"
rm -f "$IPA"
(cd "$TMP" && zip -qryX "$IPA" Payload)
rm -rf "$TMP"

echo "→ Sahkan versi dalam IPA..."
VERIFY_DIR="$(mktemp -d)"
unzip -q "$IPA" -d "$VERIFY_DIR"
IPA_VERSION="$(plutil -extract CFBundleShortVersionString raw "$VERIFY_DIR/Payload/Runner.app/Info.plist")"
IPA_BUILD="$(plutil -extract CFBundleVersion raw "$VERIFY_DIR/Payload/Runner.app/Info.plist")"
rm -rf "$VERIFY_DIR"

PUB_MARKETING="${PUB_VERSION%%+*}"
PUB_BUILD="${PUB_VERSION##*+}"
if [ "$IPA_VERSION" != "$PUB_MARKETING" ] || [ "$IPA_BUILD" != "$PUB_BUILD" ]; then
  echo "✗ IPA $IPA_VERSION ($IPA_BUILD) tidak sepadan dengan pubspec $PUB_MARKETING ($PUB_BUILD)."
  echo "  Jalankan semula tanpa --no-bump, atau betulkan pubspec."
  exit 1
fi

echo "✓ Siap. IPA $IPA_VERSION ($IPA_BUILD) untuk TestFlight/Transporter:"
ls -lah "$IPA"

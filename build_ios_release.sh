#!/bin/bash
set -e

# myWAP — build IPA App Store (TestFlight/Transporter) untuk team Cyberocket.
#
# Nota signing: team ini tiada device berdaftar, jadi automatic signing untuk
# `archive` gagal (Xcode minta development profile). Strategi:
#   1. archive TANPA signing,
#   2. export dengan -allowProvisioningUpdates (Xcode cipta profile App Store),
#   3. re-sign app dengan entitlements dari profile (kekalkan aps-environment).
# Hasil: IPA sah untuk App Store Connect / Transporter.

API_BASE_URL="https://mywap.my"
TEAM_ID="BW4B5LCN9S"
BUNDLE_ID="com.mywap.mywapMobile"
SIGN_IDENTITY="Apple Distribution: Cyberocket Tech ($TEAM_ID)"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
FLUTTER_DIR="$SCRIPT_DIR/mywap_mobile"
IOS_DIR="$FLUTTER_DIR/ios"
ARCHIVE_PATH="$FLUTTER_DIR/build/ios/Runner.xcarchive"
EXPORT_DIR="$FLUTTER_DIR/build/ios/ipa"
EXPORT_OPTIONS="$IOS_DIR/ExportOptions.plist"
IPA="$EXPORT_DIR/myWAP.ipa"

if ! command -v flutter >/dev/null 2>&1; then
  echo "✗ flutter tidak ditemui dalam PATH."
  exit 1
fi

if ! security find-identity -v -p codesigning | grep -q "$SIGN_IDENTITY"; then
  echo "✗ Tiada cert '$SIGN_IDENTITY' dalam keychain."
  echo "  Xcode → Settings → Accounts → team Cyberocket → Manage Certificates → + → Apple Distribution."
  exit 1
fi

echo "→ flutter pub get..."
cd "$FLUTTER_DIR"
flutter pub get

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

echo "✓ Siap. IPA untuk Transporter:"
ls -lah "$IPA"

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deep link (Universal Links iOS + App Links Android)
    |--------------------------------------------------------------------------
    |
    | Nilai ini disajikan secara AWAM dalam:
    |   - /.well-known/apple-app-site-association
    |   - /.well-known/assetlinks.json
    |
    | Ia bukan rahsia — OS peranti membacanya untuk mengesahkan pemilikan
    | domain. Nilai lalai di bawah adalah nilai produksi sebenar supaya
    | deep link berfungsi tanpa perlu mengubah .env. Boleh di-override
    | melalui .env jika perlu (cth. tukar keystore).
    |
    */

    // "<Team ID Apple>.<Bundle ID>" — Team ID dari Apple Developer.
    'ios_app_id' => env('IOS_APP_ID', 'BW4B5LCN9S.com.mywap.mywapMobile'),

    // Laluan yang dipautkan ke app (Universal Links).
    'ios_paths' => ['/events/*', '/kad/*', '/reset-password/*'],

    // Package Android (mesti sepadan applicationId dalam build.gradle.kts).
    'android_package_name' => env('ANDROID_PACKAGE_NAME', 'com.mywap.mywap_mobile'),

    // Cap jari SHA-256 keystore release (format AABB:CCDD:..., huruf besar).
    // Boleh ada lebih daripada satu (cth. upload key + app signing key).
    'android_sha256_fingerprints' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'ANDROID_SHA256_CERT_FINGERPRINT',
            '7C:17:27:F2:60:1A:56:AD:2C:9B:04:06:4B:EE:FA:8A:AD:BC:93:BC:6D:EE:D7:E3:B0:19:DF:03:32:CA:49:A4,1A:A1:78:91:4C:4A:CB:03:B8:68:5E:17:3C:0B:38:BF:E7:07:A9:43:18:60:94:A9:07:6A:0F:16:09:D7:A7:F9,B1:70:09:A9:5E:B2:44:5C:A9:0A:25:BF:E2:EA:06:BA:CE:15:78:62:5D:0E:F0:5A:BD:15:F2:E9:E3:ED:AD:6C'
        ))
    ))),

];

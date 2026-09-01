package com.mywap.mywap_mobile

import io.flutter.embedding.android.FlutterFragmentActivity

// FlutterFragmentActivity (bukan FlutterActivity) — diperlukan oleh local_auth
// untuk memaparkan prompt biometrik (fingerprint/face unlock) di Android.
class MainActivity : FlutterFragmentActivity()

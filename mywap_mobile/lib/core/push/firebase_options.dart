import 'package:firebase_core/firebase_core.dart';

/// Firebase config untuk myWAP. Firebase belum disediakan lagi — isi placeholder
/// di bawah dengan nilai sebenar daripada Firebase Console.
///
/// Cara dapatkan nilai:
/// 1. Buka Firebase Console → pilih project (atau cipta baru).
/// 2. Tambah app Android (package id seperti `com.mywap.app`) dan iOS.
/// 3. Muat turun `google-services.json` / `GoogleService-Info.plist`, kemudian
///    salin nilai berikut:
///    - apiKey            → `current_key`
///    - appId             → `mobilesdk_app_id`
///    - messagingSenderId → `project_number`
///    - projectId         → `project_id`
final FirebaseOptions kFirebaseOptions = FirebaseOptions(
  apiKey: '',
  appId: '',
  messagingSenderId: '',
  projectId: '',
);

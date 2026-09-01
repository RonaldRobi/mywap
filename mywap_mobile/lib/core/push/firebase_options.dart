import 'dart:io' show Platform;

import 'package:firebase_core/firebase_core.dart';

/// Firebase config untuk myWAP — project `mywap-f6b01`.
///
/// App didaftarkan di Firebase Console:
///   - Android: com.mywap.mywap_mobile  → 1:587545407330:android:d8946388a9812fe545063b
///   - iOS:     com.mywap.mywapMobile   → 1:587545407330:ios:19e26099610413a745063b
///
/// Inisialisasi eksplisit dengan [FirebaseOptions] (TANPA google-services.json /
/// GoogleService-Info.plist) supaya senang dikonfigurasi dan serasi dengan CI/test.
class DefaultFirebaseOptions {
  static FirebaseOptions get currentPlatform {
    if (Platform.isIOS) return ios;
    return android;
  }

  static const FirebaseOptions android = FirebaseOptions(
    apiKey: 'AIzaSyCZybraLFrbTgg6JR6zng3zshxPeIF0_bk',
    appId: '1:587545407330:android:d8946388a9812fe545063b',
    messagingSenderId: '587545407330',
    projectId: 'mywap-f6b01',
  );

  static const FirebaseOptions ios = FirebaseOptions(
    apiKey: 'AIzaSyAgnAama1gmcFVOyOXA-tbQnhfnR0sDuxo',
    appId: '1:587545407330:ios:19e26099610413a745063b',
    messagingSenderId: '587545407330',
    projectId: 'mywap-f6b01',
  );
}

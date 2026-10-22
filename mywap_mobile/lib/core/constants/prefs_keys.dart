/// Kunci SharedPreferences yang dikongsi merentas ciri.
library;

/// Ditanda `true` apabila tetamu melengkapkan onboarding. Ditetapkan semula
/// kepada `false` selepas log keluar supaya onboarding muncul lagi pada
/// pembukaan aplikasi berikutnya.
const String onboardingCompletedKey = 'onboarding_completed_v2';

/// Kod bahasa paparan aplikasi (`ms` atau `en`). Tiada nilai bermaksud
/// pengguna belum memilih bahasa lagi.
const String appLanguageKey = 'app_language';

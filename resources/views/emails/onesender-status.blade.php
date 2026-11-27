@if ($isTest)
Ini emel ujian pemantauan WhatsApp myWAP.
@else
Pemantauan myWAP mengesan masalah sambungan WhatsApp.
@endif

Status: {{ $status['state'] }}
Masa semakan: {{ $checkedAt }}
Butiran: {{ $status['reason'] }}

@if (! $isTest)
OTP dan pautan reset melalui WhatsApp mungkin tidak sampai sehingga sambungan pulih.

Semak status peranti dalam dashboard OneSender. Jika peranti offline, periksa sesi WhatsApp di Linked devices dan sambungkan semula jika diperlukan.
@endif

Tetapan myWAP: {{ $settingsUrl }}

Sistem menyemak setiap 5 minit dan menghantar satu amaran bagi setiap kejadian. Selepas status kembali online, sistem bersedia menghantar amaran untuk kejadian seterusnya.

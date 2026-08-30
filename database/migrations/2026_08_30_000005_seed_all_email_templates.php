<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('email_templates')->insertOrIgnore([
            [
                'key' => 'otp_login',
                'subject' => 'Kod Pengesahan Log Masuk myWAP',
                'body' => "Assalamualaikum {{name}},\n\nGunakan kod OTP di bawah untuk melengkapkan log masuk ke akaun myWAP anda. Kod ini sah untuk 5 minit.\n\nKod OTP: {{code}}\n\nJika anda tidak meminta kod ini, sila abaikan emel ini.\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'otp_email_verify',
                'subject' => 'Kod Pengesahan Emel myWAP',
                'body' => "Assalamualaikum {{name}},\n\nGunakan kod OTP di bawah untuk mengesahkan alamat emel anda. Kod ini sah untuk 5 minit.\n\nKod OTP: {{code}}\n\nJika anda tidak meminta kod ini, sila abaikan emel ini.\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'registration_received',
                'subject' => 'Pendaftaran Diterima - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nPendaftaran anda sebagai ahli {{organization}} telah diterima.\n\nNo Ahli: {{member_no}}\nOrganisasi: {{organization}}\nCawangan: {{branch}}\nYuran: RM {{fee}}\n\nSila lengkapkan bayaran yuran untuk mengaktifkan akaun anda.\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'registration_activated',
                'subject' => 'Akaun Anda Telah Diaktifkan - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nTahniah! Akaun myWAP anda telah diaktifkan.\n\nNo Ahli: {{member_no}}\nOrganisasi: {{organization}}\n\nAnda kini boleh log masuk kali pertama menggunakan No IC dan kod OTP yang akan dihantar ke emel anda.\n\n{{login_link}}\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'new_member_alert',
                'subject' => 'Ahli Baru Mendaftar - {{name}}',
                'body' => "Assalamualaikum Admin,\n\nSeorang ahli baru telah mendaftar dan membuat bayaran:\n\nNama: {{name}}\nNo Ahli: {{member_no}}\nNo IC: {{ic_number}}\nOrganisasi: {{organization}}\nCawangan: {{branch}}\nYuran: RM {{fee}}\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'registration_confirmation',
                'subject' => 'Pengesahan Pendaftaran: {{event_title}}',
                'body' => "Assalamualaikum {{name}},\n\nPendaftaran anda telah diterima. Maklumat pendaftaran anda:\n\nNo Pendaftaran: {{registration_no}}\nProgram: {{event_title}}\nTarikh: {{event_date}}\nLokasi: {{location}}\nStatus Bayaran: {{payment_status}}\n\nSila simpan No Pendaftaran anda untuk semakan kehadiran pada hari program.\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'form_invitation',
                'subject' => 'Jemputan Borang: {{form_title}}',
                'body' => "Assalamualaikum {{name}},\n\nAnda dijemput untuk mengisi borang berikut:\n\nBorang: {{form_title}}\nOrganisasi: {{organization}}\n\nSila klik pautan di bawah untuk membuka borang:\n{{form_link}}\n\nSalam,\nmyWAP",
            ],
            [
                'key' => 'password_reset',
                'subject' => 'Tetapkan Semula Kata Laluan - myWAP',
                'body' => "Assalamualaikum {{name}},\n\nKami menerima permintaan untuk menetapkan semula kata laluan akaun myWAP anda.\n\nSila klik pautan di bawah untuk memilih kata laluan baharu:\n{{url}}\n\nPautan ini sah untuk 60 minit. Jika anda tidak meminta ini, sila abaikan emel ini.\n\nSalam,\nmyWAP",
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('email_templates')->whereIn('key', [
            'otp_login',
            'otp_email_verify',
            'registration_received',
            'registration_activated',
            'new_member_alert',
            'registration_confirmation',
            'form_invitation',
            'password_reset',
        ])->delete();
    }
};

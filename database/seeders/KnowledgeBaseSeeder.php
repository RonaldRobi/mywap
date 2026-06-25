<?php

namespace Database\Seeders;

use App\Models\KnowledgeArticle;
use Illuminate\Database\Seeder;

class KnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            // ── Pendaftaran ──────────────────────────────────────────────
            [
                'question' => 'Apa itu myWAP?',
                'answer' => 'myWAP (MyMarhalah Welfare Application Platform) adalah platform pengurusan organisasi untuk pertubuhan Islam di Malaysia. Ia menyediakan sistem pengurusan ahli, yuran, program, usrah, kewangan, infaq, fasiliti, dan banyak lagi dalam satu aplikasi.',
                'keywords' => 'myWAP, MyMarhalah, platform, organisasi, pengurusan',
                'category' => 'Pendaftaran',
            ],
            [
                'question' => 'Macam mana nak daftar sebagai ahli?',
                'answer' => 'Untuk mendaftar sebagai ahli, pergi ke halaman login dan klik pautan "Daftar". Isi maklumat peribadi seperti nama, email, nombor IC, nombor telefon, dan kata laluan. Selepas mendaftar, anda perlu melengkapkan profil sebelum boleh menggunakan aplikasi sepenuhnya.',
                'keywords' => 'daftar, register, pendaftaran, ahli baru, sign up',
                'category' => 'Pendaftaran',
            ],
            [
                'question' => 'Saya lupa kata laluan, macam mana?',
                'answer' => 'Pada halaman login, klik pautan "Lupa Kata Laluan". Masukkan alamat emel yang didaftarkan. Sistem akan menghantar pautan reset kata laluan ke emel anda. Ikut arahan dalam emel untuk menetapkan semula kata laluan.',
                'keywords' => 'lupa password, reset, kata laluan, forgot password, login',
                'category' => 'Pendaftaran',
            ],
            [
                'question' => 'Macam mana nak update profil?',
                'answer' => 'Login ke dashboard myWAP. Klik pada ikon profil di penjuru kanan atas, pilih "My Profile" atau navigasi ke menu "Profile". Di sana anda boleh mengemaskini maklumat peribadi seperti nama, nombor telefon, alamat, pendidikan, pekerjaan, dan lain-lain.',
                'keywords' => 'update profil, edit profil, kemaskini, maklumat peribadi, profile',
                'category' => 'Pendaftaran',
            ],

            // ── Yuran ────────────────────────────────────────────────────
            [
                'question' => 'Berapa yuran ahli myWAP?',
                'answer' => 'Yuran ahli myWAP adalah sebanyak RM10 sebulan. Bayaran boleh dibuat secara online melalui dashboard myWAP menggunakan kad debit/kredit atau secara manual kepada bendahari organisasi masing-masing.',
                'keywords' => 'yuran, bayaran, fee, RM10, bulanan',
                'category' => 'Yuran',
            ],
            [
                'question' => 'Macam mana nak bayar yuran secara online?',
                'answer' => 'Login ke dashboard myWAP, pergi ke menu "Yuran & Bayaran". Sistem akan memaparkan status yuran anda. Klik butang "Bayar" untuk membuat pembayaran secara online melalui sistem pembayaran yang disediakan. Anda akan menerima resit setelah pembayaran berjaya.',
                'keywords' => 'bayar yuran, payment, online, bayaran yuran, resit',
                'category' => 'Yuran',
            ],
            [
                'question' => 'Boleh cetak resit yuran?',
                'answer' => 'Ya, boleh. Selepas membuat pembayaran, anda boleh memuat turun resit dalam format PDF melalui menu "Yuran & Bayaran". Klik pada butang resit atau cetak untuk mendapatkan salinan resit pembayaran.',
                'keywords' => 'resit, cetak resit, receipt, download resit, PDF',
                'category' => 'Yuran',
            ],
            [
                'question' => 'Apa jadi kalau tak bayar yuran?',
                'answer' => 'Yuran ahli perlu dibayar setiap bulan untuk mengekalkan keahlian aktif. Jika yuran tidak dibayar, status keahlian mungkin akan ditandakan sebagai tidak aktif. Sila hubungi pentadbir organisasi atau bendahari untuk sebarang pertanyaan mengenai tunggakan yuran.',
                'keywords' => 'tunggakan, tidak bayar, lewat bayar, penalty, status ahli',
                'category' => 'Yuran',
            ],

            // ── Event ────────────────────────────────────────────────────
            [
                'question' => 'Macam mana nak daftar program/event?',
                'answer' => 'Pergi ke menu "Program" di dashboard myWAP. Anda akan melihat senarai program yang akan datang. Klik pada program yang diminati untuk melihat butiran, kemudian klik butang "RSVP" atau "Hadir" untuk mendaftar kehadiran. Anda juga boleh melihat sejarah program yang pernah dihadiri.',
                'keywords' => 'program, event, daftar event, RSVP, hadir program, acara',
                'category' => 'Event',
            ],
            [
                'question' => 'Macam mana sistem kehadiran program?',
                'answer' => 'Setiap program mempunyai kod QR unik yang boleh diimbas untuk merekod kehadiran. Semasa program, fasilitator akan menyediakan kod QR. Ahli perlu mengimbas kod QR tersebut menggunakan telefon masing-masing untuk menandakan kehadiran. Sistem akan merekod masa kehadiran secara automatik.',
                'keywords' => 'kehadiran, attendance, QR code, scan, hadir',
                'category' => 'Event',
            ],
            [
                'question' => 'Boleh batalkan pendaftaran program?',
                'answer' => 'Ya, anda boleh membatalkan pendaftaran program melalui halaman butiran program. Sila batalkan lebih awal supaya slot dapat diberikan kepada ahli lain. Jika ada sebarang masalah, hubungi penganjur program.',
                'keywords' => 'batal, cancel, withdraw, batalkan program, tidak hadir',
                'category' => 'Event',
            ],

            // ── Usrah ────────────────────────────────────────────────────
            [
                'question' => 'Apa itu Usrah?',
                'answer' => 'Usrah adalah kumpulan pembelajaran kecil yang menjadi sebahagian daripada program pembangunan anggota organisasi. Setiap ahli akan ditempatkan dalam kumpulan usrah yang dikendalikan oleh seorang naqib atau fasilitator. Usrah biasanya diadakan secara mingguan atau bulanan.',
                'keywords' => 'usrah, kumpulan, naqib, fasilitator, liqa, tarbiyah',
                'category' => 'Pendaftaran',
            ],
            [
                'question' => 'Macam mana tahu kumpulan usrah saya?',
                'answer' => 'Login ke dashboard myWAP dan pergi ke menu "Usrah". Di sana anda akan melihat maklumat kumpulan usrah anda, nama naqib, senarai ahli kumpulan, dan jadual perjumpaan. Jika anda tidak ditempatkan dalam mana-mana kumpulan, sila hubungi admin.',
                'keywords' => 'kumpulan usrah, group, naqib, ahli kumpulan, jadual usrah',
                'category' => 'Pendaftaran',
            ],
            [
                'question' => 'Macam mana rekod kehadiran usrah?',
                'answer' => 'Semasa sesi usrah, naqib atau ahli boleh merekod kehadiran melalui sistem myWAP. Buka menu "Usrah" dan klik butang "Rekod Kehadiran". Anda juga boleh melihat sejarah kehadiran usrah yang lepas.',
                'keywords' => 'kehadiran usrah, rekod, attendance usrah, log usrah',
                'category' => 'Pendaftaran',
            ],

            // ── Polisi ───────────────────────────────────────────────────
            [
                'question' => 'Adakah data peribadi saya selamat?',
                'answer' => 'Ya, myWAP mengambil serius tentang keselamatan data peribadi ahli. Semua data disimpan dalam pelayan yang selamat dan hanya boleh diakses oleh pihak yang berkenaan. Sistem menggunakan teknologi enkripsi untuk melindungi data sensitif. Rujuk Dasar Privasi untuk maklumat lanjut.',
                'keywords' => 'privasi, data, keselamatan, security, encryption, GDPR',
                'category' => 'Polisi',
            ],
            [
                'question' => 'Boleh tukar organisasi?',
                'answer' => 'Ya, anda boleh bertukar organisasi. Sila hubungi admin atau pentadbir sistem myWAP untuk memproses pertukaran organisasi. Proses ini akan mengemaskini keahlian dan organisasi anda dalam sistem.',
                'keywords' => 'tukar organisasi, pindah, change organization, move',
                'category' => 'Polisi',
            ],
            [
                'question' => 'Macam mana dapatkan kad ahli?',
                'answer' => 'Kad ahli digital boleh didapati melalui menu "Kad Ahli" di dashboard myWAP. Kad tersebut mengandungi maklumat asas ahli dan nombor keahlian. Anda boleh memaparkan kad ini kepada pegawai organisasi apabila diperlukan. Kad juga boleh dikongsikan atau dicetak.',
                'keywords' => 'kad ahli, member card, ID kad, digital card, keahlian',
                'category' => 'Pendaftaran',
            ],

            // ── Teknikal ─────────────────────────────────────────────────
            [
                'question' => 'Macam mana nak booking fasiliti?',
                'answer' => 'Pergi ke menu "Facilities" atau "Tempahan Ruang" di dashboard. Pilih fasiliti yang ingin ditempah (contoh: bilik mesyuarat, dewan). Sistem akan menunjukkan jadual ketersediaan. Pilih tarikh dan masa, kemudian sahkan tempahan. Anda boleh melihat sejarah tempahan di menu "Sejarah Tempahan".',
                'keywords' => 'booking, tempahan, fasiliti, ruang, bilik, dewan, reserve',
                'category' => 'Teknikal',
            ],
            [
                'question' => 'Macam mana buat infaq/donasi?',
                'answer' => 'Pergi ke menu "Infaq" di dashboard myWAP. Anda akan melihat senarai kempen infaq yang aktif. Pilih kempen yang ingin disumbang, masukkan jumlah sumbangan, dan buat pembayaran secara online. Anda juga boleh melihat sejarah infaq yang telah dibuat.',
                'keywords' => 'infaq, donasi, sumbangan, derma, donation, charity',
                'category' => 'Teknikal',
            ],
            [
                'question' => 'Apa itu modul Ecommerce?',
                'answer' => 'Modul Ecommerce membolehkan organisasi menjual produk seperti buku, baju, atau barangan lain kepada ahli. Anda boleh melayari produk, membuat pesanan, dan membayar secara online melalui menu "Ecommerce" di dashboard. Admin organisasi boleh menguruskan produk, kategori, dan pesanan.',
                'keywords' => 'ecommerce, kedai, produk, beli, order, pesanan, jual',
                'category' => 'Teknikal',
            ],
            [
                'question' => 'Macam mana nak dapatkan notifikasi?',
                'answer' => 'myWAP akan menghantar notifikasi untuk pelbagai perkara seperti pengumuman organisasi, peringatan program, status bayaran yuran, dan banyak lagi. Notifikasi boleh dilihat melalui ikon loceng di penjuru kanan atas dashboard. Pastikan anda membenarkan notifikasi browser untuk pengalaman terbaik.',
                'keywords' => 'notifikasi, notification, alert, loceng, pemberitahuan',
                'category' => 'Teknikal',
            ],
            [
                'question' => 'Boleh akses myWAP dari telefon?',
                'answer' => 'Ya, myWAP adalah Progressive Web App (PWA) yang boleh diakses dari mana-mana browser telefon bimbit. Anda juga boleh memasang myWAP ke skrin utama telefon seperti aplikasi biasa dengan menekan butang "Install" atau "Tambah ke Skrin Utama" melalui browser.',
                'keywords' => 'mobile, telefon, PWA, install app, skrin utama, handphone',
                'category' => 'Teknikal',
            ],
            [
                'question' => 'Macam mana nak hubungi admin?',
                'answer' => 'Anda boleh menghubungi pentadbir sistem melalui emel atau telefon yang tertera di bahagian bawah halaman login atau tetapan. Untuk pertanyaan khusus organisasi, sila rujuk kepada pentadbir organisasi masing-masing melalui maklumat hubungan yang disediakan.',
                'keywords' => 'hubungi admin, contact, bantuan, help, support',
                'category' => 'Teknikal',
            ],

            // ── Lain-lain ────────────────────────────────────────────────
            [
                'question' => 'Apa itu Undian/Poll?',
                'answer' => 'Modul Undian membolehkan organisasi membuat tinjauan pendapat atau undian dalam kalangan ahli. Anda boleh menjawab undian yang dikeluarkan oleh organisasi dan melihat keputusan undian secara langsung. Ciri ini sering digunakan untuk mendapatkan maklum balas ahli tentang sesuatu isu atau keputusan.',
                'keywords' => 'undian, poll, survey, tinjauan, voting, pendapat',
                'category' => 'Lain-lain',
            ],
            [
                'question' => 'Macam mana dapatkan maklumat terkini organisasi?',
                'answer' => 'Pergi ke menu "Info Terkini" di dashboard myWAP. Di sini anda akan melihat pengumuman, berita, dan artikel terkini dari organisasi. Anda juga boleh memberi reaksi dan komen pada setiap artikel yang diterbitkan.',
                'keywords' => 'info terkini, berita, news, pengumuman, announcement, artikel',
                'category' => 'Lain-lain',
            ],
            [
                'question' => 'Apa itu Directory?',
                'answer' => 'Directory adalah direktori ahli myWAP yang membolehkan anda mencari dan melihat maklumat asas ahli lain dalam organisasi. Anda boleh mencari ahli melalui nama, cawangan, atau topik kepakaran. Hanya ahli yang memilih untuk dipaparkan secara awam akan muncul dalam direktori.',
                'keywords' => 'direktori, directory, ahli, cari ahli, senarai ahli, anggota',
                'category' => 'Lain-lain',
            ],
            [
                'question' => 'Boleh guna myWAP tanpa internet?',
                'answer' => 'myWAP memerlukan sambungan internet untuk kebanyakan fungsi. Walau bagaimanapun, terdapat beberapa halaman yang telah di-cache dan boleh diakses secara offline melalui ciri PWA (Progressive Web App). Untuk pengalaman terbaik, pastikan anda mempunyai sambungan internet yang stabil.',
                'keywords' => 'offline, tanpa internet, offline mode, cache, PWA',
                'category' => 'Lain-lain',
            ],
        ];

        foreach ($articles as $article) {
            KnowledgeArticle::create($article);
        }

        $this->command->info('✓ ' . count($articles) . ' knowledge articles seeded.');
    }
}

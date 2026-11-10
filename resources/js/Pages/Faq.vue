<script setup>
import { Head, Link } from '@inertiajs/vue3';

const faqs = [
    {
        q: 'Apa itu myWAP?',
        a: 'myWAP ialah platform rasmi ahli PKPIM, ABIM dan WADAH untuk kad ahli, status yuran, program, usrah, kempen infaq dan maklumat organisasi.',
    },
    {
        q: 'Bagaimana saya mendaftar sebagai ahli baharu?',
        a: 'Tekan Daftar Ahli Baru, isi nama, emel, nombor kad pengenalan, nombor telefon dan tarikh lahir. Selepas pendaftaran berjaya, log masuk kali pertama menggunakan nombor kad pengenalan untuk menetapkan kata laluan.',
    },
    {
        q: 'Saya ahli sedia ada tetapi belum pernah log masuk. Apa perlu saya buat?',
        a: 'Tekan Aktifkan Akaun Ahli, masukkan nombor kad pengenalan anda dan ikut langkah untuk menerima kod OTP melalui emel berdaftar. Masukkan kod 6 digit itu dan tetapkan kata laluan baharu. Jika tiada emel berdaftar, sistem akan meminta anda menambah emel.',
    },
    {
        q: 'Saya terlupa kata laluan atau nombor ahli. Apa perlu saya buat?',
        a: 'Pada skrin log masuk, tekan Lupa Kata Laluan / ID Ahli untuk tetapkan semula kata laluan atau mendapatkan semula nombor keahlian anda.',
    },
    {
        q: 'Bolehkah saya menggunakan myWAP tanpa log masuk?',
        a: 'Boleh. Sebagai tetamu, anda boleh melihat berita, artikel, video, program, kempen infaq dan maklumat organisasi. Kad ahli, status yuran, pendaftaran program dan usrah memerlukan log masuk.',
    },
    {
        q: 'Bagaimana saya membayar yuran dan menyumbang melalui infaq?',
        a: 'Selepas log masuk, buka Menu dan pilih Yuran & Bayaran untuk melihat status dan membuat bayaran yuran. Untuk infaq, buka kempen dan tekan Sumbang Sekarang. Laman kempen akan dibuka dalam pelayar supaya anda boleh menyumbang di sana.',
    },
    {
        q: 'Mengapa aplikasi meminta kebenaran notifikasi?',
        a: 'Notifikasi digunakan untuk memaklumkan anda tentang program, pengumuman dan peringatan penting. Anda boleh menukar kebenaran ini dalam tetapan peranti pada bila-bila masa.',
    },
];
</script>

<template>
    <Head title="Soalan Lazim" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-16 md:pb-0">
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center">
                    <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-8 w-auto" />
                </Link>
                <Link href="/" class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    Kembali ke Halaman Utama
                </Link>
            </div>
        </nav>

        <main class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
            <article class="rounded-3xl border border-gray-100 bg-white p-8 md:p-12 shadow-sm">
                <h1 class="text-3xl font-bold text-slate-900 mb-2">Soalan Lazim</h1>
                <p class="text-sm text-slate-500 mb-8">Jawapan ringkas tentang akaun, keahlian dan perkhidmatan myWAP.</p>

                <div class="space-y-3">
                    <details v-for="(faq, index) in faqs" :key="index" class="group rounded-2xl border border-slate-200 bg-slate-50/60">
                        <summary class="flex cursor-pointer items-center justify-between gap-3 px-5 py-4 text-sm font-semibold text-slate-900">
                            <span>{{ faq.q }}</span>
                            <span class="shrink-0 text-slate-400 transition group-open:rotate-180">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </summary>
                        <p class="px-5 pb-4 text-sm leading-relaxed text-slate-600">{{ faq.a }}</p>
                    </details>
                </div>

                <div class="mt-8 rounded-2xl border border-[#D5E3D8] bg-[#F4F6F1] p-5 text-center">
                    <p class="text-sm font-semibold text-slate-900">Masih ada soalan?</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:justify-center">
                        <Link :href="route('login')" class="rounded-2xl bg-[#2F6B32] px-5 py-2.5 text-sm font-semibold text-[#F4F6F1] transition hover:bg-[#123D2A]">
                            Log Masuk
                        </Link>
                        <Link :href="route('register')" class="rounded-2xl border border-[#123D2A] px-5 py-2.5 text-sm font-semibold text-[#123D2A] transition hover:bg-[#EAF1EA]">
                            Daftar Ahli Baru
                        </Link>
                    </div>
                </div>
            </article>
        </main>
    </div>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    infaq: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`Sumbangan Berjaya - ${infaq.title}`" />

    <div class="min-h-screen bg-slate-50 font-sans flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl border border-emerald-100 shadow-xl shadow-emerald-900/5 p-8 text-center space-y-6">
            <!-- Icon Success -->
            <div class="mx-auto w-24 h-24 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 mb-6 relative">
                <div class="absolute inset-0 rounded-full animate-ping bg-emerald-100 opacity-50"></div>
                <svg class="w-12 h-12 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <!-- Content -->
            <div>
                <h1 class="text-2xl font-black text-slate-900 mb-2">Terima Kasih!</h1>
                <p class="text-slate-600 text-sm leading-relaxed mb-6">
                    Sumbangan anda untuk kempen <span class="font-bold text-slate-800">"{{ infaq.title }}"</span> telah berjaya direkodkan. Semoga Allah SWT memberkati setiap rezeki yang disumbangkan.
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <Link 
                    :href="infaq.public_url" 
                    class="block w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold uppercase tracking-wide rounded-xl shadow-md shadow-emerald-600/20 transition hover:-translate-y-0.5"
                >
                    Kembali ke Maklumat Kempen
                </Link>
                
                <Link 
                    href="/#infaq" 
                    class="block w-full py-3.5 px-4 bg-white border-2 border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300 hover:bg-slate-50 text-sm font-bold uppercase tracking-wide rounded-xl transition"
                >
                    Lihat Kempen Lain
                </Link>
            </div>
        </div>
    </div>
</template>

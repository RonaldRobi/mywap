<script setup>
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    message: String,
});
</script>

<template>
    <Head :title="`Kehadiran Gagal: ${event.title}`" />

    <!-- Full-screen centered — no AppLayout (mobile PWA feel) -->
    <div class="min-h-screen bg-gradient-to-br from-red-50 via-white to-orange-50 flex items-center justify-center p-5">
        <div class="w-full max-w-sm">
            <div class="bg-white/80 backdrop-blur-md rounded-3xl shadow-xl border border-white/60 overflow-hidden">
                <div class="h-2 w-full" :style="{ backgroundColor: event.color_theme ?? '#ef4444' }"></div>

                <div class="p-8 text-center space-y-5">
                    <div class="mx-auto h-14 w-14 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008M20.25 12a8.25 8.25 0 11-16.5 0 8.25 8.25 0 0116.5 0z" />
                        </svg>
                    </div>

                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-red-500 mb-1">Kehadiran Tidak Dapat Disahkan</p>
                        <h1 class="text-xl font-extrabold text-gray-800">{{ event.title }}</h1>
                    </div>

                    <p class="text-sm text-gray-600 bg-gray-50 rounded-2xl p-4">{{ message }}</p>

                    <a
                        :href="route('dashboard')"
                        class="inline-block w-full rounded-2xl py-3.5 font-bold text-sm text-white shadow-lg transition-transform active:scale-95"
                        :style="{ backgroundColor: event.color_theme ?? '#ef4444' }"
                    >
                        Kembali ke Papan Pemuka
                    </a>
                </div>
            </div>
            <p class="text-center text-[11px] text-gray-300 mt-4">myWAP &middot; {{ event.organization_name }}</p>
        </div>
    </div>
</template>

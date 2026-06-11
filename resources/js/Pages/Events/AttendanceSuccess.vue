<script setup>
import { ref, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    event: Object,
    attendedAt: String, // ISO datetime string
    memberName: String,
});

// ─── Animated checkmark ──────────────────────────────────────────────────────
const showCheck = ref(false);
onMounted(() => { setTimeout(() => { showCheck.value = true; }, 100); });

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('ms-MY', {
        weekday: 'long', year: 'numeric', month: 'long',
        day: 'numeric', hour: '2-digit', minute: '2-digit',
    });
}
</script>

<template>
    <Head title="Kehadiran Disahkan" />

    <!-- Full-screen centered — intentionally no AppLayout (mobile PWA feel) -->
    <div class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-teal-50
                flex items-center justify-center p-5">

        <div class="w-full max-w-sm">

            <!-- Main success card -->
            <div class="bg-white/80 backdrop-blur-md rounded-3xl shadow-xl
                        border border-white/60 overflow-hidden">

                <!-- Colored header band -->
                <div
                    class="h-2 w-full"
                    :style="{ backgroundColor: event.organization?.color_theme ?? '#10b981' }"
                ></div>

                <div class="p-8 flex flex-col items-center text-center gap-5">

                    <!-- Animated SVG checkmark circle -->
                    <div
                        class="relative flex items-center justify-center
                               h-20 w-20 rounded-full bg-emerald-100
                               transition-all duration-700"
                        :class="showCheck ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
                    >
                        <!-- Outer ping ring -->
                        <span class="absolute inset-0 rounded-full bg-emerald-300/50 animate-ping"></span>
                        <!-- Checkmark -->
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            class="w-10 h-10 text-emerald-600"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="2.5"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>

                    <!-- Headline -->
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-600 mb-1">
                            Berjaya
                        </p>
                        <h1 class="text-2xl font-extrabold text-gray-800">Kehadiran Disahkan!</h1>
                        <p v-if="memberName" class="mt-1 text-gray-500 text-sm">
                            Selamat datang, <span class="font-semibold text-gray-700">{{ memberName }}</span>
                        </p>
                    </div>

                    <!-- Divider -->
                    <div class="w-12 h-0.5 bg-gray-100 rounded-full"></div>

                    <!-- Event details pill card -->
                    <div class="w-full rounded-2xl bg-gray-50 p-4 text-left space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-widest text-gray-400">Program</p>
                        <p class="font-bold text-gray-800 leading-snug">{{ event.title }}</p>

                        <div class="pt-1 space-y-1">
                            <p class="flex items-center gap-2 text-xs text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Masa Kemasukan: {{ formatDate(attendedAt) }}
                            </p>
                            <p v-if="event.location_or_link" class="flex items-center gap-2 text-xs text-gray-500">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ event.location_or_link }}
                            </p>
                        </div>
                    </div>

                    <!-- CTA: back to dashboard -->
                    <a
                        :href="route('dashboard')"
                        class="inline-flex items-center justify-center gap-2 w-full py-3.5 rounded-2xl
                               font-bold text-sm text-white shadow-lg shadow-emerald-200
                               transition-transform active:scale-95"
                        :style="{ backgroundColor: event.organization?.color_theme ?? '#10b981' }"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 21V12h6v9"/>
                        </svg>
                        Kembali ke Papan Pemuka
                    </a>

                    <p class="text-[11px] text-gray-300 text-center px-2">
                        Rekod kehadiran anda telah disimpan. Anda boleh tutup halaman ini.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-[11px] text-gray-300 mt-4">
                myWAP &middot; {{ event.organization?.name }}
            </p>
        </div>
    </div>
</template>

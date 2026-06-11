<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
    event: Object,
    qrSvg: String,
    attendanceUrl: String,
    attendedCount: Number,
});

// ─── Live counter polling ────────────────────────────────────────────────────
// Polls the current attendance count every 10 seconds so the presenter
// can see arrivals without manually refreshing.
const liveCount = ref(props.attendedCount ?? 0);
let pollInterval   = null;

async function fetchCount() {
    try {
        const res = await fetch(
            route('events.qr', { event: props.event.id }) + '?count=1',
            { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }
        );
        if (res.ok) {
            const data = await res.json();
            if (typeof data.attended_count === 'number') {
                liveCount.value = data.attended_count;
            }
        }
    } catch { /* silently ignore network blips */ }
}

onMounted (() => { pollInterval = setInterval(fetchCount, 10_000); });
onUnmounted(() => { clearInterval(pollInterval); });
</script>

<template>
    <Head :title="`QR Kehadiran – ${event.title}`" />

    <!-- Full-screen projector-mode layout (no AppLayout wrapper) -->
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 flex flex-col items-center justify-center p-8">

        <!-- Top bar: event info + back button (hidden when printing/presenting) -->
        <div class="w-full max-w-3xl flex items-center justify-between mb-8 print:hidden">
            <div>
                <span
                    class="inline-block text-xs font-bold uppercase tracking-widest px-3 py-1 rounded-full mb-2"
                    :style="{ backgroundColor: event.organization.color_theme + '22', color: event.organization.color_theme }"
                >
                    {{ event.organization.name }}
                </span>
                <h1 class="text-2xl font-extrabold text-gray-800 leading-tight">{{ event.title }}</h1>
                <p class="text-sm text-gray-400 mt-1">{{ event.start_formatted }}</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Print attendance sheet link -->
                <a
                    :href="route('events.print', { event: event.id })"
                    target="_blank"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200
                           text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Cetak Senarai
                </a>

                <!-- Back to events -->
                <a
                    :href="route('events.index')"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200
                           text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Kembali
                </a>
            </div>
        </div>

        <!-- QR Card -->
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-10 flex flex-col items-center gap-6 w-full max-w-md">

            <!-- Heading -->
            <div class="text-center">
                <p class="text-xs font-bold uppercase tracking-widest text-gray-400 mb-1">Pengesahan Kehadiran</p>
                <h2 class="text-xl font-extrabold text-gray-800">Sila Imbas Untuk Kehadiran</h2>
            </div>

            <!-- QR Code (server-rendered SVG) -->
            <div
                class="p-3 rounded-2xl border-2 border-gray-100"
                v-html="qrSvg"
            ></div>

            <!-- Attendance URL (for manual fallback) -->
            <p class="text-[11px] text-gray-300 font-mono break-all text-center max-w-xs">
                {{ attendanceUrl }}
            </p>

            <!-- Live attended counter -->
            <div class="flex flex-col items-center gap-0.5 bg-gray-50 rounded-2xl px-8 py-4 w-full">
                <span class="text-4xl font-black tabular-nums text-gray-800 tracking-tight">
                    {{ liveCount }}
                </span>
                <span class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Ahli Telah Hadir</span>
            </div>

            <!-- Pulse indicator: auto-refreshes every 10s -->
            <p class="flex items-center gap-1.5 text-[11px] text-gray-300">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                </span>
                Dikemaskini setiap 10 saat
            </p>
        </div>

    </div>
</template>

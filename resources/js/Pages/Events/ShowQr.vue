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
const showPrintOptions = ref(false);
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
                <!-- Print QR (A4 options) -->
                <button
                    type="button"
                    @click="showPrintOptions = true"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#123D2A]
                           text-sm font-semibold text-white hover:bg-[#2F6B32] transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5V5.25A2.25 2.25 0 019 3h6a2.25 2.25 0 012.25 2.25V7.5M6.75 16.5H5.25A2.25 2.25 0 013 14.25v-4.5A2.25 2.25 0 015.25 7.5h13.5A2.25 2.25 0 0121 9.75v4.5a2.25 2.25 0 01-2.25 2.25h-1.5M6.75 12.75h10.5v6.75a1.5 1.5 0 01-1.5 1.5H8.25a1.5 1.5 0 01-1.5-1.5v-6.75z"/>
                    </svg>
                    Cetak QR
                </button>

                <!-- Download QR (PNG) -->
                <a
                    :href="route('events.qr.download', { event: event.id })"
                    download
                    title="PNG latar belakang telus"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200
                           text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Muat Turun PNG
                </a>

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
                class="p-3 rounded-2xl border-2 border-gray-100 [&_svg]:block [&_svg]:mx-auto [&_svg]:h-auto [&_svg]:w-full [&_svg]:max-w-full"
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

        <!-- ── Print options ───────────────────────────────────────────────── -->
        <div
            v-if="showPrintOptions"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-[#071525]/55 p-4 backdrop-blur-sm"
            @click.self="showPrintOptions = false"
        >
            <div class="my-8 w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#2F6B32]">Cetak QR</p>
                        <h2 class="mt-1 text-xl font-black text-gray-900">Pilih format cetakan</h2>
                        <p class="mt-1 text-sm text-gray-500">Kedua-dua format menggunakan saiz A4 dan QR yang sama.</p>
                    </div>
                    <button type="button" @click="showPrintOptions = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <!-- Option 1 -->
                    <a
                        :href="route('events.qr.print', { event: event.id, layout: 'qr' })"
                        target="_blank"
                        rel="noopener"
                        @click="showPrintOptions = false"
                        class="group rounded-2xl border border-gray-200 p-4 transition-colors hover:border-[#2F6B32] hover:bg-[#F4F6F1]"
                    >
                        <div class="flex aspect-[210/297] items-center justify-center rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
                            <div class="w-full [&_svg]:block [&_svg]:h-auto [&_svg]:w-full" v-html="qrSvg"></div>
                        </div>
                        <p class="mt-3 text-sm font-bold text-gray-900">QR Sahaja</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500">
                            QR besar tanpa teks. Sesuai untuk tampal di kaunter atau pintu masuk.
                        </p>
                    </a>

                    <!-- Option 2 -->
                    <a
                        :href="route('events.qr.print', { event: event.id, layout: 'poster' })"
                        target="_blank"
                        rel="noopener"
                        @click="showPrintOptions = false"
                        class="group rounded-2xl border border-gray-200 p-4 transition-colors hover:border-[#2F6B32] hover:bg-[#F4F6F1]"
                    >
                        <div class="flex aspect-[4/5] flex-col overflow-hidden rounded-xl border border-gray-100 bg-[#F4F6F1] p-3 shadow-sm">
                            <div class="flex items-center justify-between gap-1">
                                <div class="flex items-center gap-1">
                                    <span class="h-1.5 w-1.5 bg-[#2F6B32]"></span>
                                    <span class="text-[6px] font-black tracking-tight text-[#071525]">myWAP</span>
                                </div>
                                <span class="h-2.5 w-5 border border-[#071525]/20 bg-white"></span>
                            </div>
                            <div class="mt-1.5 h-11 bg-[#123D2A]"></div>
                            <div class="mt-1.5 h-[2px] w-8 bg-[#2F6B32]"></div>
                            <div class="mt-1 h-2 w-4/5 bg-[#071525]/85"></div>
                            <div class="mt-0.5 h-2 w-3/5 bg-[#071525]/85"></div>
                            <div class="mt-2 grid grid-cols-3 gap-1">
                                <div class="h-2.5 bg-[#071525]/12"></div>
                                <div class="h-2.5 bg-[#071525]/12"></div>
                                <div class="h-2.5 bg-[#071525]/12"></div>
                            </div>
                            <div class="mt-auto flex items-center gap-2 bg-white p-1.5">
                                <div class="h-1.5 flex-1 bg-[#071525]/12"></div>
                                <div class="w-8 [&_svg]:block [&_svg]:h-auto [&_svg]:w-full" v-html="qrSvg"></div>
                            </div>
                        </div>
                        <p class="mt-3 text-sm font-bold text-gray-900">Poster 4:5</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500">
                            1080 × 1350 px. Logo myWAP, logo organisasi, gambar program, butiran dan QR — penuh tanpa terpotong.
                        </p>
                    </a>
                </div>

                <p class="mt-5 rounded-xl bg-[#F4F6F1] px-4 py-3 text-[11px] leading-relaxed text-gray-600">
                    Dalam dialog cetak: pilih saiz kertas <strong>4:5</strong> untuk poster (atau <strong>A4</strong>
                    dengan <strong>Fit to page</strong>), <strong>Margin: None</strong>, dan aktifkan
                    <strong>Background graphics</strong> supaya warna tema dicetak.
                </p>
            </div>
        </div>

    </div>
</template>

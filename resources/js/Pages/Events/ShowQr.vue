<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    qrSvg: String,
    attendanceUrl: String,
    attendedCount: { type: Number, default: 0 },
    recentAttendees: { type: Array, default: () => [] },
});

// ─── Live attendance panel ──────────────────────────────────────────────────
const liveCount = ref(props.attendedCount ?? 0);
const recent = ref(props.recentAttendees ?? []);
const flashIds = ref([]);
const showPrintOptions = ref(false);
const isFullscreen = ref(false);

let pollInterval = null;
let flashTimer = null;
let polling = false;

function initials(name) {
    return (name || '?')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0]?.toUpperCase() ?? '')
        .join('');
}

function methodLabel(method) {
    return {
        member: 'Ahli',
        walkin: 'Walk-in',
        guest: 'Tetamu',
        manual: 'Manual',
    }[method] ?? 'Hadir';
}

function methodClass(method) {
    return {
        member: 'bg-emerald-100 text-emerald-700',
        walkin: 'bg-amber-100 text-amber-700',
        guest: 'bg-sky-100 text-sky-700',
        manual: 'bg-gray-100 text-gray-600',
    }[method] ?? 'bg-gray-100 text-gray-600';
}

async function fetchLive() {
    if (polling) return;
    polling = true;

    try {
        const res = await fetch(
            route('events.qr', { event: props.event.id }) + '?live=1',
            { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } },
        );

        if (res.ok) {
            const data = await res.json();

            if (typeof data.attended_count === 'number') {
                liveCount.value = data.attended_count;
            }

            if (Array.isArray(data.recent)) {
                const previous = new Set(recent.value.map((row) => row.id));
                const fresh = data.recent.filter((row) => !previous.has(row.id));

                recent.value = data.recent;

                if (fresh.length) {
                    flashIds.value = fresh.map((row) => row.id);
                    clearTimeout(flashTimer);
                    flashTimer = setTimeout(() => { flashIds.value = []; }, 5000);
                }
            }
        }
    } catch {
        /* silently ignore network blips */
    } finally {
        polling = false;
    }
}

function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen?.()
            .then(() => { isFullscreen.value = true; })
            .catch(() => {});
    } else {
        document.exitFullscreen?.();
        isFullscreen.value = false;
    }
}

onMounted(() => {
    pollInterval = setInterval(fetchLive, 5000);
});

onUnmounted(() => {
    clearInterval(pollInterval);
    clearTimeout(flashTimer);
});
</script>

<template>
    <Head :title="`QR Semak Masuk – ${event.title}`" />

    <div class="flex min-h-screen flex-col bg-gradient-to-br from-gray-50 to-gray-100 lg:h-screen lg:overflow-hidden">
        <!-- Top bar -->
        <header class="z-10 flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-gray-200/70 bg-white/75 px-5 py-3 backdrop-blur print:hidden">
            <div class="min-w-0">
                <span
                    class="inline-block rounded-full px-3 py-0.5 text-[10px] font-bold uppercase tracking-widest"
                    :style="{ backgroundColor: (event.organization?.color_theme || '#334155') + '22', color: event.organization?.color_theme || '#334155' }"
                >
                    {{ event.organization?.name || 'myWAP' }}
                </span>
                <h1 class="mt-1 truncate text-lg font-black leading-tight text-gray-800 lg:text-xl">{{ event.title }}</h1>
                <p class="text-xs text-gray-400">{{ event.start_formatted }}</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    @click="toggleFullscreen"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="isFullscreen ? 'M9 9V4H4m11 0h5v5m0 11h-5v-5M4 15v5h5' : 'M4 9V4h5m11 0h-5v5m0 11v-5h5M4 15v5h5'" />
                    </svg>
                    {{ isFullscreen ? 'Keluar Penuh' : 'Skrin Penuh' }}
                </button>

                <button
                    type="button"
                    @click="showPrintOptions = true"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-[#123D2A] px-3 py-2 text-xs font-semibold text-white hover:bg-[#2F6B32]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 7.5V5.25A2.25 2.25 0 019 3h6a2.25 2.25 0 012.25 2.25V7.5M6.75 16.5H5.25A2.25 2.25 0 013 14.25v-4.5A2.25 2.25 0 015.25 7.5h13.5A2.25 2.25 0 0121 9.75v4.5a2.25 2.25 0 01-2.25 2.25h-1.5M6.75 12.75h10.5v6.75a1.5 1.5 0 01-1.5 1.5H8.25a1.5 1.5 0 01-1.5-1.5v-6.75z"/>
                    </svg>
                    Cetak QR
                </button>

                <a
                    :href="route('events.qr.download', { event: event.id })"
                    download
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Muat Turun
                </a>

                <a
                    :href="route('events.print', { event: event.id })"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Cetak Senarai
                </a>

                <a
                    :href="route('events.index')"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    Kembali
                </a>
            </div>
        </header>

        <!-- Two-pane projector layout: QR (left) + live attendance (right) -->
        <main class="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-2">
            <!-- LEFT: QR -->
            <section class="flex min-h-0 flex-col items-center justify-center border-b border-gray-200/70 p-6 text-center lg:border-b-0 lg:border-r lg:p-10">
                <p class="text-[11px] font-bold uppercase tracking-[0.25em] text-emerald-600">Pengesahan Kehadiran</p>
                <h2 class="mt-1 text-2xl font-black text-gray-800 lg:text-3xl">Sila Imbas Untuk Semak Masuk</h2>

                <div
                    class="mt-5 w-full rounded-3xl border-2 border-gray-100 bg-white p-4 shadow-xl [&_svg]:block [&_svg]:mx-auto [&_svg]:h-auto [&_svg]:w-full"
                    :style="{ maxWidth: 'min(56vh, 520px)' }"
                    v-html="qrSvg"
                ></div>

                <p class="mt-3 text-[11px] font-medium text-gray-500">
                    Buka kamera telefon → imbas QR → sahkan kehadiran anda.
                </p>
                <p class="mt-1 max-w-sm break-all text-[10px] font-mono text-gray-300">{{ attendanceUrl }}</p>
            </section>

            <!-- RIGHT: live attendance -->
            <section class="flex min-h-0 flex-col bg-white/60 p-5 lg:p-8">
                <div class="flex shrink-0 items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-gray-400">Pendaftaran Masuk</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-5xl font-black tabular-nums leading-none text-gray-900 lg:text-6xl">{{ liveCount }}</span>
                            <span class="text-sm font-semibold text-gray-400">peserta hadir</span>
                        </div>
                    </div>
                    <p class="flex items-center gap-1.5 text-[11px] font-medium text-gray-400">
                        <span class="relative flex h-2 w-2">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                        </span>
                        Langsung · setiap 5 saat
                    </p>
                </div>

                <div class="mt-4 min-h-0 flex-1 overflow-y-auto pr-1">
                    <TransitionGroup name="attendee" tag="div" class="space-y-2">
                        <div
                            v-for="row in recent"
                            :key="row.id"
                            class="flex items-center gap-3 rounded-2xl border px-3 py-2.5 transition-colors duration-500"
                            :class="flashIds.includes(row.id)
                                ? 'border-emerald-300 bg-emerald-50 ring-2 ring-emerald-200'
                                : 'border-gray-100 bg-white'"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-sm font-black text-emerald-700">
                                {{ initials(row.name) }}
                            </div>

                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-gray-800">{{ row.name }}</p>
                                <p class="truncate text-[11px] text-gray-400">
                                    <span v-if="row.member_no">No Ahli: {{ row.member_no }}</span>
                                    <span v-else-if="row.registration_no">No Daftar: {{ row.registration_no }}</span>
                                    <span v-else>Peserta</span>
                                </p>
                            </div>

                            <div class="shrink-0 text-right">
                                <p class="text-xs font-bold text-gray-700">{{ row.attended_at_label || '—' }}</p>
                                <span class="mt-0.5 inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold" :class="methodClass(row.method)">
                                    {{ methodLabel(row.method) }}
                                </span>
                            </div>
                        </div>
                    </TransitionGroup>

                    <div v-if="!recent.length" class="flex h-full flex-col items-center justify-center gap-2 py-10 text-center">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-300">
                            <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-400">Menunggu imbasan pertama…</p>
                        <p class="text-[11px] text-gray-300">Nama peserta akan muncul di sini selepas mereka mengimbas QR.</p>
                    </div>
                </div>
            </section>
        </main>

        <!-- ── Print options ─────────────────────────────────────────────── -->
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
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500">QR besar tanpa teks. Sesuai untuk tampal di kaunter atau pintu masuk.</p>
                    </a>

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
                        <p class="mt-0.5 text-xs leading-relaxed text-gray-500">1080 × 1350 px. Logo myWAP, logo organisasi, gambar program, butiran dan QR — penuh tanpa terpotong.</p>
                    </a>
                </div>

                <p class="mt-5 rounded-xl bg-[#F4F6F1] px-4 py-3 text-[11px] leading-relaxed text-gray-600">
                    Dalam dialog cetak: pilih saiz kertas <strong>4:5</strong> untuk poster (atau <strong>A4</strong> dengan <strong>Fit to page</strong>), <strong>Margin: None</strong>, dan aktifkan <strong>Background graphics</strong> supaya warna tema dicetak.
                </p>
            </div>
        </div>
    </div>
</template>

<style scoped>
.attendee-enter-active {
    transition: all 0.35s ease;
}
.attendee-enter-from {
    opacity: 0;
    transform: translateY(-8px);
}
.attendee-move {
    transition: transform 0.35s ease;
}
</style>

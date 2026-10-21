<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import MemberCardFlip from '@/Components/MemberCard/MemberCardFlip.vue';
import { CARD_H, CARD_W } from '@/Components/MemberCard/layout';

const props = defineProps({
    card: { type: Object, required: true },
    qrPrivate: { type: String, default: null },
    qrPublic: { type: String, default: null },
});

const flip = ref(null);
const downloading = ref(false);
const qrSvg = computed(() => props.qrPublic || props.qrPrivate);

async function toDataUri(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    const blob = await res.blob();
    return await new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

/** Render SVG depan kad → PNG (2× saiz design) dengan imej di-inline. */
async function renderFront(scale = 2) {
    const svgEl = document.querySelector('#membership-card-front svg');
    if (!svgEl) return null;
    const clone = svgEl.cloneNode(true);

    for (const img of clone.querySelectorAll('image')) {
        const href = img.getAttribute('href');
        if (href && !href.startsWith('data:')) {
            try { img.setAttribute('href', await toDataUri(href)); } catch { /* biar kosong */ }
        }
    }

    // Benamkan font supaya teks kekal Figtree dalam imej.
    const fontCss = [...document.styleSheets]
        .flatMap(s => { try { return [...s.cssRules]; } catch { return []; } })
        .filter(r => r.constructor?.name === 'CSSFontFaceRule' && /figtree/i.test(r.cssText))
        .map(r => r.cssText)
        .join('\n');
    if (fontCss) {
        const style = document.createElementNS('http://www.w3.org/2000/svg', 'style');
        style.textContent = fontCss;
        clone.insertBefore(style, clone.firstChild);
    }

    clone.setAttribute('width', CARD_W);
    clone.setAttribute('height', CARD_H);
    const xml = new XMLSerializer().serializeToString(clone);
    const img = await loadImage(`data:image/svg+xml;charset=utf-8,${encodeURIComponent(xml)}`);

    const canvas = document.createElement('canvas');
    canvas.width = CARD_W * scale;
    canvas.height = CARD_H * scale;
    const c = canvas.getContext('2d');
    c.drawImage(img, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/png');
}

async function downloadCard() {
    downloading.value = true;
    try {
        const url = await renderFront();
        if (!url) return;
        const link = document.createElement('a');
        link.download = `kad-ahli-${props.card.member_no || props.card.id}.png`;
        link.href = url;
        link.click();
    } finally {
        downloading.value = false;
    }
}

async function shareCard() {
    if (navigator.share) {
        await navigator.share({
            title: 'Kad Keahlian myWAP',
            text: 'Kad keahlian digital saya.',
            url: props.card.qr_value || window.location.href,
        });
    }
}

function printCard() {
    window.print();
}
</script>

<template>
    <Head title="Kad Keahlian" />
    <AppLayout :hide-mobile-bell="true" :hide-mobile-header="true" :back-route="route('member.dashboard')" back-label="Kembali ke Papan Pemuka">
        <div class="min-h-screen bg-[#F5F7F6] py-6 md:py-10">
            <div class="mx-auto max-w-sm space-y-5 px-6">

                <div class="text-center print:hidden">
                    <h1 class="text-lg font-black text-gray-900">Kad Keahlian</h1>
                    <p class="text-xs text-gray-500">Tekan kad untuk lihat bahagian {{ flip?.flipped ? 'depan' : 'belakang' }}.</p>
                </div>

                <div class="card-print-area mx-auto w-full max-w-[320px]">
                    <MemberCardFlip ref="flip" :card="card" :qr-svg="qrSvg" />
                </div>

                <div class="flex justify-center print:hidden">
                    <div class="inline-flex rounded-full bg-white p-1 shadow-sm ring-1 ring-gray-200">
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                            :class="!flip?.flipped ? 'bg-gray-900 text-white' : 'text-gray-600'"
                            @click="flip?.flipped && flip.toggle()"
                        >Depan</button>
                        <button
                            type="button"
                            class="rounded-full px-4 py-1.5 text-xs font-semibold transition"
                            :class="flip?.flipped ? 'bg-gray-900 text-white' : 'text-gray-600'"
                            @click="!flip?.flipped && flip.toggle()"
                        >Belakang</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 print:hidden">
                    <button @click="downloadCard" :disabled="downloading" class="flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-60">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        {{ downloading ? 'Menjana...' : 'Muat Turun' }}
                    </button>
                    <button @click="shareCard" class="flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Kongsi
                    </button>
                    <button @click="printCard" class="flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak
                    </button>
                    <a :href="route('member.card.letter')" class="flex items-center justify-center gap-2 rounded-2xl bg-gray-900 px-4 py-3 text-xs font-semibold text-white shadow-sm transition hover:bg-gray-800 active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Surat Pengesahan
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style>
@media print {
    /* Cetak pada saiz kad sebenar 54 × 85.6 mm. */
    .card-print-area { width: 54mm !important; max-width: none !important; }
    .card-print-area * { box-shadow: none !important; }
}
</style>

<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    infaq: {
        type: Object,
        required: true,
    },
});

const sharePreviewUrl = computed(() => {
    return route('share.infaq', props.infaq.slug || props.infaq.id, true);
});

const qrUrl = computed(() => {
    return route('infaq.qr', {
        year: props.infaq.year || new Date().getFullYear(),
        month: props.infaq.month || String(new Date().getMonth() + 1).padStart(2, '0'),
        day: props.infaq.day || String(new Date().getDate()).padStart(2, '0'),
        infaq: props.infaq.slug || props.infaq.id,
    });
});

const linkCopied = ref(false);
async function copyShareLink() {
    try {
        await navigator.clipboard.writeText(sharePreviewUrl.value);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = sharePreviewUrl.value;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    }
}
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

            <!-- QR Share -->
            <div class="rounded-2xl bg-gray-50 border border-gray-200 p-5">
                <p class="text-xs font-bold text-slate-700 mb-3">Kongsikan Kempen Ini</p>
                <div class="flex flex-col items-center gap-3">
                    <div class="bg-white p-2 border border-gray-200 rounded-xl shadow-sm">
                        <img :src="qrUrl" alt="QR Code" class="w-28 h-28"/>
                    </div>
                    <div class="flex items-center w-full rounded-lg bg-white border border-gray-200 p-1.5">
                        <span class="truncate text-xs text-gray-500 px-1 flex-1">{{ sharePreviewUrl }}</span>
                        <button
                            @click="copyShareLink"
                            class="shrink-0 rounded-md px-3 py-1 text-xs font-bold transition"
                            :class="linkCopied ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'"
                        >
                            {{ linkCopied ? 'Disalin!' : 'Salin' }}
                        </button>
                    </div>
                    <SocialShareButtons
                        :title="infaq.title"
                        :url="sharePreviewUrl"
                        text="Saya baru menyumbang untuk kempen ini. Jom turut serta!"
                        compact
                    />
                </div>
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
                    href="/sumbangan" 
                    class="block w-full py-3.5 px-4 bg-white border-2 border-slate-200 text-slate-600 hover:text-slate-900 hover:border-slate-300 hover:bg-slate-50 text-sm font-bold uppercase tracking-wide rounded-xl transition"
                >
                    Lihat Kempen Lain
                </Link>
            </div>
        </div>
    </div>
</template>

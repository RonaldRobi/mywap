<script setup>
import { computed, ref, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    infaq: { type: Object, required: true },
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

// Clear draft from localStorage
onMounted(() => {
    try { localStorage.removeItem('infaq_donate_draft'); } catch {}
});

// Generate confetti pieces
const confettiPieces = Array.from({ length: 40 }, (_, i) => ({
    id: i,
    left: Math.random() * 100,
    delay: Math.random() * 2,
    duration: 2 + Math.random() * 3,
    color: ['#059669', '#10b981', '#6366f1', '#f59e0b', '#ec4899', '#06b6d4'][i % 6],
    size: 6 + Math.random() * 8,
    rotation: Math.random() * 360,
}));
</script>

<template>
    <Head :title="`Sumbangan Berjaya - ${infaq.title}`" />

    <div class="min-h-screen bg-gradient-to-b from-emerald-50 via-slate-50 to-white font-sans flex items-center justify-center p-4 relative overflow-hidden">
        <!-- Confetti Animation -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div
                v-for="piece in confettiPieces"
                :key="piece.id"
                class="absolute top-0"
                :style="{
                    left: piece.left + '%',
                    width: piece.size + 'px',
                    height: piece.size + 'px',
                    backgroundColor: piece.color,
                    borderRadius: piece.id % 3 === 0 ? '50%' : '2px',
                    animation: `confetti-fall ${piece.duration}s ease-in ${piece.delay}s forwards`,
                    transform: `rotate(${piece.rotation}deg)`,
                }"
            />
        </div>

        <div class="max-w-md w-full relative z-10 space-y-6">
            <!-- Success Card -->
            <div class="bg-white rounded-3xl border border-emerald-100 shadow-xl shadow-emerald-900/5 p-8 text-center space-y-6">
                <!-- Animated Success Icon -->
                <div class="mx-auto w-24 h-24 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600 mb-2 relative">
                    <div class="absolute inset-0 rounded-full animate-ping bg-emerald-100 opacity-40"></div>
                    <svg class="w-12 h-12 relative z-10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <!-- Content -->
                <div>
                    <h1 class="text-2xl font-black text-slate-900 mb-2">Terima Kasih!</h1>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Sumbangan anda untuk kempen <span class="font-bold text-slate-800">"{{ infaq.title }}"</span> telah berjaya direkodkan.
                    </p>
                </div>

                <!-- Motivational Dua -->
                <div class="rounded-2xl bg-gradient-to-br from-emerald-50 to-indigo-50 border border-emerald-100 p-4">
                    <p class="text-sm text-emerald-800 italic leading-relaxed">
                        "Semoga Allah SWT memberkati setiap rezeki yang disumbangkan dan menjadikannya sebagai saham akhirat yang berpanjangan."
                    </p>
                    <p class="text-xs text-emerald-600 mt-1 font-bold">Aamiin</p>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3">
                    <Link 
                        :href="infaq.public_url" 
                        class="block w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold uppercase tracking-wide rounded-xl shadow-md shadow-emerald-600/20 transition hover:-translate-y-0.5 active:scale-[0.98]"
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

            <!-- Share Section -->
            <div class="rounded-3xl bg-white border border-gray-200 p-6 shadow-sm">
                <p class="text-sm font-black text-slate-900 mb-4 text-center">Kongsikan Kebaikan Ini</p>
                <p class="text-xs text-slate-500 text-center mb-4">Ajak rakan dan keluarga untuk turut serta. Setiap perkongsian menghampakan potensi kebaikan.</p>
                
                <div class="flex flex-col items-center gap-4">
                    <div class="bg-white p-2 border border-gray-200 rounded-xl shadow-sm">
                        <img :src="qrUrl" alt="QR Code" class="w-32 h-32"/>
                    </div>
                    <div class="flex items-center w-full rounded-lg bg-gray-50 border border-gray-200 p-1.5">
                        <span class="truncate text-xs text-gray-500 px-1 flex-1">{{ sharePreviewUrl }}</span>
                        <button
                            @click="copyShareLink"
                            class="shrink-0 rounded-md px-3 py-1.5 text-xs font-bold transition"
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

            <!-- Trust Footer -->
            <p class="text-center text-xs text-slate-400 flex items-center justify-center gap-1">
                <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                Transaksi selamat & resit akan diemelkan
            </p>
        </div>
    </div>
</template>

<style scoped>
@keyframes confetti-fall {
    0% {
        transform: translateY(-10vh) rotate(0deg);
        opacity: 1;
    }
    100% {
        transform: translateY(105vh) rotate(720deg);
        opacity: 0;
    }
}
</style>

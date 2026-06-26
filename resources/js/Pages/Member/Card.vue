<script setup>
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import html2canvas from 'html2canvas';

const props = defineProps({
    card: { type: Object, required: true },
    qrPrivate: { type: String, default: null },
    qrPublic: { type: String, default: null },
});

const cardGrad = computed(() => {
    const slug = props.card?.organization?.slug;
    if (slug === 'management') return { from: '#1e293b', to: '#334155' };
    if (slug === 'pkpim') return { from: '#312e81', to: '#4338ca' };
    if (slug === 'abim') return { from: '#004D40', to: '#00796B' };
    if (slug === 'wadah') return { from: '#78350f', to: '#92400e' };
    return { from: '#004D40', to: '#00796B' };
});

function printAsPdf() {
    window.print();
}

async function downloadAsJpg() {
    const target = document.getElementById('membership-card');
    if (!target) return;
    const canvas = await html2canvas(target, {
        scale: 2,
        backgroundColor: null,
        useCORS: true,
    });
    const link = document.createElement('a');
    link.download = `kad-ahli-${props.card.id}.jpg`;
    link.href = canvas.toDataURL('image/jpeg', 0.95);
    link.click();
}

async function shareCard() {
    if (navigator.share) {
        await navigator.share({
            title: 'Kad Keahlian myWAP',
            text: 'Kad keahlian digital saya.',
            url: window.location.href,
        });
    }
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map(v => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="Kad Keahlian" />
    <AppLayout :hide-mobile-bell="true" :hide-mobile-header="true" :back-route="route('member.dashboard')" back-label="Kembali ke Papan Pemuka">
        <div class="min-h-screen bg-[#F5F7F6] py-6 md:py-10 overflow-x-hidden">
            <div class="max-w-md md:max-w-xl mx-auto space-y-5 px-4 md:px-4">

                <!-- ═══ PREMIUM MEMBERSHIP CARD ═══ -->
                <div id="membership-card" class="relative overflow-hidden rounded-[28px] p-5 md:p-8 text-white shadow-lg"
                    :style="{ background: `linear-gradient(135deg, ${cardGrad.from}, ${cardGrad.to})`, boxShadow: `0 8px 32px ${cardGrad.from}4d` }">

                    <!-- Arabesque corner pattern -->
                    <svg class="absolute top-0 right-0 w-72 h-72 opacity-[0.03] pointer-events-none" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: scale(0.8); transform-origin: top right;">
                        <path d="M190,20 C175,10 155,8 140,15 C125,22 118,38 120,55 C122,72 132,80 148,75 C164,70 170,55 165,42 C160,30 148,25 142,32" stroke="white" stroke-width="0.7" fill="none"/>
                        <path d="M200,40 C180,42 150,50 130,38 C110,26 105,8 118,2 C132,-2 145,10 142,25" stroke="white" stroke-width="0.6" fill="none"/>
                        <path d="M200,70 C170,72 140,80 115,65 C90,50 75,20 85,12" stroke="white" stroke-width="0.5" fill="none"/>
                        <path d="M152,18 C149,14 145,11 140,14 C144,18 148,21 152,18Z" fill="white"/>
                        <path d="M130,30 C133,26 137,23 141,27 C137,31 133,34 130,30Z" fill="white"/>
                        <path d="M165,55 C162,51 158,49 155,52 C158,56 161,58 165,55Z" fill="white"/>
                        <path d="M120,60 C123,56 127,53 131,57 C127,61 123,64 120,60Z" fill="white"/>
                        <path d="M175,40 C172,36 168,34 165,37 C168,41 171,43 175,40Z" fill="white"/>
                        <circle cx="148" cy="38" r="1" fill="white"/>
                        <circle cx="135" cy="50" r="0.8" fill="white"/>
                        <circle cx="160" cy="25" r="1.2" fill="white"/>
                        <circle cx="112" cy="40" r="0.7" fill="white"/>
                        <circle cx="155" cy="65" r="1" fill="white"/>
                        <circle cx="178" cy="48" r="0.8" fill="white"/>
                    </svg>

                    <div class="relative z-10">

                        <!-- Top bar: myWAP + Org badge -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15 backdrop-blur-md border border-white/20">
                                    <img v-if="card.system_logo_path" :src="card.system_logo_path" alt="myWAP" class="h-6 w-6 object-contain">
                                    <span v-else class="text-[10px] font-bold text-white/70">MW</span>
                                </div>
                                <div>
                                    <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-white/60">myWAP</p>
                                    <h1 class="text-base md:text-lg font-bold text-white">Kad Keahlian</h1>
                                </div>
                            </div>
                            <div class="flex flex-col items-center gap-1.5">
                                <div v-if="card.organization?.logo_path" class="h-10 w-10 rounded-full overflow-hidden border-2 border-white/30 backdrop-blur-md bg-white/10">
                                    <img :src="card.organization.logo_path" :alt="card.organization?.name" class="h-full w-full object-contain">
                                </div>
                                <div v-else class="h-10 w-10 rounded-full flex items-center justify-center text-[11px] font-bold text-white bg-white/15 backdrop-blur-md border border-white/20">
                                    {{ card.organization?.name?.charAt(0) || 'O' }}
                                </div>
                                <span class="text-[10px] font-semibold text-white/80 truncate max-w-[80px] text-center leading-tight">
                                    {{ card.organization?.name || 'Organisasi' }}
                                </span>
                            </div>
                        </div>

                        <!-- Body: photo + details — portrait mobile, landscape desktop -->
                        <div class="mt-6 md:mt-8 flex flex-col md:flex-row md:items-start gap-5 md:gap-8">

                            <!-- Photo section (centered on mobile, left on desktop) -->
                            <div class="flex flex-col items-center md:items-center md:shrink-0 mx-auto md:mx-0">
                                <img v-if="card.photo_url" :src="card.photo_url" alt="Profile" class="w-24 h-24 md:w-28 md:h-28 rounded-2xl object-cover border-2 border-white/30 shadow-lg">
                                <div v-else class="w-24 h-24 md:w-28 md:h-28 rounded-2xl flex items-center justify-center text-2xl font-black text-white bg-white/15 backdrop-blur-md border-2 border-white/30 shadow-lg">
                                    {{ initials(card.name) }}
                                </div>
                                <p class="mt-3 text-lg md:text-xl font-bold text-white text-center">{{ card.name }}</p>
                                <p class="text-[11px] text-white/60">Ahli sejak {{ card.member_since || '—' }}</p>
                            </div>

                            <!-- Details grid (right on desktop) -->
                            <div class="flex-1 w-full md:mt-0">
                                <div class="grid grid-cols-2 gap-2.5">
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Email</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">{{ card.email || '—' }}</p>
                                    </div>
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Telefon</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">{{ card.phone || '—' }}</p>
                                    </div>
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Cawangan</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">{{ card.branch_name || '—' }}</p>
                                    </div>
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Profesion</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">{{ card.profession || '—' }}</p>
                                    </div>
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">Lokasi</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">{{ card.locality || '—' }}</p>
                                    </div>
                                    <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3">
                                        <p class="text-[10px] text-white/60 uppercase tracking-wide">No. Ahli</p>
                                        <p class="text-sm font-semibold text-white truncate mt-0.5">#{{ card.id }}</p>
                        </div>
                    </div>

                    <!-- QR Codes -->
                    <div v-if="qrPrivate || qrPublic" class="mt-5 grid grid-cols-2 gap-3">
                        <div v-if="qrPrivate" class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3 text-center">
                            <div class="w-20 h-20 mx-auto" v-html="qrPrivate"></div>
                            <p class="text-[10px] text-white/60 mt-2">Scan untuk akses kad</p>
                        </div>
                        <div v-if="qrPublic" class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3 text-center">
                            <div class="w-20 h-20 mx-auto" v-html="qrPublic"></div>
                            <p class="text-[10px] text-white/60 mt-2">Kongsi kad keahlian</p>
                        </div>
                    </div>
                            </div>
                        </div>
                    </div>
                </div>

            <!-- ═══ ACTION BUTTONS ═══ -->
                <div class="flex gap-3 print:hidden">
                    <button @click="printAsPdf" class="flex-1 flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Cetak PDF
                    </button>
                    <button @click="downloadAsJpg" class="flex-1 flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Muat Turun
                    </button>
                    <button @click="shareCard" class="flex-1 flex items-center justify-center gap-2 rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition active:scale-95">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        Kongsi
                    </button>
                </div>

            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden { display: none !important; }
}
</style>

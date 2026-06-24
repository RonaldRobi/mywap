<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    card: { type: Object, required: true },
});

const cardGrad = computed(() => {
    const slug = props.card?.organization?.slug;
    if (slug === 'management') return { from: '#1e293b', to: '#334155' };
    if (slug === 'pkpim') return { from: '#312e81', to: '#4338ca' };
    if (slug === 'abim') return { from: '#004D40', to: '#00796B' };
    if (slug === 'wadah') return { from: '#78350f', to: '#92400e' };
    return { from: '#004D40', to: '#00796B' };
});

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map(v => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="Pengesahan Ahli" />
    <div class="min-h-screen bg-[#F5F7F6] flex flex-col items-center justify-center py-8 px-4">
        <div class="max-w-md w-full space-y-5">

            <!-- Verified badge -->
            <div class="flex items-center justify-center gap-2 text-emerald-700">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
                <span class="text-sm font-bold">Kad Keahlian Disahkan</span>
            </div>

            <!-- Card -->
            <div class="relative overflow-hidden rounded-[28px] p-6 text-white shadow-lg"
                :style="{ background: `linear-gradient(135deg, ${cardGrad.from}, ${cardGrad.to})`, boxShadow: `0 8px 32px ${cardGrad.from}4d` }">

                <!-- Arabesque corner pattern -->
                <svg class="absolute top-0 right-0 w-64 h-64 opacity-[0.03] pointer-events-none" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: scale(0.8); transform-origin: top right;">
                    <path d="M190,20 C175,10 155,8 140,15 C125,22 118,38 120,55 C122,72 132,80 148,75 C164,70 170,55 165,42 C160,30 148,25 142,32" stroke="white" stroke-width="0.7" fill="none"/>
                    <path d="M200,40 C180,42 150,50 130,38 C110,26 105,8 118,2 C132,-2 145,10 142,25" stroke="white" stroke-width="0.6" fill="none"/>
                    <path d="M152,18 C149,14 145,11 140,14 C144,18 148,21 152,18Z" fill="white"/>
                    <path d="M130,30 C133,26 137,23 141,27 C137,31 133,34 130,30Z" fill="white"/>
                    <path d="M165,55 C162,51 158,49 155,52 C158,56 161,58 165,55Z" fill="white"/>
                    <circle cx="148" cy="38" r="0.8" fill="white"/>
                    <circle cx="160" cy="25" r="1.2" fill="white"/>
                </svg>

                <div class="relative z-10">
                    <!-- Top bar -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 backdrop-blur-md border border-white/20">
                                <span class="text-[9px] font-bold text-white/70">MW</span>
                            </div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/60">myWAP</p>
                        </div>
                        <div class="flex flex-col items-center gap-1">
                            <div v-if="card.organization?.logo_path" class="h-9 w-9 rounded-full overflow-hidden border-2 border-white/30 bg-white/10">
                                <img :src="card.organization.logo_path" :alt="card.organization?.name" class="h-full w-full object-contain">
                            </div>
                            <div v-else class="h-9 w-9 rounded-full flex items-center justify-center text-[10px] font-bold text-white bg-white/15 backdrop-blur-md border border-white/20">
                                {{ card.organization?.name?.charAt(0) || 'O' }}
                            </div>
                            <span class="text-[10px] font-semibold text-white/80 text-center leading-tight max-w-[90px] truncate">{{ card.organization?.name || 'Organisasi' }}</span>
                        </div>
                    </div>

                    <!-- Body -->
                    <div class="flex flex-col items-center mt-6">
                        <img v-if="card.photo_url" :src="card.photo_url" alt="" class="w-24 h-24 rounded-2xl object-cover border-2 border-white/30 shadow-lg">
                        <div v-else class="w-24 h-24 rounded-2xl flex items-center justify-center text-2xl font-black text-white bg-white/15 backdrop-blur-md border-2 border-white/30 shadow-lg">
                            {{ initials(card.name) }}
                        </div>
                        <h2 class="mt-4 text-xl font-bold text-white text-center">{{ card.name }}</h2>
                        <p class="text-[11px] text-white/60 mt-1">Ahli sejak {{ card.member_since || '—' }}</p>
                    </div>

                    <!-- Details -->
                    <div class="mt-5 space-y-2.5">
                        <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3 flex items-center justify-between">
                            <span class="text-[11px] text-white/60">No. Keahlian</span>
                            <span class="text-sm font-bold text-white">{{ card.member_no }}</span>
                        </div>
                        <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-3 flex items-center justify-between">
                            <span class="text-[11px] text-white/60">Organisasi</span>
                            <span class="text-sm font-bold text-white">{{ card.organization?.name || '—' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <p class="text-center text-xs text-gray-500">
                Kad keahlian digital ini disahkan oleh myWAP.
                <Link :href="route('login')" class="font-semibold text-emerald-700 hover:underline">Log masuk</Link>
                untuk kad penuh.
            </p>

        </div>
    </div>
</template>

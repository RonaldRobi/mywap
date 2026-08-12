<script setup>
import { computed } from 'vue';

const props = defineProps({
    compact: { type: Boolean, default: false },
    mini: { type: Boolean, default: false },
    stacked: { type: Boolean, default: false },
    light: { type: Boolean, default: false },
    part: {
        type: String,
        default: 'full',
        validator: (value) => ['full', 'header', 'details'].includes(value),
    },
});

const organizations = [
    {
        name: 'PKPIM',
        age: '15–25 Tahun',
        audience: 'Pelajar & Mahasiswa Islam',
        logo: '/storage/logos/organizations/zratUgj9brjqSZMoHiHh8BeyXdUl3Uy2SrgWEph1.png',
    },
    {
        name: 'ABIM',
        age: '25–40 Tahun',
        audience: 'Belia Islam',
        logo: '/storage/logos/organizations/hREFAvHpwkZILTQczpAomZ0nyoEq4F2JxweJ1zeU.png',
    },
    {
        name: 'WADAH',
        age: '40+ Tahun',
        audience: 'Pencerdasan Umat',
        logo: '/storage/logos/organizations/TQphsffDuK8ikn8duJ5LNTSIqBb5PnYfwZGlMMl4.png',
    },
];

const showHeader = computed(() => props.part === 'full' || props.part === 'header');
const showDetails = computed(() => props.part === 'full' || props.part === 'details');

const tone = computed(() => props.light
    ? {
        eyebrow: 'text-[#2F6B32]',
        heading: 'text-[#071525]',
        tagline: 'text-[#123D2A]',
        card: 'border-[#D5E3D8] bg-white',
        name: 'text-[#071525]',
        age: 'text-[#2F6B32]',
        audience: 'text-[#4A5A50]',
        rail: 'border-[#D5E3D8] bg-white',
        railName: 'text-[#123D2A]',
        railNote: 'text-[#4A5A50]',
        divider: 'border-[#D5E3D8]',
    }
    : {
        eyebrow: 'text-[#6FBF8A]',
        heading: 'text-white',
        tagline: 'text-[#D5E3D8]',
        card: 'border-white/10 bg-[#123D2A]/65',
        name: 'text-white',
        age: 'text-[#6FBF8A]',
        audience: 'text-[#D5E3D8]',
        rail: 'border-[#6FBF8A]/40 bg-[#123D2A]',
        railName: 'text-white',
        railNote: 'text-[#D5E3D8]',
        divider: 'border-[#6FBF8A]/25',
    });
</script>

<template>
    <div v-if="compact" class="flex min-w-0 items-center gap-2" aria-label="Ekosistem PKPIM ABIM WADAH">
        <div class="flex items-center gap-1 rounded-lg border border-[#D5E3D8] bg-white p-1">
            <span v-for="organization in organizations" :key="organization.name" :class="mini ? 'h-5 w-5' : 'h-7 w-7'" class="flex items-center justify-center bg-white p-0.5">
                <img :src="organization.logo" :alt="`Logo ${organization.name}`" class="h-full w-full object-contain">
            </span>
        </div>
        <div v-if="!mini" class="hidden min-w-0 sm:block">
            <p class="truncate text-[10px] font-black uppercase tracking-[0.13em] text-[#123D2A]">PKPIM · ABIM · WADAH</p>
            <p class="truncate text-[9px] font-medium text-[#2F6B32]">Ekosistem Gerakan</p>
        </div>
    </div>

    <div v-else-if="stacked" class="flex flex-col items-center gap-2.5">
        <div class="flex items-center gap-1.5 rounded-xl border border-[#D5E3D8] bg-white p-2" aria-label="Logo PKPIM ABIM WADAH">
            <span v-for="organization in organizations" :key="organization.name" class="flex h-11 w-11 items-center justify-center bg-white p-1">
                <img :src="organization.logo" :alt="`Logo ${organization.name}`" class="h-full w-full object-contain">
            </span>
        </div>
        <div class="text-center">
            <p class="text-[10px] font-black uppercase tracking-[0.16em] text-[#123D2A]">Ekosistem Gerakan</p>
            <p class="mt-0.5 text-[9px] font-bold text-[#2F6B32]">myWAP · Platform Digital</p>
        </div>
    </div>

    <div v-else>
        <template v-if="showHeader">
            <div class="rounded-2xl border border-[#D5E3D8] bg-white p-2.5 sm:p-5" aria-label="Logo PKPIM ABIM WADAH">
                <div class="grid grid-cols-3 items-center divide-x divide-[#E1EAE3]">
                    <div v-for="organization in organizations" :key="organization.name" class="flex h-10 items-center justify-center px-2 sm:h-20 sm:px-5">
                        <img :src="organization.logo" :alt="`Logo ${organization.name}`" class="max-h-full w-full object-contain">
                    </div>
                </div>
            </div>

            <div class="mt-3 text-center sm:mt-6 lg:text-left">
                <p class="text-[9px] font-bold uppercase tracking-[0.2em] sm:text-[10px] sm:tracking-[0.22em]" :class="tone.eyebrow">Platform rasmi gerakan</p>
                <h1 class="mt-1 text-lg font-black uppercase leading-tight tracking-tight sm:mt-2 sm:text-3xl" :class="tone.heading">Ekosistem Gerakan</h1>
                <p class="mt-1 text-xs font-semibold sm:mt-2 sm:text-base" :class="tone.tagline">Tiga wadah, satu keluarga gerakan.</p>
            </div>
        </template>

        <template v-if="showDetails">
            <div class="grid gap-2.5" :class="showHeader ? 'mt-5' : ''">
                <article
                    v-for="organization in organizations"
                    :key="organization.name"
                    class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3"
                    :class="tone.card"
                >
                    <div class="min-w-0">
                        <h2 class="text-base font-black tracking-wide" :class="tone.name">{{ organization.name }}</h2>
                        <p class="mt-0.5 truncate text-xs" :class="tone.audience">{{ organization.audience }}</p>
                    </div>
                    <span class="shrink-0 text-[11px] font-bold" :class="tone.age">{{ organization.age }}</span>
                </article>
            </div>

            <div class="mt-5 rounded-xl border px-4 py-3.5" :class="tone.rail">
                <div class="flex items-center justify-center gap-2 text-sm font-black tracking-wide" :class="tone.railName">
                    <span>PKPIM</span>
                    <span class="text-[#6FBF8A]">→</span>
                    <span>ABIM</span>
                    <span class="text-[#6FBF8A]">→</span>
                    <span>WADAH</span>
                </div>
                <p class="mt-2 border-t pt-2 text-center text-[9px] font-bold uppercase tracking-[0.14em]" :class="[tone.divider, tone.railNote]">
                    Perjalanan pembangunan insan sepanjang hayat
                </p>
            </div>
        </template>
    </div>
</template>

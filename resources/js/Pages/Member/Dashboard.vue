<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, Teleport } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    member: { type: Object, required: true },
    feeStatus: { type: Object, required: true },
    nextEvent: { type: Object, default: null },
    upcomingEvents: { type: Array, default: () => [] },
    banners: { type: Array, default: () => [] },
    videos: { type: Array, default: () => [] },
    libraryBooks: { type: Array, default: () => [] },
    usrah: { type: Object, default: null },
    infaqItems: { type: Array, default: () => [] },
    latestNews: { type: Array, default: () => [] },
    latestArticles: { type: Array, default: () => [] },
    activePolls: { type: Array, default: () => [] },
});

const themeMap = {
    management: { accent: 'bg-slate-500', accentText: 'text-slate-600', accentBg: 'bg-slate-50', badge: 'bg-slate-100 text-slate-700', dot: 'bg-slate-500' },
    pkpim: { accent: 'bg-indigo-500', accentText: 'text-indigo-600', accentBg: 'bg-indigo-50', badge: 'bg-indigo-100 text-indigo-700', dot: 'bg-indigo-500' },
    abim: { accent: 'bg-emerald-500', accentText: 'text-emerald-600', accentBg: 'bg-emerald-50', badge: 'bg-emerald-100 text-emerald-700', dot: 'bg-emerald-500' },
    wadah: { accent: 'bg-amber-500', accentText: 'text-amber-600', accentBg: 'bg-amber-50', badge: 'bg-amber-100 text-amber-700', dot: 'bg-amber-500' },
};
const darkGradMap = {
    management: { from: '#1e293b', to: '#334155' },
    pkpim: { from: '#312e81', to: '#4338ca' },
    abim: { from: '#064e3b', to: '#065f46' },
    wadah: { from: '#78350f', to: '#92400e' },
};
const lightGradMap = {
    management: { from: '#94a3b8', to: '#64748b' },
    pkpim: { from: '#818cf8', to: '#6366f1' },
    abim: { from: '#34d399', to: '#10b981' },
    wadah: { from: '#fbbf24', to: '#f59e0b' },
};
const colorMap = {
    management: '#64748b', pkpim: '#6366f1', abim: '#10b981', wadah: '#f59e0b',
};
const orgSlug = computed(() => props.member?.organization?.slug);
const theme = computed(() => themeMap[orgSlug.value] ?? themeMap.abim);
const themeColor = computed(() => colorMap[orgSlug.value] ?? colorMap.abim);
const darkGrad = computed(() => darkGradMap[orgSlug.value] ?? darkGradMap.abim);
const lightGrad = computed(() => lightGradMap[orgSlug.value] ?? lightGradMap.abim);

const page = usePage();
const notifCount = computed(() => page.props.notifications?.unread_count ?? 0);
function markNotifsRead() {
    if (notifCount.value > 0) {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
    }
}

function openDrawer() {
    window.dispatchEvent(new CustomEvent('open-mobile-drawer'));
}

const payForm = useForm({});
const infaqForms = ref({});
const booksScroller = ref(null);
const openVideo = ref(null);

function playVideo(video) {
    openVideo.value = video;
}

function closeVideo() {
    openVideo.value = null;
}

const coverStyles = [
    'from-sky-100 to-sky-300 text-sky-900',
    'from-emerald-100 to-emerald-300 text-emerald-900',
    'from-indigo-100 to-indigo-300 text-indigo-900',
    'from-amber-100 to-amber-300 text-amber-900',
    'from-violet-100 to-violet-300 text-violet-900',
];
const featuredInfaq = computed(() =>
    [...(props.infaqItems ?? [])]
        .sort((a, b) => Number(b.progress_percent ?? 0) - Number(a.progress_percent ?? 0))
        .slice(0, 4)
);
const featuredNews = computed(() => (props.latestNews ?? []).slice(0, 5));
const firstInfaqUrl = computed(() => featuredInfaq.value[0]?.public_url ?? null);

const activeBannerIndex = ref(0);
const carouselItems = computed(() => {
    if (props.banners.length) return props.banners;
    return [{ id: 0, title: 'Selamat datang ke myWAP', image_path: null }];
});
let bannerTimer;
onMounted(() => {
    bannerTimer = setInterval(() => {
        if (carouselItems.value.length > 1) {
            activeBannerIndex.value = (activeBannerIndex.value + 1) % carouselItems.value.length;
        }
    }, 5000);
});
onUnmounted(() => {
    if (bannerTimer) clearInterval(bannerTimer);
});

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map(v => v[0]).join('').toUpperCase();
}

function payFee() {
    payForm.post(route('member.pay.fee'), { preserveScroll: true });
}
function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency', currency: 'MYR', maximumFractionDigits: 2,
    }).format(value ?? 0);
}
function getInfaqForm(infaqId) {
    if (!infaqForms.value[infaqId]) {
        infaqForms.value[infaqId] = useForm({ amount: 10 });
    }
    return infaqForms.value[infaqId];
}
function donateInfaq(infaqId) {
    const infaq = props.infaqItems.find(i => i.id === infaqId);
    if (!infaq) return;
    const form = getInfaqForm(infaqId);
    form.post(infaq.public_url + '/donate', { preserveScroll: true });
}
function quickDonate(infaqId, amount) {
    const form = getInfaqForm(infaqId);
    form.amount = amount;
    donateInfaq(infaqId);
}
function scrollBooks(direction) {
    if (!booksScroller.value) return;
    booksScroller.value.scrollBy({
        left: direction === 'left' ? -880 : 880,
        behavior: 'smooth',
    });
}
</script>

<template>
    <Head title="Member Dashboard" />
    <AppLayout :hide-mobile-bell="true" :hide-mobile-header="true">
        <div class="min-h-screen bg-[#F5F7F6] pt-4 pb-6 overflow-x-hidden px-4 md:px-6" style="padding-left: max(1rem, env(safe-area-inset-left)); padding-right: max(1rem, env(safe-area-inset-right))">
            <div class="max-w-md md:max-w-none mx-auto space-y-5 md:space-y-7">

                <!-- Flash Messages -->
                <template v-if="$page.props.flash?.success || $page.props.flash?.error">
                    <div v-if="$page.props.flash?.success" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="$page.props.flash?.error" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {{ $page.props.flash.error }}
                    </div>
                </template>

                <!-- ═══ 1. HEADER — compact, native-feel ═══ -->
                <header class="flex items-center justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="openDrawer" class="shrink-0 p-1.5 rounded-xl text-gray-600 hover:bg-gray-100 transition-colors" aria-label="Menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <p class="text-[11px] text-gray-500 font-medium leading-tight">Assalamualaikum,</p>
                            <h1 class="text-lg md:text-xl font-bold text-gray-900 truncate">{{ member.name }}</h1>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="markNotifsRead" class="relative p-2 rounded-xl text-gray-500 hover:bg-gray-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span v-if="notifCount > 0" class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full flex items-center justify-center text-white text-[10px] font-bold" :class="theme.accent">
                                {{ notifCount > 9 ? '9+' : notifCount }}
                            </span>
                        </button>
                        <Link :href="route('profile.show')" class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0" :class="theme.accent">
                            {{ member.name?.charAt(0)?.toUpperCase() || 'A' }}
                        </Link>
                    </div>
                </header>

                <!-- ═══ 2. BANNER CAROUSEL ═══ -->
                <section class="relative aspect-[21/9] rounded-[28px] overflow-hidden shadow-lg">
                    <div v-for="(banner, i) in carouselItems" :key="banner.id" v-show="i === activeBannerIndex" class="absolute inset-0">
                        <img v-if="banner.image_path" :src="banner.image_path" :alt="banner.title" class="w-full h-full object-cover" />
                        <div v-else class="w-full h-full flex items-center justify-center" :style="{ background: `linear-gradient(135deg, ${lightGrad.from}, ${lightGrad.to})` }">
                            <span class="text-white font-bold text-lg">Selamat datang ke myWAP</span>
                        </div>
                    </div>
                    <div v-if="carouselItems.length > 1" class="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex gap-1.5">
                        <button v-for="(_, i) in carouselItems" :key="i" @click="activeBannerIndex = i"
                            class="h-1.5 rounded-full transition-all duration-300"
                            :class="i === activeBannerIndex ? 'w-5 bg-white' : 'w-1.5 bg-white/40'" />
                    </div>
                </section>

                <!-- ═══ 3. PINTASTAN — fintech style ═══ -->
                <section>
                    <h2 class="text-sm font-bold text-gray-900 mb-3">Pintasan</h2>
                    <div class="grid grid-cols-4 gap-2.5">
                        <Link :href="route('member.financial.overview')" class="bg-white px-2 py-3 rounded-[22px] shadow-[0_2px_10px_rgba(0,0,0,0.04),0_1px_3px_rgba(0,0,0,0.02)] text-center hover:-translate-y-0.5 active:scale-95 transition-all">
                            <div class="w-14 h-14 mx-auto bg-gradient-to-br from-emerald-400 to-emerald-600 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-200/60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-gray-800 leading-tight">Yuran Saya</p>
                        </Link>
                        <Link :href="route('member.facilities.index')" class="bg-white px-2 py-3 rounded-[22px] shadow-[0_2px_10px_rgba(0,0,0,0.04),0_1px_3px_rgba(0,0,0,0.02)] text-center hover:-translate-y-0.5 active:scale-95 transition-all">
                            <div class="w-14 h-14 mx-auto bg-gradient-to-br from-amber-400 to-amber-600 rounded-2xl flex items-center justify-center shadow-lg shadow-amber-200/60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-gray-800 leading-tight">Tempah</p>
                        </Link>
                        <Link :href="route('news.index')" class="bg-white px-2 py-3 rounded-[22px] shadow-[0_2px_10px_rgba(0,0,0,0.04),0_1px_3px_rgba(0,0,0,0.02)] text-center hover:-translate-y-0.5 active:scale-95 transition-all">
                            <div class="w-14 h-14 mx-auto bg-gradient-to-br from-indigo-400 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-200/60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21H9a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2zM7 7H5a2 2 0 00-2 2v10a2 2 0 002 2h2M12 7h5M12 11h5M12 15h5"/></svg>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-gray-800 leading-tight">Info</p>
                        </Link>
                        <Link v-if="firstInfaqUrl" :href="firstInfaqUrl" class="bg-white px-2 py-3 rounded-[22px] shadow-[0_2px_10px_rgba(0,0,0,0.04),0_1px_3px_rgba(0,0,0,0.02)] text-center hover:-translate-y-0.5 active:scale-95 transition-all">
                            <div class="w-14 h-14 mx-auto bg-gradient-to-br from-rose-400 to-rose-600 rounded-2xl flex items-center justify-center shadow-lg shadow-rose-200/60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0v4M5 11h14l-1 9H6l-1-9z"/></svg>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-gray-800 leading-tight">Infaq</p>
                        </Link>
                        <div v-else class="bg-white/60 px-2 py-3 rounded-[22px] shadow-sm text-center opacity-50">
                            <div class="w-14 h-14 mx-auto bg-gradient-to-br from-rose-300 to-rose-400 rounded-2xl flex items-center justify-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0v4M5 11h14l-1 9H6l-1-9z"/></svg>
                            </div>
                            <p class="mt-2 text-[11px] font-semibold text-gray-400">Infaq</p>
                        </div>
                    </div>
                </section>

                <!-- ═══ 4. KAD KEAHLIAN — premium card ═══ -->
                <section class="relative overflow-hidden rounded-[28px] p-5 text-white shadow-lg" :style="{ background: `linear-gradient(135deg, ${darkGrad.from}, ${darkGrad.to})`, boxShadow: `0 8px 32px ${darkGrad.from}4d` }">
                    <!-- Arabesque corner pattern -->
                    <svg class="absolute top-0 right-0 w-64 h-64 opacity-[0.03] pointer-events-none" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: scale(0.8); transform-origin: top right;">
                        <path d="M190,20 C175,10 155,8 140,15 C125,22 118,38 120,55 C122,72 132,80 148,75 C164,70 170,55 165,42 C160,30 148,25 142,32" stroke="white" stroke-width="0.7" fill="none"/>
                        <path d="M200,40 C180,42 150,50 130,38 C110,26 105,8 118,2 C132,-2 145,10 142,25" stroke="white" stroke-width="0.6" fill="none"/>
                        <path d="M152,18 C149,14 145,11 140,14 C144,18 148,21 152,18Z" fill="white"/>
                        <path d="M130,30 C133,26 137,23 141,27 C137,31 133,34 130,30Z" fill="white"/>
                        <path d="M165,55 C162,51 158,49 155,52 C158,56 161,58 165,55Z" fill="white"/>
                        <path d="M120,60 C123,56 127,53 131,57 C127,61 123,64 120,60Z" fill="white"/>
                        <circle cx="148" cy="38" r="0.8" fill="white"/>
                        <circle cx="160" cy="25" r="1.2" fill="white"/>
                        <circle cx="112" cy="40" r="0.7" fill="white"/>
                    </svg>
                    <div class="relative z-10">
                        <!-- Top bar -->
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2.5">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/15 backdrop-blur-md border border-white/20">
                                    <img v-if="member.system_logo_path" :src="member.system_logo_path" alt="myWAP" class="h-5 w-5 object-contain">
                                    <span v-else class="text-[9px] font-bold text-white/70">MW</span>
                                </div>
                                <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-white/60">myWAP</p>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <img v-if="member.organization?.logo_path" :src="member.organization.logo_path" :alt="member.organization?.name" class="h-7 w-7 rounded-full object-cover border border-white/30">
                                <span class="text-[10px] font-semibold text-white/80 max-w-[80px] truncate">{{ member.organization?.name || 'Organisasi' }}</span>
                            </div>
                        </div>
                        <!-- Body -->
                        <div class="mt-4 flex items-center gap-4">
                            <img v-if="member.photo_url" :src="member.photo_url" alt="" class="w-16 h-16 rounded-2xl object-cover border-2 border-white/30 shrink-0">
                            <div v-else class="w-16 h-16 rounded-2xl flex items-center justify-center text-lg font-black text-white bg-white/15 backdrop-blur-md border-2 border-white/30 shrink-0">
                                {{ initials(member.name) }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-base font-bold text-white truncate">{{ member.name }}</h3>
                                <p class="text-[11px] text-white/60 mt-0.5">Ahli sejak {{ member.member_since || '—' }}</p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <span v-if="member.email" class="inline-flex items-center gap-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-2 py-0.5 text-[10px] text-white/80">
                                        {{ member.email }}
                                    </span>
                                    <span v-if="member.phone" class="inline-flex items-center gap-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-2 py-0.5 text-[10px] text-white/80">
                                        {{ member.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <!-- Glassmorphism stats -->
                        <div class="flex items-end justify-between mt-4">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-2.5">
                                    <p class="text-[10px] text-white/60">Yuran</p>
                                    <p class="mt-0.5 text-sm font-bold">{{ feeStatus.status === 'active' ? 'Aktif' : 'Tertunggak' }}</p>
                                </div>
                                <div class="backdrop-blur-xl bg-white/10 rounded-2xl border border-white/15 p-2.5">
                                    <p class="text-[10px] text-white/60">Usrah</p>
                                    <p class="mt-0.5 text-sm font-bold line-clamp-1">{{ usrah?.name || 'Belum Set' }}</p>
                                </div>
                            </div>
                            <Link :href="route('member.card')" class="text-[10px] font-semibold text-white/70 hover:text-white transition shrink-0">
                                Lihat Kad Penuh →
                            </Link>
                        </div>
                    </div>
                </section>

                <!-- ═══ 5. VIDEO ═══ -->
                <section v-if="videos.length">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-rose-600 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Video</h2>
                                <p class="text-[11px] text-gray-500">Tonton video terkini</p>
                            </div>
                        </div>
                        <Link :href="route('member.videos.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-2 md:gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <button
                            v-for="item in videos"
                            :key="item.id"
                            @click="playVideo(item)"
                            class="min-w-[165px] md:min-w-0 bg-white rounded-xl md:rounded-2xl shadow-sm overflow-hidden shrink-0 text-left transition hover:shadow-md"
                        >
                            <div class="relative aspect-video overflow-hidden bg-gray-100">
                                <img :src="item.thumbnail_url" :alt="item.title" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="ml-0.5 h-4 w-4 text-gray-900" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                                <div class="absolute top-2 left-2 md:top-3 md:left-3 z-10">
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 md:px-2 md:py-0.5 text-[10px] font-bold uppercase tracking-wide bg-white/90 backdrop-blur-md text-gray-800 border border-white/20 shadow-sm">Video</span>
                                </div>
                                <div class="absolute bottom-2 left-2 right-2 md:bottom-3 md:left-3 md:right-3 z-10">
                                    <h3 class="text-xs md:text-sm font-bold text-white line-clamp-2 drop-shadow-sm">{{ item.title }}</h3>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- ═══ 6. KEMPEN INFAQ ═══ -->
                <section v-if="infaqItems.length">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-gray-900">Kempen Infaq</h2>
                        <Link :href="route('member.financial.overview')" class="text-xs font-semibold" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-2 md:gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in featuredInfaq" :key="`infaq-${item.id}`" class="min-w-[165px] md:min-w-0 bg-white rounded-xl md:rounded-2xl shadow-sm overflow-hidden shrink-0">
                            <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
                                <img v-if="item.image_path" :src="item.image_path" :alt="item.title" class="absolute inset-0 w-full h-full object-cover">
                                <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(to right, ${lightGrad.from}, ${lightGrad.to})` }"></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                <div class="absolute bottom-2 left-2 right-2 md:bottom-3 md:left-3 md:right-3 z-10">
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 md:px-2 md:py-0.5 text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur-md text-white border border-white/20">
                                        {{ item.type === 'progress' ? 'Progress' : 'One-Off' }}
                                    </span>
                                    <h3 class="text-xs md:text-sm font-bold text-white mt-1 line-clamp-1">{{ item.title }}</h3>
                                </div>
                            </div>
                            <div class="px-3 pt-1.5 pb-2 md:px-4 md:pt-3 md:pb-4">
                                <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                                    <span class="font-semibold" :class="theme.accentText">{{ formatCurrency(item.collected_amount) }}</span>
                                    <span>daripada {{ formatCurrency(item.target_amount || 0) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-1.5 rounded-full transition-all duration-700" :class="theme.accent" :style="{ width: (item.progress_percent || 0) + '%' }"></div>
                                </div>
                                <div class="grid grid-cols-3 gap-2 mt-2">
                                    <button @click="quickDonate(item.id, 10)" :disabled="getInfaqForm(item.id).processing" :class="[theme.accentBg, theme.accentText, 'rounded-xl border py-2 text-xs font-semibold transition hover:opacity-80 disabled:opacity-50']">RM10</button>
                                    <button @click="quickDonate(item.id, 30)" :disabled="getInfaqForm(item.id).processing" :class="[theme.accentBg, theme.accentText, 'rounded-xl border py-2 text-xs font-semibold transition hover:opacity-80 disabled:opacity-50']">RM30</button>
                                    <button @click="quickDonate(item.id, 50)" :disabled="getInfaqForm(item.id).processing" :class="[theme.accentBg, theme.accentText, 'rounded-xl border py-2 text-xs font-semibold transition hover:opacity-80 disabled:opacity-50']">RM50</button>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <!-- ═══ 7. PROGRAM ═══ -->
                <section>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-gray-900">Program</h2>
                        <Link :href="route('events.index')" class="text-xs font-semibold" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div v-if="upcomingEvents.length" class="flex gap-2 md:gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="event in upcomingEvents" :key="`event-${event.id}`" class="min-w-[165px] md:min-w-0 bg-white rounded-xl md:rounded-2xl shadow-sm overflow-hidden shrink-0">
                            <Link :href="route('events.index')" class="block">
                                <div class="relative aspect-[16/9] overflow-hidden bg-gray-100">
                                    <img v-if="event.featured_image_url && !event.featured_image_url.includes('placehold.co')" :src="event.featured_image_url" :alt="event.title" class="h-full w-full object-cover">
                                    <div v-else class="h-full w-full" :style="{ background: `linear-gradient(135deg, ${lightGrad.from}, ${lightGrad.to})` }"></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                    <div class="absolute top-2 left-2 md:top-3 md:left-3 z-10">
                                        <span class="inline-flex rounded-full px-1.5 py-0.5 md:px-2 md:py-0.5 text-[10px] font-bold uppercase tracking-wide bg-white/90 backdrop-blur-md text-gray-800 border border-white/20 shadow-sm">
                                            {{ event.type === 'physical' ? 'Fizikal' : 'Online' }}
                                        </span>
                                    </div>
                                    <div class="absolute bottom-2 left-2 right-2 md:bottom-3 md:left-3 md:right-3 z-10">
                                        <h3 class="text-xs md:text-sm font-bold text-white line-clamp-2 drop-shadow-sm">{{ event.title }}</h3>
                                    </div>
                                </div>
                                <div class="px-3 pt-1.5 pb-2 md:px-4 md:pt-3 md:pb-4">
                                    <div class="flex items-center gap-1 text-[11px] md:text-xs text-gray-500">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 md:w-3.5 md:h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        <span class="truncate">{{ event.start_formatted }}</span>
                                    </div>
                                    <p v-if="event.location_or_link" class="mt-0.5 text-[11px] md:text-xs text-gray-400 truncate flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 md:w-3.5 md:h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ event.location_or_link }}
                                    </p>
                                </div>
                            </Link>
                        </article>
                    </div>
                    <div v-else class="rounded-2xl bg-white border-2 border-dashed border-gray-200 px-4 py-8 text-center">
                        <div class="w-12 h-12 mx-auto rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-semibold text-gray-700">Tiada program akan datang</p>
                        <p class="text-xs text-gray-400 mt-1">Tekan Lihat Semua untuk terokai program lepas</p>
                    </div>
                </section>

                <!-- ═══ 8. UNDIAN ═══ -->
                <section v-if="activePolls.length">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Undian &amp; Survey</h2>
                                <p class="text-[11px] text-gray-500">{{ activePolls.filter(p => !p.has_responded).length }} belum dijawab</p>
                            </div>
                        </div>
                        <Link :href="route('member.polls.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <div v-for="poll in activePolls" :key="poll.id" class="min-w-[280px] md:min-w-0 overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm shrink-0">
                            <div :class="['h-1 w-full', poll.has_responded ? 'bg-gradient-to-r from-emerald-400 to-emerald-300' : 'bg-gradient-to-r from-indigo-500 to-purple-500']"></div>
                            <div class="p-3.5">
                                <div class="mb-2 flex items-start justify-between gap-2">
                                    <span :class="['rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide', poll.type === 'poll' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700']">
                                        {{ poll.type === 'poll' ? 'POLL' : 'SURVEY' }}
                                    </span>
                                    <span v-if="poll.ends_at_formatted" class="flex items-center gap-1 text-[10px] text-gray-400 shrink-0">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ poll.ends_at_formatted }}
                                    </span>
                                </div>
                                <h4 class="text-sm font-bold text-gray-900 leading-snug mb-1">{{ poll.title }}</h4>
                                <p v-if="poll.first_question" class="text-[11px] text-gray-500 line-clamp-2 mb-2 italic">"{{ poll.first_question }}"</p>

                                <div v-if="poll.has_responded && poll.options_preview.length" class="space-y-1 mb-3">
                                    <div v-for="opt in poll.options_preview.slice(0, 3)" :key="opt.id" class="flex items-center gap-2">
                                        <div class="flex-1 h-4 rounded-md bg-gray-100 overflow-hidden">
                                            <div class="h-full rounded-md transition-all duration-700" :class="opt.count > 0 ? 'bg-gradient-to-r from-emerald-400 to-emerald-300' : ''" :style="{ width: opt.width + '%' }"></div>
                                        </div>
                                        <span class="text-[10px] font-semibold text-gray-600 w-6 text-right shrink-0">{{ opt.count }}</span>
                                    </div>
                                    <p v-if="poll.options_preview.length > 3" class="text-[10px] text-gray-400">+{{ poll.options_preview.length - 3 }} lagi</p>
                                </div>

                                <div v-else-if="!poll.has_responded && poll.options_preview.length" class="flex flex-wrap gap-1 mb-3">
                                    <span v-for="opt in poll.options_preview.slice(0, 3)" :key="opt.id" class="rounded-lg border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ opt.text }}</span>
                                    <span v-if="poll.options_preview.length > 3" class="rounded-lg border border-dashed border-gray-300 px-2 py-0.5 text-[10px] font-medium text-gray-400">+{{ poll.options_preview.length - 3 }}</span>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                        <span class="text-xs font-bold text-gray-700 tabular-nums">{{ poll.response_count }}</span>
                                        <span class="text-[10px] text-gray-400">respon</span>
                                    </div>
                                    <Link :href="poll.has_responded ? route('member.polls.results', poll.id) : route('member.polls.show', poll.id)" :class="['rounded-xl px-3 py-1.5 text-xs font-bold transition-all', poll.has_responded ? 'border border-emerald-200 text-emerald-700' : 'bg-gradient-to-r from-indigo-600 to-purple-600 text-white shadow-sm hover:shadow-md']">
                                        <span v-if="poll.has_responded">Lihat Keputusan</span>
                                        <span v-else class="flex items-center gap-1">
                                            Jawab
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                        </span>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ═══ 9. AKTIVITI + 10. STATUS YURAN ═══ -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <section class="bg-white rounded-3xl p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="text-sm font-bold text-gray-900">Aktiviti Saya</h2>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-center gap-2.5">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-5.747-8.62h11.494M4.5 19.5h15a2 2 0 002-2v-11a2 2 0 00-2-2h-15a2 2 0 00-2 2v11a2 2 0 002 2z"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[11px] text-gray-500">Usrah</p>
                                    <p class="text-sm font-semibold text-gray-800 truncate">{{ usrah?.name || 'Belum ditetapkan kumpulan' }}</p>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-[11px] text-gray-500">Tempahan Ruang</p>
                                        <p class="text-sm font-semibold text-gray-800 truncate">Semak ruang &amp; buat tempahan</p>
                                    </div>
                                </div>
                                <Link :href="route('member.facilities.index')" class="shrink-0 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-100">Tempah</Link>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-3xl p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-sm font-bold text-gray-900">Status Yuran</h2>
                            <Link :href="route('member.financial.overview')" class="text-xs font-semibold" :class="theme.accentText">Bayaran</Link>
                        </div>
                        <div class="mt-3 rounded-2xl bg-gray-50 p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-gray-700">Yuran Tahunan</p>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="feeStatus.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ feeStatus.status === 'active' ? 'Aktif' : 'Tertunggak' }}
                                </span>
                            </div>
                            <template v-if="feeStatus.status === 'active'">
                                <p class="mt-3 text-xs text-gray-600">Keahlian anda aktif.</p>
                                <p v-if="feeStatus.last_reference" class="mt-1.5 text-[11px] font-mono text-gray-400">Ref: {{ feeStatus.last_reference }}</p>
                            </template>
                            <template v-else>
                                <p class="mt-3 text-xs text-gray-600">Yuran tahunan perlu diperbaharui.</p>
                                <p class="mt-1 text-2xl font-black text-gray-900">{{ formatCurrency(feeStatus.amount_due) }}</p>
                                <button @click="payFee" :disabled="payForm.processing" :class="[theme.accent, 'mt-3 w-full rounded-xl py-3 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60 shadow-sm']">
                                    {{ payForm.processing ? 'Memproses...' : 'Bayar Sekarang' }}
                                </button>
                            </template>
                        </div>
                    </section>
                </div>

                <!-- ═══ 11. BERITA ═══ -->
                <section v-if="latestNews.length">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-gray-900">Berita Untuk Anda</h2>
                        <Link :href="route('news.index')" class="text-xs font-semibold" :class="theme.accentText">Buka Feed</Link>
                    </div>
                    <div class="flex gap-2 md:gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in featuredNews" :key="`news-${item.id}`" class="min-w-[165px] md:min-w-0 bg-white rounded-xl md:rounded-2xl shadow-sm overflow-hidden shrink-0">
                            <Link :href="route('news.show', item.id)" class="block">
                                <div class="relative aspect-[4/3] overflow-hidden bg-gray-100">
                                    <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="h-full w-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                </div>
                                <div class="px-3 pt-1.5 pb-2 md:px-4 md:pt-3 md:pb-4">
                                    <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide">
                                        <span v-if="item.category_name" class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ item.category_name }}</span>
                                        <span v-if="item.organization_name" :class="[theme.accentBg, theme.accentText, 'rounded-full px-2 py-0.5']">{{ item.organization_name }}</span>
                                    </div>
                                    <h4 class="mt-1 line-clamp-2 text-xs md:text-sm font-bold text-gray-900">{{ item.title }}</h4>
                                    <p class="mt-0.5 line-clamp-2 text-xs text-gray-500">{{ item.excerpt || 'Tekan untuk baca lanjut.' }}</p>
                                </div>
                            </Link>
                        </article>
                    </div>
                </section>

                <!-- ═══ 12. ARTIKEL ═══ -->
                <section v-if="latestArticles?.length" class="mt-5">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-gray-900">Artikel</h2>
                        <Link :href="route('articles.index')" class="text-xs font-semibold" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-2 md:gap-3 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in latestArticles" :key="`article-${item.id}`" class="min-w-[165px] md:min-w-0 bg-white rounded-xl md:rounded-2xl shadow-sm overflow-hidden shrink-0">
                            <Link :href="route('articles.show', item.slug)" class="block">
                                <div class="relative aspect-[16/9] overflow-hidden bg-gray-100">
                                    <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="h-full w-full object-cover">
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                </div>
                                <div class="px-3 pt-1.5 pb-2 md:px-4 md:pt-3 md:pb-4">
                                    <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide">
                                        <span v-if="item.organization_name" :class="[theme.accentBg, theme.accentText, 'rounded-full px-2 py-0.5']">{{ item.organization_name }}</span>
                                        <span v-if="item.author_name" class="text-gray-400 truncate">oleh {{ item.author_name }}</span>
                                    </div>
                                    <h4 class="mt-1 line-clamp-1 text-xs md:text-sm font-bold text-gray-900">{{ item.title }}</h4>
                                    <p v-if="item.excerpt" class="mt-0.5 line-clamp-1 text-xs text-gray-500">{{ item.excerpt }}</p>
                                </div>
                            </Link>
                        </article>
                    </div>
                </section>

                <!-- ═══ 13. PUSTAKA ═══ -->
                <section>
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-sm font-bold text-gray-900">Pustaka</h2>
                        <Link :href="route('member.library')" class="text-xs font-semibold" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div v-if="libraryBooks.length" class="flex items-center gap-2">
                        <button @click="scrollBooks('left')" class="hidden md:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-base text-gray-500 transition hover:bg-gray-50">‹</button>
                        <div ref="booksScroller" class="flex-1 overflow-x-auto hide-scrollbar">
                            <div class="flex gap-3 pb-1 min-w-full">
                                <a v-for="(book, index) in libraryBooks" :key="book.id" :href="book.file_path" target="_blank" class="group block w-[110px] shrink-0 md:w-[140px]">
                                    <div class="aspect-[2/3] overflow-hidden rounded-xl border border-gray-200 shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md">
                                        <img v-if="book.cover_image_path" :src="book.cover_image_path" :alt="book.title" class="h-full w-full object-cover">
                                        <div v-else :class="['h-full bg-gradient-to-b p-2.5', coverStyles[index % coverStyles.length]]">
                                            <p class="line-clamp-4 text-sm font-bold leading-tight">{{ book.title }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-1.5 line-clamp-2 text-xs font-semibold text-gray-700">{{ book.title }}</p>
                                </a>
                            </div>
                        </div>
                        <button @click="scrollBooks('right')" class="hidden md:inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-base text-gray-500 transition hover:bg-gray-50">›</button>
                    </div>
                    <div v-else class="rounded-2xl bg-gray-50 px-4 py-6 text-center text-xs text-gray-500">
                        Tiada buku terkini dalam pustaka.
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>

    <!-- Video Player Modal -->
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150"
        >
            <div v-if="openVideo" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" @click.self="closeVideo">
                <div class="w-full max-w-4xl">
                    <div class="relative aspect-video w-full overflow-hidden rounded-2xl bg-black shadow-2xl">
                        <iframe
                            :src="openVideo.embed_url + '?autoplay=1'"
                            class="absolute inset-0 h-full w-full"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen
                        />
                    </div>
                    <p class="mt-3 text-lg font-bold text-white">{{ openVideo.title }}</p>
                    <button @click="closeVideo" class="mt-3 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

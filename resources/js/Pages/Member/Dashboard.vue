<script setup>
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted, onUnmounted, Teleport, Transition } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const profileMenuOpen = ref(false);

const timeGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'Selamat Pagi';
    if (hour >= 12 && hour < 14) return 'Selamat Tengah Hari';
    if (hour >= 14 && hour < 18) return 'Selamat Petang';
    return 'Selamat Malam';
});
const todayDate = computed(() => {
    const d = new Date();
    return d.toLocaleDateString('ms-MY', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
});

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
    activePopup: { type: Object, default: null },
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

// Popup
const showPopup = ref(false);

onMounted(() => {
    if (props.activePopup) {
        let isDismissed = false;
        try {
            isDismissed = !!localStorage.getItem('popup_dismissed_' + props.activePopup.id);
        } catch {}
        if (!isDismissed) {
            setTimeout(() => { showPopup.value = true; }, 800);
        }
    }
});

function dismissPopup() {
    if (props.activePopup) {
        try { localStorage.setItem('popup_dismissed_' + props.activePopup.id, '1'); } catch {}
    }
    showPopup.value = false;
}

function popupButtonClick(url) {
    dismissPopup();
    if (url) window.location.href = url;
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
const bannerHovered = ref(false);
const carouselItems = computed(() => {
    if (props.banners.length) return props.banners;
    return [{ id: 0, title: 'Selamat datang ke myWAP', image_path: null }];
});
let bannerTimer;
function advanceBanner() {
    if (carouselItems.value.length > 1) {
        activeBannerIndex.value = (activeBannerIndex.value + 1) % carouselItems.value.length;
    }
}
function startBannerTimer() {
    stopBannerTimer();
    bannerTimer = setInterval(() => {
        if (!bannerHovered.value) advanceBanner();
    }, 5000);
}
function stopBannerTimer() {
    if (bannerTimer) clearInterval(bannerTimer);
}
onMounted(() => startBannerTimer());
onUnmounted(() => stopBannerTimer());

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
        <div class="min-h-screen bg-[#F5F7F6] pb-6 overflow-x-hidden">

            <!-- ═══ 1. HEADER — sticky glassmorphism ═══ -->
            <header class="sticky top-0 z-30 backdrop-blur-md bg-white/70 border-b border-gray-100/80 shadow-sm flex items-center justify-between px-4 md:px-6 py-2.5" style="padding-left: max(1rem, env(safe-area-inset-left)); padding-right: max(1rem, env(safe-area-inset-right))">
                    <div class="flex items-center gap-3 min-w-0">
                        <button @click="openDrawer" class="shrink-0 p-1.5 rounded-xl text-gray-600 hover:bg-gray-100 transition-colors" aria-label="Menu">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <h1 class="text-sm md:text-base font-bold text-gray-900 truncate">Assalamualaikum, {{ member.name }}</h1>
                            <p class="text-[10px] text-gray-400 font-medium leading-tight">{{ timeGreeting }} · {{ todayDate }}</p>
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
                        <div class="relative">
                            <button
                                @click="profileMenuOpen = !profileMenuOpen"
                                class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shrink-0 transition-colors" :class="theme.accent"
                            >
                                {{ member.name?.charAt(0)?.toUpperCase() || 'A' }}
                            </button>
                            <transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-if="profileMenuOpen"
                                    v-click-outside="() => profileMenuOpen = false"
                                    class="absolute right-0 mt-2 w-48 bg-white rounded-2xl shadow-lg border border-gray-100 py-1 z-50"
                                >
                                    <Link
                                        :href="route('profile.show')"
                                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
                                        @click="profileMenuOpen = false"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        Profil Saya
                                    </Link>
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <Link
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"
                                        @click="profileMenuOpen = false"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        Log Keluar
                                    </Link>
                                </div>
                            </transition>
                        </div>
                    </div>
                </header>

            <div class="max-w-md md:max-w-none mx-auto space-y-6 md:space-y-8 px-4 md:px-6 pt-4" style="padding-left: max(1rem, env(safe-area-inset-left)); padding-right: max(1rem, env(safe-area-inset-right))">

                <!-- Flash Messages -->
                <template v-if="$page.props.flash?.success || $page.props.flash?.error">
                    <div v-if="$page.props.flash?.success" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                        {{ $page.props.flash.success }}
                    </div>
                    <div v-if="$page.props.flash?.error" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                        {{ $page.props.flash.error }}
                    </div>
                </template>

                <!-- ═══ 2. BANNER CAROUSEL ═══ -->
                <section
                    class="relative aspect-[21/9] rounded-[28px] overflow-hidden shadow-lg group"
                    @mouseenter="bannerHovered = true"
                    @mouseleave="bannerHovered = false"
                >
                    <Transition name="banner-fade" mode="out-in">
                        <div :key="activeBannerIndex" class="absolute inset-0">
                            <template v-if="carouselItems[activeBannerIndex]">
                                <a
                                    v-if="carouselItems[activeBannerIndex].link_url"
                                    :href="carouselItems[activeBannerIndex].link_url"
                                    :target="carouselItems[activeBannerIndex].link_target || '_blank'"
                                    :rel="(carouselItems[activeBannerIndex].link_target || '_blank') === '_blank' ? 'noopener noreferrer' : undefined"
                                    class="block w-full h-full"
                                >
                                    <img v-if="carouselItems[activeBannerIndex].image_path" :src="carouselItems[activeBannerIndex].image_path" :alt="carouselItems[activeBannerIndex].title" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center" :style="{ background: `linear-gradient(135deg, ${lightGrad.from}, ${lightGrad.to})` }">
                                        <span class="text-white font-bold text-lg">{{ carouselItems[activeBannerIndex].title }}</span>
                                    </div>
                                    <div class="absolute bottom-14 left-1/2 -translate-x-1/2 z-10">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/20 backdrop-blur-sm px-3 py-1 text-[11px] font-semibold text-white border border-white/30 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                            Buka Link
                                        </span>
                                    </div>
                                </a>
                                <template v-else>
                                    <img v-if="carouselItems[activeBannerIndex].image_path" :src="carouselItems[activeBannerIndex].image_path" :alt="carouselItems[activeBannerIndex].title" class="w-full h-full object-cover" />
                                    <div v-else class="w-full h-full flex items-center justify-center" :style="{ background: `linear-gradient(135deg, ${lightGrad.from}, ${lightGrad.to})` }">
                                        <span class="text-white font-bold text-lg">{{ carouselItems[activeBannerIndex].title }}</span>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </Transition>
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
                <section class="member-card group relative overflow-hidden rounded-[28px] p-5 text-white shadow-xl" :style="{ background: `linear-gradient(135deg, ${darkGrad.from} 0%, ${darkGrad.to} 50%, ${darkGrad.from} 100%)`, boxShadow: `0 12px 40px ${darkGrad.from}66, 0 4px 12px rgba(0,0,0,0.15)` }">
                    <!-- Animated mesh gradient blobs -->
                    <div class="card-blob card-blob-1 absolute -top-16 -right-16 w-48 h-48 rounded-full opacity-20 blur-2xl pointer-events-none" :style="{ background: lightGrad.from }"></div>
                    <div class="card-blob card-blob-2 absolute -bottom-12 -left-12 w-40 h-40 rounded-full opacity-15 blur-2xl pointer-events-none" :style="{ background: lightGrad.to }"></div>
                    <div class="card-blob card-blob-3 absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-56 h-32 rounded-full opacity-10 blur-3xl pointer-events-none" :style="{ background: `linear-gradient(90deg, ${lightGrad.from}, ${lightGrad.to})` }"></div>
                    <!-- Holographic shine overlay -->
                    <div class="card-shine absolute inset-0 pointer-events-none rounded-[28px] opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                    <!-- Geometric pattern overlay -->
                    <svg class="absolute inset-0 w-full h-full opacity-[0.04] pointer-events-none" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <pattern id="card-geo" x="0" y="0" width="40" height="40" patternUnits="userSpaceOnUse">
                                <circle cx="20" cy="20" r="0.8" fill="white"/>
                                <path d="M0 20h40M20 0v40" stroke="white" stroke-width="0.15" opacity="0.5"/>
                            </pattern>
                        </defs>
                        <rect width="100%" height="100%" fill="url(#card-geo)"/>
                    </svg>
                    <!-- Arabesque corner accents -->
                    <svg class="absolute top-0 right-0 w-40 h-40 opacity-[0.06] pointer-events-none" viewBox="0 0 160 160" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M150,15 C138,8 122,6 110,12 C98,18 92,30 94,44 C96,58 104,64 116,60 C128,56 133,44 129,34 C125,24 116,20 111,26" stroke="white" stroke-width="0.8" fill="none"/>
                        <path d="M160,30 C144,32 120,38 104,28 C88,18 84,5 94,1 C104,-2 114,8 111,20" stroke="white" stroke-width="0.6" fill="none"/>
                        <circle cx="120" cy="30" r="1.2" fill="white"/>
                        <circle cx="135" cy="18" r="0.8" fill="white"/>
                        <circle cx="100" cy="38" r="1" fill="white"/>
                    </svg>
                    <svg class="absolute bottom-0 left-0 w-32 h-32 opacity-[0.04] pointer-events-none" viewBox="0 0 128 128" fill="none" xmlns="http://www.w3.org/2000/svg" style="transform: rotate(180deg);">
                        <path d="M120,12 C110,6 98,5 88,10 C78,15 74,24 75,35 C76,46 83,51 93,48 C103,45 106,35 103,27 C100,19 93,16 89,21" stroke="white" stroke-width="0.7" fill="none"/>
                        <circle cx="96" cy="24" r="1" fill="white"/>
                        <circle cx="80" cy="30" r="0.7" fill="white"/>
                    </svg>

                    <div class="relative z-10">
                        <!-- Top bar -->
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/15 backdrop-blur-md border border-white/25 shadow-sm">
                                    <img v-if="member.system_logo_path" :src="member.system_logo_path" alt="myWAP" class="h-4 w-4 object-contain">
                                    <span v-else class="text-[8px] font-black text-white/80">MW</span>
                                </div>
                                <div class="flex flex-col">
                                    <p class="text-[9px] font-bold uppercase tracking-[0.2em] text-white/50 leading-none">Kad Ahli</p>
                                    <p class="text-[10px] font-semibold text-white/70 leading-tight">myWAP</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="flex items-center gap-1.5 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-2.5 py-1">
                                    <img v-if="member.organization?.logo_path" :src="member.organization.logo_path" :alt="member.organization?.name" class="h-5 w-5 rounded-full object-cover border border-white/30">
                                    <span class="text-[10px] font-semibold text-white/90 max-w-[90px] truncate">{{ member.organization?.name || 'Organisasi' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Divider line -->
                        <div class="mt-3.5 mb-3.5 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>

                        <!-- Body -->
                        <div class="flex items-center gap-4">
                            <div class="relative shrink-0">
                                <img v-if="member.photo_url" :src="member.photo_url" alt="" class="w-[72px] h-[72px] rounded-2xl object-cover border-2 border-white/30 shadow-lg">
                                <div v-else class="w-[72px] h-[72px] rounded-2xl flex items-center justify-center text-xl font-black text-white bg-white/15 backdrop-blur-md border-2 border-white/30 shadow-lg">
                                    {{ initials(member.name) }}
                                </div>
                                <!-- Status dot -->
                                <div class="absolute -bottom-0.5 -right-0.5 p-0.5 bg-white/20 rounded-full backdrop-blur-md">
                                    <div :class="['w-3 h-3 rounded-full shadow-sm', feeStatus.status === 'active' ? 'bg-emerald-400 card-pulse-green' : 'bg-red-400 card-pulse-red']"></div>
                                </div>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-lg font-extrabold text-white truncate tracking-tight">{{ member.name }}</h3>
                                <p class="text-[11px] text-white/50 mt-0.5 font-medium">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 inline -mt-0.5 mr-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Ahli sejak {{ member.member_since || '—' }}
                                </p>
                                <div class="flex flex-wrap gap-1.5 mt-2">
                                    <span v-if="member.email" class="inline-flex items-center gap-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-2 py-0.5 text-[10px] text-white/80">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                        {{ member.email }}
                                    </span>
                                    <span v-if="member.phone" class="inline-flex items-center gap-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 px-2 py-0.5 text-[10px] text-white/80">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-white/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                        {{ member.phone }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Glassmorphism stats row -->
                        <div class="grid grid-cols-3 gap-2 mt-4">
                            <div class="backdrop-blur-xl bg-white/[0.08] rounded-2xl border border-white/[0.12] p-2.5 text-center">
                                <p class="text-[9px] text-white/45 font-semibold uppercase tracking-wider">Yuran</p>
                                <div class="flex items-center justify-center gap-1 mt-1">
                                    <div :class="['w-1.5 h-1.5 rounded-full shrink-0', feeStatus.status === 'active' ? 'bg-emerald-400' : 'bg-red-400']"></div>
                                    <p class="text-[13px] font-bold">{{ feeStatus.status === 'active' ? 'Aktif' : 'Tunggak' }}</p>
                                </div>
                            </div>
                            <div class="backdrop-blur-xl bg-white/[0.08] rounded-2xl border border-white/[0.12] p-2.5 text-center">
                                <p class="text-[9px] text-white/45 font-semibold uppercase tracking-wider">Usrah</p>
                                <p class="mt-1 text-[13px] font-bold line-clamp-1">{{ usrah?.name || '—' }}</p>
                            </div>
                            <div class="backdrop-blur-xl bg-white/[0.08] rounded-2xl border border-white/[0.12] p-2.5 text-center">
                                <p class="text-[9px] text-white/45 font-semibold uppercase tracking-wider">Sejak</p>
                                <p class="mt-1 text-[13px] font-bold">{{ member.member_since || '—' }}</p>
                            </div>
                        </div>

                        <!-- CTA -->
                        <div class="mt-4 flex items-center justify-end">
                            <Link :href="route('member.card')" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/15 px-3.5 py-1.5 text-[11px] font-semibold text-white/80 hover:text-white transition-all duration-300 group/btn">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                Lihat Kad Penuh
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 transition-transform duration-300 group-hover/btn:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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
                    <div class="flex gap-3 md:gap-4 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <button
                            v-for="item in videos"
                            :key="item.id"
                            @click="playVideo(item)"
                            class="min-w-[220px] md:min-w-0 bg-white rounded-[20px] shadow-sm overflow-hidden shrink-0 text-left transition hover:shadow-md"
                        >
                            <div class="relative aspect-video overflow-hidden bg-gray-100">
                                <img :src="item.thumbnail_url" :alt="item.title" class="absolute inset-0 w-full h-full object-cover">
                                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="ml-0.5 h-4 w-4 text-gray-900" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                </div>
                                <div class="absolute top-3 left-3 z-10">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider bg-white/90 backdrop-blur-md text-gray-800 shadow-sm">Video</span>
                                </div>
                                <div class="absolute bottom-3 left-3 right-3 z-10">
                                    <h3 class="text-sm font-bold text-white line-clamp-2 drop-shadow-sm">{{ item.title }}</h3>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- ═══ 6. KEMPEN INFAQ ═══ -->
                <section v-if="infaqItems.length">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Kempen Infaq</h2>
                                <p class="text-[11px] text-gray-500">Salurkan sumbangan anda</p>
                            </div>
                        </div>
                        <Link :href="route('infaq.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-3 md:gap-4 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in featuredInfaq" :key="`infaq-${item.id}`" class="min-w-[200px] md:min-w-0 bg-white rounded-[20px] shadow-sm overflow-hidden shrink-0">
                            <Link :href="item.public_url" class="block relative aspect-[4/3] overflow-hidden bg-gray-100 group">
                                <img v-if="item.image_path" :src="item.image_path" :alt="item.title" class="absolute inset-0 w-full h-full object-cover transition duration-300 group-hover:scale-105">
                                <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(to right, ${lightGrad.from}, ${lightGrad.to})` }"></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                <div class="absolute bottom-2 left-2 right-2 md:bottom-3 md:left-3 md:right-3 z-10">
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 md:px-2 md:py-0.5 text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur-md text-white border border-white/20">
                                        {{ item.type === 'progress' ? 'Kutipan Dana' : 'Derma Bebas' }}
                                    </span>
                                    <h3 class="text-xs md:text-sm font-bold text-white mt-1 line-clamp-1 group-hover:underline">{{ item.title }}</h3>
                                </div>
                            </Link>
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
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Program</h2>
                                <p class="text-[11px] text-gray-500">Acara dan aktiviti akan datang</p>
                            </div>
                        </div>
                        <Link :href="route('events.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div v-if="upcomingEvents.length" class="flex gap-3 md:gap-4 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="event in upcomingEvents" :key="`event-${event.id}`" class="w-[170px] h-[230px] md:w-auto md:h-auto md:min-w-0 bg-white rounded-[20px] shadow-sm overflow-hidden shrink-0">
                            <Link :href="route('events.index')" class="flex flex-col h-full md:block md:h-auto">
                                <div class="relative h-[100px] md:aspect-[16/9] w-full overflow-hidden bg-gray-100 shrink-0">
                                    <img v-if="event.featured_image_url && !event.featured_image_url.includes('placehold.co')" :src="event.featured_image_url" :alt="event.title" class="absolute inset-0 w-full h-full object-cover">
                                    <div v-else class="absolute inset-0" :style="{ background: `linear-gradient(135deg, ${lightGrad.from}, ${lightGrad.to})` }"></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>
                                    <div class="absolute top-2 left-2 md:top-3 md:left-3 z-10">
                                        <span class="inline-flex rounded-full px-1.5 py-0.5 md:px-2 md:py-0.5 text-[10px] font-bold uppercase tracking-wide bg-white/90 backdrop-blur-md text-gray-800 border border-white/20 shadow-sm">
                                            {{ event.type === 'physical' ? 'Fizikal' : 'Online' }}
                                        </span>
                                    </div>
                                    <div class="absolute bottom-2 left-2 right-2 md:bottom-3 md:left-3 md:right-3 z-10 hidden md:block">
                                        <h3 class="text-xs md:text-sm font-bold text-white line-clamp-2 drop-shadow-sm">{{ event.title }}</h3>
                                    </div>
                                </div>
                                <div class="flex flex-col flex-1 px-3 pt-2 pb-3 md:px-4 md:pt-3 md:pb-4">
                                    <h3 class="text-xs font-bold text-gray-900 leading-snug line-clamp-2 block md:hidden mb-1.5">{{ event.title }}</h3>
                                    <div class="block md:hidden space-y-0.5">
                                        <div class="text-[11px] text-gray-500">{{ event.start_formatted }}</div>
                                        <div class="text-[11px] text-gray-400 truncate">{{ event.type === 'physical' ? (event.location_or_link || 'Fizikal') : 'Online' }}</div>
                                    </div>
                                    <div class="hidden md:flex items-center gap-2 text-[13px] text-gray-500">
                                        <span>{{ event.start_formatted }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span class="truncate">{{ event.type === 'physical' ? (event.location_or_link || 'Fizikal') : 'Online' }}</span>
                                    </div>
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
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500 to-blue-600 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Berita Untuk Anda</h2>
                                <p class="text-[11px] text-gray-500">Ikuti perkembangan terkini</p>
                            </div>
                        </div>
                        <Link :href="route('news.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Buka Feed</Link>
                    </div>
                    <div class="flex gap-3 md:gap-4 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in featuredNews" :key="`news-${item.id}`" class="min-w-[200px] md:min-w-0 bg-white rounded-[20px] shadow-sm overflow-hidden shrink-0">
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
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-violet-500 to-purple-700 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Artikel</h2>
                                <p class="text-[11px] text-gray-500">Ilmu dan informasi bermanfaat</p>
                            </div>
                        </div>
                        <Link :href="route('articles.index')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
                    </div>
                    <div class="flex gap-3 md:gap-4 overflow-x-auto md:grid md:grid-cols-2 lg:grid-cols-3 md:overflow-visible pb-1 hide-scrollbar">
                        <article v-for="item in latestArticles" :key="`article-${item.id}`" class="w-[170px] h-[220px] md:w-auto md:h-auto md:min-w-0 bg-white rounded-[20px] shadow-sm overflow-hidden shrink-0">
                            <Link :href="route('articles.show', item.slug)" class="flex flex-col h-full md:block md:h-auto">
                                <div class="relative h-[100px] md:aspect-[16/9] w-full overflow-hidden bg-gray-100 shrink-0">
                                    <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="absolute inset-0 w-full h-full object-cover">
                                </div>
                                <div class="flex flex-col flex-1 px-3 pt-2 pb-3 md:px-4 md:pt-3 md:pb-4">
                                    <h4 class="text-xs font-bold text-gray-900 leading-snug line-clamp-2 block md:hidden mb-1">{{ item.title }}</h4>
                                    <p v-if="item.excerpt" class="text-[11px] text-gray-500 line-clamp-1 block md:hidden">{{ item.excerpt }}</p>
                                    <div class="hidden md:block">
                                        <div v-if="item.organization_name" class="text-[11px] font-semibold uppercase tracking-wider" :class="theme.accentText">{{ item.organization_name }}</div>
                                        <h4 class="mt-1 line-clamp-2 text-sm font-bold text-gray-900 leading-snug">{{ item.title }}</h4>
                                        <p v-if="item.excerpt" class="mt-1 text-[13px] text-gray-500 line-clamp-1">{{ item.excerpt }}</p>
                                    </div>
                                </div>
                            </Link>
                        </article>
                    </div>
                </section>

                <!-- ═══ 13. PUSTAKA ═══ -->
                <section>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-br from-teal-500 to-cyan-600 shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold text-gray-900">Pustaka</h2>
                                <p class="text-[11px] text-gray-500">Koleksi buku dan rujukan</p>
                            </div>
                        </div>
                        <Link :href="route('member.library')" class="text-xs font-semibold shrink-0" :class="theme.accentText">Lihat Semua</Link>
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

    <Teleport to="body">
        <Transition name="popup-fade">
            <div v-if="showPopup && activePopup" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 p-4" @click.self="dismissPopup">
                <div :class="['relative w-full bg-white rounded-2xl shadow-2xl overflow-hidden max-h-[90vh] overflow-y-auto', activePopup.popup_size === 'sm' ? 'max-w-sm' : activePopup.popup_size === 'lg' ? 'max-w-2xl' : 'max-w-lg']">
                    <button @click="dismissPopup" class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-black/20 backdrop-blur-md flex items-center justify-center text-white hover:bg-black/40 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <img v-if="activePopup.image_path" :src="activePopup.image_path" :alt="activePopup.title" class="w-full aspect-[2/1] object-cover">

                    <div :class="['px-6 pb-6', activePopup.image_path ? 'pt-4' : 'pt-6']">
                        <h3 class="text-lg font-bold text-gray-900">{{ activePopup.title }}</h3>
                        <p v-if="activePopup.content" class="mt-2 text-sm text-gray-600 leading-relaxed" v-html="activePopup.content.replace(/\n/g, '<br>')"></p>

                        <div v-if="activePopup.button_text || activePopup.button_text_2" class="mt-5 flex flex-wrap gap-3">
                            <a v-if="activePopup.button_text" :href="activePopup.button_url || '#'" @click.prevent="popupButtonClick(activePopup.button_url)" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">
                                {{ activePopup.button_text }}
                            </a>
                            <a v-if="activePopup.button_text_2" :href="activePopup.button_url_2 || '#'" @click.prevent="popupButtonClick(activePopup.button_url_2)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                {{ activePopup.button_text_2 }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.banner-fade-enter-active,
.banner-fade-leave-active {
    transition: opacity 0.6s ease;
}
.banner-fade-enter-from,
.banner-fade-leave-to {
    opacity: 0;
}

/* Card blob orbit animations */
.card-blob-1 {
    animation: blob-orbit-1 8s ease-in-out infinite;
}
.card-blob-2 {
    animation: blob-orbit-2 10s ease-in-out infinite;
}
.card-blob-3 {
    animation: blob-orbit-3 12s ease-in-out infinite;
}
@keyframes blob-orbit-1 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(-20px, 15px) scale(1.1); }
    66% { transform: translate(10px, -10px) scale(0.95); }
}
@keyframes blob-orbit-2 {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(15px, -20px) scale(1.05); }
    66% { transform: translate(-10px, 10px) scale(1.1); }
}
@keyframes blob-orbit-3 {
    0%, 100% { transform: translate(-50%, -50%) scale(1); opacity: 0.1; }
    50% { transform: translate(-50%, -50%) scale(1.3); opacity: 0.15; }
}

/* Holographic shine sweep */
.card-shine {
    background: linear-gradient(
        105deg,
        transparent 30%,
        rgba(255,255,255,0.06) 45%,
        rgba(255,255,255,0.12) 50%,
        rgba(255,255,255,0.06) 55%,
        transparent 70%
    );
    background-size: 200% 100%;
    animation: shine-sweep 3s ease-in-out infinite;
}
@keyframes shine-sweep {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Status dot pulse */
.card-pulse-green {
    animation: pulse-green 2s ease-in-out infinite;
}
.card-pulse-red {
    animation: pulse-red 1.5s ease-in-out infinite;
}
@keyframes pulse-green {
    0%, 100% { box-shadow: 0 0 0 0 rgba(52, 211, 153, 0.5); }
    50% { box-shadow: 0 0 0 4px rgba(52, 211, 153, 0); }
}
@keyframes pulse-red {
    0%, 100% { box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.5); }
    50% { box-shadow: 0 0 0 4px rgba(248, 113, 113, 0); }
}

.popup-fade-enter-active { transition: opacity 0.3s ease; }
.popup-fade-leave-active { transition: opacity 0.2s ease; }
.popup-fade-enter-from,
.popup-fade-leave-to { opacity: 0; }
</style>

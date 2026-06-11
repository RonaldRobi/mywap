<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const props = defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
    banners: Array,
    infaq: Array,
    news: Array,
    articles: Array,
    organizations: Array,
});

const isMobileMenuOpen = ref(false);
const toggleMobileMenu = () => isMobileMenuOpen.value = !isMobileMenuOpen.value;

const currentSlide = ref(0);
let slideInterval = null;

const nextSlide = () => {
    if (props.banners && props.banners.length > 0) {
        currentSlide.value = (currentSlide.value + 1) % props.banners.length;
    }
};

const prevSlide = () => {
    if (props.banners && props.banners.length > 0) {
        currentSlide.value = currentSlide.value === 0 ? props.banners.length - 1 : currentSlide.value - 1;
    }
};

const setSlide = (index) => {
    currentSlide.value = index;
};

// Intersection Observer for Scroll Animations
const setupScrollAnimations = () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('reveal-visible');
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    document.querySelectorAll('.reveal-on-scroll').forEach((el) => {
        observer.observe(el);
    });
};

onMounted(() => {
    if (props.banners && props.banners.length > 1) {
        slideInterval = setInterval(nextSlide, 5000);
    }
    // Small timeout to ensure DOM elements are rendered
    setTimeout(setupScrollAnimations, 100);
});

onUnmounted(() => {
    if (slideInterval) clearInterval(slideInterval);
});
</script>

<template>
    <Head title="Welcome to myWAP" />
    
    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 selection:bg-emerald-500 selection:text-white pb-16 md:pb-0">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex h-16 items-center justify-between">
                    <div class="flex items-center">
                        <Link href="/" class="flex items-center">
                            <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-10 w-auto" />
                        </Link>
                        <div class="hidden md:ml-10 md:flex md:space-x-8">
                            <a href="#home" class="text-slate-600 hover:text-emerald-600 px-3 py-2 text-sm font-medium transition-colors">Home</a>
                            <a href="#infaq" class="text-slate-600 hover:text-emerald-600 px-3 py-2 text-sm font-medium transition-colors">Infaq</a>
                            <a href="#artikel" class="text-slate-600 hover:text-emerald-600 px-3 py-2 text-sm font-medium transition-colors">Artikel</a>
                        </div>
                    </div>
                    <div class="hidden md:flex md:items-center md:space-x-4">
                        <template v-if="$page.props.auth?.user">
                            <Link :href="route('dashboard')" class="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-emerald-700 transition">
                                Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')" class="text-sm font-semibold text-slate-700 hover:text-emerald-600 transition">
                                Log Masuk
                            </Link>
                            <Link v-if="canRegister" :href="route('register')" class="rounded-full bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white shadow hover:bg-emerald-700 transition">
                                Daftar
                            </Link>
                        </template>
                    </div>
                    <div class="-mr-2 flex md:hidden">
                        <button @click="toggleMobileMenu" type="button" class="inline-flex items-center justify-center rounded-md p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-500 focus:outline-none">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Mobile Menu Overlay -->
        <transition
            enter-active-class="transition-opacity duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="isMobileMenuOpen" class="fixed inset-0 z-[100] bg-slate-900/50 backdrop-blur-sm md:hidden" @click="toggleMobileMenu"></div>
        </transition>

        <!-- Mobile menu Drawer -->
        <transition
            enter-active-class="transition duration-300 ease-out transform"
            enter-from-class="translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition duration-300 ease-in transform"
            leave-from-class="translate-x-0"
            leave-to-class="translate-x-full"
        >
            <div v-if="isMobileMenuOpen" class="fixed inset-y-0 right-0 z-[110] w-72 bg-white shadow-2xl md:hidden flex flex-col">
                <div class="flex h-16 items-center justify-between px-4 border-b border-slate-100 shrink-0">
                    <span class="font-bold text-slate-800 ml-2">Menu Lanjut</span>
                    <button @click="toggleMobileMenu" type="button" class="inline-flex items-center justify-center rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-500 focus:outline-none transition">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="space-y-1 px-4 py-6 flex-1 overflow-y-auto">
                    <a href="#home" @click="toggleMobileMenu" class="block rounded-lg px-4 py-3 text-base font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Home</a>
                    <a href="#info" @click="toggleMobileMenu" class="block rounded-lg px-4 py-3 text-base font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Profil Organisasi</a>
                    <a href="#infaq" @click="toggleMobileMenu" class="block rounded-lg px-4 py-3 text-base font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Kempen Infaq</a>
                    <a href="#artikel" @click="toggleMobileMenu" class="block rounded-lg px-4 py-3 text-base font-medium text-slate-700 hover:bg-emerald-50 hover:text-emerald-700 transition">Artikel & Berita</a>
                </div>
                <div class="border-t border-slate-100 p-6 shrink-0 bg-slate-50">
                    <div class="flex flex-col gap-3">
                        <template v-if="$page.props.auth?.user">
                            <Link :href="route('dashboard')" class="block w-full rounded-full bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white shadow hover:bg-emerald-700 transition">Dashboard Sistem</Link>
                        </template>
                        <template v-else>
                            <Link v-if="canLogin" :href="route('login')" class="block w-full rounded-full border border-slate-200 bg-white px-4 py-3 text-center text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition">Log Masuk</Link>
                            <Link v-if="canRegister" :href="route('register')" class="block w-full rounded-full bg-emerald-600 px-4 py-3 text-center text-sm font-bold text-white shadow hover:bg-emerald-700 transition">Daftar Ahli Baru</Link>
                        </template>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Bottom Navigation Bar (Mobile Only) -->
        <div class="fixed bottom-0 left-0 right-0 z-50 bg-white border-t border-slate-200 md:hidden pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
            <div class="flex justify-around items-center h-16">
                <a href="#home" class="flex flex-col items-center justify-center w-full h-full text-slate-500 hover:text-emerald-600 transition-colors">
                    <svg class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <span class="text-[10px] font-bold">Home</span>
                </a>
                <Link href="/sumbangan" class="flex flex-col items-center justify-center w-full h-full text-slate-500 hover:text-emerald-600 transition-colors">
                    <svg class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    <span class="text-[10px] font-bold">Infaq</span>
                </Link>
                <Link href="/artikel" class="flex flex-col items-center justify-center w-full h-full text-slate-500 hover:text-emerald-600 transition-colors">
                    <svg class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5L18.5 7H20a2 2 0 012 2v10a2 2 0 01-2 2z"/></svg>
                    <span class="text-[10px] font-bold">Artikel</span>
                </Link>
                <template v-if="$page.props.auth?.user">
                    <Link :href="route('dashboard')" class="flex flex-col items-center justify-center w-full h-full text-emerald-600 hover:text-emerald-700 transition-colors">
                        <svg class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <span class="text-[10px] font-bold">Menu</span>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="route('login')" class="flex flex-col items-center justify-center w-full h-full text-emerald-600 hover:text-emerald-700 transition-colors">
                        <svg class="h-6 w-6 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/></svg>
                        <span class="text-[10px] font-bold">Log Masuk</span>
                    </Link>
                </template>
            </div>
        </div>

        <!-- Hero Section -->
        <main id="home" class="bg-slate-50 pt-8 pb-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <!-- Banner Slider Box -->
                <div class="relative overflow-hidden rounded-3xl shadow-2xl bg-slate-200 aspect-[16/9] md:aspect-[21/9] flex items-center group ring-1 ring-slate-900/5">
                    <!-- Slider Backgrounds -->
                    <div class="absolute inset-0">
                        <template v-if="banners && banners.length > 0">
                            <transition-group name="fade" tag="div" class="w-full h-full">
                                <div v-for="(banner, index) in banners" :key="banner.id || index" v-show="currentSlide === index" class="absolute inset-0 w-full h-full">
                                    <img :src="banner.image_path" class="w-full h-full object-cover" alt="Banner" />
                                </div>
                            </transition-group>
                        </template>
                        <div v-else class="absolute inset-0 w-full h-full bg-slate-100 flex items-center justify-center text-slate-400">
                            <span class="text-sm font-medium">Tiada Banner</span>
                        </div>
                    </div>

                    <!-- Slider Controls -->
                    <button v-if="banners && banners.length > 1" @click="prevSlide" class="absolute left-4 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-white/40 hover:bg-white/80 text-slate-800 backdrop-blur-sm transition-all duration-300 opacity-0 group-hover:opacity-100 hidden md:block shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                    </button>
                    <button v-if="banners && banners.length > 1" @click="nextSlide" class="absolute right-4 top-1/2 -translate-y-1/2 z-20 p-2.5 rounded-full bg-white/40 hover:bg-white/80 text-slate-800 backdrop-blur-sm transition-all duration-300 opacity-0 group-hover:opacity-100 hidden md:block shadow-sm">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" /></svg>
                    </button>

                    <!-- Slider Indicators -->
                    <div v-if="banners && banners.length > 1" class="absolute bottom-6 left-0 right-0 flex justify-center gap-2 z-20">
                        <button v-for="(banner, index) in banners" :key="`ind-${index}`" @click="setSlide(index)" :class="[currentSlide === index ? 'bg-white w-8 shadow' : 'bg-white/60 w-2.5 hover:bg-white/90', 'h-2.5 rounded-full transition-all duration-300']" aria-label="Tukar slaid"></button>
                    </div>
                </div>


            </div>
        </main>

        <!-- Info / Profile Section -->
        <section id="info" class="py-20 bg-white scroll-mt-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:items-center lg:gap-16">
                    <div class="reveal-on-scroll">
                        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                            Penyelesaian Digital Holistik
                        </h2>
                        <p class="mt-6 text-lg text-slate-600 leading-relaxed">
                            Platform ini merupakan sebuah penyelesaian digital holistik yang direka khas untuk menguruskan kitaran hayat ahli merentasi tiga wadah utama: <strong>PKPIM</strong> (Persatuan Kebangsaan Pelajar Islam Malaysia), <strong>ABIM</strong> (Angkatan Belia Islam Malaysia), dan <strong>WADAH</strong> (Wadah Pencerdasan Umat Malaysia).
                        </p>
                        <p class="mt-4 text-lg text-slate-600 leading-relaxed">
                            Sistem ini berfungsi sebagai jambatan digital yang memastikan tiada ahli yang "tercicir" apabila mereka melepasi had umur tertentu, sekali gus mengekalkan momentum perkaderan, kesetiaan ahli, dan kesinambungan perjuangan gerakan secara berterusan.
                        </p>
                    </div>
                    <div class="mt-10 lg:mt-0 grid grid-cols-1 gap-6 sm:grid-cols-3 lg:grid-cols-1">
                        <div v-for="(org, index) in organizations" :key="org.id" class="reveal-on-scroll rounded-2xl bg-slate-50 p-6 border border-slate-100 shadow-sm flex items-center gap-5 transition hover:shadow-md" :style="`transition-delay: ${index * 150}ms`">
                            <div v-if="org.logo_path" class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-white border border-slate-200 overflow-hidden p-2">
                                <img :src="org.logo_path" class="w-full h-full object-contain" :alt="org.name">
                            </div>
                            <div v-else class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-white font-black text-2xl" :style="{ backgroundColor: org.color_theme || '#10b981' }">
                                {{ org.name.charAt(0) }}
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-900 text-lg uppercase">{{ org.name }}</h3>
                                <p class="text-sm text-slate-500 font-medium">
                                    Peringkat Umur: {{ org.min_age }} {{ org.max_age ? ' - ' + org.max_age : 'ke atas' }} tahun
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Infaq Section -->
        <section id="infaq" class="py-20 bg-slate-50 border-t border-slate-100 scroll-mt-16 overflow-hidden">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center reveal-on-scroll">
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Infaq & Sumbangan
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">
                        Sokong perjuangan kami melalui pelbagai kempen infaq yang sedang berjalan.
                    </p>
                </div>

                <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(item, index) in infaq" :key="item.id" class="reveal-on-scroll flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-slate-100 transition hover:-translate-y-1 hover:shadow-[0_8px_30px_-4px_rgba(0,0,0,0.1)]" :style="`transition-delay: ${index * 150}ms`">
                        <div class="relative h-48 shrink-0">
                            <img v-if="item.image_path" :src="item.image_path" class="h-full w-full object-cover" :alt="item.title" />
                            <div v-else class="h-full w-full bg-emerald-50 flex items-center justify-center text-emerald-200">
                                <svg class="h-16 w-16" fill="currentColor" viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col justify-between p-6">
                            <div class="flex-1">
                                <h3 class="text-xl font-bold text-slate-900 line-clamp-2">
                                    {{ item.title }}
                                </h3>
                                <p class="mt-3 text-sm text-slate-500 line-clamp-3">
                                    {{ item.description }}
                                </p>
                            </div>
                            <div class="mt-6">
                                <div v-if="item.target_amount > 0" class="mb-5">
                                    <div class="flex justify-between text-xs font-bold text-slate-700 mb-1.5">
                                        <span class="text-emerald-600">Terkumpul: RM {{ parseFloat(item.collected_amount).toLocaleString() }}</span>
                                        <span>Sasaran: RM {{ parseFloat(item.target_amount).toLocaleString() }}</span>
                                    </div>
                                    <div class="w-full bg-slate-100 rounded-full h-2.5">
                                        <div class="bg-emerald-500 h-2.5 rounded-full transition-all duration-1000" :style="`width: ${item.progress_percent}%`"></div>
                                    </div>
                                </div>
                                <Link :href="item.public_url" class="block w-full rounded-xl bg-slate-900 px-4 py-3 text-center text-sm font-bold text-white transition shadow-sm hover:bg-slate-800 hover:shadow relative z-20">
                                    Sumbang Sekarang
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="!infaq || infaq.length === 0" class="reveal-on-scroll mt-12 rounded-xl bg-white p-8 text-center text-slate-500 border border-slate-100 shadow-sm">
                    <p class="text-lg">Tiada kempen infaq yang aktif pada masa ini.</p>
                </div>
                <div v-else class="reveal-on-scroll mt-12 text-center">
                    <Link href="/sumbangan" class="inline-flex items-center justify-center rounded-full bg-white px-8 py-3.5 text-sm font-bold text-emerald-700 shadow-sm border border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 transition">
                        Lihat Semua Infaq
                        <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Artikel / News Section -->
        <section id="artikel" class="py-20 bg-white border-t border-slate-100 scroll-mt-16 overflow-hidden">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center reveal-on-scroll">
                    <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
                        Artikel & Info Terkini
                    </h2>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-slate-600">
                        Perkembangan terkini, berita dan artikel dakwah dari myWAP.
                    </p>
                </div>

                <div class="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="(article, index) in articles" :key="article.id" 
                         class="reveal-on-scroll group flex flex-col overflow-hidden rounded-2xl bg-white shadow-[0_4px_20px_-4px_rgba(0,0,0,0.05)] border border-slate-100 transition-all duration-500 hover:-translate-y-2 hover:shadow-[0_12px_40px_-10px_rgba(16,185,129,0.15)]" 
                         :style="`transition-delay: ${index * 150}ms`">
                        <Link :href="route('articles.show', article.slug)" class="block shrink-0 relative h-56 overflow-hidden">
                            <img v-if="article.cover_image_path" :src="article.cover_image_path" class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-110" :alt="article.title" />
                            <div v-else class="h-full w-full bg-slate-100 flex items-center justify-center text-slate-300">
                                <svg class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5L18.5 7H20a2 2 0 012 2v10a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-0 transition-opacity duration-500 group-hover:opacity-100"></div>
                        </Link>
                        <div class="flex flex-1 flex-col justify-between p-6">
                            <div class="flex-1 transform transition-all duration-500">
                                <p class="text-[11px] font-bold tracking-wider uppercase text-emerald-600 mb-2">{{ article.published_at }}</p>
                                <Link :href="route('articles.show', article.slug)" class="block">
                                    <h3 class="text-xl font-bold text-slate-900 line-clamp-2 group-hover:text-emerald-600 transition-colors duration-300">
                                        {{ article.title }}
                                    </h3>
                                    <p class="mt-3 text-sm text-slate-600 line-clamp-3 leading-relaxed">
                                        {{ article.excerpt }}
                                    </p>
                                </Link>
                            </div>
                            <div class="mt-6 pt-4 border-t border-slate-100 flex items-center transform transition-transform duration-500 group-hover:translate-x-1">
                                <Link :href="route('articles.show', article.slug)" class="inline-flex items-center text-sm font-bold text-emerald-600 hover:text-emerald-700 transition">
                                    Baca seterusnya
                                    <svg class="ml-1.5 h-4 w-4 transition-transform duration-300 group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="!articles || articles.length === 0" class="mt-12 rounded-xl bg-slate-50 p-8 text-center text-slate-500 border border-slate-100">
                    <p class="text-lg">Tiada artikel diterbitkan pada masa ini.</p>
                </div>
                <div v-else class="reveal-on-scroll mt-12 text-center">
                    <Link href="/artikel" class="inline-flex items-center justify-center rounded-full bg-white px-8 py-3.5 text-sm font-bold text-emerald-700 shadow-sm border border-emerald-200 hover:bg-emerald-50 hover:text-emerald-800 transition">
                        Lihat Semua Artikel
                        <svg class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-slate-950 py-12 text-slate-400 text-center">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-12 w-auto mx-auto mb-4 brightness-0 invert opacity-90" />
                <p class="text-sm mb-8 text-slate-500 max-w-md mx-auto">
                    Sistem Pengurusan Kitaran Hayat Ahli merentasi PKPIM, ABIM, dan WADAH.
                </p>
                <div class="border-t border-slate-800 pt-8 flex flex-col md:flex-row items-center justify-between">
                    <p class="text-sm">
                        &copy; {{ new Date().getFullYear() }} myWAP. Hak Cipta Terpelihara.
                    </p>
                    <div class="mt-4 md:mt-0 flex gap-4">
                        <a href="#" class="text-slate-500 hover:text-white transition">Terma & Syarat</a>
                        <a href="#" class="text-slate-500 hover:text-white transition">Privasi</a>
                    </div>
                </div>
            </div>
        </footer>
    </div>
</template>

<style>
html { scroll-behavior: smooth; }

.fade-enter-active,
.fade-leave-active {
  transition: opacity 1s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Scroll Reveal Animations */
.reveal-on-scroll {
    opacity: 0;
    transform: translateY(40px);
    transition: opacity 0.8s cubic-bezier(0.5, 0, 0, 1), transform 0.8s cubic-bezier(0.5, 0, 0, 1);
    will-change: opacity, transform;
}

.reveal-visible {
    opacity: 1;
    transform: translateY(0);
}
</style>

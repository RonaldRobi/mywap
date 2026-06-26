<script setup>
import { computed, ref } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    infaq: {
        type: Object,
        required: true,
    },
    recentDonations: {
        type: Array,
        default: () => [],
    },
    relatedInfaqs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const systemLogo = computed(() => page.props.brand?.system_logo_path ?? null);

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}

function formatCompact(value) {
    if (!value) return '0';
    if (value >= 1000000) return (value / 1000000).toFixed(1) + 'J';
    if (value >= 1000) return (value / 1000).toFixed(1) + 'K';
    return value.toString();
}

const ogTitle = computed(() => props.infaq.title);
const ogDescription = computed(() => (props.infaq.description || 'Jom menyumbang bersama kami.').substring(0, 200));
const ogImage = computed(() => props.infaq.image_path || '');

const qrUrl = computed(() => {
    return route('infaq.qr', {
        year: props.infaq.year || new Date().getFullYear(),
        month: props.infaq.month || String(new Date().getMonth() + 1).padStart(2, '0'),
        day: props.infaq.day || String(new Date().getDate()).padStart(2, '0'),
        infaq: props.infaq.slug || props.infaq.id,
    });
});

const sharePreviewUrl = computed(() => {
    return route('share.infaq', props.infaq.slug || props.infaq.id, true);
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

const showFaq = ref(false);
</script>

<template>
    <Head :title="infaq.title">
        <meta property="og:title" :content="ogTitle" />
        <meta property="og:description" :content="ogDescription" />
        <meta property="og:image" :content="ogImage" />
        <meta property="og:url" :content="sharePreviewUrl" />
        <meta property="og:type" content="website" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="ogTitle" />
        <meta name="twitter:description" :content="ogDescription" />
        <meta name="twitter:image" :content="ogImage" />
    </Head>

    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-28 md:pb-0">
        <!-- Minimal Navigation -->
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center">
                    <img v-if="systemLogo" :src="systemLogo" alt="Logo" class="h-8 w-auto" />
                    <span v-else class="text-lg font-black text-slate-800">myWAP</span>
                </Link>
                <div class="flex items-center gap-3">
                    <Link href="/#infaq" class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                        Semua Kempen
                    </Link>
                </div>
            </div>
        </nav>

        <div class="mx-auto max-w-5xl px-4 py-6 md:py-8 md:px-6 space-y-6">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Main Content -->
                <div class="w-full lg:w-2/3 space-y-8">
                    <!-- Infaq Details Card -->
                    <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                        <!-- Hero Image with Impact Stats Overlay -->
                        <div class="bg-gray-100 w-full relative">
                            <div v-if="infaq.image_path" class="aspect-[16/9] md:aspect-[16/7] w-full">
                                <img :src="infaq.image_path" :alt="infaq.title" class="h-full w-full object-cover">
                            </div>
                            <div v-else class="aspect-[16/9] md:aspect-[16/7] w-full bg-gradient-to-br from-emerald-600 to-emerald-900 flex items-center justify-center">
                                <svg class="w-20 h-20 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 via-slate-900/30 to-transparent"></div>

                            <!-- Hero Content Overlay -->
                            <div class="absolute bottom-0 left-0 right-0 p-6 md:p-8">
                                <div class="flex flex-wrap gap-2 mb-3">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider backdrop-blur-sm shadow-sm"
                                        :class="infaq.type === 'progress' ? 'bg-emerald-500/90 text-white' : 'bg-indigo-500/90 text-white'">
                                        {{ infaq.type === 'progress' ? 'Kempen Kutipan Dana' : 'Derma Bebas' }}
                                    </span>
                                    <span v-if="infaq.allow_recurring" class="inline-flex rounded-full bg-purple-500/90 text-white px-3 py-1 text-xs font-bold uppercase tracking-wider backdrop-blur-sm">
                                        Boleh Recurring
                                    </span>
                                    <span class="inline-flex rounded-full bg-white/20 backdrop-blur-sm text-white px-3 py-1 text-xs font-bold">
                                        {{ infaq.days_running }} Hari Berjalan
                                    </span>
                                </div>
                                <h1 class="text-2xl md:text-4xl font-black text-white leading-tight drop-shadow-lg mb-4">{{ infaq.title }}</h1>

                                <!-- Quick Stats in Hero -->
                                <div class="flex flex-wrap gap-6 md:gap-8">
                                    <div>
                                        <p class="text-xs font-bold text-white/60 uppercase tracking-wider">Terkumpul</p>
                                        <p class="text-xl md:text-2xl font-black text-white">{{ formatCurrency(infaq.collected_amount) }}</p>
                                    </div>
                                    <div v-if="infaq.type === 'progress' && infaq.target_amount">
                                        <p class="text-xs font-bold text-white/60 uppercase tracking-wider">Matlamat</p>
                                        <p class="text-xl md:text-2xl font-black text-white">{{ formatCurrency(infaq.target_amount) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-white/60 uppercase tracking-wider">Penyumbang</p>
                                        <p class="text-xl md:text-2xl font-black text-white">{{ infaq.total_donors }} Orang</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Progress Bar (below image, full width) -->
                        <div v-if="infaq.type === 'progress' && infaq.target_amount" class="px-6 md:px-8 pt-4">
                            <div class="flex items-center justify-between text-sm font-semibold text-slate-500 mb-1.5">
                                <span class="text-emerald-600 font-black">{{ infaq.progress_percent }}%</span>
                                <span>Dari matlamat</span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500 transition-all duration-1000" :style="{ width: infaq.progress_percent + '%' }"></div>
                            </div>
                        </div>

                        <div class="p-6 md:p-8">
                            <!-- Trust Badges Strip -->
                            <div class="flex flex-wrap items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Transaksi Selamat
                                </div>
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                    Disalurkan Terus
                                </div>
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-500">
                                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Resit Disediakan
                                </div>
                            </div>

                            <!-- Organization Info Bar -->
                            <div class="flex flex-wrap items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                                <div v-if="infaq.organization_logo" class="shrink-0">
                                    <img :src="infaq.organization_logo" :alt="infaq.organization_name" class="h-12 w-12 rounded-xl object-contain border border-slate-200 p-1" />
                                </div>
                                <div v-else class="h-12 w-12 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600 shrink-0">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Dianjurkan Oleh</p>
                                    <p class="text-base font-bold text-slate-800 truncate">{{ infaq.organization_name }}</p>
                                </div>
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Penyumbang</p>
                                        <p class="text-base font-bold text-slate-800">{{ infaq.total_donors }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <div class="text-right">
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Tempoh</p>
                                        <p class="text-base font-bold text-slate-800">{{ infaq.days_running }} Hari</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <h2 class="text-xl font-black text-slate-900 mb-4">Tentang Kempen Ini</h2>
                            <p class="text-base leading-relaxed text-slate-600 whitespace-pre-line">{{ infaq.description || 'Tiada maklumat lanjut buat masa ini.' }}</p>

                            <!-- Impact Section -->
                            <div class="mt-8 p-5 rounded-2xl bg-gradient-to-br from-emerald-50 to-emerald-100/50 border border-emerald-200">
                                <h3 class="font-black text-emerald-900 mb-3 flex items-center gap-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                    Impak Sumbangan Anda
                                </h3>
                                <p class="text-sm text-emerald-800 leading-relaxed">
                                    Setiap ringgit yang disumbangkan akan disalurkan sepenuhnya kepada program-program yang telah dirancang. 
                                    Kami komited untuk memastikan ketelusan dalam setiap perbelanjaan dan akan memberikan laporan berkala 
                                    kepada para penyumbang. Semoga setiap sumbangan menjadi ladang pahala yang berpanjangan.
                                </p>
                            </div>

                            <!-- FAQ Section -->
                            <div class="mt-8 pt-6 border-t border-slate-100">
                                <button @click="showFaq = !showFaq" class="flex items-center justify-between w-full text-left">
                                    <h3 class="font-black text-slate-900">Soalan Lazim</h3>
                                    <svg class="h-5 w-5 text-slate-400 transition" :class="showFaq ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div v-if="showFaq" class="mt-4 space-y-4">
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="font-bold text-slate-800 text-sm mb-1">Bagaimana saya tahu sumbangan saya sampai?</p>
                                        <p class="text-sm text-slate-600">Kami akan menghantar kemas kini berkala melalui emel. Anda juga boleh melihat jumlah terkumpul dikemaskini secara langsung di halaman ini.</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="font-bold text-slate-800 text-sm mb-1">Adakah saya akan menerima resit?</p>
                                        <p class="text-sm text-slate-600">Ya, resit digital akan dihantar ke emel yang anda daftarkan selepas pembayaran berjaya.</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="font-bold text-slate-800 text-sm mb-1">Bolehkah saya sumbang secara anonymous?</p>
                                        <p class="text-sm text-slate-600">Ya, anda boleh tandakan pilihan "Jangan paparkan nama saya" semasa mengisi maklumat sumbangan.</p>
                                    </div>
                                    <div v-if="infaq.allow_recurring" class="rounded-xl bg-slate-50 p-4">
                                        <p class="font-bold text-slate-800 text-sm mb-1">Bagaimana sumbangan bulanan berfungsi?</p>
                                        <p class="text-sm text-slate-600">Anda akan membuat pengesahan autodebit sekali melalui FPX. Selepas itu, jumlah yang sama akan didebit secara automatik setiap bulan tanpa perlu masuk semula. Anda boleh berhenti bila-bila masa dengan menghubungi pihak pengurusan.</p>
                                    </div>
                                    <div class="rounded-xl bg-slate-50 p-4">
                                        <p class="font-bold text-slate-800 text-sm mb-1">Adakah pembayaran selamat?</p>
                                        <p class="text-sm text-slate-600">Kami menggunakan perkhidmatan FPX dan kad kredit/debit yang disulitkan dengan teknologi SSL. Data peribadi anda dijamin selamat.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Share Section -->
                            <div class="mt-8 pt-6 border-t border-slate-100">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Kongsikan Kempen Ini</p>
                                <SocialShareButtons
                                    :title="infaq.title"
                                    :text="infaq.description || 'Jom menyumbang bersama kami.'"
                                    :url="sharePreviewUrl"
                                />
                            </div>
                        </div>
                    </article>

                    <!-- Related Infaq -->
                    <div v-if="relatedInfaqs.length" class="bg-white rounded-3xl border border-gray-100 p-6 md:p-8 shadow-sm">
                        <h2 class="text-xl font-black text-slate-900 mb-6">Saranan Kempen Lainnya</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <Link v-for="item in relatedInfaqs" :key="item.id" :href="item.public_url" class="group block overflow-hidden rounded-2xl border border-slate-100 bg-slate-50 hover:shadow-md transition">
                                <div class="aspect-video bg-gray-200 relative overflow-hidden">
                                    <img v-if="item.image_path" :src="item.image_path" class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
                                    <div v-else class="w-full h-full bg-gradient-to-br from-emerald-600 to-emerald-800 flex items-center justify-center">
                                        <svg class="w-10 h-10 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                </div>
                                <div class="p-4">
                                    <h3 class="font-bold text-slate-800 line-clamp-2 mb-2 group-hover:text-emerald-600 transition">{{ item.title }}</h3>
                                    <p class="text-sm font-bold text-emerald-600">{{ formatCurrency(item.collected_amount) }} Terkumpul</p>
                                </div>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="w-full lg:w-1/3 space-y-6">
                    <div class="sticky top-24 space-y-6">
                        <!-- Donation Card -->
                        <div class="rounded-3xl border border-gray-100 bg-white p-6 md:p-8 shadow-lg shadow-emerald-900/5">
                            <div class="mb-6">
                                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1">Terkumpul</p>
                                <h2 class="text-3xl font-black text-emerald-600 mb-2">{{ formatCurrency(infaq.collected_amount) }}</h2>
                                <template v-if="infaq.type === 'progress' && infaq.target_amount">
                                    <div class="flex items-center justify-between text-sm font-semibold text-slate-500 mb-2">
                                        <span>{{ infaq.progress_percent }}%</span>
                                        <span>Matlamat: {{ formatCurrency(infaq.target_amount) }}</span>
                                    </div>
                                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-1000" :style="{ width: infaq.progress_percent + '%' }"></div>
                                    </div>
                                </template>
                            </div>

                            <div class="space-y-3">
                                <Link
                                    :href="infaq.public_url + '/donate'"
                                    class="block w-full text-center rounded-xl bg-slate-900 px-4 py-4 text-sm font-black uppercase tracking-wider text-white transition shadow-sm hover:bg-slate-800 hover:shadow-md hover:-translate-y-0.5"
                                >
                                    Sumbang Sekarang
                                </Link>

                                <div class="grid grid-cols-2 gap-2">
                                    <Link :href="infaq.public_url + '/donate'" class="block text-center py-2.5 rounded-lg border border-slate-200 text-sm font-bold text-slate-600 hover:border-emerald-300 hover:text-emerald-600 transition">RM30</Link>
                                    <Link :href="infaq.public_url + '/donate'" class="block text-center py-2.5 rounded-lg border border-slate-200 text-sm font-bold text-slate-600 hover:border-emerald-300 hover:text-emerald-600 transition">RM50</Link>
                                    <Link :href="infaq.public_url + '/donate'" class="block text-center py-2.5 rounded-lg border border-slate-200 text-sm font-bold text-slate-600 hover:border-emerald-300 hover:text-emerald-600 transition">RM100</Link>
                                    <Link :href="infaq.public_url + '/donate'" class="block text-center py-2.5 rounded-lg border border-slate-200 text-sm font-bold text-slate-600 hover:border-emerald-300 hover:text-emerald-600 transition">RM200</Link>
                                </div>

                                <p class="text-center text-xs font-semibold text-slate-400 flex items-center justify-center gap-1">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                    Bayaran Selamat & Terjamin
                                </p>
                            </div>
                        </div>

                        <!-- QR Share Card -->
                        <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                            <h3 class="font-black text-slate-900 mb-4 text-sm flex items-center gap-2">
                                <svg class="h-5 w-5 text-slate-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2m4 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Imbas QR untuk Kongsi
                            </h3>
                            <div class="flex flex-col items-center">
                                <div class="bg-white p-2 border border-gray-200 rounded-xl shadow-sm mb-3">
                                    <img :src="qrUrl" alt="QR Code" class="w-36 h-36"/>
                                </div>
                                <div class="flex items-center w-full rounded-lg bg-gray-50 border border-gray-200 p-1.5 mb-3">
                                    <span class="truncate text-xs text-gray-600 px-1 flex-1">{{ sharePreviewUrl }}</span>
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
                                    :text="infaq.description || 'Jom menyumbang bersama kami.'"
                                    compact
                                />
                            </div>
                        </div>

                        <!-- Recent Donors -->
                        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="font-black text-slate-900 mb-4 flex items-center gap-2">
                                <svg class="h-5 w-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                                Sumbangan Terkini
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="donation in recentDonations"
                                    :key="donation.id"
                                    class="border-b border-slate-50 pb-4 last:border-0 last:pb-0"
                                >
                                    <div class="flex items-center justify-between mb-1">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 font-bold text-sm shrink-0">
                                                {{ donation.donor_name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-bold text-slate-800 truncate">{{ donation.donor_name }}</p>
                                                <p class="text-xs font-semibold text-slate-400">{{ donation.created_at }}</p>
                                            </div>
                                        </div>
                                        <p class="text-sm font-black text-emerald-600 shrink-0 ml-2">{{ formatCurrency(donation.amount) }}</p>
                                    </div>
                                    <p v-if="donation.prayer_message" class="text-xs text-slate-500 italic mt-1 ml-13 pl-13 border-l-2 border-emerald-200 pl-3">"{{ donation.prayer_message }}"</p>
                                </div>

                                <p v-if="!recentDonations.length" class="text-center text-sm font-medium text-slate-500 py-4 bg-slate-50 rounded-2xl">Jadilah penyumbang pertama!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sticky Mobile CTA -->
        <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-slate-200 p-3 shadow-[0_-4px_20px_-4px_rgba(0,0,0,0.1)] md:hidden">
            <div class="flex items-center gap-3">
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-bold text-slate-500">Terkumpul</p>
                    <p class="text-lg font-black text-emerald-600">{{ formatCurrency(infaq.collected_amount) }}</p>
                </div>
                <Link
                    :href="infaq.public_url + '/donate'"
                    class="shrink-0 rounded-xl bg-slate-900 px-6 py-3 text-sm font-black uppercase tracking-wider text-white shadow-lg hover:bg-slate-800 transition active:scale-95"
                >
                    Sumbang Sekarang
                </Link>
            </div>
        </div>
    </div>
</template>
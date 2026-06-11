<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    member: {
        type: Object,
        required: true,
    },
    feeStatus: {
        type: Object,
        required: true,
    },
    nextEvent: {
        type: Object,
        default: null,
    },
    banners: {
        type: Array,
        default: () => [],
    },
    libraryBooks: {
        type: Array,
        default: () => [],
    },
    usrah: {
        type: Object,
        default: null,
    },
    infaqItems: {
        type: Array,
        default: () => [],
    },
    latestNews: {
        type: Array,
        default: () => [],
    },
});

const payForm = useForm({});
const infaqForms = ref({});
const booksScroller = ref(null);
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

function payFee() {
    payForm.post(route('member.pay.fee'), { preserveScroll: true });
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
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
    form.post(infaq.public_url + '/donate', {
        preserveScroll: true,
    });
}

function quickDonate(infaqId, amount) {
    const form = getInfaqForm(infaqId);
    form.amount = amount;
    donateInfaq(infaqId);
}

function scrollBooks(direction) {
    if (!booksScroller.value) {
        return;
    }

    booksScroller.value.scrollBy({
        left: direction === 'left' ? -880 : 880,
        behavior: 'smooth',
    });
}
</script>

<template>
    <Head title="Member Dashboard" />

    <AppLayout>
        <template #header>Dashboard Ahli</template>

        <div class="relative mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-3xl">
                <div class="absolute -top-20 -left-14 h-48 w-48 rounded-full bg-emerald-200/40 blur-3xl"></div>
                <div class="absolute top-1/3 -right-10 h-56 w-56 rounded-full bg-indigo-200/40 blur-3xl"></div>
            </div>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div class="space-y-4 md:space-y-7">
                <section class="md:hidden rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-indigo-50 p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Quick Action</p>
                        <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-gray-500 ring-1 ring-gray-200">Mobile View</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-2.5">
                        <Link :href="route('member.financial.overview')" class="mobile-action-card">
                            <span class="mobile-action-icon bg-emerald-100 text-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </span>
                            <span>Yuran Saya</span>
                        </Link>
                        <Link :href="route('member.facilities.index')" class="mobile-action-card">
                            <span class="mobile-action-icon bg-amber-100 text-amber-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>
                            </span>
                            <span>Tempah Ruang</span>
                        </Link>
                        <Link :href="route('news.index')" class="mobile-action-card">
                            <span class="mobile-action-icon bg-indigo-100 text-indigo-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21H9a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2zM7 7H5a2 2 0 00-2 2v10a2 2 0 002 2h2M12 7h5M12 11h5M12 15h5"/></svg>
                            </span>
                            <span>Info Terkini</span>
                        </Link>
                        <Link v-if="firstInfaqUrl" :href="firstInfaqUrl" class="mobile-action-card">
                            <span class="mobile-action-icon bg-rose-100 text-rose-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0v4M5 11h14l-1 9H6l-1-9z"/></svg>
                            </span>
                            <span>Sumbang Infaq</span>
                        </Link>
                        <button v-else type="button" class="mobile-action-card opacity-60">
                            <span class="mobile-action-icon bg-rose-100 text-rose-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 118 0v4M5 11h14l-1 9H6l-1-9z"/></svg>
                            </span>
                            <span>Tiada Infaq</span>
                        </button>
                    </div>
                </section>

                <section class="relative overflow-hidden rounded-3xl border border-white/30 bg-gradient-to-br from-slate-900 via-emerald-900 to-emerald-700 p-4 text-white shadow-xl sm:p-5 md:p-8">
                    <div class="absolute -right-16 -top-16 h-60 w-60 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="absolute -bottom-20 left-1/3 h-56 w-56 rounded-full bg-emerald-300/25 blur-2xl"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.18),transparent_35%)]"></div>
                    <div class="relative z-10 flex flex-col gap-5 md:flex-row md:items-end md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-100/80">Dashboard Command</p>
                            <h2 class="mt-2 text-xl font-black leading-tight sm:text-2xl md:text-3xl lg:text-4xl">Assalamualaikum, {{ member.name }}</h2>
                            <div class="mt-3 flex flex-wrap items-center gap-2.5 sm:mt-4 sm:gap-3">
                                <span class="inline-flex rounded-full bg-white/15 px-3 py-1 text-xs font-semibold ring-1 ring-white/30">
                                    {{ member.organization?.name || 'NGO' }}
                                </span>
                                <Link :href="route('profile.show')" class="inline-flex rounded-full bg-white px-4 py-1.5 text-xs font-semibold text-emerald-900 transition hover:bg-emerald-50">
                                    Lihat Profil &amp; Journey
                                </Link>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 md:w-72">
                            <div class="rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur-sm">
                                <p class="text-[11px] text-emerald-100/80">Yuran</p>
                                <p class="mt-1 text-sm font-bold text-white">{{ feeStatus.status === 'active' ? 'Aktif' : 'Tertunggak' }}</p>
                            </div>
                            <div class="rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur-sm">
                                <p class="text-[11px] text-emerald-100/80">Usrah</p>
                                <p class="mt-1 text-sm font-bold text-white line-clamp-1">{{ usrah?.name || 'Belum Set' }}</p>
                            </div>
                            <div class="col-span-2 rounded-2xl border border-white/20 bg-white/10 p-3 backdrop-blur-sm">
                                <p class="text-[11px] text-emerald-100/80">Program Seterusnya</p>
                                <p class="mt-1 text-sm font-bold text-white line-clamp-1">{{ nextEvent?.title || 'Tiada program akan datang' }}</p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="md:hidden">
                    <div class="mb-2 flex items-end justify-between">
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Kempen Infaq Pilihan</h3>
                            <p class="text-xs text-gray-500">Swipe dan sumbang terus dari dashboard.</p>
                        </div>
                        <Link :href="route('member.financial.overview')" class="text-[11px] font-bold text-emerald-700">Lihat Semua</Link>
                    </div>
                    <div class="-mx-4 overflow-x-auto px-4 pb-2">
                        <div class="flex snap-x snap-mandatory gap-3">
                            <article
                                v-for="item in featuredInfaq"
                                :key="`mobile-infaq-${item.id}`"
                                class="card-float w-[85%] shrink-0 snap-start overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-md"
                            >
                                <div class="relative aspect-[4/5] overflow-hidden bg-gradient-to-r from-emerald-400 via-emerald-500 to-teal-500 p-3 text-white">
                                    <img v-if="item.image_path" :src="item.image_path" :alt="item.title" class="absolute inset-0 h-full w-full object-cover opacity-35">
                                    <div class="relative z-10">
                                        <span class="inline-flex rounded-full bg-white/20 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">
                                            {{ item.type === 'progress' ? 'Progress' : 'One-Off' }}
                                        </span>
                                        <h4 class="mt-2 line-clamp-2 text-base font-black">{{ item.title }}</h4>
                                        <p class="mt-1 text-xs text-emerald-50">{{ formatCurrency(item.collected_amount) }} terkumpul</p>
                                    </div>
                                </div>
                                <div class="p-3.5">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                        <div class="h-2 rounded-full bg-emerald-500 transition-all duration-700" :style="{ width: (item.progress_percent || 0) + '%' }"></div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                                        <span>{{ item.progress_percent || 0 }}% capai</span>
                                        <span>{{ formatCurrency(item.target_amount || 0) }}</span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-3 gap-2">
                                        <button type="button" @click="quickDonate(item.id, 10)" :disabled="getInfaqForm(item.id).processing" class="rounded-xl bg-emerald-50 px-2 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">RM10</button>
                                        <button type="button" @click="quickDonate(item.id, 30)" :disabled="getInfaqForm(item.id).processing" class="rounded-xl bg-emerald-50 px-2 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">RM30</button>
                                        <button type="button" @click="quickDonate(item.id, 50)" :disabled="getInfaqForm(item.id).processing" class="rounded-xl bg-emerald-50 px-2 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200">RM50</button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </section>

                <section class="md:hidden">
                    <div class="mb-2 flex items-end justify-between">
                        <div>
                            <h3 class="text-lg font-black text-gray-900">Berita Untuk Anda</h3>
                            <p class="text-xs text-gray-500">Story cards yang cepat dibaca.</p>
                        </div>
                        <Link :href="route('news.index')" class="text-[11px] font-bold text-indigo-700">Buka Feed</Link>
                    </div>
                    <div class="-mx-4 overflow-x-auto px-4 pb-2">
                        <div class="flex snap-x snap-mandatory gap-3">
                            <article
                                v-for="item in featuredNews"
                                :key="`mobile-news-${item.id}`"
                                class="card-float w-[82%] shrink-0 snap-start overflow-hidden rounded-3xl border border-indigo-100 bg-white shadow-sm"
                            >
                                <Link :href="route('news.show', item.id)" class="block">
                                    <div class="relative aspect-[4/5] overflow-hidden bg-gray-100">
                                        <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="h-full w-full object-cover">
                                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/55 to-transparent"></div>
                                        <p class="absolute bottom-3 left-3 right-3 line-clamp-2 text-sm font-black text-white">{{ item.title }}</p>
                                    </div>
                                    <div class="p-3">
                                        <div class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wide text-gray-500">
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ item.category_name }}</span>
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ item.organization_name }}</span>
                                        </div>
                                        <p class="mt-2 line-clamp-2 text-xs text-gray-600">{{ item.excerpt || 'Tap untuk baca lanjut.' }}</p>
                                    </div>
                                </Link>
                            </article>
                        </div>
                    </div>
                </section>

                <div class="grid grid-cols-1 gap-4 md:gap-6 md:grid-cols-2">
                    <section class="rounded-3xl border border-gray-100 bg-white/95 p-4 shadow-sm ring-1 ring-gray-100 md:p-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-base font-black text-gray-900">Aktiviti Saya</h3>
                            <Link :href="route('events.index')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Lihat Program</Link>
                        </div>

                        <div class="mt-4 space-y-2.5 sm:space-y-3">
                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3.5 sm:p-4">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-xs text-gray-500">Program</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ nextEvent?.title || 'Tiada program akan datang' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3.5 sm:p-4">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-5.747-8.62h11.494M4.5 19.5h15a2 2 0 002-2v-11a2 2 0 00-2-2h-15a2 2 0 00-2 2v11a2 2 0 002 2z"/></svg>
                                    </span>
                                    <div>
                                        <p class="text-xs text-gray-500">Usrah</p>
                                        <p class="text-sm font-semibold text-gray-800">{{ usrah?.name || 'Belum ditetapkan kumpulan' }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-gray-100 bg-gray-50 p-3.5 sm:p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>
                                        </span>
                                        <div>
                                            <p class="text-xs text-gray-500">Tempahan Ruang</p>
                                            <p class="text-sm font-semibold text-gray-800">Semak ruang & buat tempahan</p>
                                        </div>
                                    </div>
                                    <Link :href="route('member.facilities.index')" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">
                                        Tempah
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 to-white p-4 shadow-sm md:p-6">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-base font-black text-gray-900">Status Ahli / Yuran</h3>
                            <Link :href="route('member.financial.overview')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                Yuran &amp; Bayaran
                            </Link>
                        </div>
                        <div class="mt-4 rounded-2xl bg-white p-3.5 ring-1 ring-emerald-100 sm:p-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-semibold text-gray-700">Yuran Tahunan</p>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="feeStatus.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">
                                    {{ feeStatus.status === 'active' ? 'Aktif' : 'Tertunggak' }}
                                </span>
                            </div>

                            <template v-if="feeStatus.status === 'active'">
                                <p class="mt-3 text-sm text-gray-600">Keahlian anda aktif. Teruskan penglibatan anda dalam program komuniti.</p>
                                <p v-if="feeStatus.last_reference" class="mt-2 text-xs font-mono text-gray-400">Ref: {{ feeStatus.last_reference }}</p>
                            </template>

                            <template v-else>
                                <p class="mt-3 text-sm text-gray-600">Yuran tahunan perlu diperbaharui.</p>
                                <p class="mt-2 text-2xl font-black text-gray-900">{{ formatCurrency(feeStatus.amount_due) }}</p>
                                <button
                                    @click="payFee"
                                    :disabled="payForm.processing"
                                    class="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    {{ payForm.processing ? 'Memproses...' : 'Bayar Yuran Sekarang' }}
                                </button>
                            </template>
                        </div>
                    </section>
                </div>

                <section class="hidden md:block">
                    <div class="mb-3 flex items-end justify-between md:mb-4">
                        <div>
                            <h3 class="text-lg font-black text-gray-900 sm:text-xl md:text-2xl">Info Terkini</h3>
                            <p class="text-sm text-gray-500">Swipe kad untuk baca makluman terbaru.</p>
                        </div>
                        <Link :href="route('news.index')" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                            Lihat Semua
                        </Link>
                    </div>

                    <div class="-mx-4 overflow-x-auto px-4 pb-2 md:mx-0 md:px-0">
                        <div class="flex snap-x snap-mandatory gap-3 md:grid md:grid-cols-3 md:gap-4">
                            <article
                                v-for="item in latestNews"
                                :key="item.id"
                                class="group w-[82%] shrink-0 snap-start overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md sm:w-[60%] md:w-auto"
                            >
                                <Link :href="route('news.show', item.id)" class="block">
                                    <div class="aspect-[16/10] bg-gray-100">
                                        <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="h-full w-full object-cover">
                                        <div v-else class="grid h-full place-items-center text-xs text-gray-400">Tiada gambar</div>
                                    </div>
                                    <div class="p-3.5">
                                        <div class="flex flex-wrap items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ item.category_name }}</span>
                                            <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ item.organization_name }}</span>
                                        </div>
                                        <h4 class="mt-2 line-clamp-2 text-sm font-black text-gray-900">{{ item.title }}</h4>
                                        <p class="mt-1 line-clamp-2 text-xs text-gray-600">{{ item.excerpt || 'Tekan untuk baca lanjut.' }}</p>
                                        <p class="mt-2 text-[11px] text-gray-400">{{ item.published_at || '-' }}</p>
                                    </div>
                                </Link>
                            </article>
                        </div>

                        <div v-if="!latestNews.length" class="rounded-2xl border border-gray-100 bg-white px-4 py-8 text-center text-sm text-gray-500">
                            Belum ada info terkini untuk dipaparkan.
                        </div>
                    </div>
                </section>

                <section class="hidden md:block">
                    <div class="mb-3 flex items-end justify-between md:mb-4">
                        <div>
                            <h3 class="text-lg font-black text-gray-900 sm:text-xl md:text-2xl">Berita Bergambar</h3>
                            <p class="text-sm text-gray-500">Makluman visual terkini untuk ahli.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <article
                            v-for="banner in banners"
                            :key="banner.id"
                            class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-md transition-all hover:-translate-y-1 hover:shadow-lg"
                        >
                            <div class="relative">
                                <img :src="banner.image_path" :alt="banner.title" class="aspect-[4/5] w-full object-cover">
                                <div class="absolute inset-x-3 bottom-3 rounded-xl bg-slate-900/35 px-3 py-2 backdrop-blur-md ring-1 ring-white/25">
                                    <p class="text-sm font-bold text-white">{{ banner.title }}</p>
                                </div>
                            </div>
                        </article>

                        <div v-if="!banners.length" class="md:col-span-3 rounded-2xl border border-gray-100 bg-white px-4 py-10 text-center text-sm text-gray-500">
                            Tiada berita bergambar buat masa ini.
                        </div>
                    </div>
                </section>

                <section class="hidden md:block">
                    <div class="mb-3 flex items-end justify-between md:mb-4">
                        <div>
                            <h3 class="text-lg font-black text-gray-900 sm:text-xl md:text-2xl">Infaq &amp; Sumbangan</h3>
                            <p class="text-sm text-gray-500">Sokong program melalui infaq komuniti.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3">
                        <article
                            v-for="item in infaqItems"
                            :key="item.id"
                            class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                        >
                            <div class="h-40 w-full overflow-hidden bg-gray-100">
                                <img v-if="item.image_path" :src="item.image_path" :alt="item.title" class="h-full w-full object-cover">
                                <div v-else class="grid h-full place-items-center text-sm text-gray-400">No Image</div>
                            </div>

                            <div class="p-3.5 sm:p-4">
                                <div class="mb-2 flex items-center gap-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="item.type === 'progress' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'"
                                    >
                                        {{ item.type === 'progress' ? 'Progress' : 'One-Off' }}
                                    </span>
                                </div>

                                <h4 class="line-clamp-2 text-sm font-bold text-gray-900">{{ item.title }}</h4>
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500">{{ item.description }}</p>
                                <Link :href="item.public_url" class="mt-2 inline-flex text-xs font-semibold text-emerald-700 hover:text-emerald-800">
                                    Lihat Butiran
                                </Link>

                                <div class="mt-3 text-xs text-gray-600">
                                    <template v-if="item.type === 'progress'">
                                        <div class="mb-1 flex items-center justify-between">
                                            <span class="font-semibold">{{ formatCurrency(item.collected_amount) }}</span>
                                            <span class="text-gray-400">/ {{ formatCurrency(item.target_amount) }}</span>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full bg-emerald-500" :style="{ width: item.progress_percent + '%' }"></div>
                                        </div>
                                        <p class="mt-1 text-[11px] text-gray-400">{{ item.progress_percent }}% terkumpul</p>
                                    </template>
                                    <template v-else>
                                        <p>
                                            Terkumpul:
                                            <span class="font-semibold">{{ formatCurrency(item.collected_amount) }}</span>
                                        </p>
                                    </template>
                                </div>

                                <form class="mt-3 flex items-center gap-2" @submit.prevent="donateInfaq(item.id)">
                                    <input
                                        v-model.number="getInfaqForm(item.id).amount"
                                        type="number"
                                        min="1"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                        placeholder="Jumlah (RM)"
                                    >
                                    <button
                                        type="submit"
                                        :disabled="getInfaqForm(item.id).processing"
                                        class="shrink-0 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60"
                                    >
                                        {{ getInfaqForm(item.id).processing ? '...' : 'Derma' }}
                                    </button>
                                </form>
                            </div>
                        </article>

                        <div v-if="!infaqItems.length" class="sm:col-span-2 md:col-span-3 rounded-2xl border border-gray-100 bg-white px-4 py-10 text-center text-sm text-gray-500">
                            Tiada infaq aktif buat masa ini.
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/95 p-4 shadow-sm ring-1 ring-gray-100 md:p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-sm font-black text-gray-900 sm:text-base md:text-xl">PUSTAKA – JUDUL TERKINI</h3>
                        <Link :href="route('member.library')" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800">Lihat Semua Buku</Link>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button" @click="scrollBooks('left')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-lg text-gray-500 transition hover:bg-gray-50">‹</button>

                        <div ref="booksScroller" class="flex-1 overflow-x-auto scroll-smooth">
                            <div class="flex min-w-full gap-4 pb-2">
                                <a
                                    v-for="(book, index) in libraryBooks"
                                    :key="book.id"
                                    :href="book.file_path"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="group block w-[120px] shrink-0 sm:w-[140px] md:w-[150px]"
                                >
                                    <div class="aspect-[2/3] overflow-hidden rounded-md border border-gray-200 shadow-sm transition group-hover:-translate-y-0.5 group-hover:shadow-md">
                                        <img v-if="book.cover_image_path" :src="book.cover_image_path" :alt="book.title" class="h-full w-full object-cover">
                                        <div v-else :class="['h-full bg-gradient-to-b p-3', coverStyles[index % coverStyles.length]]">
                                            <p class="line-clamp-4 text-sm font-bold leading-tight">{{ book.title }}</p>
                                        </div>
                                    </div>
                                    <p class="mt-2 line-clamp-2 text-xs font-semibold text-gray-700">{{ book.title }}</p>
                                </a>
                            </div>

                            <div v-if="!libraryBooks.length" class="rounded-2xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
                                Tiada buku terkini dalam pustaka.
                            </div>
                        </div>

                        <button type="button" @click="scrollBooks('right')" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-gray-200 bg-white text-lg text-gray-500 transition hover:bg-gray-50">›</button>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.mobile-action-card {
    @apply flex items-center justify-center gap-2 rounded-2xl bg-white px-3 py-3 text-xs font-bold text-gray-700 ring-1 ring-gray-200 transition;
}

.mobile-action-card:hover {
    @apply -translate-y-0.5 bg-gray-50;
}

.mobile-action-icon {
    @apply inline-flex h-7 w-7 items-center justify-center rounded-lg ring-1 ring-black/5;
}

.card-float {
    animation: card-float-in 480ms ease-out both;
}

@keyframes card-float-in {
    0% {
        opacity: 0;
        transform: translateY(10px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
</style>

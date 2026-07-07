<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import InfaqCard from '@/Components/InfaqCard.vue';

const page = usePage();
const systemLogo = computed(() => page.props.brand?.system_logo_path ?? null);

const props = defineProps({
    infaqs: { type: Array, required: true },
});

const searchQuery = ref('');
const activeTypeFilter = ref('all');
const sortBy = ref('newest');
const viewMode = ref('grid');

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(value ?? 0);
}

const typeFilters = [
    { value: 'all', label: 'Semua' },
    { value: 'progress', label: 'Kutip Dana' },
    { value: 'one_off', label: 'Bebas' },
];

const sortOptions = [
    { value: 'newest', label: 'Terkini' },
    { value: 'most_collected', label: 'Terkumpul Tertinggi' },
    { value: 'highest_goal', label: 'Matlamat Tertinggi' },
    { value: 'most_progress', label: 'Kemajuan Tertinggi' },
];

const filteredInfaqs = computed(() => {
    let items = [...props.infaqs];

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        items = items.filter(i =>
            i.title.toLowerCase().includes(q) ||
            (i.organization_name && i.organization_name.toLowerCase().includes(q))
        );
    }

    if (activeTypeFilter.value !== 'all') {
        items = items.filter(i => i.type === activeTypeFilter.value);
    }

    switch (sortBy.value) {
        case 'most_collected':
            items.sort((a, b) => (b.collected_amount || 0) - (a.collected_amount || 0));
            break;
        case 'highest_goal':
            items.sort((a, b) => (b.target_amount || 0) - (a.target_amount || 0));
            break;
        case 'most_progress':
            items.sort((a, b) => (b.progress_percent || 0) - (a.progress_percent || 0));
            break;
        case 'newest':
        default:
            items.sort((a, b) => (b.days_running || 0) - (a.days_running || 0));
            break;
    }

    return items;
});

const totalCollected = computed(() =>
    props.infaqs.reduce((sum, i) => sum + (parseFloat(i.collected_amount) || 0), 0)
);

const totalCampaigns = computed(() => props.infaqs.length);

watch(searchQuery, () => {});
</script>

<template>
    <Head title="Semua Kempen Infaq" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white font-sans pb-16 md:pb-8">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Kembali ke Laman Utama
                </Link>
                <img v-if="systemLogo" :src="systemLogo" alt="Logo" class="h-8 w-auto" />
                <span v-else class="text-sm font-black text-slate-800">myWAP</span>
            </div>
        </nav>

        <div class="mx-auto max-w-5xl px-4 py-8">
            <!-- Header -->
            <div class="mb-8 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-2">Kempen Infaq</h1>
                <p class="text-slate-500 font-semibold text-sm md:text-base">Sertai kami dalam menyumbang dan membantu mereka yang memerlukan.</p>
            </div>

            <!-- Stats Banner -->
            <div v-if="totalCampaigns" class="mb-8 grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="rounded-2xl bg-white border border-slate-100 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ totalCampaigns }}</p>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-1">Kempen Aktif</p>
                </div>
                <div class="rounded-2xl bg-white border border-slate-100 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ formatCurrency(totalCollected) }}</p>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mt-1">Jumlah Terkumpul</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 text-center shadow-sm">
                    <p class="text-xl font-black text-emerald-700">{{ formatCurrency(totalCollected) }}</p>
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-wide mt-1">Boleh Bantu Lagi</p>
                </div>
                <div class="rounded-2xl bg-slate-900 p-4 text-center shadow-sm">
                    <p class="text-xl font-black text-white">{{ totalCampaigns }}</p>
                    <p class="text-xs font-bold text-slate-300 uppercase tracking-wide mt-1">Peluang Sumbang</p>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div v-if="props.infaqs.length" class="mb-6 space-y-3">
                <!-- Search Row -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <div class="relative flex-1">
                        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Cari kempen atau penganjur..."
                            class="w-full pl-10 pr-4 py-2.5 rounded-xl border-slate-200 text-sm focus:border-emerald-500 focus:ring-emerald-500 bg-white shadow-sm"
                        />
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400 hover:text-slate-600 transition">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <div class="flex gap-2">
                        <select v-model="sortBy" class="rounded-xl border-slate-200 text-sm font-semibold py-2.5 px-3 bg-white shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                        </select>

                        <!-- View Mode Toggle -->
                        <div class="flex rounded-xl border border-slate-200 bg-white p-0.5 shadow-sm h-fit">
                            <button @click="viewMode = 'grid'" :class="['p-2 rounded-lg transition', viewMode === 'grid' ? 'bg-slate-100 text-slate-800' : 'text-slate-400 hover:text-slate-600']">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                            </button>
                            <button @click="viewMode = 'list'" :class="['p-2 rounded-lg transition', viewMode === 'list' ? 'bg-slate-100 text-slate-800' : 'text-slate-400 hover:text-slate-600']">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Type Filter Chips -->
                <div class="flex items-center gap-2">
                    <button
                        v-for="f in typeFilters"
                        :key="f.value"
                        @click="activeTypeFilter = f.value"
                        :class="['rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wider transition', activeTypeFilter === f.value ? 'bg-slate-900 text-white shadow' : 'bg-white border border-slate-200 text-slate-500 hover:border-slate-300 hover:text-slate-700']"
                    >
                        {{ f.label }}
                    </button>

                    <span v-if="filteredInfaqs.length !== props.infaqs.length" class="ml-2 text-xs font-semibold text-slate-400">
                        {{ filteredInfaqs.length }} dari {{ props.infaqs.length }} kempen
                    </span>
                </div>
            </div>

            <!-- Results -->
            <template v-if="filteredInfaqs.length">
                <!-- Grid View -->
                <div v-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <InfaqCard v-for="item in filteredInfaqs" :key="item.id" :infaq="item" />
                </div>

                <!-- List View -->
                <div v-else class="space-y-3">
                    <InfaqCard v-for="item in filteredInfaqs" :key="item.id" :infaq="item" :list-view="true" />
                </div>
            </template>

            <!-- No search results -->
            <div v-else-if="props.infaqs.length && !filteredInfaqs.length" class="text-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">Tiada Hasil Carian</h3>
                <p class="text-sm text-slate-500 mb-4">Tiada kempen sepadan dengan kriteria carian anda.</p>
                <button @click="searchQuery = ''; activeTypeFilter = 'all'" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Tetapkan Semula
                </button>
            </div>

            <!-- No campaigns at all -->
            <div v-else class="text-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">Tiada Kempen Infaq</h3>
                <p class="text-sm text-slate-500">Belum ada kempen infaq yang aktif buat masa ini.</p>
            </div>
        </div>
    </div>
</template>

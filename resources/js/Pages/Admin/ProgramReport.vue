<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    events: Object,
    summary: Array,
    attendance_rate: Number,
    breakdown: Array,
    breakdown_mode: String,
    categories: Array,
    filters: Object,
    years: Array,
    months: Array,
    organizations: Array,
    branches: Array,
    is_superadmin: Boolean,
    is_branch_admin: Boolean,
    scope_label: String,
    period_label: String,
});

const form = reactive({
    year: props.filters?.year ?? 'all',
    month: props.filters?.month ?? 'all',
    organization_id: props.filters?.organization_id ?? '',
    branch_id: props.filters?.branch_id ?? '',
    search: props.filters?.search ?? '',
});

watch(() => props.filters, (value) => {
    if (value) {
        Object.assign(form, value);
    }
}, { deep: true });

const loading = ref(false);
const requestError = ref('');

function apply() {
    router.get(route('admin.program-report.index'), { ...form }, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => {
            loading.value = true;
            requestError.value = '';
        },
        onFinish: () => {
            loading.value = false;
        },
        onError: () => {
            requestError.value = 'Laporan tidak dapat dimuatkan. Cuba lagi.';
        },
    });
}

function reset() {
    form.year = String(new Date().getFullYear());
    form.month = 'all';
    form.organization_id = '';
    form.branch_id = '';
    form.search = '';
    apply();
}

const hasFilters = computed(() => (
    form.month !== 'all'
    || form.organization_id !== ''
    || form.branch_id !== ''
    || form.search !== ''
    || form.year === 'all'
));

const exportQuery = computed(() => ({
    year: form.year,
    month: form.month,
    organization_id: form.organization_id,
    branch_id: form.branch_id,
    search: form.search,
}));

const csvUrl = computed(() => route('admin.program-report.export.csv', exportQuery.value));
const pdfUrl = computed(() => route('admin.program-report.export.pdf', exportQuery.value));

const summaryMap = computed(() => Object.fromEntries((props.summary ?? []).map((item) => [item.key, item.value])));

const maxBreakdown = computed(() => Math.max(1, ...(props.breakdown ?? []).map((item) => item.total)));

const statusClass = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-rose-50 text-rose-600 border-rose-200',
};
</script>

<template>
    <Head title="Laporan Program" />

    <AppLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-900">Laporan Program</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ period_label }} · {{ scope_label }}
                    </p>
                    <p class="mt-0.5 text-xs text-gray-400">
                        Semua program yang telah selesai, untuk semakan dan laporan pengurusan.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a
                        :href="csvUrl"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        Muat Turun CSV
                    </a>
                    <a
                        :href="pdfUrl"
                        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 transition-colors"
                    >
                        Muat Turun PDF
                    </a>
                </div>
            </div>

            <form class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm" @submit.prevent="apply">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-600">Tahun</span>
                        <select v-model="form.year" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0">
                            <option value="all">Semua tahun</option>
                            <option v-for="year in years" :key="year" :value="String(year)">{{ year }}</option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-600">Bulan</span>
                        <select v-model="form.month" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0">
                            <option value="all">Semua bulan</option>
                            <option v-for="month in months" :key="month.value" :value="month.value">{{ month.label }}</option>
                        </select>
                    </label>

                    <label v-if="is_superadmin" class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-600">Organisasi</span>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0">
                            <option value="">Semua organisasi</option>
                            <option v-for="organization in organizations" :key="organization.id" :value="String(organization.id)">{{ organization.name }}</option>
                        </select>
                    </label>

                    <label v-if="!is_branch_admin && branches.length" class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-600">Cawangan</span>
                        <select v-model="form.branch_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0">
                            <option value="">Semua cawangan</option>
                            <option v-for="branch in branches" :key="branch.id" :value="String(branch.id)">{{ branch.name }}</option>
                        </select>
                    </label>

                    <label class="block lg:col-span-2">
                        <span class="mb-1 block text-xs font-semibold text-gray-600">Carian</span>
                        <input
                            v-model="form.search"
                            type="search"
                            placeholder="Tajuk program atau lokasi"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 focus:border-indigo-400 focus:ring-0"
                        />
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 transition-colors">
                        Tapis
                    </button>
                    <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors" @click="reset">
                        Set semula
                    </button>
                    <span v-if="loading" class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500" role="status">
                        <span class="h-3 w-3 animate-spin rounded-full border-2 border-gray-300 border-t-gray-700"></span>
                        Mengemas kini laporan
                    </span>
                </div>
            </form>

            <div v-if="requestError" class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3" role="alert">
                <p class="text-sm font-semibold text-rose-700">{{ requestError }}</p>
                <button type="button" class="rounded-xl border border-rose-300 bg-white px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100" @click="apply">
                    Cuba lagi
                </button>
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" :aria-busy="loading">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ summaryMap.total_programs ?? 0 }}</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">Jumlah program</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ summaryMap.total_registrations ?? 0 }}</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">Jumlah pendaftaran</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ summaryMap.total_unique_participants ?? 0 }}</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">Peserta unik</p>
                    <p class="mt-0.5 text-[11px] text-gray-400">Dikira sekali bagi setiap program</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ summaryMap.total_attended ?? 0 }}</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">Jumlah kehadiran</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ attendance_rate }}%</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">Kadar kehadiran</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">
                            {{ breakdown_mode === 'month' ? 'Program mengikut bulan' : 'Program mengikut tahun' }}
                        </h2>
                        <p class="text-xs text-gray-400">{{ period_label }} · {{ scope_label }}</p>
                    </div>

                    <div v-if="breakdown.every((item) => item.total === 0)" class="mt-6 rounded-2xl border border-dashed border-gray-200 py-8 text-center">
                        <p class="text-sm text-gray-400">Tiada program direkodkan untuk tempoh ini.</p>
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div v-for="item in breakdown" :key="item.label" class="flex items-center gap-3">
                            <span class="w-12 shrink-0 text-xs font-semibold text-gray-500">{{ item.label }}</span>
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full bg-indigo-500 transition-all" :style="{ width: (item.total / maxBreakdown) * 100 + '%' }"></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-xs font-bold text-gray-700">{{ item.total }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Program mengikut kategori</h2>

                    <div v-if="categories.length === 0" class="mt-6 rounded-2xl border border-dashed border-gray-200 py-8 text-center">
                        <p class="text-sm text-gray-400">Tiada kategori untuk tempoh ini.</p>
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div v-for="category in categories" :key="category.value" class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2 last:border-0 last:pb-0">
                            <span class="text-sm text-gray-600">{{ category.label }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ category.total }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Senarai program</h2>
                    <p class="text-xs text-gray-400">{{ events.total }} program</p>
                </div>

                <div v-if="events.data.length === 0" class="px-5 py-12 text-center">
                    <p class="text-sm font-semibold text-gray-500">Tiada program yang telah selesai untuk tempoh ini.</p>
                    <p v-if="hasFilters" class="mt-1 text-xs text-gray-400">Cuba ubah tapisan atau set semula untuk melihat semua program.</p>
                    <button v-if="hasFilters" type="button" class="mt-4 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="reset">
                        Set semula tapisan
                    </button>
                </div>

                <div v-else>
                    <div class="hidden md:block">
                        <table class="w-full min-w-[720px] text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-semibold">Program</th>
                                    <th scope="col" class="px-5 py-3 font-semibold">Tarikh</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Peserta</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Hadir</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold">Kadar</th>
                                    <th scope="col" class="px-5 py-3 text-right font-semibold"><span class="sr-only">Tindakan</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="event in events.data" :key="event.id" class="align-top hover:bg-gray-50/60">
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-gray-800">{{ event.title }}</p>
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                            <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-bold" :class="statusClass[event.status] ?? statusClass.draft">
                                                {{ event.status_label }}
                                            </span>
                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-bold text-gray-600">
                                                {{ event.category_label }}
                                            </span>
                                            <span class="text-[11px] text-gray-400">{{ event.type_label }}</span>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-400">
                                            {{ event.organization_name }}
                                            <span v-if="event.branch_name"> · {{ event.branch_name }}</span>
                                        </p>
                                    </td>
                                    <td class="px-5 py-3 text-gray-600">
                                        <p>{{ event.date_label }}</p>
                                        <p v-if="event.location_or_link" class="mt-0.5 max-w-[180px] truncate text-xs text-gray-400">{{ event.location_or_link }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <p class="font-semibold text-gray-800">{{ event.unique_participants_count }}</p>
                                        <p class="text-[11px] text-gray-400">{{ event.registrations_count }} rekod</p>
                                    </td>
                                    <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ event.attended_count }}</td>
                                    <td class="px-5 py-3 text-right text-gray-600">{{ event.attendance_rate }}%</td>
                                    <td class="px-5 py-3 text-right">
                                        <Link :href="event.url" class="inline-flex rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-white">
                                            Lihat
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="divide-y divide-gray-100 md:hidden">
                        <article v-for="event in events.data" :key="event.id" class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="font-bold leading-snug text-gray-900">{{ event.title }}</h3>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ event.date_label }}</p>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-[11px] font-bold" :class="statusClass[event.status] ?? statusClass.draft">
                                    {{ event.status_label }}
                                </span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-bold text-gray-600">{{ event.category_label }}</span>
                                <span class="text-[11px] text-gray-400">{{ event.type_label }}</span>
                            </div>

                            <p class="mt-2 text-xs text-gray-400">
                                {{ event.organization_name }}
                                <span v-if="event.branch_name"> · {{ event.branch_name }}</span>
                            </p>

                            <div class="mt-3 grid grid-cols-3 gap-2 rounded-2xl bg-gray-50 p-3 text-center">
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ event.unique_participants_count }}</p>
                                    <p class="text-[11px] text-gray-500">Peserta</p>
                                </div>
                                <div>
                                    <p class="text-sm font-black text-emerald-600">{{ event.attended_count }}</p>
                                    <p class="text-[11px] text-gray-500">Hadir</p>
                                </div>
                                <div>
                                    <p class="text-sm font-black text-gray-900">{{ event.attendance_rate }}%</p>
                                    <p class="text-[11px] text-gray-500">Kadar</p>
                                </div>
                            </div>

                            <Link :href="event.url" class="mt-3 flex w-full items-center justify-center rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Lihat program
                            </Link>
                        </article>
                    </div>
                </div>

                <div v-if="events.last_page > 1" class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 px-5 py-4">
                    <p class="text-xs text-gray-500">Halaman {{ events.current_page }} daripada {{ events.last_page }}</p>
                    <div class="flex flex-wrap gap-1">
                        <template v-for="link in events.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="rounded-lg px-2.5 py-1.5 text-xs font-semibold"
                                :class="link.active ? 'bg-gray-900 text-white' : 'border border-gray-200 text-gray-600 hover:bg-gray-50'"
                                v-html="link.label"
                            />
                            <span v-else class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-gray-300" v-html="link.label"></span>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

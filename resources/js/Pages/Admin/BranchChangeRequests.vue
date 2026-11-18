<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    requests: { type: Object, default: () => ({ data: [], links: [] }) },
    branches: Array,
    filters: { type: Object, default: () => ({ search: '', from_branch_id: '', to_branch_id: '', per_page: 25 }) },
});

const user = usePage().props.auth.user;
const showRejectModal = ref(false);
const rejectTarget = ref(null);
const rejectForm = useForm({ rejection_reason: '' });

const selectedIds = ref([]);
const bulkApproving = ref(false);
const search = ref(props.filters.search ?? '');
const fromBranchId = ref(props.filters.from_branch_id ?? '');
const toBranchId = ref(props.filters.to_branch_id ?? '');
let filterDebounce;

const requestRows = computed(() => props.requests?.data ?? []);
const visiblePages = computed(() => {
    const last = props.requests?.last_page ?? 1;
    const current = props.requests?.current_page ?? 1;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [1];
    if (current > 3) pages.push('...');
    for (let i = Math.max(2, current - 1); i <= Math.min(last - 1, current + 1); i++) pages.push(i);
    if (current < last - 2) pages.push('...');
    pages.push(last);
    return pages;
});

function filterParams(page = 1) {
    return {
        search: search.value.trim(),
        from_branch_id: fromBranchId.value || '',
        to_branch_id: toBranchId.value || '',
        per_page: props.filters.per_page ?? 25,
        page,
    };
}

function requestNow(page = 1) {
    clearTimeout(filterDebounce);
    selectedIds.value = [];
    router.get(route('branch-change-requests.index'), filterParams(page), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

watch([search, fromBranchId, toBranchId], () => {
    clearTimeout(filterDebounce);
    filterDebounce = setTimeout(() => requestNow(), 300);
});

const allSelected = computed(() =>
    requestRows.value.length > 0 && requestRows.value.every((r) => selectedIds.value.includes(r.id))
);

function toggleSelectAll() {
    selectedIds.value = allSelected.value ? [] : requestRows.value.map((r) => r.id);
}

function toggleSelect(id) {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter((x) => x !== id)
        : [...selectedIds.value, id];
}

function bulkApprove() {
    if (!selectedIds.value.length) return;
    if (!confirm(`Luluskan ${selectedIds.value.length} permohonan pertukaran cawangan sekali gus?`)) return;

    bulkApproving.value = true;
    router.post(route('branch-change-requests.bulk-approve'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
        onFinish: () => { bulkApproving.value = false; },
    });
}

function openReject(r) {
    rejectTarget.value = r;
    rejectForm.rejection_reason = '';
    showRejectModal.value = true;
}

function submitReject() {
    rejectForm.post(route('branch-change-requests.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            rejectTarget.value = null;
        },
    });
}

function approve(r) {
    if (!confirm(`Luluskan pertukaran cawangan untuk ${r.user.name}?`)) return;
    useForm({}).post(route('branch-change-requests.approve', r.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard" title="Permohonan Tukar Cawangan">
        <Head title="Permohonan Tukar Cawangan" />

        <template #header>
            Permohonan Tukar Cawangan
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Permohonan Tukar Cawangan</h2>
            </div>

            <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Eksport Data Ahli</p>
                        <p class="text-xs text-gray-500">Muat turun senarai ahli cawangan/negeri anda dalam format CSV.</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <a
                            :href="route('admin.members.export', { type: 'simple' })"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 transition"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12l-3-3m3 3l3-3M4 20h16"/>
                            </svg>
                            Ringkas
                        </a>
                        <a
                            :href="route('admin.members.export', { type: 'full' })"
                            class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-800 transition"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12l-3-3m3 3l3-3M4 20h16"/>
                            </svg>
                            Penuh
                        </a>
                    </div>
                </div>
            </div>

             <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                 <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem_auto]">
                     <label class="sr-only" for="branch-change-search">Cari permohonan</label>
                     <input id="branch-change-search" v-model="search" type="search" placeholder="Cari nama, no. ahli atau emel..." class="min-h-11 rounded-xl border border-gray-200 px-3 text-sm focus:border-gray-900 focus:ring-gray-900">
                     <label class="sr-only" for="from-branch-filter">Cawangan asal</label>
                     <select id="from-branch-filter" v-model="fromBranchId" class="min-h-11 rounded-xl border border-gray-200 px-3 text-sm focus:border-gray-900 focus:ring-gray-900">
                         <option value="">Semua cawangan asal</option>
                         <option v-for="branch in branches" :key="`from-${branch.id}`" :value="branch.id">{{ branch.name }}</option>
                     </select>
                     <label class="sr-only" for="to-branch-filter">Cawangan sasaran</label>
                     <select id="to-branch-filter" v-model="toBranchId" class="min-h-11 rounded-xl border border-gray-200 px-3 text-sm focus:border-gray-900 focus:ring-gray-900">
                         <option value="">Semua cawangan sasaran</option>
                         <option v-for="branch in branches" :key="`to-${branch.id}`" :value="branch.id">{{ branch.name }}</option>
                     </select>
                     <button type="button" class="min-h-11 rounded-xl border border-gray-200 px-4 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="search = ''; fromBranchId = ''; toBranchId = ''">Reset</button>
                 </div>
             </div>

             <div v-if="requestRows.length === 0" class="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center">
                 <p class="text-gray-500">{{ search || fromBranchId || toBranchId ? 'Tiada permohonan sepadan dengan carian.' : 'Tiada permohonan yang menunggu kelulusan.' }}</p>
             </div>

            <div v-else class="space-y-3">
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/70 px-4 py-3 shadow-sm">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-semibold text-indigo-900">
                            <input
                                type="checkbox"
                                :checked="allSelected"
                                @change="toggleSelectAll"
                                class="h-4 w-4 rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                            >
                            Pilih semua pada halaman ini ({{ requestRows.length }})
                        </label>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-xs font-semibold text-indigo-700">{{ selectedIds.length }} dipilih</span>
                            <button
                                @click="selectedIds = []"
                                :disabled="!selectedIds.length"
                                class="rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 transition disabled:opacity-40"
                            >
                                Kosongkan
                            </button>
                            <button
                                @click="bulkApprove"
                                :disabled="!selectedIds.length || bulkApproving"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition disabled:opacity-50"
                            >
                                {{ bulkApproving ? 'Memproses...' : `Luluskan Terpilih (${selectedIds.length})` }}
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-for="r in requestRows"
                    :key="r.id"
                    class="rounded-2xl border bg-white p-4 md:p-5 shadow-sm transition"
                    :class="selectedIds.includes(r.id) ? 'border-indigo-300 ring-1 ring-indigo-200' : 'border-gray-100'"
                >
                    <div class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(r.id)"
                            @change="toggleSelect(r.id)"
                            class="mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                            :aria-label="`Pilih permohonan ${r.user.name}`"
                        >
                        <div class="flex min-w-0 flex-1 items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ r.user.name }}</p>
                            <p class="text-xs text-gray-500">{{ r.user.email }} · {{ r.user.member_no }}</p>
                            <div class="mt-2 flex items-center gap-2 text-sm">
                                <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    {{ r.from_branch || 'Tiada Cawangan' }}
                                </span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="rounded-lg bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    {{ r.to_branch }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Dihantar {{ r.submitted_at }}</p>
                            </div>
                            <div class="flex shrink-0 gap-2">
                            <button
                                @click="approve(r)"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition"
                            >
                                Approve
                            </button>
                            <button
                                @click="openReject(r)"
                                class="rounded-xl border border-red-200 bg-white px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition"
                            >
                                Reject
                            </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="requests.last_page > 1" class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-xs text-gray-500">{{ requests.from }}–{{ requests.to }} daripada {{ requests.total }}</p>
                    <div class="flex items-center gap-1">
                        <button v-for="(page, index) in visiblePages" :key="`${page}-${index}`" type="button" :disabled="page === '...'" class="min-h-10 min-w-10 rounded-lg px-2 text-xs font-bold" :class="page == requests.current_page ? 'bg-gray-900 text-white' : 'border border-gray-200 text-gray-600 hover:border-gray-900'" @click="page !== '...' && requestNow(page)">{{ page }}</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showRejectModal = false">
            <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Tolak Permohonan</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Tolak pertukaran cawangan untuk <strong>{{ rejectTarget?.user.name }}</strong>?
                </p>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-gray-500">Sebab (optional)</label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0"
                        placeholder="Nyatakan sebab penolakan..."
                    ></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showRejectModal = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button @click="submitReject" :disabled="rejectForm.processing" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 transition disabled:opacity-50">
                        {{ rejectForm.processing ? 'Memproses...' : 'Tolak' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

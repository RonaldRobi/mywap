<script setup>
import { ref, computed, watch, reactive } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    isSuperadmin: Boolean,
    defaultOrganizationId: Number,
    organizations: { type: Array, default: () => [] },
    members: { type: Object, default: () => ({ data: [], links: [] }) },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', organization_id: '', branch_id: '' }) },
});

// Filters
const search = ref(props.filters.search ?? '');
const orgFilter = ref(props.filters.organization_id ?? '');
const branchFilter = ref(props.filters.branch_id ?? '');

let debounce;
watch([search, orgFilter, branchFilter], () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('admin.bulk-branch'), {
            search: search.value?.trim() || '',
            organization_id: orgFilter.value || '',
            branch_id: branchFilter.value || '',
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
});

// Selection
const selectedIds = ref(new Set());
const selectAll = ref(false);

const allPageIds = computed(() => props.members.data.map(m => m.id));

watch(selectAll, (val) => {
    if (val) {
        allPageIds.value.forEach(id => selectedIds.value.add(id));
    } else {
        allPageIds.value.forEach(id => selectedIds.value.delete(id));
    }
});

watch(() => props.members.data, () => {
    selectAll.value = false;
});

function toggleMember(id) {
    if (selectedIds.value.has(id)) {
        selectedIds.value.delete(id);
    } else {
        selectedIds.value.add(id);
    }
    selectAll.value = selectedIds.value.size === allPageIds.value.length && allPageIds.value.length > 0;
}

// Selected members data for preview
const selectedMembers = computed(() =>
    props.members.data.filter(m => selectedIds.value.has(m.id))
);

const selectionFromOtherPages = ref([]);

// Target branch
const targetBranchId = ref('');
const processing = ref(false);
const result = ref(null);

const targetBranchName = computed(() => {
    const b = props.branches.find(b => b.id == targetBranchId.value);
    return b?.name ?? '';
});

const unchangedMembers = computed(() =>
    selectedMembers.value.filter(m => m.branch_id == targetBranchId.value)
);

const willChangeMembers = computed(() =>
    selectedMembers.value.filter(m => m.branch_id != targetBranchId.value)
);

const totalSelected = computed(() =>
    selectedIds.value.size + selectionFromOtherPages.value.length
);

// Pagination helpers
const visiblePages = computed(() => {
    const last = props.members.last_page ?? 1;
    const cur = props.members.current_page ?? 1;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [1];
    if (cur > 3) pages.push('...');
    for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
    if (cur < last - 2) pages.push('...');
    pages.push(last);
    return pages;
});

function changePerPage(e) {
    router.get(route('admin.bulk-branch'), { ...route().params, per_page: e.target.value, page: 1 }, { preserveState: true, replace: true });
}

// Submit
async function submitBulkChange() {
    if (!targetBranchId.value) return alert('Sila pilih cawangan sasaran.');
    if (!willChangeMembers.value.length && !selectionFromOtherPages.value.length) {
        return alert('Tiada ahli yang perlu ditukar cawangan.');
    }

    processing.value = true;
    result.value = null;

    try {
        const res = await window.axios.patch(route('admin.members.bulk-branch'), {
            branch_id: targetBranchId.value,
            member_ids: willChangeMembers.value.map(m => m.id),
        });
        result.value = res.data;
        selectedIds.value.clear();
        selectionFromOtherPages.value = [];
        targetBranchId.value = '';
    } catch (e) {
        alert(e.response?.data?.message ?? 'Ralat semasa memproses.');
    } finally {
        processing.value = false;
    }
}
</script>

<template>
    <Head title="Tukar Cawangan (Bulk)" />
    <AppLayout>
        <template #header>Tukar Cawangan (Bulk)</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <div class="mb-6">
                <h2 class="text-2xl font-black tracking-tight text-gray-900">Tukar Cawangan Secara Pukal</h2>
                <p class="text-sm font-medium text-gray-500 mt-1">Pilih ahli dari senarai untuk ditukar ke cawangan yang sama serentak.</p>
            </div>

            <div class="flex flex-col lg:flex-row gap-6">
                <!-- ═══ LEFT PANEL — Member List ═══ -->
                <div class="flex-1 min-w-0">
                    <!-- Filters -->
                    <div class="flex flex-col md:flex-row gap-3 mb-4">
                        <div class="relative flex-1">
                            <input v-model="search" type="text" placeholder="Cari nama, no ahli, email, IC..." class="pl-4 w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                        </div>
                        <select v-if="isSuperadmin" v-model="orgFilter" class="md:w-44 rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm">
                            <option value="">Semua Organisasi</option>
                            <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                        <select v-model="branchFilter" class="md:w-44 rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm">
                            <option value="">Semua Cawangan</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <!-- Table -->
                    <div class="rounded-3xl border border-gray-100 bg-white overflow-hidden shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50/70 border-b border-gray-100 text-xs uppercase tracking-wider text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3 w-10">
                                            <input type="checkbox" v-model="selectAll" class="rounded border-gray-300 text-gray-900 focus:ring-0">
                                        </th>
                                        <th class="px-4 py-3">Nama / No Ahli</th>
                                        <th class="px-4 py-3">Cawangan Semasa</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <tr v-if="members.data.length === 0">
                                        <td colspan="3" class="px-4 py-12 text-center text-gray-400">Tiada ahli dijumpai.</td>
                                    </tr>
                                    <tr v-for="m in members.data" :key="m.id" class="hover:bg-gray-50/50 transition-colors cursor-pointer" @click="toggleMember(m.id)" :class="{ 'bg-indigo-50/50': selectedIds.has(m.id) }">
                                        <td class="px-4 py-3">
                                            <input type="checkbox" :checked="selectedIds.has(m.id)" @click.stop="toggleMember(m.id)" class="rounded border-gray-300 text-gray-900 focus:ring-0">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-bold text-gray-900">{{ m.name }}</div>
                                            <div class="text-xs font-mono text-gray-400">{{ m.member_no || '—' }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">{{ m.branch_name }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div class="flex items-center justify-between gap-4 mt-4 pb-6">
                        <div class="text-xs text-gray-500">
                            <span class="font-semibold text-gray-700">{{ members.from ?? 0 }}</span>–<span class="font-semibold text-gray-700">{{ members.to ?? 0 }}</span> dari <span class="font-semibold text-gray-700">{{ members.total ?? 0 }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <select @change="changePerPage" class="rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-900 focus:ring-0">
                                <option :value="25" :selected="(filters.per_page ?? 50) == 25">25</option>
                                <option :value="50" :selected="(filters.per_page ?? 50) == 50">50</option>
                                <option :value="100" :selected="(filters.per_page ?? 50) == 100">100</option>
                            </select>
                            <div v-if="members.last_page > 1" class="flex items-center gap-1">
                                <Link v-if="members.prev_page_url" :href="members.prev_page_url" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-900 text-sm">‹</Link>
                                <template v-for="(page, i) in visiblePages" :key="i">
                                    <span v-if="page === '...'" class="text-gray-400 px-1 text-xs">...</span>
                                    <Link v-else :href="members.links?.find(l => l.label == page)?.url ?? '#'" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold transition-all" :class="page == members.current_page ? 'bg-gray-900 text-white shadow-sm' : 'border border-gray-200 text-gray-600 hover:border-gray-900'">{{ page }}</Link>
                                </template>
                                <Link v-if="members.next_page_url" :href="members.next_page_url" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-900 text-sm">›</Link>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ═══ RIGHT PANEL — Actions ═══ -->
                <div class="lg:w-96 shrink-0">
                    <div class="sticky top-6 rounded-3xl border border-gray-100 bg-white shadow-sm p-5 space-y-5">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Dipilih</p>
                            <p class="mt-1 text-3xl font-black text-gray-900">{{ totalSelected }} <span class="text-base font-semibold text-gray-500">ahli</span></p>
                        </div>

                        <!-- Target Branch -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1.5">Cawangan Sasaran</label>
                            <select v-model="targetBranchId" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm">
                                <option value="" disabled>Pilih cawangan</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>

                        <!-- Preview -->
                        <div v-if="targetBranchId && selectedMembers.length" class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Pratonton</p>
                            <div class="max-h-60 overflow-y-auto space-y-1.5 rounded-xl border border-gray-100 p-3 bg-gray-50/50">
                                <div v-for="m in selectedMembers" :key="m.id" class="flex items-center justify-between text-xs">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-800 truncate">{{ m.name }}</p>
                                        <p class="text-gray-400 font-mono text-[10px]">{{ m.member_no || '—' }}</p>
                                    </div>
                                    <div class="flex items-center gap-1.5 shrink-0">
                                        <span class="text-[10px] text-gray-400">{{ m.branch_name }}</span>
                                        <svg class="w-3 h-3 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                        <span class="text-[10px] font-semibold" :class="m.branch_id == targetBranchId ? 'text-amber-600' : 'text-emerald-600'">{{ m.branch_id == targetBranchId ? '(sama)' : targetBranchName }}</span>
                                    </div>
                                </div>
                            </div>

                            <p v-if="unchangedMembers.length" class="text-[11px] text-amber-600">⚠ {{ unchangedMembers.length }} ahli sudah berada di cawangan ini dan akan dilangkau.</p>
                        </div>

                        <!-- Submit -->
                        <button
                            @click="submitBulkChange"
                            :disabled="processing || !targetBranchId || !totalSelected"
                            class="w-full rounded-2xl bg-gray-900 px-5 py-3 text-sm font-bold text-white hover:bg-gray-800 disabled:opacity-40 transition-all"
                        >
                            {{ processing ? 'Memproses...' : `Tukar ${willChangeMembers.length} Ahli` }}
                        </button>

                        <!-- Result -->
                        <div v-if="result" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-sm font-bold text-emerald-700">{{ result.success_count }} ahli berjaya ditukar ke {{ targetBranchName }}.</p>
                            <div v-if="result.errors?.length" class="mt-2 max-h-32 overflow-y-auto space-y-1">
                                <p v-for="(err, i) in result.errors" :key="i" class="text-[11px] text-amber-700">{{ err }}</p>
                            </div>
                        </div>

                        <!-- Clear selection -->
                        <button
                            v-if="totalSelected"
                            @click="selectedIds.clear(); selectionFromOtherPages = []; selectAll = false"
                            class="w-full text-xs font-semibold text-gray-400 hover:text-gray-600"
                        >
                            Kosongkan pilihan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    payments:     Object,
    organization: Object,
    summary:      Object,
    filters:      Object,
});

const filters = reactive({
    status:    props.filters?.status    ?? '',
    search:    props.filters?.search    ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to:   props.filters?.date_to   ?? '',
});

function applyFilters() {
    router.get(route('admin.transactions'), { ...filters }, { preserveState: true, replace: true });
}

function resetFilters() {
    filters.status    = '';
    filters.search    = '';
    filters.date_from = '';
    filters.date_to   = '';
    applyFilters();
}

function exportCsv() {
    const params = new URLSearchParams({ ...filters });
    window.location.href = route('admin.transactions.export.csv') + '?' + params.toString();
}

const statusColors = {
    successful: 'bg-emerald-100 text-emerald-700',
    pending:    'bg-amber-100 text-amber-700',
    failed:     'bg-red-100 text-red-700',
    refunded:   'bg-gray-100 text-gray-600',
};

const statusLabels = {
    pending:    'Menunggu',
    successful: 'Berjaya',
    failed:     'Gagal',
    refunded:   'Dipulangkan',
};

const typeColors = {
    membership_fee: 'bg-blue-100 text-blue-700',
    infaq_donation: 'bg-emerald-100 text-emerald-700',
    order:          'bg-purple-100 text-purple-700',
};

const typeLabels = {
    membership_fee: 'Yuran',
    infaq_donation: 'Infaq',
    order:          'Pesanan',
};

const hasActiveFilters = computed(() => filters.status || filters.search || filters.date_from || filters.date_to);
</script>

<template>
    <AppLayout>
        <Head :title="`Transaksi — ${organization.name}`" />

        <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">

            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Transaksi — {{ organization.name }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Papar & tapis semua transaksi kewangan.</p>
                </div>
                <button
                    @click="exportCsv"
                    class="inline-flex items-center gap-1.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Eksport CSV
                </button>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-5 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Jumlah Terkumpul</p>
                    <p class="mt-1 text-3xl font-black text-emerald-600">RM {{ Number(summary.total_collected ?? 0).toFixed(2) }}</p>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-5 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Menunggu Pengesahan</p>
                    <p class="mt-1 text-3xl font-black" :class="(summary.pending_count ?? 0) > 0 ? 'text-amber-500' : 'text-gray-400'">{{ summary.pending_count ?? 0 }} transaksi</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl p-4 shadow-sm space-y-3">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cari</label>
                        <input
                            v-model="filters.search"
                            @keyup.enter="applyFilters"
                            placeholder="Nama, emel, rujukan..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0 focus:border-gray-300 placeholder:text-gray-300"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
                        <select v-model="filters.status" @change="applyFilters" class="rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0">
                            <option value="">Semua</option>
                            <option value="successful">Berjaya</option>
                            <option value="pending">Menunggu</option>
                            <option value="failed">Gagal</option>
                            <option value="refunded">Dipulangkan</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Dari</label>
                        <input type="date" v-model="filters.date_from" @change="applyFilters" class="rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Hingga</label>
                        <input type="date" v-model="filters.date_to" @change="applyFilters" class="rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" />
                    </div>
                    <button v-if="hasActiveFilters" @click="resetFilters" class="text-xs text-gray-400 hover:text-gray-600 underline whitespace-nowrap pb-0.5">Reset</button>
                </div>
                <div v-if="payments.total" class="text-xs text-gray-400">
                    {{ payments.total }} transaksi ditemui
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Ahli</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Jenis</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Penerangan</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amaun</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rujukan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tarikh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="payments.data.length === 0">
                                <td colspan="8" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada rekod transaksi.</td>
                            </tr>
                            <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ p.id }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ p.user_name ?? '—' }}</p>
                                    <p class="text-xs text-gray-400">{{ p.user_email ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="typeColors[p.type] ?? 'bg-gray-100 text-gray-600'">
                                        {{ typeLabels[p.type] ?? p.type ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-[200px]">
                                    <span class="truncate block" :title="p.description ?? p.type">{{ p.description ?? '—' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">RM {{ Number(p.amount).toFixed(2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          :class="statusColors[p.status] ?? 'bg-gray-100 text-gray-600'">
                                        {{ statusLabels[p.status] ?? p.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono max-w-[120px] truncate" :title="p.reference">{{ p.reference ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ p.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="payments.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">
                        Menunjukkan {{ payments.from }}–{{ payments.to }} daripada {{ payments.total }}
                    </p>
                    <div class="flex gap-2">
                        <a v-if="payments.prev_page_url" :href="payments.prev_page_url"
                           class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">← Sebelum</a>
                        <a v-if="payments.next_page_url" :href="payments.next_page_url"
                           class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Seterusnya →</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

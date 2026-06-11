<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    payments:     Object,
    organization: Object,
    summary:      Object,
    filters:      Object,
});

const filters = reactive({
    status: props.filters?.status ?? '',
});

function applyFilters() {
    router.get(route('admin.transactions'), filters, { preserveState: true, replace: true });
}

const statusColors = {
    successful: 'bg-emerald-100 text-emerald-700',
    pending:    'bg-amber-100 text-amber-700',
    failed:     'bg-red-100 text-red-700',
    refunded:   'bg-gray-100 text-gray-600',
};
</script>

<template>
    <AppLayout>
        <Head :title="`Transaksi — ${organization.name}`" />

        <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-black text-gray-900">Transaksi — {{ organization.name }}</h1>
                <p class="text-sm text-gray-500 mt-0.5">Paparan bacaan sahaja. Hubungi Superadmin untuk perubahan.</p>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-5 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Jumlah Terkumpul</p>
                    <p class="mt-1 text-3xl font-black text-emerald-600">RM {{ Number(summary.total_collected ?? 0).toFixed(2) }}</p>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-5 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Menunggu Pengesahan</p>
                    <p class="mt-1 text-3xl font-black text-amber-500">{{ summary.pending_count ?? 0 }} transaksi</p>
                </div>
            </div>

            <!-- Filter -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl p-4 shadow-sm flex items-end gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Tapis Status</label>
                    <select v-model="filters.status" @change="applyFilters" class="rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0">
                        <option value="">Semua</option>
                        <option value="successful">Berjaya</option>
                        <option value="pending">Menunggu</option>
                        <option value="failed">Gagal</option>
                        <option value="refunded">Dipulangkan</option>
                    </select>
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
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Penerangan</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amaun</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Rujukan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tarikh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="payments.data.length === 0">
                                <td colspan="7" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada rekod transaksi.</td>
                            </tr>
                            <tr v-for="p in payments.data" :key="p.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ p.id }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ p.user_name }}</p>
                                    <p class="text-xs text-gray-400">{{ p.user_email }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ p.description ?? p.type }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">RM {{ Number(p.amount).toFixed(2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                          :class="statusColors[p.status] ?? 'bg-gray-100 text-gray-600'">
                                        {{ p.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ p.reference ?? '—' }}</td>
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

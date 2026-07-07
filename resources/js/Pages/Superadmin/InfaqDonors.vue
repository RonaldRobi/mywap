<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    infaq: { type: Object, required: true },
    donations: { type: Array, required: true },
});

const searchQuery = ref('');
const statusFilter = ref('all');

const statusOptions = ['all', 'confirmed', 'pending'];

const filteredDonations = computed(() => {
    let items = [...props.donations];
    const q = searchQuery.value.toLowerCase().trim();
    if (q) {
        items = items.filter(d =>
            d.donor_name.toLowerCase().includes(q) ||
            (d.donor_email && d.donor_email.toLowerCase().includes(q)) ||
            (d.donor_phone && d.donor_phone.includes(q)) ||
            (d.reference && d.reference.toLowerCase().includes(q))
        );
    }
    if (statusFilter.value !== 'all') {
        items = items.filter(d => d.status === statusFilter.value);
    }
    return items;
});

const stats = computed(() => {
    const confirmed = props.donations.filter(d => d.status === 'confirmed');
    return {
        total: props.donations.length,
        confirmed: confirmed.length,
        pending: props.donations.filter(d => d.status === 'pending').length,
        totalAmount: confirmed.reduce((s, d) => s + d.amount, 0),
        uniqueDonors: new Set(props.donations.map(d => d.donor_email || d.donor_name)).size,
    };
});

function formatMYR(val) {
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(val ?? 0);
}

function getStatusBadge(status) {
    switch (status) {
        case 'confirmed': return { bg: 'bg-emerald-100', text: 'text-emerald-700', label: 'Disahkan' };
        case 'pending': return { bg: 'bg-amber-100', text: 'text-amber-700', label: 'Tertunda' };
        case 'failed': return { bg: 'bg-red-100', text: 'text-red-700', label: 'Gagal' };
        default: return { bg: 'bg-gray-100', text: 'text-gray-600', label: status };
    }
}

function exportCsv() {
    const header = ['Nama', 'Emel', 'Telefon', 'Jumlah (RM)', 'Status', 'Tarikh', 'Recurring', 'Anonymous', 'Doa'];
    const rows = filteredDonations.value.map(d => [
        d.donor_name,
        d.donor_email || '',
        d.donor_phone || '',
        d.amount,
        d.status,
        d.created_at,
        d.is_recurring ? 'Ya' : 'Tidak',
        d.is_anonymous ? 'Ya' : 'Tidak',
        (d.prayer_message || '').replace(/"/g, '""'),
    ]);
    const csv = [header, ...rows].map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `penderma-${props.infaq.slug}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head :title="`Penderma - ${infaq.title}`" />
    <AppLayout :back-route="route('superadmin.infaq.index')" back-label="Kembali ke Urus Infaq">
        <template #header>Senarai Penderma</template>

        <div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
            <!-- Campaign Info Banner -->
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-black text-gray-900">{{ infaq.title }}</h2>
                        <div class="flex flex-wrap items-center gap-3 mt-1">
                            <span class="text-sm text-gray-500">{{ infaq.organization_name }}</span>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="infaq.type === 'progress' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'">
                                {{ infaq.type === 'progress' ? 'Progress' : 'One-Off' }}
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <Link :href="infaq.public_url" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition">
                            Lihat Kempen
                        </Link>
                        <button @click="exportCsv" class="rounded-xl bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800 transition">
                            Eksport CSV
                        </button>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div v-if="infaq.type === 'progress' && infaq.target_amount" class="mt-4">
                    <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                        <span class="font-semibold">{{ formatMYR(infaq.collected_amount) }}</span>
                        <span class="text-gray-400">/ {{ formatMYR(infaq.target_amount) }} ({{ infaq.progress_percent }}%)</span>
                    </div>
                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-2 rounded-full bg-emerald-500" :style="{ width: infaq.progress_percent + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="rounded-2xl bg-white border border-gray-100 p-4 shadow-sm text-center">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total }}</p>
                    <p class="text-xs font-semibold text-gray-400 mt-0.5">Jumlah Transaksi</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-4 shadow-sm text-center">
                    <p class="text-2xl font-black text-emerald-600">{{ formatMYR(stats.totalAmount) }}</p>
                    <p class="text-xs font-semibold text-gray-400 mt-0.5">Jumlah Disahkan</p>
                </div>
                <div class="rounded-2xl bg-white border border-gray-100 p-4 shadow-sm text-center">
                    <p class="text-2xl font-black text-gray-900">{{ stats.uniqueDonors }}</p>
                    <p class="text-xs font-semibold text-gray-400 mt-0.5">Penderma Unik</p>
                </div>
                <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 shadow-sm text-center">
                    <p class="text-2xl font-black text-emerald-700">{{ stats.confirmed }}</p>
                    <p class="text-xs font-semibold text-emerald-600 mt-0.5">Disahkan</p>
                </div>
                <div class="rounded-2xl bg-amber-50 border border-amber-100 p-4 shadow-sm text-center">
                    <p class="text-2xl font-black text-amber-700">{{ stats.pending }}</p>
                    <p class="text-xs font-semibold text-amber-600 mt-0.5">Tertunda</p>
                </div>
            </div>

            <!-- Search & Filter -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input v-model="searchQuery" type="text" placeholder="Cari nama, emel, telefon, atau rujukan..."
                        class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 text-sm focus:border-emerald-400 focus:ring-0 outline-none" />
                </div>
                <select v-model="statusFilter" class="rounded-xl border border-gray-200 text-sm font-semibold py-2.5 px-3 bg-white focus:border-emerald-400 focus:ring-0 outline-none">
                    <option value="all">Semua Status</option>
                    <option value="confirmed">Disahkan</option>
                    <option value="pending">Tertunda</option>
                </select>
            </div>

            <!-- Donor Table -->
            <div class="rounded-3xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Penderma</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden md:table-cell">Emel</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Jumlah</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden sm:table-cell">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Tarikh</th>
                                <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider hidden lg:table-cell">Doa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="d in filteredDonations" :key="d.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-8 w-8 rounded-full bg-gray-100 flex items-center justify-center text-gray-500 font-bold text-xs shrink-0">
                                            {{ d.donor_name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">{{ d.donor_name }}</p>
                                            <p class="text-xs text-gray-400 sm:hidden">{{ d.created_at }}</p>
                                        </div>
                                    </div>
                                    <div v-if="d.is_recurring" class="mt-1 ml-10 inline-flex items-center rounded-full bg-purple-50 px-1.5 py-0.5 text-[10px] font-semibold text-purple-600">
                                        Recurring
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 hidden md:table-cell truncate max-w-[160px]">{{ d.donor_email || '-' }}</td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900">{{ formatMYR(d.amount) }}</td>
                                <td class="px-4 py-3 text-center hidden sm:table-cell">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="getStatusBadge(d.status).bg + ' ' + getStatusBadge(d.status).text">
                                        {{ getStatusBadge(d.status).label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500 hidden lg:table-cell whitespace-nowrap">{{ d.created_at }}</td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    <span v-if="d.prayer_message" class="text-gray-500 italic text-xs line-clamp-1 max-w-[200px]">"{{ d.prayer_message }}"</span>
                                    <span v-else class="text-gray-300 text-xs">-</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty state -->
                <div v-if="!filteredDonations.length" class="py-12 text-center">
                    <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p class="text-sm font-bold text-gray-500">Tiada penderma ditemui</p>
                    <p class="text-xs text-gray-400 mt-1" v-if="props.donations.length">Tiada hasil untuk carian ini.</p>
                    <p class="text-xs text-gray-400 mt-1" v-else>Belum ada sumbangan untuk kempen ini.</p>
                </div>

                <!-- Footer -->
                <div v-if="filteredDonations.length" class="border-t border-gray-100 bg-gray-50/30 px-4 py-3 flex items-center justify-between text-xs text-gray-500">
                    <span>Menunjukkan {{ filteredDonations.length }} dari {{ props.donations.length }} penderma</span>
                    <span v-if="statusFilter === 'all'">Jumlah: {{ formatMYR(stats.totalAmount) }}</span>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

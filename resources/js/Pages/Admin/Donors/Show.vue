<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    donor:     Object,
    donations: Array,
    stats:     Object,
});

const statusColors = {
    confirmed: 'bg-emerald-100 text-emerald-700',
    pending:   'bg-amber-100 text-amber-700',
    failed:    'bg-red-100 text-red-700',
};
</script>

<template>
    <AppLayout>
        <Head :title="`Penderma — ${donor.name}`" />

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
            <!-- Back -->
            <Link :href="route('admin.donors.index')" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition">
                ← Kembali ke senarai penderma
            </Link>

            <!-- Donor Profile -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 shadow-sm">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-14 h-14 rounded-full flex items-center justify-center font-bold text-xl text-white"
                         :style="{ backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#f97316','#14b8a6','#0ea5e9'][donor.id % 6] }">
                        {{ (donor.name || '?')[0].toUpperCase() }}
                    </div>
                    <div>
                        <h1 class="text-xl font-black text-gray-900">{{ donor.name }}</h1>
                        <p class="text-sm text-gray-500">{{ donor.email ?? '—' }} · {{ donor.phone ?? '—' }}</p>
                        <p v-if="donor.user_name" class="text-xs text-indigo-500 mt-0.5">Akaun: {{ donor.user_name }} ({{ donor.user_email }})</p>
                    </div>
                </div>

                <!-- Stats -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="bg-gray-50 rounded-2xl p-3 text-center">
                        <p class="text-xs text-gray-400">Jumlah Sumbangan</p>
                        <p class="mt-1 font-bold text-emerald-600">RM {{ Number(donor.total_donated).toFixed(2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-3 text-center">
                        <p class="text-xs text-gray-400">Disahkan</p>
                        <p class="mt-1 font-bold text-emerald-600">RM {{ Number(stats.confirmed_amount).toFixed(2) }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-3 text-center">
                        <p class="text-xs text-gray-400">Kekerapan</p>
                        <p class="mt-1 font-bold text-gray-800">{{ stats.confirmed_count }} transaksi</p>
                    </div>
                    <div class="bg-gray-50 rounded-2xl p-3 text-center">
                        <p class="text-xs text-gray-400">Berkala</p>
                        <p class="mt-1 font-bold" :class="stats.recurring_count > 0 ? 'text-indigo-600' : 'text-gray-400'">{{ stats.recurring_count }}</p>
                    </div>
                </div>
            </div>

            <!-- Donation History -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-700">Sejarah Sumbangan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Kempen</th>
                                <th class="px-4 py-2 text-right text-xs font-semibold text-gray-500 uppercase">Amaun</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Berkala</th>
                                <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase">Tarikh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="donations.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada rekod sumbangan.</td>
                            </tr>
                            <tr v-for="d in donations" :key="d.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-2">
                                    <a v-if="d.infaq_url" :href="d.infaq_url" target="_blank" class="text-gray-800 hover:text-indigo-600 font-medium">
                                        {{ d.infaq_title ?? '—' }}
                                    </a>
                                    <span v-else class="text-gray-400">—</span>
                                    <p v-if="d.prayer_message" class="text-xs text-gray-400 italic mt-0.5 truncate max-w-[200px]">"{{ d.prayer_message }}"</p>
                                </td>
                                <td class="px-4 py-2 text-right font-bold text-gray-800">RM {{ Number(d.amount).toFixed(2) }}</td>
                                <td class="px-4 py-2">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                                          :class="statusColors[d.status] ?? 'bg-gray-100 text-gray-600'">
                                        {{ d.status === 'confirmed' ? 'Disahkan' : d.status === 'pending' ? 'Menunggu' : 'Gagal' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-400">
                                    <span v-if="d.is_recurring" class="text-indigo-600 font-semibold">{{ d.frequency === 'MT' ? 'Bulanan' : d.frequency === 'WK' ? 'Mingguan' : 'Tahunan' }}</span>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-4 py-2 text-xs text-gray-400 whitespace-nowrap">{{ d.created_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

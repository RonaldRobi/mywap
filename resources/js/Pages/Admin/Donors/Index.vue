<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    donors:  Object,
    summary: Object,
    filters: Object,
});

const filters = reactive({
    search: props.filters?.search ?? '',
    sort:   props.filters?.sort   ?? 'recent',
});

function applyFilters() {
    router.get(route('admin.donors.index'), { ...filters }, { preserveState: true, replace: true });
}

function resetFilters() {
    filters.search = '';
    filters.sort = 'recent';
    applyFilters();
}

const hasFilters = computed(() => filters.search);
</script>

<template>
    <AppLayout>
        <Head title="Penderma" />

        <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Penderma</h1>
                <p class="text-sm text-gray-500 mt-0.5">Senarai semua penderma merentas kempen infaq.</p>
            </div>

            <!-- Summary -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-4 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Penderma</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ summary.total_donors }}</p>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-4 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Jumlah Sumbangan</p>
                    <p class="mt-1 text-2xl font-black text-emerald-600">RM {{ Number(summary.total_amount ?? 0).toFixed(2) }}</p>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-4 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Purata / Penderma</p>
                    <p class="mt-1 text-2xl font-black text-blue-600">RM {{ Number(summary.avg_per_donor ?? 0).toFixed(2) }}</p>
                </div>
                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-4 shadow-sm">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Jumlah Transaksi</p>
                    <p class="mt-1 text-2xl font-black text-purple-600">{{ summary.total_donations }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl p-4 shadow-sm flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Cari</label>
                    <input
                        v-model="filters.search"
                        @keyup.enter="applyFilters"
                        placeholder="Nama, emel, telefon..."
                        class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0 focus:border-gray-300 placeholder:text-gray-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Susun</label>
                    <select v-model="filters.sort" @change="applyFilters" class="rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0">
                        <option value="recent">Terkini</option>
                        <option value="most">Tertinggi</option>
                        <option value="frequent">Kerap</option>
                    </select>
                </div>
                <button v-if="hasFilters" @click="resetFilters" class="text-xs text-gray-400 hover:text-gray-600 underline pb-0.5">Reset</button>
            </div>

            <!-- Donor List -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Penderma</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Jumlah</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Kekerapan</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Terakhir</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="donors.data.length === 0">
                                <td colspan="4" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada rekod penderma.</td>
                            </tr>
                            <tr v-for="d in donors.data" :key="d.id" class="hover:bg-gray-50/60 transition cursor-pointer" @click="router.get(route('admin.donors.show', d.id))">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm text-white"
                                             :style="{ backgroundColor: ['#6366f1','#8b5cf6','#ec4899','#f97316','#14b8a6','#0ea5e9'][d.id % 6] }">
                                            {{ (d.name || '?')[0].toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-800">{{ d.name }}</p>
                                            <p class="text-xs text-gray-400">{{ d.email ?? d.phone ?? '—' }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-800">RM {{ Number(d.total_donated).toFixed(2) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-indigo-100 text-indigo-700">{{ d.donation_count }}x</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ d.last_donated_at ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="donors.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Menunjukkan {{ donors.from }}–{{ donors.to }} daripada {{ donors.total }}</p>
                    <div class="flex gap-2">
                        <a v-if="donors.prev_page_url" :href="donors.prev_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">← Sebelum</a>
                        <a v-if="donors.next_page_url" :href="donors.next_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Seterusnya →</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

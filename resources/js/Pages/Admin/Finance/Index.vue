<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, watch, onMounted, onUnmounted, computed } from 'vue';
import { Chart, registerables } from 'chart.js';
Chart.register(...registerables);

const props = defineProps({ stats: Object, chart: Array, transactions: Object, year: Number, years: Array, organizations: Array, filters: Object });

const isSuperadmin = computed(() => props.organizations.length > 0);
const selectedYear = ref(props.year);
const selectedOrg = ref(props.filters.organization_id || '');

function applyFilters() {
    const params = { year: selectedYear.value };
    if (selectedOrg.value) params.organization_id = selectedOrg.value;
    window.location.href = route('admin.finance.index') + '?' + new URLSearchParams(params).toString();
}
watch(selectedYear, applyFilters);

// ─── Esc key ────────────────────────────────────────────────────────────
function onKeydown(e) { if (e.key === 'Escape') memberSlideOver.value = null; }
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

// ─── Charts ──────────────────────────────────────────────────────────────
const barCanvas = ref(null);
const pieCanvas = ref(null);

onMounted(() => {
    if (barCanvas.value) {
        new Chart(barCanvas.value, {
            type: 'bar',
            data: {
                labels: props.chart.map(c => c.month.slice(0, 3)),
                datasets: [{
                    label: 'Pendapatan (RM)',
                    data: props.chart.map(c => c.total),
                    backgroundColor: '#6366f1',
                    borderRadius: 6,
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => 'RM ' + v } } },
            },
        });
    }

    if (pieCanvas.value && props.stats.by_source.length) {
        const colors = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        new Chart(pieCanvas.value, {
            type: 'doughnut',
            data: {
                labels: props.stats.by_source.map(s => s.type),
                datasets: [{
                    data: props.stats.by_source.map(s => s.total),
                    backgroundColor: colors.slice(0, props.stats.by_source.length),
                    borderWidth: 2,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } } },
            },
        });
    }
});

// ─── Member Slide-over ──────────────────────────────────────────────────
const memberSlideOver = ref(null);
const memberHistory = ref([]);
const memberLoading = ref(false);

async function showMemberDetails(trans) {
    if (!trans.user_id) return;
    memberSlideOver.value = { name: trans.member_name, member_no: trans.member_no };
    memberHistory.value = [];
    memberLoading.value = true;
    try {
        const res = await fetch(route('admin.finance.member', trans.user_id));
        if (res.ok) {
            const json = await res.json();
            memberSlideOver.value = { name: json.data.name, member_no: json.data.member_no, organization: json.data.organization };
            memberHistory.value = json.data.history;
        }
    } catch { /* ignore */ }
    memberLoading.value = false;
}

// ─── Helpers ─────────────────────────────────────────────────────────────
function formatAmount(v) { return v ? 'RM ' + Number(v).toFixed(2) : '—'; }

const sourceColors = {
    'Yuran Keahlian': 'bg-indigo-50 text-indigo-700 border-indigo-100',
    'E-Commerce': 'bg-green-50 text-green-700 border-green-100',
    'Infaq': 'bg-amber-50 text-amber-700 border-amber-100',
    'Kempen': 'bg-blue-50 text-blue-700 border-blue-100',
};
</script>

<template>
    <Head title="Kewangan Organisasi" />
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Kewangan Organisasi</template>
        <div class="mx-auto max-w-7xl px-4 py-6">

            <!-- Filters -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <select v-model="selectedYear" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <select v-if="isSuperadmin" v-model="selectedOrg" @change="applyFilters" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                </select>
                <div class="flex gap-2 ml-auto">
                    <a :href="route('admin.finance.export.pdf', { year: selectedYear, organization_id: selectedOrg })" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Jana Laporan PDF</a>
                    <div class="relative group">
                        <button class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Export ▾</button>
                        <div class="absolute right-0 top-full z-20 mt-1 hidden w-40 rounded-xl border border-gray-100 bg-white shadow-lg group-hover:block">
                            <a :href="route('admin.finance.export.excel', { year: selectedYear, organization_id: selectedOrg })" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Excel</a>
                            <a :href="route('admin.finance.export.csv', { year: selectedYear, organization_id: selectedOrg })" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">CSV</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-lg font-black text-gray-900">{{ formatAmount(stats.total) }}</p>
                    <p class="text-xs font-semibold text-gray-500">Jumlah Pendapatan</p>
                </div>
                <div v-for="s in stats.by_source" :key="s.type"
                    class="rounded-2xl border p-4 text-center shadow-sm"
                    :class="sourceColors[s.type] || 'bg-gray-50 text-gray-700 border-gray-100'">
                    <p class="text-lg font-black">{{ formatAmount(s.total) }}</p>
                    <p class="text-xs font-semibold">{{ s.type }}</p>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="mb-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Pendapatan Bulanan</p>
                    <canvas ref="barCanvas" height="200"></canvas>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Pecahan Mengikut Sumber</p>
                    <div v-if="!stats.by_source.length" class="flex items-center justify-center h-48 text-sm text-gray-400">Tiada data</div>
                    <canvas v-else ref="pieCanvas" height="200"></canvas>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">#</th><th class="px-4 py-3">Tarikh</th><th class="px-4 py-3">Ahli</th>
                            <th class="px-4 py-3">No Ahli</th><th class="px-4 py-3">Jenis</th><th class="px-4 py-3 text-right">Jumlah</th>
                            <th class="px-4 py-3">Rujukan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(t, i) in transactions.data" :key="t.id" class="border-b border-gray-50 hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-xs text-gray-400">{{ transactions.from + i }}</td>
                            <td class="px-4 py-3 text-xs text-gray-600">{{ t.created_at ? new Date(t.created_at).toLocaleDateString('ms-MY') : '—' }}</td>
                            <td class="px-4 py-3 text-xs font-medium" :class="t.user_id ? 'text-indigo-700 cursor-pointer hover:underline' : 'text-gray-900'" @click="showMemberDetails(t)">{{ t.member_name }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ t.member_no || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="sourceColors[t.type] || 'bg-gray-50 text-gray-600 border-gray-100'">{{ t.type }}</span>
                            </td>
                            <td class="px-4 py-3 text-xs text-right font-semibold text-gray-900">{{ formatAmount(t.amount) }}</td>
                            <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ t.reference || '—' }}</td>
                        </tr>
                        <tr v-if="!transactions.data.length">
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">Tiada transaksi.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="transactions.links" class="mt-4 flex justify-center gap-1">
                <component v-for="(link, i) in transactions.links" :is="link.url ? Link : 'span'" :key="i" :href="link.url" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
            </div>
        </div>

        <!-- ─── Member Finance Slide-over ──────────────────────────────── -->
        <Teleport to="body">
            <div v-if="memberSlideOver" class="fixed inset-0 z-50" @click.self="memberSlideOver = null">
                <div class="fixed right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl overflow-y-auto">
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ memberSlideOver.name }}</h3>
                            <p class="text-sm text-gray-500">{{ memberSlideOver.organization }} · {{ memberSlideOver.member_no || '—' }}</p>
                        </div>
                        <button @click="memberSlideOver = null" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100">&times;</button>
                    </div>
                    <div class="p-6">
                        <div v-if="memberLoading" class="space-y-3">
                            <div class="h-4 w-3/4 rounded bg-gray-100 animate-pulse"></div>
                            <div class="h-4 w-1/2 rounded bg-gray-100 animate-pulse"></div>
                        </div>
                        <div v-else-if="!memberHistory.length" class="py-10 text-center text-sm text-gray-400">Tiada rekod transaksi.</div>
                        <div v-else class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">Sejarah Transaksi</p>
                            <div v-for="(h, i) in memberHistory" :key="i" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="sourceColors[h.type] || 'bg-gray-50 text-gray-600'">{{ h.type }}</span>
                                    <span class="text-xs text-gray-400">{{ h.created_at ? new Date(h.created_at).toLocaleDateString('ms-MY') : '—' }}</span>
                                </div>
                                <div class="mt-2 flex items-center justify-between">
                                    <p class="text-xs text-gray-500">{{ h.description || '—' }}</p>
                                    <p class="text-sm font-bold text-gray-900">{{ formatAmount(h.amount) }}</p>
                                </div>
                                <p class="mt-1 text-[10px] text-gray-400">Rujukan: {{ h.reference }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>

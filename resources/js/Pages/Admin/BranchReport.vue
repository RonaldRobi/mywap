<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    branch: Object,
    branches: Array,
    year: Number,
    years: Array,
    summary: Array,
    collected_amount: Number,
    activity: { type: Array, default: () => [] },
    monthly: Array,
    events: Array,
    attendance_rate: Number,
});

const selectedYear = ref(props.year);
const selectedBranchId = ref(props.branch?.id);

watch(() => props.year, (value) => { selectedYear.value = value; });
watch(() => props.branch?.id, (value) => { selectedBranchId.value = value; });

function applyFilter() {
    router.get(route('admin.branch-report.index'), {
        year: selectedYear.value,
        branch_id: selectedBranchId.value,
    }, { preserveState: true, preserveScroll: true });
}

watch([selectedYear, selectedBranchId], applyFilter);

const maxMonthly = computed(() => Math.max(1, ...props.monthly.map((m) => m.events)));

const exportUrl = computed(() => route('admin.branch-report.export', {
    year: selectedYear.value,
    branch_id: selectedBranchId.value,
}));

const primarySummary = computed(() => props.summary.slice(0, 8));
const eventSummary = computed(() => props.summary.slice(8));
</script>

<template>
    <Head :title="`Laporan Cawangan: ${branch?.name ?? ''}`" />

    <AppLayout>
        <div class="mx-auto w-full max-w-6xl px-4 py-6 md:px-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-900">Laporan Cawangan</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ branch?.name }} {{ branch?.state ? '· '.concat(branch.state) : '' }} · Tahun {{ selectedYear }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <select
                        v-if="branches.length > 1"
                        v-model.number="selectedBranchId"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0"
                    >
                        <option v-for="item in branches" :key="item.id" :value="item.id">{{ item.name }}</option>
                    </select>

                    <select
                        v-model.number="selectedYear"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 focus:border-indigo-400 focus:ring-0"
                    >
                        <option v-for="item in years" :key="item" :value="item">{{ item }}</option>
                    </select>

                    <a
                        :href="exportUrl"
                        class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 transition-colors"
                    >
                        Muat Turun CSV
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <div v-for="item in primarySummary" :key="item.key" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ item.value }}</p>
                    <p class="mt-1 text-xs font-semibold text-gray-500">{{ item.label }}</p>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Yuran terkumpul {{ selectedYear }}</p>
                    <p class="mt-1 text-2xl font-black text-emerald-700">RM {{ Number(collected_amount).toFixed(2) }}</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Kadar kehadiran program</p>
                    <p class="mt-1 text-2xl font-black text-indigo-700">{{ attendance_rate }}%</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Jumlah program</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ branch ? summary.find((s) => s.key === 'total_events')?.value ?? 0 : 0 }}</p>
                </div>
            </div>

            <div class="mt-4 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Keaktifan Ahli Cawangan</h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div v-for="item in activity" :key="item.key" class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <p class="text-2xl font-black text-gray-900">{{ item.value }}</p>
                        <p class="mt-1 text-xs font-semibold text-gray-500">{{ item.label }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm lg:col-span-2">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Program Mengikut Bulan</h2>

                    <div v-if="monthly.every((m) => m.events === 0)" class="mt-6 rounded-2xl border border-dashed border-gray-200 py-8 text-center">
                        <p class="text-sm text-gray-400">Tiada program direkodkan untuk tahun ini.</p>
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <div v-for="month in monthly" :key="month.month" class="flex items-center gap-3">
                            <span class="w-10 shrink-0 text-xs font-semibold text-gray-500">{{ month.month }}</span>
                            <div class="h-3 flex-1 overflow-hidden rounded-full bg-gray-100">
                                <div
                                    class="h-full rounded-full bg-indigo-500 transition-all"
                                    :style="{ width: (month.events / maxMonthly) * 100 + '%' }"
                                ></div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-xs font-bold text-gray-700">{{ month.events }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Ringkasan Program</h2>
                    <div class="mt-4 space-y-3">
                        <div v-for="item in eventSummary" :key="item.key" class="flex items-center justify-between gap-3 border-b border-gray-50 pb-2 last:border-0 last:pb-0">
                            <span class="text-sm text-gray-600">{{ item.label }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ item.value }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Senarai Program</h2>
                </div>

                <div v-if="events.length === 0" class="px-5 py-10 text-center">
                    <p class="text-sm text-gray-400">Belum ada program bagi tahun {{ selectedYear }}.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Program</th>
                                <th class="px-5 py-3 font-semibold">Tarikh</th>
                                <th class="px-5 py-3 text-right font-semibold">Peserta</th>
                                <th class="px-5 py-3 text-right font-semibold">Hadir</th>
                                <th class="px-5 py-3 text-right font-semibold">Kadar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="event in events" :key="event.id">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-800">{{ event.title }}</p>
                                    <p class="text-xs text-gray-400">{{ event.status_label }}</p>
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ event.start_formatted }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-800">{{ event.registrations_count }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ event.attended_count }}</td>
                                <td class="px-5 py-3 text-right text-gray-600">{{ event.attendance_rate }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

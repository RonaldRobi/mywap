<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    registrations: Object, // paginator
    stats: Object,
    events: Array,
    organizations: Array,
    filters: Object,
});

const qs = (extra = {}) => {
    const params = {
        event_id: document.getElementById('f-event')?.value || '',
        org: document.getElementById('f-org')?.value || '',
        attendance: document.getElementById('f-attendance')?.value || '',
        search: document.getElementById('f-search')?.value || '',
        ...extra,
    };
    const query = Object.entries(params).filter(([, v]) => v !== '').map(([k, v]) => `${k}=${encodeURIComponent(v)}`).join('&');
    return query ? `?${query}` : '';
};

function applyFilters() {
    router.get(route('admin.attendance'), {}, {
        data: {
            event_id: document.getElementById('f-event')?.value || '',
            org: document.getElementById('f-org')?.value || '',
            attendance: document.getElementById('f-attendance')?.value || '',
            search: document.getElementById('f-search')?.value || '',
        },
        preserveState: true,
        replace: true,
    });
}
</script>

<template>
    <Head title="Dashboard Kehadiran" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <h1 class="text-2xl font-black text-gray-900 mb-1">Dashboard Kehadiran</h1>
            <p class="text-sm text-gray-500 mb-6">Kehadiran peserta mengikut event &amp; organisasi.</p>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="rounded-3xl bg-white border border-gray-100 p-5 text-center shadow-sm">
                    <p class="text-3xl font-black text-gray-900">{{ stats.total_registered }}</p>
                    <p class="text-xs text-gray-400 font-semibold uppercase mt-1">Jumlah Daftar</p>
                </div>
                <div class="rounded-3xl bg-emerald-50 border border-emerald-100 p-5 text-center">
                    <p class="text-3xl font-black text-emerald-600">{{ stats.total_attended }}</p>
                    <p class="text-xs text-emerald-500 font-semibold uppercase mt-1">Hadir</p>
                </div>
                <div class="rounded-3xl bg-amber-50 border border-amber-100 p-5 text-center">
                    <p class="text-3xl font-black text-amber-600">{{ stats.total_pending_payment }}</p>
                    <p class="text-xs text-amber-500 font-semibold uppercase mt-1">Bayaran Menunggu</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl bg-white border border-gray-100 p-4 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input id="f-search" :value="filters.search || ''" placeholder="Cari nama / no reg..." class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                <select id="f-event" :value="filters.event_id || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Event</option>
                    <option v-for="e in events" :key="e.id" :value="e.id">{{ e.title }}</option>
                </select>
                <select v-if="organizations.length" id="f-org" :value="filters.org || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <select id="f-attendance" :value="filters.attendance || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Kehadiran</option>
                    <option value="hadir">Hadir</option>
                    <option value="tidak_hadir">Tidak Hadir</option>
                </select>
                <div class="flex gap-2">
                    <button @click="applyFilters" class="flex-1 rounded-xl bg-gray-900 text-white px-3 py-2 text-sm font-semibold">Tapis</button>
                </div>
            </div>

            <!-- Export -->
            <div class="flex gap-2 mb-6">
                <a :href="route('admin.attendance.export.excel') + qs()" class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700">Export Excel</a>
                <a :href="route('admin.attendance.export.pdf') + qs()" class="rounded-xl bg-red-600 text-white px-4 py-2 text-sm font-semibold hover:bg-red-700">Export PDF</a>
            </div>

            <!-- Table -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Bayaran</th>
                            <th class="px-4 py-3">Kehadiran</th>
                            <th class="px-4 py-3">Masa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ r.registration_no }}<span v-if="r.member_no"> · {{ r.member_no }}</span></p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.organization_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.event_title || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="r.payment_status === 'successful' || r.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ r.payment_status === 'successful' || r.payment_status === 'paid' ? 'Berjaya' : 'Menunggu' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold" :class="r.attended ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-gray-200 bg-gray-50 text-gray-500'">
                                    {{ r.attended ? 'Hadir' : 'Tidak Hadir' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ r.attended_at || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

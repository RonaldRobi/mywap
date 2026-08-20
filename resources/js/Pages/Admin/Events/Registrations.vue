<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
    registrations: Object, // paginator
    organizations: Array,
    filters: Object,
});

const statusForm = useForm({ status: '' });

function updateStatus(reg, status) {
    if (!confirm(`Tukar status pendaftaran ${reg.registration_no} kepada "${status}"?`)) return;
    statusForm.status = status;
    statusForm.patch(route('admin.events.registrations.update', reg.id));
}

function applyFilters() {
    router.get(route('admin.events.registrations', props.event.id), {
        org: document.getElementById('f-org')?.value || '',
        status: document.getElementById('f-status')?.value || '',
        search: document.getElementById('f-search')?.value || '',
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="`Pendaftaran: ${event.title}`" />

    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between gap-3 mb-1">
                <h1 class="text-2xl font-black text-gray-900">Pendaftaran</h1>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ event.title }}</p>

            <!-- Filters -->
            <div class="rounded-3xl bg-white border border-gray-100 p-4 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input id="f-search" :value="filters.search || ''" placeholder="Cari nama / no reg..." class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                <select id="f-status" :value="filters.status || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Status</option>
                    <option value="confirmed">Disahkan</option>
                    <option value="pending">Menunggu</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <select id="f-org" :value="filters.org || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <button @click="applyFilters" class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm font-semibold">Tapis</button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="rounded-3xl bg-white border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-black text-gray-900">{{ registrations.total }}</p>
                    <p class="text-xs text-gray-400 font-semibold uppercase">Jumlah</p>
                </div>
                <div class="rounded-3xl bg-emerald-50 border border-emerald-100 p-4 text-center">
                    <p class="text-2xl font-black text-emerald-600">{{ registrations.data.filter(r => r.status === 'confirmed').length }}</p>
                    <p class="text-xs text-emerald-500 font-semibold uppercase">Disahkan</p>
                </div>
                <div class="rounded-3xl bg-amber-50 border border-amber-100 p-4 text-center">
                    <p class="text-2xl font-black text-amber-600">{{ registrations.data.filter(r => r.status === 'pending').length }}</p>
                    <p class="text-xs text-amber-500 font-semibold uppercase">Menunggu</p>
                </div>
            </div>

            <!-- List -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3">Borang</th>
                            <th class="px-4 py-3">Bayaran</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ r.registration_no }}<span v-if="r.member_no"> · {{ r.member_no }}</span></p>
                                <p v-if="r.phone" class="text-xs text-gray-400">{{ r.phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.organization_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.form_title || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="r.payment_status === 'successful' || r.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ r.payment_status === 'successful' || r.payment_status === 'paid' ? 'Berjaya' : 'Menunggu' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="{ 'text-emerald-600': r.status === 'confirmed', 'text-amber-600': r.status === 'pending', 'text-red-500': r.status === 'cancelled' }">
                                    {{ r.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <select
                                    :value="r.status"
                                    @change="updateStatus(r, $event.target.value)"
                                    class="rounded-xl border border-gray-200 px-2 py-1.5 text-xs focus:ring-0"
                                >
                                    <option value="confirmed">Disahkan</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="cancelled">Dibatalkan</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    bookings: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({ status: '' }),
    },
    summary: {
        type: Object,
        default: () => ({ pending: 0, approved: 0, rejected: 0 }),
    },
});

const remarkInputs = ref({});

const filterForm = useForm({
    status: props.filters.status || '',
});

function applyFilter() {
    router.get(route('admin.facility-bookings.index'), {
        status: filterForm.status || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function updateStatus(bookingId, bookingStatus) {
    useForm({
        booking_status: bookingStatus,
        admin_remarks: remarkInputs.value[bookingId] ?? '',
    }).patch(route('admin.facility-bookings.update', bookingId), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Facility Bookings" />

    <AppLayout>
        <template #header>Facility Bookings</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Status</label>
                        <select v-model="filterForm.status" class="w-48 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="">Semua</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <button @click="applyFilter" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                        Tapis
                    </button>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending</p>
                        <p class="mt-1 text-2xl font-black text-amber-800">{{ summary.pending }}</p>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Approved</p>
                        <p class="mt-1 text-2xl font-black text-emerald-800">{{ summary.approved }}</p>
                    </div>
                    <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Rejected</p>
                        <p class="mt-1 text-2xl font-black text-red-800">{{ summary.rejected }}</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <th class="px-2 py-2">Ruang</th>
                                <th class="px-2 py-2">Organisasi</th>
                                <th class="px-2 py-2">Ahli</th>
                                <th class="px-2 py-2">Mula</th>
                                <th class="px-2 py-2">Tamat</th>
                                <th class="px-2 py-2">Harga</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Catatan Admin</th>
                                <th class="px-2 py-2">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookings.data" :key="booking.id" class="border-b border-gray-50">
                                <td class="px-2 py-2">{{ booking.facility_name }}</td>
                                <td class="px-2 py-2">{{ booking.organization_name }}</td>
                                <td class="px-2 py-2">{{ booking.member_name }}</td>
                                <td class="px-2 py-2">{{ booking.start_datetime }}</td>
                                <td class="px-2 py-2">{{ booking.end_datetime }}</td>
                                <td class="px-2 py-2">RM {{ Number(booking.total_price).toFixed(2) }}</td>
                                <td class="px-2 py-2 font-semibold">{{ booking.booking_status }}</td>
                                <td class="px-2 py-2">
                                    <template v-if="booking.booking_status === 'pending'">
                                        <input
                                            v-model="remarkInputs[booking.id]"
                                            type="text"
                                            placeholder="Opsyenal"
                                            class="w-40 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-500 focus:ring-0"
                                        >
                                    </template>
                                    <span v-else class="text-xs text-gray-500">{{ booking.admin_remarks || '—' }}</span>
                                </td>
                                <td class="px-2 py-2">
                                    <div class="flex items-center gap-2" v-if="booking.booking_status === 'pending'">
                                        <button @click="updateStatus(booking.id, 'approved')" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                            Approve
                                        </button>
                                        <button @click="updateStatus(booking.id, 'rejected')" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">
                                            Reject
                                        </button>
                                    </div>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                            </tr>
                            <tr v-if="bookings.data.length === 0">
                                <td colspan="9" class="px-2 py-4 text-gray-500">Tiada rekod tempahan ditemui.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard Admin</Link>
            </div>
        </div>
    </AppLayout>
</template>

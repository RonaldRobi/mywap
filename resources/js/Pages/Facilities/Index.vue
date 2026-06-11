<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { nextTick, onMounted, ref } from 'vue';

const props = defineProps({
    facilities: {
        type: Array,
        default: () => [],
    },
    myBookings: {
        type: Array,
        default: () => [],
    },
    historyFilters: {
        type: Object,
        default: () => ({ status: '' }),
    },
    jumpToHistory: {
        type: Boolean,
        default: false,
    },
});

const historyStatus = ref(props.historyFilters?.status ?? '');

function applyHistoryFilter() {
    router.get(route('member.facilities.index'), {
        view: 'history',
        history_status: historyStatus.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

onMounted(async () => {
    if (!props.jumpToHistory) return;
    await nextTick();
    const target = document.getElementById('booking-history');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>

<template>
    <Head title="Tempahan Ruang" />

    <AppLayout>
        <template #header>Tempahan Ruang</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <div class="mb-4">
                <h1 class="text-2xl font-black text-gray-900">Senarai Ruang</h1>
                <p class="mt-1 text-sm text-gray-500">Semua ahli boleh lihat ruang aktif merentas organisasi dan membuat tempahan.</p>
            </div>

            <div v-if="facilities.length === 0" class="rounded-2xl border border-gray-100 bg-white p-5 text-sm text-gray-500">
                Tiada ruang aktif tersedia buat masa ini.
            </div>

            <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article v-for="facility in facilities" :key="facility.id" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ facility.organization_name }}</p>
                    <h2 class="mt-1 text-lg font-black text-gray-900">{{ facility.name }}</h2>
                    <p class="mt-2 text-sm text-gray-600">{{ facility.description || '—' }}</p>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-gray-500">
                        <p>Lokasi: <span class="font-semibold text-gray-700">{{ facility.location || '—' }}</span></p>
                        <p>Kapasiti: <span class="font-semibold text-gray-700">{{ facility.capacity || '—' }}</span></p>
                        <p>Jenis: <span class="font-semibold text-gray-700">{{ facility.type }}</span></p>
                        <p>Harga: <span class="font-semibold text-gray-700">RM {{ Number(facility.price_per_unit).toFixed(2) }}</span></p>
                    </div>

                    <Link
                        :href="route('member.facilities.show', facility.id)"
                        class="mt-4 inline-flex rounded-xl bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Lihat & Tempah
                    </Link>
                </article>
            </div>

            <section id="booking-history" class="mt-8 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-900">Sejarah Tempahan Saya</h2>
                <p class="mt-1 text-xs text-gray-500">20 rekod terkini tempahan anda.</p>

                <div class="mt-3 flex flex-wrap items-end gap-2">
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</label>
                        <select v-model="historyStatus" class="w-44 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="">Semua</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <button @click="applyHistoryFilter" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Tapis
                    </button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <th class="px-2 py-2">Ruang</th>
                                <th class="px-2 py-2">Organisasi</th>
                                <th class="px-2 py-2">Mula</th>
                                <th class="px-2 py-2">Tamat</th>
                                <th class="px-2 py-2">Harga</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Catatan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in myBookings" :key="booking.id" class="border-b border-gray-50">
                                <td class="px-2 py-2 font-semibold text-gray-700">
                                    <Link :href="route('member.facilities.show', booking.facility_id)" class="hover:text-gray-900 hover:underline">
                                        {{ booking.facility_name }}
                                    </Link>
                                </td>
                                <td class="px-2 py-2">{{ booking.organization_name || '-' }}</td>
                                <td class="px-2 py-2">{{ booking.start_datetime || '-' }}</td>
                                <td class="px-2 py-2">{{ booking.end_datetime || '-' }}</td>
                                <td class="px-2 py-2">RM {{ Number(booking.total_price).toFixed(2) }}</td>
                                <td class="px-2 py-2">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                        :class="booking.booking_status === 'approved'
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : booking.booking_status === 'rejected'
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ booking.booking_status }}
                                    </span>
                                </td>
                                <td class="px-2 py-2 text-xs text-gray-500">{{ booking.admin_remarks || '—' }}</td>
                            </tr>
                            <tr v-if="myBookings.length === 0">
                                <td colspan="7" class="px-2 py-4 text-gray-500">Belum ada sejarah tempahan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

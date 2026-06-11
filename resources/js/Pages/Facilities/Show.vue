<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    facility: {
        type: Object,
        required: true,
    },
    bookings: {
        type: Array,
        default: () => [],
    },
    myBookings: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    start_datetime: '',
    end_datetime: '',
});

function submitBooking() {
    form.post(route('member.facilities.book', props.facility.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="`Tempahan - ${facility.name}`" />

    <AppLayout>
        <template #header>Tempahan Ruang</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Butiran Ruang</p>
                <h1 class="mt-1 text-2xl font-black text-gray-900">{{ facility.name }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ facility.description || '—' }}</p>
                <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Organisasi: {{ facility.organization_name || '-' }}</p>

                <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-gray-600 md:grid-cols-4">
                    <p>Lokasi: <span class="font-semibold text-gray-800">{{ facility.location || '—' }}</span></p>
                    <p>Jenis: <span class="font-semibold text-gray-800">{{ facility.type }}</span></p>
                    <p>Kapasiti: <span class="font-semibold text-gray-800">{{ facility.capacity || '—' }}</span></p>
                    <p>Harga: <span class="font-semibold text-gray-800">RM {{ Number(facility.price_per_unit).toFixed(2) }}</span></p>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-900">Buat Tempahan</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitBooking">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Start Datetime</label>
                        <input v-model="form.start_datetime" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.start_datetime" class="mt-1 text-xs text-red-600">{{ form.errors.start_datetime }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">End Datetime</label>
                        <input v-model="form.end_datetime" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.end_datetime" class="mt-1 text-xs text-red-600">{{ form.errors.end_datetime }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menghantar...' : 'Hantar Tempahan' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-900">Slot Sedia Ada (Pending/Approved)</h2>
                <p class="mt-1 text-xs text-gray-500">Paparan slot umum untuk elak pertindihan masa tempahan.</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <th class="px-2 py-2">Mula</th>
                                <th class="px-2 py-2">Tamat</th>
                                <th class="px-2 py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in bookings" :key="booking.id" class="border-b border-gray-50">
                                <td class="px-2 py-2">{{ booking.start_datetime }}</td>
                                <td class="px-2 py-2">{{ booking.end_datetime }}</td>
                                <td class="px-2 py-2 font-semibold">{{ booking.booking_status }}</td>
                            </tr>
                            <tr v-if="bookings.length === 0">
                                <td colspan="3" class="px-2 py-4 text-gray-500">Tiada tempahan lagi.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-900">Tempahan Saya Untuk Ruang Ini</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <th class="px-2 py-2">Mula</th>
                                <th class="px-2 py-2">Tamat</th>
                                <th class="px-2 py-2">Harga</th>
                                <th class="px-2 py-2">Status</th>
                                <th class="px-2 py-2">Catatan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in myBookings" :key="booking.id" class="border-b border-gray-50">
                                <td class="px-2 py-2">{{ booking.start_datetime || '-' }}</td>
                                <td class="px-2 py-2">{{ booking.end_datetime || '-' }}</td>
                                <td class="px-2 py-2">RM {{ Number(booking.total_price).toFixed(2) }}</td>
                                <td class="px-2 py-2 font-semibold">{{ booking.booking_status }}</td>
                                <td class="px-2 py-2 text-xs text-gray-500">{{ booking.admin_remarks || '—' }}</td>
                            </tr>
                            <tr v-if="myBookings.length === 0">
                                <td colspan="5" class="px-2 py-4 text-gray-500">Belum ada tempahan untuk ruang ini.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div>
                <Link :href="route('member.facilities.index')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Senarai Ruang</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    registrations: Object, // Laravel paginator
});

function statusClass(status) {
    return {
        confirmed: 'text-emerald-600 bg-emerald-50 border-emerald-200',
        pending: 'text-amber-600 bg-amber-50 border-amber-200',
        cancelled: 'text-red-600 bg-red-50 border-red-200',
    }[status] ?? 'text-gray-600 bg-gray-50 border-gray-200';
}
</script>

<template>
    <Head title="Pendaftaran Saya" />

    <AppLayout>
        <div class="max-w-4xl mx-auto px-4 py-8">
            <h1 class="text-2xl font-black text-gray-900 mb-1">Pendaftaran Saya</h1>
            <p class="text-sm text-gray-500 mb-6">Sejarah pendaftaran event anda.</p>

            <div v-if="registrations.data.length === 0" class="rounded-3xl bg-white border border-gray-100 p-10 text-center">
                <p class="text-gray-400 text-sm">Tiada pendaftaran lagi. Terokai event yang tersedia dan daftar!</p>
            </div>

            <div class="space-y-3">
                <div
                    v-for="r in registrations.data"
                    :key="r.id"
                    class="rounded-3xl bg-white border border-gray-100 p-5 shadow-sm space-y-3"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ r.event?.title }}</p>
                            <p class="text-sm text-gray-500 mt-0.5">{{ r.event?.start_formatted }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold" :class="statusClass(r.status)">
                            {{ r.status_label }}
                        </span>
                    </div>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-1.5 font-mono text-gray-600">{{ r.registration_no }}</span>
                        <span v-if="r.form_title" class="rounded-xl bg-gray-50 border border-gray-100 px-3 py-1.5 text-gray-600">{{ r.form_title }}</span>
                        <span
                            class="rounded-xl border px-3 py-1.5 font-semibold"
                            :class="r.payment_status === 'successful' || r.payment_status === 'paid' ? 'bg-emerald-50 border-emerald-200 text-emerald-600' : 'bg-amber-50 border-amber-200 text-amber-600'"
                        >
                            {{ r.payment_status === 'successful' || r.payment_status === 'paid' ? 'Bayaran Berjaya' : 'Bayaran Menunggu' }}
                        </span>
                        <span
                            v-if="r.attended"
                            class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 font-semibold text-indigo-600"
                        >
                            Hadir · {{ r.attended_at }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    registration: Object,
    event: Object,
    form: Object,
});
</script>

<template>
    <Head :title="`Pendaftaran Berjaya: ${registration.registration_no}`" />

    <AppLayout>
        <div class="max-w-2xl mx-auto px-4 py-12">
            <div class="rounded-3xl bg-emerald-50 border border-emerald-200 p-8 text-center space-y-5">
                <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <div>
                    <h1 class="text-2xl font-black text-gray-900">Pendaftaran Berjaya!</h1>
                    <p class="text-sm text-gray-600 mt-1">No Pendaftaran anda:</p>
                    <p class="mt-2 inline-block rounded-2xl bg-white border border-emerald-300 px-5 py-2 font-mono font-bold text-emerald-700 text-lg tracking-wide">
                        {{ registration.registration_no }}
                    </p>
                </div>

                <div class="rounded-2xl bg-white/70 p-5 text-left space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Nama</span>
                        <span class="font-semibold text-gray-800">{{ registration.name }}</span>
                    </div>
                    <div v-if="registration.phone" class="flex justify-between">
                        <span class="text-gray-400">Telefon</span>
                        <span class="font-semibold text-gray-800">{{ registration.phone }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Event</span>
                        <span class="font-semibold text-gray-800">{{ event.title }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Status Bayaran</span>
                        <span
                            class="font-semibold"
                            :class="registration.payment_status === 'successful' || registration.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'"
                        >
                            {{ registration.payment_status === 'successful' || registration.payment_status === 'paid' ? 'Berjaya' : 'Menunggu' }}
                        </span>
                    </div>
                </div>

                <div v-if="form?.price" class="text-sm text-gray-500">
                    Yuran: RM {{ Number(form.price).toFixed(2) }}
                </div>

                <p class="text-xs text-emerald-700 bg-emerald-100 rounded-2xl p-3">
                    Simpan No Pendaftaran ini — anda perlu mengemukakannya (atau No IC / telefon) semasa semakan kehadiran pada hari event.
                </p>

                <a :href="route('dashboard')" class="inline-block w-full rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                    Kembali ke Papan Pemuka
                </a>
            </div>
        </div>
    </AppLayout>
</template>

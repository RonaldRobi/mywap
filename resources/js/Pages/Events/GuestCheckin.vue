<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    attendUrl: String,
    error: { type: String, default: '' },
});

const form = useForm({ identifier: '' });

function submit() {
    form.post(route('events.attend.identify', { id: props.event.id, token: props.attendUrl.split('/').pop() }));
}
</script>

<template>
    <Head :title="`Semakan Kehadiran: ${event.title}`" />

    <!-- Full-screen centered — no AppLayout (mobile PWA feel) -->
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center p-5">
        <div class="w-full max-w-sm">

            <div class="bg-white/80 backdrop-blur-md rounded-3xl shadow-xl border border-white/60 overflow-hidden">
                <div class="h-2 w-full" :style="{ backgroundColor: event.color_theme ?? '#4f46e5' }"></div>

                <div class="p-8 space-y-5">
                    <div class="text-center">
                        <div class="mx-auto h-14 w-14 rounded-2xl bg-indigo-100 flex items-center justify-center mb-3">
                            <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9a2 2 0 10-4 0 2 2 0 004 0zM6 20a6 6 0 0112 0" />
                            </svg>
                        </div>
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-500 mb-1">Semakan Kehadiran</p>
                        <h1 class="text-xl font-extrabold text-gray-800 leading-snug">{{ event.title }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ event.start_formatted }}</p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 p-4 text-center">
                        <p class="text-sm text-gray-600">
                            Anda telah mendaftar sebagai peserta. Sila masukkan salah satu maklumat berikut:
                        </p>
                        <p class="mt-1 text-xs font-semibold text-gray-400">No Pendaftaran / No IC / No Telefon / Emel</p>
                    </div>

                    <p v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-2xl p-3">{{ error }}</p>

                    <form @submit.prevent="submit" class="space-y-3">
                        <input
                            v-model="form.identifier"
                            required
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-center text-sm font-semibold tracking-wider focus:ring-0 focus:border-indigo-300"
                            placeholder="cth. REG-1A2B3C4D"
                        />
                        <p v-if="form.errors.identifier" class="text-xs text-red-500 text-center">{{ form.errors.identifier }}</p>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-2xl py-3.5 font-bold text-sm text-white shadow-lg transition-transform active:scale-95 disabled:opacity-50"
                            :style="{ backgroundColor: event.color_theme ?? '#4f46e5' }"
                        >
                            {{ form.processing ? 'Menyemak...' : 'Semak & Sahkan Kehadiran' }}
                        </button>
                    </form>
                </div>
            </div>

            <p class="text-center text-[11px] text-gray-300 mt-4">myWAP &middot; {{ event.organization_name }}</p>
        </div>
    </div>
</template>

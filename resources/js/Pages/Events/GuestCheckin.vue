<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    event: Object,
    attendUrl: String,
    error: { type: String, default: '' },
    registerAction: { type: Object, default: null },
});

const token = computed(() => props.attendUrl.split('/').pop());

// Aliran 1: peserta yang sudah mendaftar — padankan rekod sedia ada.
const identifyForm = useForm({ identifier: '' });

// Aliran 2: walk-in — tiada rekod; cipta pendaftaran baharu.
const walkInForm = useForm({ name: '', phone: '', email: '' });

function submitIdentify() {
    identifyForm.post(route('events.attend.identify', { id: props.event.id, token: token.value }));
}

function submitWalkIn() {
    walkInForm.post(route('events.attend.walkin', { id: props.event.id, token: token.value }));
}
</script>

<template>
    <Head :title="`Semakan Kehadiran: ${event.title}`" />

    <!-- Full-screen centered — no AppLayout (mobile PWA feel) -->
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-blue-50 flex items-center justify-center p-5">
        <div class="w-full max-w-sm">

            <div class="bg-white/80 backdrop-blur-md rounded-3xl shadow-xl border border-white/60 overflow-hidden">
                <div class="h-2 w-full" :style="{ backgroundColor: event.color_theme ?? '#4f46e5' }"></div>

                <div class="p-8 space-y-6">
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

                    <p v-if="error" class="text-xs text-red-600 bg-red-50 border border-red-100 rounded-2xl p-3">{{ error }}</p>

                    <!-- ── Aliran 1: sudah mendaftar ───────────────────────────── -->
                    <form @submit.prevent="submitIdentify" class="space-y-3">
                        <div>
                            <p class="text-sm font-bold text-gray-800">Sudah mendaftar?</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Taip salah satu: No Pendaftaran / No IC / No Telefon / Emel
                            </p>
                        </div>
                        <input
                            v-model="identifyForm.identifier"
                            required
                            class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-center text-sm font-semibold tracking-wider focus:ring-0 focus:border-indigo-300"
                            placeholder="cth. REG-1A2B3C4D"
                        />
                        <p v-if="identifyForm.errors.identifier" class="text-xs text-red-500 text-center">{{ identifyForm.errors.identifier }}</p>

                        <button
                            type="submit"
                            :disabled="identifyForm.processing"
                            class="w-full rounded-2xl py-3.5 font-bold text-sm text-white shadow-lg transition-transform active:scale-95 disabled:opacity-50"
                            :style="{ backgroundColor: event.color_theme ?? '#4f46e5' }"
                        >
                            {{ identifyForm.processing ? 'Menyemak...' : 'Semak & Sahkan Kehadiran' }}
                        </button>
                    </form>

                    <!-- ── Pemisah ────────────────────────────────────────────── -->
                    <div class="flex items-center gap-3">
                        <span class="h-px flex-1 bg-gray-200"></span>
                        <span class="text-[11px] font-bold uppercase tracking-widest text-gray-400">Atau</span>
                        <span class="h-px flex-1 bg-gray-200"></span>
                    </div>

                    <!-- ── Aliran 2: belum mendaftar (walk-in) ────────────────── -->
                    <form @submit.prevent="submitWalkIn" class="space-y-3">
                        <div>
                            <p class="text-sm font-bold text-gray-800">Belum mendaftar?</p>
                            <p class="text-xs text-gray-500 mt-0.5">Isi maklumat di bawah untuk daftar hadir terus.</p>
                        </div>

                        <div>
                            <input
                                v-model="walkInForm.name"
                                required
                                class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold focus:ring-0 focus:border-emerald-300"
                                placeholder="Nama penuh"
                            />
                            <p v-if="walkInForm.errors.name" class="mt-1 text-xs text-red-500">{{ walkInForm.errors.name }}</p>
                        </div>

                        <div>
                            <input
                                v-model="walkInForm.phone"
                                required
                                type="tel"
                                class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold focus:ring-0 focus:border-emerald-300"
                                placeholder="No. telefon (cth. 0123456789)"
                            />
                            <p v-if="walkInForm.errors.phone" class="mt-1 text-xs text-red-500">{{ walkInForm.errors.phone }}</p>
                        </div>

                        <div>
                            <input
                                v-model="walkInForm.email"
                                type="email"
                                class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold focus:ring-0 focus:border-emerald-300"
                                placeholder="Emel (pilihan)"
                            />
                            <p v-if="walkInForm.errors.email" class="mt-1 text-xs text-red-500">{{ walkInForm.errors.email }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="walkInForm.processing"
                            class="w-full rounded-2xl bg-emerald-600 py-3.5 font-bold text-sm text-white shadow-lg transition-transform active:scale-95 disabled:opacity-50"
                        >
                            {{ walkInForm.processing ? 'Merekod...' : 'Daftar Hadir Sekali' }}
                        </button>
                    </form>

                    <!-- Pendaftaran rasmi (jika masih dibuka) -->
                    <div v-if="registerAction?.url" class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 p-4 text-center">
                        <p class="text-xs text-gray-600">Mahu daftar secara rasmi dengan maklumat penuh?</p>
                        <a :href="registerAction.url" class="mt-1 inline-block text-xs font-bold text-amber-700 underline">
                            Sila Daftar di Sini
                        </a>
                    </div>
                </div>
            </div>

            <p class="text-center text-[11px] text-gray-300 mt-4">myWAP &middot; {{ event.organization_name }}</p>
        </div>
    </div>
</template>

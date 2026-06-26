<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    infaq: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const systemLogo = computed(() => page.props.brand?.system_logo_path ?? null);

const form = useForm({
    amount: 50,
    payment_method: 'fpx',
    is_recurring: false,
    frequency: 'monthly',
    donor_name: '',
    donor_phone: '',
    donor_email: '',
    prayer_message: '',
    is_anonymous: false,
    wants_updates: true,
});

function submitDonation() {
    form.post(props.infaq.public_url + '/donate');
}
</script>

<template>
    <Head :title="`Sumbangan - ${infaq.title}`" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-16 md:pb-8">
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-3xl px-4 py-4 flex items-center justify-between">
                <Link :href="infaq.public_url" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Kembali
                </Link>
                <img v-if="systemLogo" :src="systemLogo" alt="Logo" class="h-8 w-auto" />
                <span v-else class="text-sm font-black text-slate-800">myWAP</span>
            </div>
        </nav>

        <div class="mx-auto max-w-3xl px-4 py-8">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-black text-slate-900 mb-2">Maklumat Sumbangan</h1>
                <p class="text-slate-500 font-semibold">{{ infaq.title }}</p>
            </div>

            <form @submit.prevent="submitDonation" class="space-y-8">
                <!-- Section 1: Amount -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">1. Jumlah Sumbangan (RM)</h2>
                    
                    <div class="grid grid-cols-3 gap-3 mb-4">
                        <button type="button" @click="form.amount = 30" :class="['py-3 rounded-xl font-bold border transition', form.amount === 30 ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300']">RM30</button>
                        <button type="button" @click="form.amount = 50" :class="['py-3 rounded-xl font-bold border transition', form.amount === 50 ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300']">RM50</button>
                        <button type="button" @click="form.amount = 100" :class="['py-3 rounded-xl font-bold border transition', form.amount === 100 ? 'bg-emerald-50 border-emerald-500 text-emerald-700' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300']">RM100</button>
                    </div>

                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-500">RM</span>
                        <input type="number" v-model="form.amount" min="1" step="0.01" class="w-full pl-12 pr-4 py-3 rounded-xl border-slate-200 font-bold text-lg focus:border-emerald-500 focus:ring-emerald-500" placeholder="Jumlah Lain" required>
                    </div>
                    <p v-if="form.errors.amount" class="mt-2 text-sm text-red-600">{{ form.errors.amount }}</p>
                </div>

                <!-- Section 2: Payment Method -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">2. Metode Pembayaran</h2>
                    
                    <div class="space-y-3">
                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition" :class="{'border-emerald-500 bg-emerald-50/50': form.payment_method === 'fpx'}">
                            <input type="radio" v-model="form.payment_method" value="fpx" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <div class="ml-3 flex-1 flex items-center justify-between">
                                <span class="font-bold text-slate-700">FPX / Online Banking</span>
                                <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase rounded">Popular</span>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition" :class="{'border-emerald-500 bg-emerald-50/50': form.payment_method === 'card'}">
                            <input type="radio" v-model="form.payment_method" value="card" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <div class="ml-3 flex-1">
                                <span class="font-bold text-slate-700">Kad Kredit / Debit</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section 3: Frequency (recurring) -->
                <div v-if="infaq.allow_recurring" class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-800 mb-4">3. Kekerapan Sumbangan</h2>

                    <div class="space-y-3">
                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition" :class="{'border-emerald-500 bg-emerald-50/50': !form.is_recurring}">
                            <input type="radio" :value="false" v-model="form.is_recurring" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <div class="ml-3 flex-1">
                                <span class="font-bold text-slate-700">One-off (Sekali)</span>
                            </div>
                        </label>
                        <label class="flex items-center p-4 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition" :class="{'border-emerald-500 bg-emerald-50/50': form.is_recurring}">
                            <input type="radio" :value="true" v-model="form.is_recurring" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                            <div class="ml-3 flex-1 flex items-center justify-between">
                                <span class="font-bold text-slate-700">Bulanan (Auto-debit)</span>
                                <span class="px-2 py-1 bg-purple-100 text-purple-700 text-[10px] font-black uppercase rounded">Berkala</span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Section 4: Information -->
                <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6">
                    <h2 class="text-lg font-bold text-slate-800">{{ infaq.allow_recurring ? '4' : '3' }}. Isikan Maklumat</h2>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Nama Penuh Pendaftar</label>
                        <input type="text" v-model="form.donor_name" class="w-full rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" placeholder="Nama seperti dalam kad pengenalan" required>
                        <p v-if="form.errors.donor_name" class="mt-1 text-sm text-red-600">{{ form.errors.donor_name }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">No. Telefon Bimbit</label>
                        <div class="flex">
                            <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 text-slate-500 font-bold">+60</span>
                            <input type="text" v-model="form.donor_phone" class="flex-1 rounded-none rounded-r-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" placeholder="01234567890" required>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Sila masukkan tanpa '-'. Contoh: 0123456789</p>
                        <p v-if="form.errors.donor_phone" class="mt-1 text-sm text-red-600">{{ form.errors.donor_phone }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Emel</label>
                        <input type="email" v-model="form.donor_email" class="w-full rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" placeholder="abubakr@mail.com" required>
                        <p class="mt-1 text-xs text-slate-500">Pastikan emel anda betul untuk penghantaran resit</p>
                        <p v-if="form.errors.donor_email" class="mt-1 text-sm text-red-600">{{ form.errors.donor_email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Doa #1</label>
                        <textarea v-model="form.prayer_message" rows="3" maxlength="400" class="w-full rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" placeholder="Tuliskan doa untuk kempen yang dianjurkan oleh penganjur atau untuk diri anda sendiri. Doa akan dipaparkan serta diaminkan oleh #penyumbang."></textarea>
                        <div class="flex justify-between items-center mt-1">
                            <p v-if="form.errors.prayer_message" class="text-sm text-red-600">{{ form.errors.prayer_message }}</p>
                            <p v-else class="text-xs text-slate-500"></p>
                            <p class="text-xs font-semibold" :class="form.prayer_message.length >= 400 ? 'text-red-500' : 'text-slate-400'">{{ form.prayer_message.length }}/400</p>
                        </div>
                    </div>

                    <div class="space-y-4 pt-4 border-t border-slate-100">
                        <label class="flex items-start gap-3 cursor-pointer group">
                            <div class="flex items-center h-5">
                                <input type="checkbox" v-model="form.is_anonymous" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-slate-700 group-hover:text-slate-900">Jangan paparkan nama saya</span>
                                <span class="text-xs text-slate-500">*Identiti Hamba Allah akan dipaparkan sebagai penyumbang.</span>
                            </div>
                        </label>

                        <label class="flex items-start gap-3 cursor-pointer group">
                            <div class="flex items-center h-5">
                                <input type="checkbox" v-model="form.wants_updates" class="w-5 h-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                            </div>
                            <div class="flex flex-col pt-0.5">
                                <span class="text-sm font-medium text-slate-600 group-hover:text-slate-900">Saya bersetuju untuk menerima informasi bantuan sumbangan bagi mereka yang memerlukan.</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="pt-4">
                    <button type="submit" :disabled="form.processing" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black text-lg py-4 rounded-2xl shadow-lg transition disabled:opacity-70 flex justify-center items-center gap-2">
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Sedang Diproses...' : 'Teruskan Pembayaran' }}</span>
                    </button>
                    <p class="text-center text-xs font-semibold text-slate-400 mt-4 flex items-center justify-center gap-1">
                        <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                        Transaksi dijamin selamat dan disulitkan
                    </p>
                </div>
            </form>
        </div>
    </div>
</template>

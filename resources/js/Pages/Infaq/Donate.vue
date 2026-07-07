<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, nextTick, watch } from 'vue';

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(value ?? 0);
}

const props = defineProps({
    infaq: { type: Object, required: true },
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

const currentStep = ref(1);
const totalSteps = computed(() => props.infaq.allow_recurring ? 4 : 3);
const showValidationSummary = ref(false);

// Quick amount presets with impact descriptions
const quickAmounts = [
    { value: 10, label: 'RM10', impact: 'Makanan untuk 1 keluarga' },
    { value: 30, label: 'RM30', impact: 'Alat tulis 5 pelajar' },
    { value: 50, label: 'RM50', impact: '1 kelas Quran sebulan' },
    { value: 100, label: 'RM100', impact: 'Bantuan kecemasan keluarga' },
    { value: 200, label: 'RM200', impact: 'Tajaan penuh 1 anak yatim' },
];

// Pre-fill amount from URL query param
onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const urlAmount = params.get('amount');
    if (urlAmount && !isNaN(Number(urlAmount)) && Number(urlAmount) > 0) {
        form.amount = Number(urlAmount);
    }

    // Load saved draft from localStorage
    try {
        const saved = localStorage.getItem('infaq_donate_draft');
        if (saved) {
            const draft = JSON.parse(saved);
            if (draft.infaq_id === props.infaq.id) {
                Object.keys(draft.data).forEach(key => {
                    if (key in form) form[key] = draft.data[key];
                });
            }
        }
    } catch {}
});

// Auto-save draft
const formFields = computed(() => ({
    amount: form.amount,
    payment_method: form.payment_method,
    is_recurring: form.is_recurring,
    frequency: form.frequency,
    donor_name: form.donor_name,
    donor_phone: form.donor_phone,
    donor_email: form.donor_email,
    prayer_message: form.prayer_message,
    is_anonymous: form.is_anonymous,
    wants_updates: form.wants_updates,
}));
watch(formFields, (data) => {
    try {
        localStorage.setItem('infaq_donate_draft', JSON.stringify({
            infaq_id: props.infaq.id,
            data,
            saved_at: new Date().toISOString(),
        }));
    } catch {}
}, { deep: true });

function selectQuickAmount(amount) {
    form.amount = amount;
}

function goToStep(step) {
    if (step < currentStep.value) {
        currentStep.value = step;
        return;
    }
    if (step === 2) {
        if (!form.amount || form.amount < 1) {
            form.errors.amount = 'Sila masukkan jumlah sumbangan';
            showValidationSummary.value = true;
            return;
        }
        currentStep.value = 2;
    } else if (step === 3 && props.infaq.allow_recurring) {
        currentStep.value = 3;
    } else if (step === totalSteps.value) {
        // Validate info fields
        const requiredFields = { donor_name: 'Nama', donor_phone: 'No. Telefon', donor_email: 'Emel' };
        for (const [field, label] of Object.entries(requiredFields)) {
            if (!form[field]) {
                form.errors[field] = `Sila isikan ${label}`;
                showValidationSummary.value = true;
                return;
            }
        }
        currentStep.value = totalSteps.value;
    }

    showValidationSummary.value = false;
    nextTick(() => {
        const el = document.getElementById('step-' + currentStep.value);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

function nextStep() {
    goToStep(currentStep.value + 1);
}

function submitDonation() {
    if (!form.amount || form.amount < 1) {
        form.errors.amount = 'Sila masukkan jumlah sumbangan yang sah';
        showValidationSummary.value = true;
        return;
    }
    if (!form.donor_name) { form.errors.donor_name = 'Sila isikan nama'; showValidationSummary.value = true; return; }
    if (!form.donor_phone) { form.errors.donor_phone = 'Sila isikan no. telefon'; showValidationSummary.value = true; return; }
    if (!form.donor_email) { form.errors.donor_email = 'Sila isikan emel'; showValidationSummary.value = true; return; }

    // Clear draft on submit
    try { localStorage.removeItem('infaq_donate_draft'); } catch {}

    form.post(props.infaq.public_url + '/donate');
}

const stepLabels = computed(() => {
    const labels = [
        { num: 1, label: 'Jumlah' },
        { num: 2, label: 'Bayaran' },
    ];
    if (props.infaq.allow_recurring) {
        labels.push({ num: 3, label: 'Kekerapan' });
    }
    labels.push({ num: totalSteps.value, label: 'Maklumat' });
    return labels;
});

const activeQuickAmount = computed(() => {
    return quickAmounts.find(q => q.value === form.amount);
});
</script>

<template>
    <Head :title="`Sumbangan - ${infaq.title}`" />

    <div class="min-h-screen bg-gradient-to-b from-slate-50 to-white font-sans text-slate-900 pb-16 md:pb-8">
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
            <!-- Header -->
            <div class="mb-8 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 mb-4">
                    <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h1 class="text-2xl font-black text-slate-900 mb-2">Maklumat Sumbangan</h1>
                <p class="text-slate-500 font-semibold">{{ infaq.title }}</p>
            </div>

            <!-- Step Progress Indicator -->
            <div class="mb-8">
                <div class="flex items-center justify-between max-w-md mx-auto">
                    <template v-for="(step, idx) in stepLabels" :key="step.num">
                        <div class="flex items-center">
                            <div
                                class="flex items-center justify-center w-10 h-10 rounded-full text-sm font-black transition-all duration-300"
                                :class="currentStep >= step.num ? 'bg-emerald-500 text-white shadow-lg shadow-emerald-500/25' : 'bg-slate-100 text-slate-400'"
                            >
                                <svg v-if="currentStep > step.num" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                <span v-else>{{ step.num }}</span>
                            </div>
                            <span class="ml-2 hidden sm:block text-xs font-bold text-slate-500 uppercase tracking-wider">{{ step.label }}</span>
                        </div>
                        <div v-if="idx < stepLabels.length - 1" class="flex-1 mx-2 h-0.5 rounded-full" :class="currentStep > step.num ? 'bg-emerald-500' : 'bg-slate-200'" />
                    </template>
                </div>
            </div>

            <!-- Validation Summary -->
            <div v-if="showValidationSummary && Object.values(form.errors).some(e => e)" class="mb-6 rounded-xl bg-red-50 border border-red-200 p-4">
                <p class="text-sm font-bold text-red-800 mb-2">Sila betulkan ralat berikut:</p>
                <ul class="text-sm text-red-600 list-disc list-inside space-y-0.5">
                    <li v-for="(err, key) in form.errors" :key="key" v-show="err">{{ err }}</li>
                </ul>
            </div>

            <form @submit.prevent="submitDonation" class="space-y-8">
                <!-- Step 1: Amount -->
                <div id="step-1" class="transition-all duration-500" :class="{'opacity-100 scale-100': currentStep >= 1, 'opacity-0 scale-95 h-0 overflow-hidden': currentStep < 1}">
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm" :class="{'ring-2 ring-emerald-500/50': currentStep === 1}">
                        <div class="flex items-center gap-3 mb-4">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-black">1</span>
                            <h2 class="text-lg font-bold text-slate-800">Jumlah Sumbangan (RM)</h2>
                        </div>

                        <!-- Impact Banner -->
                        <div v-if="activeQuickAmount" class="mb-4 rounded-xl bg-emerald-50 border border-emerald-100 p-3 flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <div>
                                <p class="text-xs font-bold text-emerald-800">{{ activeQuickAmount.impact }}</p>
                                <p class="text-[10px] text-emerald-600">Anggaran impak sumbangan anda</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-3 mb-4">
                            <button
                                v-for="q in quickAmounts"
                                :key="q.value"
                                type="button"
                                @click="selectQuickAmount(q.value)"
                                :class="['py-3 rounded-xl font-bold border transition relative', form.amount === q.value ? 'bg-emerald-50 border-emerald-500 text-emerald-700 shadow-sm' : 'bg-white border-slate-200 text-slate-600 hover:border-slate-300 hover:bg-slate-50']"
                            >
                                {{ q.label }}
                                <span v-if="form.amount === q.value" class="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </button>
                        </div>

                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-500">RM</span>
                            <input
                                type="number"
                                v-model="form.amount"
                                min="1"
                                step="0.01"
                                class="w-full pl-12 pr-4 py-3 rounded-xl border-slate-200 font-bold text-lg focus:border-emerald-500 focus:ring-emerald-500"
                                :class="{'border-red-300 focus:border-red-500 focus:ring-red-500': form.errors.amount}"
                                placeholder="Jumlah Lain"
                                required
                            />
                        </div>
                        <p v-if="form.errors.amount" class="mt-2 text-sm text-red-600 font-semibold">{{ form.errors.amount }}</p>

                        <div class="mt-4 flex justify-end">
                            <button type="button" @click="nextStep" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800 transition">
                                Seterusnya
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Payment Method -->
                <div id="step-2" class="transition-all duration-500" :class="{'opacity-100 scale-100': currentStep >= 2, 'opacity-0 scale-95 h-0 overflow-hidden': currentStep < 2}">
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm" :class="{'ring-2 ring-emerald-500/50': currentStep === 2}">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-black">2</span>
                            <h2 class="text-lg font-bold text-slate-800">Metode Pembayaran</h2>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center p-4 border rounded-xl cursor-pointer transition" :class="form.payment_method === 'fpx' ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="form.payment_method" value="fpx" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                                <div class="ml-3 flex-1 flex items-center justify-between">
                                    <span class="font-bold text-slate-700">FPX / Online Banking</span>
                                    <span class="px-2 py-1 bg-emerald-100 text-emerald-700 text-[10px] font-black uppercase rounded-full">Popular</span>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border rounded-xl cursor-pointer transition" :class="form.payment_method === 'card' ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" v-model="form.payment_method" value="card" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                                <div class="ml-3 flex-1">
                                    <span class="font-bold text-slate-700">Kad Kredit / Debit</span>
                                </div>
                            </label>
                        </div>

                        <div class="mt-4 flex justify-between">
                            <button type="button" @click="goToStep(1)" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                Kembali
                            </button>
                            <button type="button" @click="nextStep" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800 transition">
                                Seterusnya
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Frequency (recurring) - only if allow_recurring -->
                <div v-if="infaq.allow_recurring" id="step-3" class="transition-all duration-500" :class="{'opacity-100 scale-100': currentStep >= 3, 'opacity-0 scale-95 h-0 overflow-hidden': currentStep < 3}">
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm" :class="{'ring-2 ring-emerald-500/50': currentStep === 3}">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-black">3</span>
                            <h2 class="text-lg font-bold text-slate-800">Kekerapan Sumbangan</h2>
                        </div>

                        <div class="space-y-3">
                            <label class="flex items-center p-4 border rounded-xl cursor-pointer transition" :class="!form.is_recurring ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" :value="false" v-model="form.is_recurring" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                                <div class="ml-3 flex-1">
                                    <span class="font-bold text-slate-700">One-off (Sekali)</span>
                                    <p class="text-xs text-slate-500 mt-0.5">Sumbangan sekali sahaja tanpa komitmen bulanan</p>
                                </div>
                            </label>
                            <label class="flex items-center p-4 border rounded-xl cursor-pointer transition" :class="form.is_recurring ? 'border-emerald-500 bg-emerald-50/50 ring-1 ring-emerald-500/30' : 'border-slate-200 hover:bg-slate-50'">
                                <input type="radio" :value="true" v-model="form.is_recurring" class="h-4 w-4 text-emerald-600 focus:ring-emerald-500 border-gray-300">
                                <div class="ml-3 flex-1 flex items-center justify-between">
                                    <div>
                                        <span class="font-bold text-slate-700">Bulanan (Auto-debit)</span>
                                        <p class="text-xs text-slate-500 mt-0.5">{{ formatCurrency(form.amount) }} akan didebit automatik setiap bulan</p>
                                    </div>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-700 text-[10px] font-black uppercase rounded-full">Berkala</span>
                                </div>
                            </label>
                        </div>

                        <div class="mt-4 flex justify-between">
                            <button type="button" @click="goToStep(2)" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                Kembali
                            </button>
                            <button type="button" @click="nextStep" class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white hover:bg-slate-800 transition">
                                Seterusnya
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Step 4 (or 3 if no recurring): Information -->
                <div :id="'step-' + totalSteps" class="transition-all duration-500" :class="{'opacity-100 scale-100': currentStep >= totalSteps, 'opacity-0 scale-95 h-0 overflow-hidden': currentStep < totalSteps}">
                    <div class="bg-white p-6 md:p-8 rounded-3xl border border-slate-200 shadow-sm space-y-6" :class="{'ring-2 ring-emerald-500/50': currentStep === totalSteps}">
                        <div class="flex items-center gap-3 mb-6">
                            <span class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 text-sm font-black">{{ totalSteps }}</span>
                            <h2 class="text-lg font-bold text-slate-800">Isikan Maklumat</h2>
                        </div>

                        <!-- Order Summary Card -->
                        <div class="rounded-2xl bg-slate-50 border border-slate-100 p-4 flex items-center justify-between">
                            <div>
                                <p class="text-xs text-slate-500 mb-0.5">Jumlah Sumbangan</p>
                                <p class="text-xl font-black text-slate-900">{{ formatCurrency(form.amount) }}</p>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-1 rounded-full text-[10px] font-bold uppercase bg-slate-200 text-slate-600">{{ form.payment_method === 'fpx' ? 'FPX' : 'Kad' }}</span>
                                <p v-if="form.is_recurring && infaq.allow_recurring" class="text-[10px] font-semibold text-purple-600 mt-1">Auto-debit Bulanan</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Nama Penuh Pendaftar <span class="text-red-500">*</span></label>
                            <input type="text" v-model="form.donor_name" class="w-full rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" :class="{'border-red-300': form.errors.donor_name}" placeholder="Nama seperti dalam kad pengenalan" required>
                            <p v-if="form.errors.donor_name" class="mt-1 text-sm text-red-600">{{ form.errors.donor_name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">No. Telefon Bimbit <span class="text-red-500">*</span></label>
                            <div class="flex">
                                <span class="inline-flex items-center px-4 rounded-l-xl border border-r-0 border-slate-200 bg-slate-50 text-slate-500 font-bold">+60</span>
                                <input type="text" v-model="form.donor_phone" class="flex-1 rounded-none rounded-r-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" :class="{'border-red-300': form.errors.donor_phone}" placeholder="01234567890" required>
                            </div>
                            <div class="flex justify-between">
                                <p class="mt-1 text-xs text-slate-500">Sila masukkan tanpa '-'. Contoh: 0123456789</p>
                                <p v-if="form.errors.donor_phone" class="mt-1 text-sm text-red-600">{{ form.errors.donor_phone }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Emel <span class="text-red-500">*</span></label>
                            <input type="email" v-model="form.donor_email" class="w-full rounded-xl border-slate-200 focus:border-emerald-500 focus:ring-emerald-500" :class="{'border-red-300': form.errors.donor_email}" placeholder="abubakr@mail.com" required>
                            <p class="mt-1 text-xs text-slate-500">Pastikan emel anda betul untuk penghantaran resit</p>
                            <p v-if="form.errors.donor_email" class="mt-1 text-sm text-red-600">{{ form.errors.donor_email }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Doa</label>
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
                                    <span class="text-xs text-slate-500">Identiti "Hamba Allah" akan dipaparkan sebagai penyumbang.</span>
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

                        <div class="flex justify-between pt-2">
                            <button type="button" @click="goToStep(totalSteps - 1)" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-bold text-slate-600 hover:bg-slate-50 transition">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                                Kembali
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="pt-4">
                    <button type="submit" :disabled="form.processing" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-black text-lg py-4 rounded-2xl shadow-lg transition disabled:opacity-70 flex justify-center items-center gap-2 hover:-translate-y-0.5 active:scale-[0.98]">
                        <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Sedang Diproses...' : `Teruskan Pembayaran — ${formatCurrency(form.amount)}` }}</span>
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

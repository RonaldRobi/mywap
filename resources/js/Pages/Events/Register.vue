<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PaymentGatewayBadge from '@/Components/PaymentGatewayBadge.vue';

const props = defineProps({
    form: Object,
    event: Object,
    isGuest: { type: Boolean, default: false },
    paymentGateway: { type: Object, default: null },
    canShare: { type: Boolean, default: false },
    publicUrl: { type: String, default: '' },
    qrSvg: { type: String, default: null },
});

const isPaid = computed(() => props.form.payment_required && props.form.price);
const showQr = ref(false);
const copied = ref(false);

const featuredImage = computed(() => props.form.header_image_url || props.event.featured_image_url || null);

// Form Builder ialah single source of truth — hanya answers dihantar,
// tiada field peserta auto dijana.
const registerForm = useForm({
    answers: {},
    payment_method: 'fpx',
});

function copyLink() {
    const url = props.publicUrl || window.location.href;
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            copied.value = true;
            setTimeout(() => { copied.value = false; }, 1500);
        });
    } else {
        window.prompt('Salin pautan ini:', url);
    }
}

function initAnswers() {
    for (const q of props.form.questions) {
        if (!(q.id in registerForm.answers)) {
            registerForm.answers[q.id] = q.type === 'checkbox' ? [] : '';
        }
    }
}
initAnswers();

function submit() {
    const routeName = props.isGuest ? 'events.register.public.store' : 'events.register.store';
    const params = props.isGuest
        ? { token: props.form.share_token || window.location.pathname.split('/').pop() }
        : { event: props.event.slug, form: props.form.id };

    registerForm.post(route(routeName, params), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head :title="`Pendaftaran: ${form.title}`" />

    <AppLayout>
        <div class="max-w-2xl mx-auto px-4 py-8">
            <!-- Toolbar kongsi — hanya admin/superadmin (awam tidak nampak) -->
            <div v-if="canShare" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 mb-6">
                <p class="text-[11px] font-bold uppercase tracking-widest text-amber-600 mb-2">Alat Kongsi (Admin)</p>
                <div class="flex flex-wrap gap-2">
                    <button @click="copyLink" class="inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                        {{ copied ? 'Pautan Disalin!' : 'Salin Pautan' }}
                    </button>
                    <button @click="showQr = true" class="inline-flex items-center gap-1.5 rounded-xl bg-white border border-amber-300 px-4 py-2 text-sm font-bold text-amber-700 hover:bg-amber-100 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        QR Code
                    </button>
                </div>
            </div>

            <!-- Event banner: Tajuk → Featured Image -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl shadow-sm mb-6 overflow-hidden">
                <div class="p-6">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
                        {{ isGuest ? 'Pendaftaran Awam' : 'Pendaftaran Event' }}
                    </p>
                    <h1 class="text-xl font-black text-gray-900">{{ event.title }}</h1>
                </div>

                <div v-if="featuredImage" class="aspect-[16/9] bg-gray-100 overflow-hidden">
                    <img :src="featuredImage" :alt="event.title" class="w-full h-full object-cover" />
                </div>

                <div class="p-6 pt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm">
                    <p class="text-gray-500">{{ event.start_formatted }}</p>
                    <p class="text-gray-400">{{ event.organization_name }}</p>
                </div>
            </div>

            <!-- Borang Pendaftaran -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-8 shadow-sm space-y-6">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">{{ form.organization_name }}</p>
                    <h2 class="text-2xl font-black text-gray-900">{{ form.title }}</h2>
                    <p v-if="form.description" class="text-sm text-gray-500 mt-2 whitespace-pre-line">{{ form.description }}</p>
                </div>

                <hr class="border-gray-100" />

                <!-- Questions -->
                <div v-for="q in form.questions" :key="q.id" class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">
                        {{ q.label }}
                        <span v-if="q.required" class="text-red-500">*</span>
                    </label>
                    <p v-if="q.help_text" class="text-xs text-gray-400">{{ q.help_text }}</p>

                    <input
                        v-if="['text', 'email', 'phone', 'number', 'date'].includes(q.type)"
                        v-model="registerForm.answers[q.id]"
                        :type="q.type === 'phone' ? 'tel' : q.type"
                        :placeholder="q.placeholder || ''"
                        :required="q.required"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300"
                    />

                    <textarea
                        v-else-if="q.type === 'textarea'"
                        v-model="registerForm.answers[q.id]"
                        :placeholder="q.placeholder || ''"
                        :required="q.required"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300"
                    ></textarea>

                    <input
                        v-else-if="q.type === 'file'"
                        type="file"
                        :required="q.required"
                        @input="registerForm.answers[q.id] = $event.target.files[0]"
                        class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-xl file:border-0 file:bg-gray-100 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200"
                    />

                    <select
                        v-else-if="q.type === 'select'"
                        v-model="registerForm.answers[q.id]"
                        :required="q.required"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                    >
                        <option value="">{{ q.placeholder || 'Pilih...' }}</option>
                        <option v-for="opt in q.options" :key="opt" :value="opt">{{ opt }}</option>
                    </select>

                    <div v-else-if="q.type === 'radio'" class="space-y-1.5">
                        <label v-for="opt in q.options" :key="opt" class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="registerForm.answers[q.id]" :value="opt" class="text-indigo-600 border-gray-300" />
                            {{ opt }}
                        </label>
                    </div>

                    <div v-else-if="q.type === 'checkbox'" class="space-y-1.5">
                        <label v-for="opt in q.options" :key="opt" class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" :value="opt" v-model="registerForm.answers[q.id]" class="rounded border-gray-300 text-indigo-600" />
                            {{ opt }}
                        </label>
                    </div>

                    <p v-if="registerForm.errors[`answers.${q.id}`]" class="text-xs text-red-500">{{ registerForm.errors[`answers.${q.id}`] }}</p>
                </div>

                <!-- Ringkasan Pembayaran (sebelum Hantar & Bayar) -->
                <div v-if="isPaid" class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-3">
                    <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Ringkasan Pembayaran</p>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Program</span>
                            <span class="font-semibold text-gray-800 text-right">{{ event.title }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Jumlah Bayaran</span>
                            <span class="font-bold text-emerald-600">RM {{ Number(form.price).toFixed(2) }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">Kaedah Pembayaran</span>
                            <span class="font-semibold text-gray-800">{{ paymentGateway?.methods || 'Pembayaran dalam talian' }}</span>
                        </div>
                    </div>

                    <PaymentGatewayBadge v-if="paymentGateway" :gateway="paymentGateway" />

                    <!-- Pilih kaedah bayaran -->
                    <div class="pt-1">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Pilih kaedah bayaran:</p>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm cursor-pointer transition"
                                :class="registerForm.payment_method === 'fpx' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input type="radio" v-model="registerForm.payment_method" value="fpx" class="text-indigo-600 border-gray-300" />
                                <span>
                                    <span class="block font-semibold text-gray-800">FPX</span>
                                    <span class="block text-[11px] text-gray-400">Internet Banking</span>
                                </span>
                            </label>
                            <label
                                class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm cursor-pointer transition"
                                :class="registerForm.payment_method === 'duitnow_qr' ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                            >
                                <input type="radio" v-model="registerForm.payment_method" value="duitnow_qr" class="text-indigo-600 border-gray-300" />
                                <span>
                                    <span class="block font-semibold text-gray-800">QR Pay</span>
                                    <span class="block text-[11px] text-gray-400">Imbas &amp; bayar</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Terms -->
                <div v-if="form.terms" class="rounded-2xl bg-gray-50 p-4">
                    <p class="text-sm font-bold text-gray-700 mb-1">Terma &amp; Syarat</p>
                    <p class="text-xs text-gray-500 whitespace-pre-line">{{ form.terms }}</p>
                </div>

                <button
                    @click="submit"
                    :disabled="registerForm.processing"
                    class="w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                >
                    {{ registerForm.processing ? 'Memproses...' : (isPaid ? `Hantar & Bayar RM ${Number(form.price).toFixed(2)}` : 'Hantar') }}
                </button>

                <p class="text-center text-xs text-gray-400">Dikuasakan oleh myWAP</p>
            </div>
        </div>

        <!-- Modal QR Code (admin sahaja) -->
        <div v-if="showQr && canShare" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="showQr = false">
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl text-center space-y-4">
                <p class="text-sm font-bold text-gray-800">QR Code Pendaftaran</p>
                <div class="mx-auto flex justify-center rounded-2xl border border-gray-100 bg-white p-4">
                    <div class="w-48 h-48 [&_svg]:w-full [&_svg]:h-full" v-html="qrSvg"></div>
                </div>
                <p class="text-xs text-gray-500 break-all">{{ publicUrl }}</p>
                <div class="flex gap-2">
                    <button @click="copyLink" class="flex-1 rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-700">
                        {{ copied ? 'Disalin!' : 'Salin Pautan' }}
                    </button>
                    <button @click="showQr = false" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Tutup</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

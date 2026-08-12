<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
import MovementBranding from '@/Components/MovementBranding.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    login_type: '',
    email: '',
    ic_number: '',
    password: '',
    remember: false,
});

const otpForm = useForm({
    ic_number: '',
    code: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const flow = ref(null);
const step = ref('role');
const memberCheckProcessing = ref(false);
const memberCheckError = ref('');
const memberOrganization = ref(null);
const showIcNotFoundModal = ref(false);

const otpProcessing = ref(false);
const otpError = ref('');
const otpSent = ref(false);
const memberHasEmail = ref(false);
const memberMaskedEmail = ref('');
const memberHasRequestedOtp = ref(false);

const biometricAlert = ref('');

const showBiometricAlert = (type) => {
    biometricAlert.value = type;
    setTimeout(() => { biometricAlert.value = ''; }, 4000);
};

const currentIdentifierError = computed(() => {
    if (flow.value === 'admin') {
        return form.errors.email;
    }

    if (flow.value === 'member') {
        return form.errors.ic_number;
    }

    return '';
});

const _page = usePage();

function csrfHeaders() {
    const token = _page.props.csrf_token;
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
        'X-XSRF-TOKEN': token,
    };
}

const resetFlowErrors = () => {
    form.clearErrors();
    otpForm.clearErrors();
    memberCheckError.value = '';
    otpError.value = '';
    otpSent.value = false;
};

const goToRoleSelection = () => {
    flow.value = null;
    step.value = 'role';
    memberOrganization.value = null;
    form.reset('email', 'ic_number', 'password', 'remember', 'login_type');
    otpForm.reset();
    resetFlowErrors();
};

const selectFlow = (selectedFlow) => {
    flow.value = selectedFlow;
    form.login_type = selectedFlow;
    form.reset('email', 'ic_number', 'password');
    form.clearErrors();
    memberCheckError.value = '';

    if (selectedFlow === 'admin') {
        step.value = 'admin';
        return;
    }

    memberOrganization.value = null;
    step.value = 'member-id';
};

const checkMember = async () => {
    form.clearErrors('ic_number');
    memberCheckError.value = '';

    if (!form.ic_number?.trim()) {
        form.setError('ic_number', 'No Kad Pengenalan / Passport diperlukan.');
        return;
    }

    memberCheckProcessing.value = true;

    try {
        const url = `${route('login.check-member')}?ic_number=${encodeURIComponent(form.ic_number)}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json();

        if (!response.ok || !payload?.found) {
            memberCheckError.value = '';
            showIcNotFoundModal.value = true;
            return;
        }

        memberOrganization.value = payload.organization;
        otpForm.ic_number = form.ic_number;

        if (payload.is_first_login) {
            memberHasRequestedOtp.value = payload.has_requested_otp;
            memberHasEmail.value = payload.has_email;
            memberMaskedEmail.value = payload.masked_email;

            if (payload.has_requested_otp) {
                step.value = 'member-otp-blocked';
            } else {
                step.value = 'member-otp-send';
            }
        } else {
            step.value = 'member-password';
        }
    } catch {
        memberCheckError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        memberCheckProcessing.value = false;
    }
};

const sendOtp = async () => {
    otpError.value = '';
    otpProcessing.value = true;

    try {
        const endpoint = memberHasEmail.value ? 'login.send-otp' : 'login.update-and-send-otp';
        const body = memberHasEmail.value
            ? { ic_number: otpForm.ic_number }
            : { ic_number: otpForm.ic_number, email: otpForm.email };

        if (!memberHasEmail.value && !otpForm.email?.trim()) {
            otpError.value = 'Sila masukkan alamat emel anda.';
            otpProcessing.value = false;
            return;
        }

        const response = await fetch(route(endpoint), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });

        const data = await response.json();

        if (!response.ok) {
            otpError.value = data.message || 'Ralat menghantar OTP. Sila cuba sebentar lagi.';
            return;
        }

        otpSent.value = true;
        step.value = 'member-otp-verify';
    } catch {
        otpError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        otpProcessing.value = false;
    }
};

const verifyOtp = async () => {
    otpForm.clearErrors();
    otpError.value = '';
    otpProcessing.value = true;

    try {
        const response = await fetch(route('login.verify-otp'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({
                ic_number: otpForm.ic_number,
                code: otpForm.code,
                password: otpForm.password,
                password_confirmation: otpForm.password_confirmation,
            }),
        });

        const data = await response.json();

        if (!response.ok) {
            otpError.value = data.message || 'Kod OTP tidak sah. Sila cuba sebentar lagi.';
            return;
        }

        window.location.href = data.redirect;
    } catch {
        otpError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        otpProcessing.value = false;
    }
};

const submit = () => {
    form.clearErrors();

    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

</script>

<template>
    <div class="min-h-screen bg-[#F4F6F1]">
        <Head title="Log in" />

        <main class="grid min-h-screen lg:grid-cols-[minmax(0,3fr)_minmax(400px,2fr)]">
            <section class="identity-panel relative overflow-hidden bg-[#071525] px-4 py-6 sm:px-8 sm:py-8 lg:flex lg:min-h-screen lg:items-stretch lg:px-10 lg:py-10 xl:px-14">
                <div class="relative z-10 mx-auto w-full max-w-4xl">
                    <MovementBranding />
                </div>
            </section>

            <section class="flex items-center bg-[#F4F6F1] px-4 py-10 sm:px-8 lg:px-10 xl:px-16">
                <div class="login-card mx-auto w-full max-w-md border border-[#6FBF8A] border-t-4 border-t-[#2F6B32] bg-[#F4F6F1] p-5 shadow-[0_18px_55px_rgba(7,21,37,0.12)] sm:p-8">
                    <div class="mb-6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#2F6B32]">Platform digital ekosistem gerakan</p>
                        <p class="mt-2 text-lg font-black tracking-tight text-[#123D2A]">myWAP</p>
                        <h2 class="mt-5 text-2xl font-black text-[#071525] sm:text-3xl">Log Masuk</h2>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">Akses keahlian, program dan khidmat gerakan PKPIM, ABIM dan WADAH.</p>
                    </div>

                    <div v-if="status" class="mb-4 rounded-xl border border-[#6FBF8A] bg-[#EDF5EE] px-3 py-2 text-sm font-medium text-[#123D2A]">
                        {{ status }}
                    </div>

                    <Transition name="step-fade" mode="out-in">
                        <div :key="step" class="space-y-4">
                            <div v-if="step === 'role'" class="space-y-4">
                                <button
                                    type="button"
                                    class="w-full rounded-xl bg-[#2F6B32] px-4 py-3 text-left text-sm font-semibold text-[#F4F6F1] transition hover:bg-[#123D2A]"
                                    @click="selectFlow('admin')"
                                >
                                    Log Masuk Admin
                                </button>

                                <button
                                    type="button"
                                    class="w-full rounded-xl bg-[#2F6B32] px-4 py-3 text-left text-sm font-semibold text-[#F4F6F1] transition hover:bg-[#123D2A]"
                                    @click="selectFlow('member')"
                                >
                                    Log Masuk Ahli
                                </button>
                            </div>

                            <form v-else-if="step === 'admin'" @submit.prevent="submit" class="space-y-4">
                                <div>
                                    <InputLabel for="email" value="Email" class="!text-slate-200" />
                                    <TextInput
                                        id="email"
                                        type="email"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="form.email"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="nama@domain.com"
                                    />
                                    <InputError class="mt-2" :message="currentIdentifierError" />
                                </div>

                                <div>
                                    <InputLabel for="password" value="Password" class="!text-slate-200" />
                                    <TextInput
                                        id="password"
                                        type="password"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="form.password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex items-center gap-2">
                                        <Checkbox name="remember" v-model:checked="form.remember" />
                                        <span class="text-sm text-slate-300">Remember me</span>
                                    </label>

                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm font-medium text-[#2F6B32] hover:text-[#123D2A]"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="login-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-[#2F6B32] px-4 py-2.5 text-sm font-semibold text-white shadow-none hover:bg-[#123D2A]"
                                        :class="{ 'opacity-25': form.processing }"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                                    </PrimaryButton>
                                </div>
                            </form>

                            <div v-else-if="step === 'member-id'" class="space-y-4">
                                <div>
                                    <InputLabel for="ic_number" value="No. Ahli / No. Kad Pengenalan / E-mel" class="!text-slate-200" />
                                    <TextInput
                                        id="ic_number"
                                        type="text"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="form.ic_number"
                                        required
                                        autofocus
                                        autocomplete="username"
                                        placeholder="contoh: W0001, 900101015555, atau e-mel"
                                    />
                                    <InputError class="mt-2" :message="currentIdentifierError" />
                                    <InputError class="mt-2" :message="memberCheckError" />
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="login-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-[#2F6B32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#123D2A]"
                                        :class="{ 'opacity-25': memberCheckProcessing }"
                                        :disabled="memberCheckProcessing"
                                        @click="checkMember"
                                    >
                                        {{ memberCheckProcessing ? 'Menyemak...' : 'Seterusnya' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <form v-else-if="step === 'member-password'" @submit.prevent="submit" class="space-y-4">
                                <div class="rounded-2xl border border-[#6FBF8A] bg-[#F4F6F1] p-4 text-sm text-[#123D2A]">
                                    <p class="font-semibold">
                                        Anda adalah ahli {{ memberOrganization?.name }}
                                    </p>
                                    <img
                                        v-if="memberOrganization?.logo_url"
                                        :src="memberOrganization.logo_url"
                                        :alt="`Logo ${memberOrganization.name}`"
                                        class="mt-3 h-16 w-auto rounded-lg border border-white/20 bg-white/90 p-2"
                                    >
                                </div>

                                <div>
                                    <InputLabel for="password_member" value="Password" class="!text-slate-200" />
                                    <TextInput
                                        id="password_member"
                                        type="password"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="form.password"
                                        required
                                        autocomplete="current-password"
                                        placeholder="••••••••"
                                    />
                                    <InputError class="mt-2" :message="form.errors.password" />
                                    <InputError class="mt-2" :message="currentIdentifierError" />
                                </div>

                                <!-- Biometric placeholder -->
                                <div class="relative">
                                    <div class="flex items-center gap-3">
                                        <span class="flex-1 border-t border-white/10"></span>
                                        <span class="text-xs text-slate-400">atau</span>
                                        <span class="flex-1 border-t border-white/10"></span>
                                    </div>

                                    <div class="mt-3 flex items-center justify-center gap-6">
                                        <button
                                            type="button"
                                            class="flex flex-col items-center gap-1 text-slate-300 transition hover:text-[#2F6B32]"
                                            @click="showBiometricAlert('faceid')"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-7 w-7">
                                                <path d="M2 8V6a2 2 0 0 1 2-2h2" />
                                                <path d="M2 16v2a2 2 0 0 0 2 2h2" />
                                                <path d="M18 4h2a2 2 0 0 1 2 2v2" />
                                                <path d="M18 20h2a2 2 0 0 0 2-2v-2" />
                                                <circle cx="9" cy="11" r="1" fill="currentColor" stroke="none" />
                                                <circle cx="15" cy="11" r="1" fill="currentColor" stroke="none" />
                                                <path d="M8 16c1.5 1.3 4.5 1.3 6 0" />
                                            </svg>
                                            <span class="text-[10px] font-medium">Face ID</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="flex flex-col items-center gap-1 text-slate-300 transition hover:text-[#2F6B32]"
                                            @click="showBiometricAlert('touchid')"
                                        >
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-7 w-7">
                                                <path d="M12 10a2 2 0 0 1 2 2c0 .8-.2 1.6-.6 2.2" />
                                                <path d="M8 12a4 4 0 0 1 4-4" />
                                                <path d="M6.5 14.3A6 6 0 0 1 18 12" />
                                                <path d="M5 17.3A8.5 8.5 0 0 1 20.5 12" />
                                                <path d="M4 19.8A11 11 0 0 1 23 12" />
                                                <path d="M3 21.5A13 13 0 0 1 24 12" />
                                                <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
                                            </svg>
                                            <span class="text-[10px] font-medium">Touch ID</span>
                                        </button>
                                    </div>

                                    <Transition name="biometric-fade">
                                        <div
                                            v-if="biometricAlert"
                                            class="mt-3 rounded-lg border border-[#6FBF8A]/40 bg-[#DCECDF] px-3 py-2 text-center text-xs text-[#123D2A]"
                                        >
                                            <template v-if="biometricAlert === 'faceid'">Face ID</template>
                                            <template v-else>Touch ID</template>
                                            akan tersedia dalam aplikasi mudah alih myWAP.
                                        </div>
                                    </Transition>
                                </div>

                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <label class="inline-flex items-center gap-2">
                                        <Checkbox name="remember" v-model:checked="form.remember" />
                                        <span class="text-sm text-slate-300">Remember me</span>
                                    </label>

                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="text-sm font-medium text-[#2F6B32] hover:text-[#123D2A]"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="login-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="step = 'member-id'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-[#2F6B32] px-4 py-2.5 text-sm font-semibold text-white shadow-none hover:bg-[#123D2A]"
                                        :class="{ 'opacity-25': form.processing }"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                                    </PrimaryButton>
                                </div>
                            </form>

                            <div v-else-if="step === 'member-otp-send'" class="space-y-4">
                                <div class="rounded-2xl border border-[#6FBF8A] bg-[#F4F6F1] p-4 text-sm text-[#123D2A]">
                                    <p class="font-semibold">
                                        Log Masuk Kali Pertama
                                    </p>
                                    <p class="mt-2 text-emerald-200/80">
                                        Anda adalah ahli {{ memberOrganization?.name }}. Sila sahkan identiti anda untuk log masuk kali pertama.
                                    </p>
                                </div>

                                <div v-if="!memberHasEmail" class="space-y-2">
                                    <InputLabel for="otp_email" value="Alamat Emel" class="!text-slate-200" />
                                    <TextInput
                                        id="otp_email"
                                        type="email"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="otpForm.email"
                                        required
                                        placeholder="nama@domain.com"
                                    />
                                    <p class="text-xs text-slate-400">Akaun anda tiada emel berdaftar. Sila masukkan emel untuk menerima kod OTP.</p>
                                </div>

                                <div v-else class="rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
                                    Kod OTP akan dihantar ke emel: <span class="font-medium text-emerald-200">{{ memberMaskedEmail }}</span>
                                </div>

                                <InputError class="mt-2" :message="otpError" />

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="login-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="step = 'member-id'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-[#2F6B32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#123D2A]"
                                        :class="{ 'opacity-25': otpProcessing }"
                                        :disabled="otpProcessing"
                                        @click="sendOtp"
                                    >
                                        {{ otpProcessing ? 'Menghantar...' : 'Hantar Kod OTP' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-else-if="step === 'member-otp-verify'" class="space-y-4">
                                <div class="rounded-2xl border border-[#6FBF8A] bg-[#F4F6F1] p-4 text-sm text-[#123D2A]">
                                    <p class="font-semibold">
                                        Sahkan OTP & Cipta Kata Laluan
                                    </p>
                                    <p class="mt-1 text-emerald-200/80">
                                        Kod OTP telah dihantar ke emel berdaftar anda.
                                    </p>
                                </div>

                                <div>
                                    <InputLabel for="otp_code" value="Kod OTP" class="!text-slate-200" />
                                    <TextInput
                                        id="otp_code"
                                        type="text"
                                        class="mt-1 block w-full text-center text-lg tracking-widest focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="otpForm.code"
                                        required
                                        maxlength="6"
                                        placeholder="••••••"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="otp_password" value="Kata Laluan Baru" class="!text-slate-200" />
                                    <TextInput
                                        id="otp_password"
                                        type="password"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="otpForm.password"
                                        required
                                        minlength="8"
                                        placeholder="Minimum 8 aksara"
                                    />
                                </div>

                                <div>
                                    <InputLabel for="otp_password_confirmation" value="Pengesahan Kata Laluan" class="!text-slate-200" />
                                    <TextInput
                                        id="otp_password_confirmation"
                                        type="password"
                                        class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                        v-model="otpForm.password_confirmation"
                                        required
                                        placeholder="Taip semula kata laluan"
                                    />
                                </div>

                                <InputError class="mt-2" :message="otpError" />

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="login-secondary rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="step = 'member-otp-send'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-[#2F6B32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#123D2A]"
                                        :class="{ 'opacity-25': otpProcessing }"
                                        :disabled="otpProcessing"
                                        @click="verifyOtp"
                                    >
                                        {{ otpProcessing ? 'Mengesahkan...' : 'Log Masuk' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-else-if="step === 'member-otp-blocked'" class="space-y-4">
                                <div class="rounded-2xl border border-[#6FBF8A] bg-[#EDF5EE] p-4 text-sm text-[#123D2A]">
                                    <p class="font-semibold">
                                        Permintaan Log Masuk Kali Pertama Telah Dihantar
                                    </p>
                                    <p class="mt-3 leading-relaxed text-[#2F6B32]">
                                        Anda telah pun menghantar permintaan log masuk kali pertama.
                                        Sila gunakan pautan <strong>'Lupa Kata Laluan'</strong> di bawah untuk menetapkan semula kata laluan.
                                    </p>
                                </div>

                                <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
                                    <p v-if="memberMaskedEmail">
                                        Pautan reset akan dihantar ke emel: <span class="font-medium text-[#2F6B32]">{{ memberMaskedEmail }}</span>
                                    </p>
                                    <p v-else>
                                        Sila hubungi urusetia organisasi untuk bantuan lanjut.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="block w-full rounded-xl bg-[#2F6B32] px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-[#123D2A]"
                                    >
                                        Lupa Kata Laluan
                                    </Link>

                                    <button
                                        type="button"
                                        class="login-secondary w-full rounded-xl px-4 py-2 text-sm font-semibold transition"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali ke Pilihan Log Masuk
                                    </button>
                                </div>
                            </div>

                            <p class="text-center text-sm text-slate-300">
                                New here?
                                <Link :href="route('register')" class="font-semibold text-[#2F6B32] hover:text-[#123D2A]">
                                    Create account
                                </Link>
                            </p>
                            <p class="text-center text-xs text-slate-400">
                                Tanpa akaun? Anda masih boleh
                                <Link :href="route('member.facilities.index')" class="font-semibold text-emerald-300 hover:text-emerald-200">
                                    tempah Perkhidmatan/Fasiliti
                                </Link>
                                atau melawat
                                <Link :href="route('mall.index')" class="font-semibold text-[#2F6B32] hover:text-[#123D2A]">
                                    MyWAP Mall
                                </Link>.
                            </p>
                        </div>
                    </Transition>
                    <div class="mt-6 border-t border-[#D5E3D8] pt-4 text-center">
                        <p class="text-[9px] font-bold uppercase tracking-[0.16em] text-[#2F6B32]">Platform rasmi ekosistem</p>
                        <p class="mt-1 text-xs font-black tracking-[0.12em] text-[#123D2A]">PKPIM · ABIM · WADAH</p>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <Modal :show="showIcNotFoundModal" @close="showIcNotFoundModal = false" maxWidth="md">
        <div class="p-6">
            <h2 class="text-xl font-bold text-slate-900">Ahli Tidak Dijumpai</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                No IC/Pasport yang anda masukkan tidak ditemui dalam sistem.
                Sila hubungi <strong>urusetia organisasi</strong> masing-masing untuk bantuan lanjut.
            </p>
            <div class="mt-6 flex justify-end">
                <button @click="showIcNotFoundModal = false" class="rounded-xl bg-[#2F6B32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#123D2A]">
                    OK
                </button>
            </div>
        </div>
    </Modal>
</template>

<style scoped>
.step-fade-enter-active,
.step-fade-leave-active {
    transition: all 260ms ease;
}

.step-fade-enter-from,
.step-fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.biometric-fade-enter-active,
.biometric-fade-leave-active {
    transition: all 200ms ease;
}

.biometric-fade-enter-from,
.biometric-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.login-card :deep(.text-slate-200) { color: #123d2a; }
.login-card :deep(.text-slate-300) { color: #52645a; }
.login-card :deep(.text-slate-400) { color: #718078; }
.login-card :deep(.text-emerald-300) { color: #2f6b32; }
.login-card :deep(.text-emerald-100),
.login-card :deep(.text-emerald-200\/80),
.login-card :deep(.text-emerald-200) { color: #123d2a; }
.login-card :deep(.bg-white\/5) { background: #edf5ee; }
.login-card :deep(.border-white\/10),
.login-card :deep(.border-emerald-200\/30) { border-color: #b8d9c0; }
.login-card :deep(input) { border-color: #6fbf8a; background: #f4f6f1; color: #071525; }
.login-card :deep(input::placeholder) { color: #91a097; }
.login-card :deep(.border-white\/20) { border-color: #c8d8cb; color: #123d2a; }

.login-secondary {
    border: 1px solid #6fbf8a;
    color: #2f6b32;
    background: transparent;
}

.login-secondary:hover {
    background: #f4f6f1;
    border-color: #2f6b32;
}

.identity-panel::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.045;
    background-image:
        linear-gradient(30deg, #6fbf8a 12%, transparent 12.5%, transparent 87%, #6fbf8a 87.5%, #6fbf8a),
        linear-gradient(150deg, #6fbf8a 12%, transparent 12.5%, transparent 87%, #6fbf8a 87.5%, #6fbf8a),
        linear-gradient(30deg, #6fbf8a 12%, transparent 12.5%, transparent 87%, #6fbf8a 87.5%, #6fbf8a),
        linear-gradient(150deg, #6fbf8a 12%, transparent 12.5%, transparent 87%, #6fbf8a 87.5%, #6fbf8a);
    background-position: 0 0, 0 0, 36px 62px, 36px 62px;
    background-size: 72px 124px;
}

.identity-panel::after {
    content: '';
    position: absolute;
    inset: auto 0 0;
    height: 8px;
    background: #2f6b32;
}
</style>

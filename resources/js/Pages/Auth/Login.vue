<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import AccernityCard from '@/Components/ui/AccernityCard.vue';
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';
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
    <AuroraBackground>
        <Head title="Log in" />

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col px-3 py-5 sm:px-4 sm:py-6 md:px-8 md:py-10 lg:flex-row lg:items-center lg:gap-8">

            <section class="hidden flex-1 lg:block">
                <div class="max-w-xl">
                    <p class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200 backdrop-blur-sm">
                        myWAP
                    </p>
                    <h1 class="mt-5 text-4xl font-black leading-tight text-white xl:text-5xl">
                        Komuniti Islamik,
                        <span class="bg-gradient-to-r from-emerald-300 to-cyan-200 bg-clip-text text-transparent">lebih teratur & berdaya.</span>
                    </h1>
                    <p class="mt-4 text-sm leading-relaxed text-slate-300">
                        Urus program, infaq, pustaka dan perkembangan ahli dalam satu platform moden.
                    </p>

                    <div class="mt-8 grid grid-cols-3 gap-3">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <p class="text-xs text-slate-300">Program</p>
                            <p class="mt-1 text-xl font-black text-white">Live</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <p class="text-xs text-slate-300">Infaq</p>
                            <p class="mt-1 text-xl font-black text-white">Smart</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                            <p class="text-xs text-slate-300">Members</p>
                            <p class="mt-1 text-xl font-black text-white">Connected</p>
                        </div>
                    </div>

                    <div class="mt-8 rounded-3xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                        <div class="flex items-center justify-between text-xs text-slate-300">
                            <span>Platform Focus</span>
                            <span class="rounded-full bg-cyan-400/15 px-2 py-1 text-cyan-200">Aurora Background</span>
                        </div>
                        <div class="mt-3 h-24 overflow-hidden rounded-2xl border border-white/10 bg-slate-950/60 p-3">
                            <div class="grid h-full grid-cols-3 gap-2">
                                <div class="rounded-xl bg-gradient-to-b from-emerald-400/20 to-transparent"></div>
                                <div class="rounded-xl bg-gradient-to-b from-indigo-400/20 to-transparent"></div>
                                <div class="rounded-xl bg-gradient-to-b from-cyan-400/20 to-transparent"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="w-full lg:w-[460px]">
                <AccernityCard>
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-100">Welcome Back</p>
                        <h2 class="mt-1 text-2xl font-black text-white sm:text-3xl">Sign in to continue</h2>
                        <p class="mt-1 text-sm text-slate-300">Masukkan butiran akaun anda untuk akses dashboard.</p>
                    </div>

                    <div v-if="status" class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                        {{ status }}
                    </div>

                    <Transition name="step-fade" mode="out-in">
                        <div :key="step" class="space-y-4">
                            <div v-if="step === 'role'" class="space-y-4">
                                <button
                                    type="button"
                                    class="w-full rounded-xl border border-cyan-200/40 bg-white/10 px-4 py-3 text-left text-sm font-semibold text-white transition hover:bg-white/15"
                                    @click="selectFlow('admin')"
                                >
                                    Log Masuk Admin
                                </button>

                                <button
                                    type="button"
                                    class="w-full rounded-xl border border-emerald-200/40 bg-white/10 px-4 py-3 text-left text-sm font-semibold text-white transition hover:bg-white/15"
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
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
                                        class="text-sm font-medium text-cyan-200 hover:text-cyan-100"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-black/35 hover:bg-slate-900"
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-emerald-300 focus:ring-emerald-300"
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
                                        class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600"
                                        :class="{ 'opacity-25': memberCheckProcessing }"
                                        :disabled="memberCheckProcessing"
                                        @click="checkMember"
                                    >
                                        {{ memberCheckProcessing ? 'Menyemak...' : 'Seterusnya' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <form v-else-if="step === 'member-password'" @submit.prevent="submit" class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-emerald-300 focus:ring-emerald-300"
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
                                            class="flex flex-col items-center gap-1 text-slate-300 transition hover:text-cyan-300"
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
                                            class="flex flex-col items-center gap-1 text-slate-300 transition hover:text-cyan-300"
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
                                            class="mt-3 rounded-lg border border-cyan-200/30 bg-cyan-500/10 px-3 py-2 text-center text-xs text-cyan-100"
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
                                        class="text-sm font-medium text-cyan-200 hover:text-cyan-100"
                                    >
                                        Forgot password?
                                    </Link>
                                </div>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="step = 'member-id'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-black/35 hover:bg-slate-900"
                                        :class="{ 'opacity-25': form.processing }"
                                        :disabled="form.processing"
                                    >
                                        {{ form.processing ? 'Signing in...' : 'Sign in' }}
                                    </PrimaryButton>
                                </div>
                            </form>

                            <div v-else-if="step === 'member-otp-send'" class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-emerald-300 focus:ring-emerald-300"
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
                                        class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="step = 'member-id'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600"
                                        :class="{ 'opacity-25': otpProcessing }"
                                        :disabled="otpProcessing"
                                        @click="sendOtp"
                                    >
                                        {{ otpProcessing ? 'Menghantar...' : 'Hantar Kod OTP' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-else-if="step === 'member-otp-verify'" class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200/30 bg-emerald-500/10 p-4 text-sm text-emerald-100">
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 text-center text-lg tracking-widest focus:border-emerald-300 focus:ring-emerald-300"
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-emerald-300 focus:ring-emerald-300"
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
                                        class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-emerald-300 focus:ring-emerald-300"
                                        v-model="otpForm.password_confirmation"
                                        required
                                        placeholder="Taip semula kata laluan"
                                    />
                                </div>

                                <InputError class="mt-2" :message="otpError" />

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="step = 'member-otp-send'"
                                    >
                                        Kembali
                                    </button>
                                    <PrimaryButton
                                        class="flex-1 justify-center rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-600"
                                        :class="{ 'opacity-25': otpProcessing }"
                                        :disabled="otpProcessing"
                                        @click="verifyOtp"
                                    >
                                        {{ otpProcessing ? 'Mengesahkan...' : 'Log Masuk' }}
                                    </PrimaryButton>
                                </div>
                            </div>

                            <div v-else-if="step === 'member-otp-blocked'" class="space-y-4">
                                <div class="rounded-2xl border border-amber-200/30 bg-amber-500/10 p-4 text-sm text-amber-100">
                                    <p class="font-semibold">
                                        Permintaan Log Masuk Kali Pertama Telah Dihantar
                                    </p>
                                    <p class="mt-3 text-amber-200/80 leading-relaxed">
                                        Anda telah pun menghantar permintaan log masuk kali pertama.
                                        Sila gunakan pautan <strong>'Lupa Kata Laluan'</strong> di bawah untuk menetapkan semula kata laluan.
                                    </p>
                                </div>

                                <div class="rounded-xl border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
                                    <p v-if="memberMaskedEmail">
                                        Pautan reset akan dihantar ke emel: <span class="font-medium text-amber-200">{{ memberMaskedEmail }}</span>
                                    </p>
                                    <p v-else>
                                        Sila hubungi urusetia organisasi untuk bantuan lanjut.
                                    </p>
                                </div>

                                <div class="flex flex-col gap-2">
                                    <Link
                                        v-if="canResetPassword"
                                        :href="route('password.request')"
                                        class="block w-full rounded-xl bg-amber-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-amber-500"
                                    >
                                        Lupa Kata Laluan
                                    </Link>

                                    <button
                                        type="button"
                                        class="w-full rounded-xl border border-white/20 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                                        @click="goToRoleSelection"
                                    >
                                        Kembali ke Pilihan Log Masuk
                                    </button>
                                </div>
                            </div>

                            <p class="text-center text-sm text-slate-300">
                                New here?
                                <Link :href="route('register')" class="font-semibold text-cyan-200 hover:text-cyan-100">
                                    Create account
                                </Link>
                            </p>
                        </div>
                    </Transition>
                </AccernityCard>
            </section>
        </div>
    </AuroraBackground>

    <Modal :show="showIcNotFoundModal" @close="showIcNotFoundModal = false" maxWidth="md">
        <div class="p-6">
            <h2 class="text-xl font-bold text-slate-900">Ahli Tidak Dijumpai</h2>
            <p class="mt-3 text-sm leading-relaxed text-slate-600">
                No IC/Pasport yang anda masukkan tidak ditemui dalam sistem.
                Sila hubungi <strong>urusetia organisasi</strong> masing-masing untuk bantuan lanjut.
            </p>
            <div class="mt-6 flex justify-end">
                <button @click="showIcNotFoundModal = false" class="rounded-xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
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
</style>

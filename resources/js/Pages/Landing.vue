<script setup>
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    ic_number: '',
    password: '',
    remember: false,
});

const flow = ref('login'); // login | first-time | forgot-id
const loginError = ref('');

// First-time flow
const ftStep = ref('ic');
const ftIcNumber = ref('');
const ftEmail = ref('');
const ftCode = ref('');
const ftPassword = ref('');
const ftPasswordConfirmation = ref('');
const ftOrg = ref(null);
const ftIsFirstLogin = ref(false);
const ftProcessing = ref(false);
const ftError = ref('');

// Forgot ID flow
const fiStep = ref('ic');
const fiIcNumber = ref('');
const fiDob = ref('');
const fiMaskedEmail = ref('');
const fiMemberNo = ref('');
const fiProcessing = ref(false);
const fiError = ref('');
const fiVerified = ref(false);

const resetFlow = () => {
    form.reset('email', 'ic_number', 'password', 'remember');
    form.clearErrors();
    loginError.value = '';
    flow.value = 'login';

    ftStep.value = 'ic';
    ftIcNumber.value = '';
    ftEmail.value = '';
    ftCode.value = '';
    ftPassword.value = '';
    ftPasswordConfirmation.value = '';
    ftOrg.value = null;
    ftIsFirstLogin.value = false;
    ftProcessing.value = false;
    ftError.value = '';

    fiStep.value = 'ic';
    fiIcNumber.value = '';
    fiDob.value = '';
    fiMaskedEmail.value = '';
    fiMemberNo.value = '';
    fiProcessing.value = false;
    fiError.value = '';
    fiVerified.value = false;
};

const handleLogin = () => {
    loginError.value = '';
    form.clearErrors();

    const identifier = form.email || form.ic_number;

    if (!identifier?.trim()) {
        loginError.value = 'Sila masukkan No KP / Emel.';
        return;
    }

    if (!form.password) {
        loginError.value = 'Sila masukkan kata laluan.';
        return;
    }

    form.post(route('login'), {
        onError: (errors) => {
            loginError.value = errors.email || errors.ic_number || 'Log masuk gagal.';
        },
        onFinish: () => {
            if (!loginError.value) {
                form.reset('password');
            }
        },
    });
};

const updateIdentifier = (value) => {
    const val = value?.trim() || '';
    if (val.includes('@')) {
        form.email = val;
        form.ic_number = '';
    } else {
        form.ic_number = val;
        form.email = '';
    }
};

// ─── First-Time Flow ──────────────────────────────────────────────────

const ftLookup = async () => {
    ftError.value = '';
    if (!ftIcNumber.value?.trim()) {
        ftError.value = 'No Kad Pengenalan / Passport diperlukan.';
        return;
    }

    ftProcessing.value = true;
    try {
        const url = `${route('login.check-member')}?ic_number=${encodeURIComponent(ftIcNumber.value)}`;
        const res = await fetch(url, { method: 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        const payload = await res.json();

        if (!res.ok || !payload?.found) {
            ftError.value = payload?.message || 'Ahli tidak dijumpai.';
            return;
        }

        ftOrg.value = payload.organization;
        ftIsFirstLogin.value = payload.is_first_login ?? false;
        ftStep.value = 'email';
    } catch {
        ftError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        ftProcessing.value = false;
    }
};

const ftSendOtp = async () => {
    ftError.value = '';
    if (!ftEmail.value?.trim()) {
        ftError.value = 'Emel diperlukan.';
        return;
    }

    ftProcessing.value = true;
    try {
        const res = await fetch(route('login.update-and-send-otp'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ ic_number: ftIcNumber.value, email: ftEmail.value }),
        });
        const payload = await res.json();

        if (!res.ok) {
            ftError.value = payload?.message || 'Ralat menghantar OTP.';
            return;
        }

        ftStep.value = 'otp';
    } catch {
        ftError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        ftProcessing.value = false;
    }
};

const ftVerifyOtp = async () => {
    ftError.value = '';
    if (!ftCode.value?.trim() || ftCode.value.length !== 6) {
        ftError.value = 'Kod OTP 6-digit diperlukan.';
        return;
    }

    const body = { ic_number: ftIcNumber.value, code: ftCode.value };

    if (ftIsFirstLogin.value) {
        if (!ftPassword.value || ftPassword.value.length < 8) {
            ftError.value = 'Kata laluan diperlukan (minimum 8 aksara).';
            return;
        }
        if (ftPassword.value !== ftPasswordConfirmation.value) {
            ftError.value = 'Kata laluan tidak sepadan.';
            return;
        }
        body.password = ftPassword.value;
        body.password_confirmation = ftPasswordConfirmation.value;
    }

    ftProcessing.value = true;
    try {
        const res = await fetch(route('login.verify-otp'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        const payload = await res.json();

        if (!res.ok) {
            ftError.value = payload?.message || 'Kod OTP tidak sah.';
            return;
        }

        router.visit(payload.redirect);
    } catch {
        ftError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        ftProcessing.value = false;
    }
};

// ─── Forgot ID Flow ───────────────────────────────────────────────────

const fiLookup = async () => {
    fiError.value = '';
    if (!fiIcNumber.value?.trim()) {
        fiError.value = 'No Kad Pengenalan / Passport diperlukan.';
        return;
    }

    fiProcessing.value = true;
    try {
        const res = await fetch(route('login.forgot-id'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ ic_number: fiIcNumber.value }),
        });
        const payload = await res.json();

        if (!res.ok) {
            fiError.value = payload?.message || 'No IC tidak ditemui.';
            return;
        }

        fiStep.value = 'verify';
    } catch {
        fiError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        fiProcessing.value = false;
    }
};

const fiVerify = async () => {
    fiError.value = '';
    if (!fiDob.value?.trim()) {
        fiError.value = 'Tarikh lahir diperlukan.';
        return;
    }

    fiProcessing.value = true;
    try {
        const res = await fetch(route('login.forgot-id'), {
            method: 'POST',
            headers: csrfHeaders(),
            credentials: 'same-origin',
            body: JSON.stringify({ ic_number: fiIcNumber.value, dob: fiDob.value }),
        });
        const payload = await res.json();

        if (!res.ok) {
            fiError.value = payload?.message || 'Tarikh lahir tidak tepat.';
            return;
        }

        fiMaskedEmail.value = payload.masked_email;
        fiMemberNo.value = payload.member_no;
        fiVerified.value = true;
        fiStep.value = 'result';
    } catch {
        fiError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        fiProcessing.value = false;
    }
};

// ─── Quick Login (Testing) ────────────────────────────────────────────

const resetLinkSending = ref(false);

const sendResetLink = () => {
    resetLinkSending.value = true;
    const icNumber = fiIcNumber.value || ftIcNumber.value;

    router.post(route('password.email'), { ic_number: icNumber }, {
        preserveScroll: true,
        onFinish: () => { resetLinkSending.value = false; },
    });
};

const quickLoginRole = ref('');

const quickLogin = () => {
    if (!quickLoginRole.value || form.processing) return;

    form.clearErrors();
    form.password = 'password';
    form.remember = true;

    if (quickLoginRole.value === 'superadmin') {
        form.email = 'superadmin@mywap.my';
        form.ic_number = '';
    } else if (quickLoginRole.value === 'admin_pkpim') {
        form.email = 'admin@mywap.my';
        form.ic_number = '';
    } else if (quickLoginRole.value === 'member') {
        form.email = '';
        form.ic_number = '980512101234';
    }

    setTimeout(() => {
        form.post(route('login'), {
            onError: (errors) => {
                loginError.value = errors.email || errors.ic_number || 'Log masuk gagal.';
            },
        });
    }, 100);
};
</script>

<template>
    <Head title="myWAP" />

    <AuroraBackground>
        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-center justify-center px-3 py-6 sm:px-4 sm:py-10 md:px-8">
            <div class="grid w-full grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-[1.1fr_0.9fr] lg:items-stretch">
                <section class="hidden rounded-[32px] border border-white/10 bg-white/5 p-10 text-white backdrop-blur-sm lg:flex lg:flex-col lg:justify-between">
                    <div>
                        <p class="inline-flex rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-100">
                            Lifecycle Membership
                        </p>
                        <h1 class="mt-5 text-5xl font-black tracking-tight">myWAP</h1>
                        <p class="mt-4 max-w-lg text-sm leading-relaxed text-slate-200">
                            Platform pengurusan keahlian berfasa untuk PKPIM, ABIM dan WADAH dengan automasi transisi umur.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl border border-indigo-300/15 bg-indigo-400/10 p-3 backdrop-blur-sm">
                                <p class="text-[10px] font-semibold uppercase text-indigo-200">PKPIM</p>
                                <p class="mt-1 text-xs font-bold text-white">&lt; 20</p>
                            </div>
                            <div class="rounded-2xl border border-emerald-300/15 bg-emerald-400/10 p-3 backdrop-blur-sm">
                                <p class="text-[10px] font-semibold uppercase text-emerald-200">ABIM</p>
                                <p class="mt-1 text-xs font-bold text-white">20 - 29</p>
                            </div>
                            <div class="rounded-2xl border border-amber-300/15 bg-amber-400/10 p-3 backdrop-blur-sm">
                                <p class="text-[10px] font-semibold uppercase text-amber-200">WADAH</p>
                                <p class="mt-1 text-xs font-bold text-white">30+</p>
                            </div>
                        </div>

                        <div class="rounded-3xl border border-white/10 bg-slate-950/30 p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-300">Unified access</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <div class="h-16 rounded-2xl bg-gradient-to-b from-cyan-400/20 to-transparent"></div>
                                <div class="h-16 rounded-2xl bg-gradient-to-b from-emerald-400/20 to-transparent"></div>
                                <div class="h-16 rounded-2xl bg-gradient-to-b from-violet-400/20 to-transparent"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="relative overflow-hidden rounded-[30px] border border-white/55 bg-white/90 p-5 shadow-[0_22px_60px_rgba(2,6,23,0.35)] backdrop-blur-md sm:p-7 lg:p-8">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/40 via-white/25 to-white/15"></div>
                    <div class="relative z-10 mb-6 flex gap-2">
                        <span class="h-3 w-3 rounded-full bg-rose-300/90"></span>
                        <span class="h-3 w-3 rounded-full bg-amber-300/90"></span>
                        <span class="h-3 w-3 rounded-full bg-emerald-300/90"></span>
                    </div>

                    <div class="relative z-10 mb-5 lg:hidden">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-500">Lifecycle Membership</p>
                        <h1 class="mt-1 text-3xl font-black text-slate-900">myWAP</h1>
                    </div>

                    <div class="relative z-10">
                        <h2 class="text-3xl font-black text-slate-900">Log Masuk</h2>
                        <p class="mt-1 text-sm text-slate-500">Akses papan pemuka mengikut peranan anda.</p>
                    </div>

                    <!-- Quick Login for Testing -->
                    <div class="relative z-10 mt-6 mb-2 rounded-2xl border border-yellow-500/30 bg-yellow-50 p-4">
                        <p class="mb-2 text-xs font-bold text-yellow-700 uppercase tracking-widest flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                            Log Masuk Pantas (Pengujian)
                        </p>
                        <div class="flex gap-2">
                            <select v-model="quickLoginRole" class="flex-1 rounded-xl border-yellow-200 bg-white text-sm text-slate-800 focus:border-yellow-400 focus:ring-yellow-400">
                                <option value="">Sila Pilih...</option>
                                <option value="superadmin">1. Superadmin (Semua)</option>
                                <option value="admin_pkpim">2. Admin (PKPIM)</option>
                                <option value="member">3. Ahli (ABIM)</option>
                            </select>
                            <button @click="quickLogin" :disabled="!quickLoginRole || form.processing" class="shrink-0 rounded-xl bg-yellow-500 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-yellow-400 transition-all disabled:opacity-50">
                                Masuk
                            </button>
                        </div>
                    </div>

                    <div v-if="status" class="relative z-10 mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700">
                        {{ status }}
                    </div>

                    <Transition name="flow-fade" mode="out-in">
                        <!-- ─── MAIN LOGIN FORM ──────────────────────────────── -->
                        <div v-if="flow === 'login'" :key="'login'" class="relative z-10 mt-6 space-y-4">
                            <form @submit.prevent="handleLogin" class="space-y-4">
                                <div class="relative">
                                    <input
                                        id="identifier"
                                        :value="form.email || form.ic_number"
                                        @input="updateIdentifier($event.target.value)"
                                        type="text"
                                        autocomplete="username"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-cyan-400 focus:bg-white focus:ring-0"
                                        placeholder="No Kad Pengenalan / Emel"
                                    >
                                    <label
                                        for="identifier"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-cyan-600"
                                    >
                                        No Kad Pengenalan / Emel
                                    </label>
                                </div>

                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        autocomplete="current-password"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-cyan-400 focus:bg-white focus:ring-0"
                                        placeholder="Kata Laluan"
                                    >
                                    <label
                                        for="password"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-cyan-600"
                                    >
                                        Kata Laluan
                                    </label>
                                </div>

                                <p v-if="loginError" class="text-xs text-red-500">{{ loginError }}</p>
                                <p v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</p>
                                <p v-if="form.errors.ic_number" class="text-xs text-red-500">{{ form.errors.ic_number }}</p>
                                <p v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</p>

                                <label class="inline-flex items-center gap-2 text-sm text-slate-500">
                                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-400">
                                    Ingat saya
                                </label>

                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="w-full rounded-3xl bg-slate-950 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-slate-900/25 transition hover:bg-slate-900 disabled:opacity-60"
                                >
                                    {{ form.processing ? 'Memproses...' : 'Log Masuk' }}
                                </button>
                            </form>

                            <div class="space-y-2 text-center text-sm">
                                <button
                                    type="button"
                                    class="block w-full font-semibold text-emerald-700 hover:text-emerald-600"
                                    @click="resetFlow(); flow = 'first-time'"
                                >
                                    Log Masuk Kali Pertama
                                </button>
                                <button
                                    type="button"
                                    class="block w-full font-semibold text-slate-600 hover:text-slate-500"
                                    @click="resetFlow(); flow = 'forgot-id'"
                                >
                                    Lupa Kata Laluan / ID Ahli
                                </button>
                            </div>

                            <p class="text-center text-sm text-slate-500 pt-2 border-t border-slate-200">
                                <Link :href="route('infaq.index')" class="font-semibold text-emerald-700 hover:text-emerald-600">Kempen Sumbangan</Link>
                                <span class="mx-2 text-slate-300">|</span>
                                <Link :href="route('articles.index')" class="font-semibold text-slate-600 hover:text-slate-500">Artikel</Link>
                            </p>

                            <p class="text-center text-sm text-slate-500">
                                Pengguna baru?
                                <Link :href="route('register')" class="font-semibold text-cyan-700 hover:text-cyan-600">
                                    Daftar akaun
                                </Link>
                            </p>
                        </div>

                        <!-- ─── FIRST-TIME SETUP ────────────────────────────── -->
                        <div v-else-if="flow === 'first-time'" :key="'first-time'" class="relative z-10 mt-6 space-y-4">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-3xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                                    @click="resetFlow()"
                                >
                                    Kembali
                                </button>
                                <h3 class="text-lg font-bold text-slate-900">Log Masuk Kali Pertama</h3>
                            </div>

                            <!-- Step 1: IC -->
                            <div v-if="ftStep === 'ic'" class="space-y-4">
                                <p class="text-sm text-slate-600">Masukkan No Kad Pengenalan / Passport untuk memulakan persediaan akaun.</p>
                                <div class="relative">
                                    <input
                                        id="ft_ic"
                                        v-model="ftIcNumber"
                                        type="text"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                        placeholder="No Kad Pengenalan / Passport"
                                    >
                                    <label
                                        for="ft_ic"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                    >
                                        No Kad Pengenalan / Passport
                                    </label>
                                </div>
                                <p v-if="ftError" class="text-xs text-red-500">{{ ftError }}</p>
                                <button
                                    type="button"
                                    :disabled="ftProcessing"
                                    class="w-full rounded-3xl bg-emerald-700 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-600 disabled:opacity-60"
                                    @click="ftLookup"
                                >
                                    {{ ftProcessing ? 'Menyemak...' : 'Seterusnya' }}
                                </button>
                            </div>

                            <!-- Step 2: Email -->
                            <div v-else-if="ftStep === 'email'" class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                                    <p class="font-semibold">Anda adalah ahli {{ ftOrg?.name }}</p>
                                    <img v-if="ftOrg?.logo_url" :src="ftOrg.logo_url" :alt="`Logo ${ftOrg.name}`" class="mt-2 h-12 w-auto rounded-lg border border-emerald-200 bg-white p-1">
                                </div>
                                <p class="text-sm text-slate-600">Daftarkan emel anda untuk menerima kod pengesahan.</p>
                                <div class="relative">
                                    <input
                                        id="ft_email"
                                        v-model="ftEmail"
                                        type="email"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                        placeholder="Emel"
                                    >
                                    <label
                                        for="ft_email"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                    >
                                        Emel
                                    </label>
                                </div>
                                <p v-if="ftError" class="text-xs text-red-500">{{ ftError }}</p>
                                <button
                                    type="button"
                                    :disabled="ftProcessing"
                                    class="w-full rounded-3xl bg-emerald-700 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-600 disabled:opacity-60"
                                    @click="ftSendOtp"
                                >
                                    {{ ftProcessing ? 'Menghantar...' : 'Hantar Kod' }}
                                </button>
                            </div>

                            <!-- Step 3: OTP -->
                            <div v-else class="space-y-4">
                                <p class="text-sm text-slate-600">Masukkan kod 6-digit yang dihantar ke <strong>{{ ftEmail }}</strong>.</p>
                                <div class="relative">
                                    <input
                                        id="ft_code"
                                        v-model="ftCode"
                                        type="text"
                                        inputmode="numeric"
                                        maxlength="6"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-center text-2xl font-bold tracking-[0.3em] text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                        placeholder="000000"
                                    >
                                    <label
                                        for="ft_code"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                    >
                                        Kod OTP
                                    </label>
                                </div>

                                <div v-if="ftIsFirstLogin" class="space-y-3 border-t border-slate-200 pt-4">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Tetapkan Kata Laluan</p>
                                    <div class="relative">
                                        <input
                                            id="ft_password"
                                            v-model="ftPassword"
                                            type="password"
                                            class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                            placeholder="Kata Laluan (min 8 aksara)"
                                        >
                                        <label
                                            for="ft_password"
                                            class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                        >
                                            Kata Laluan
                                        </label>
                                    </div>
                                    <div class="relative">
                                        <input
                                            id="ft_password_confirmation"
                                            v-model="ftPasswordConfirmation"
                                            type="password"
                                            class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                            placeholder="Sahkan Kata Laluan"
                                        >
                                        <label
                                            for="ft_password_confirmation"
                                            class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                        >
                                            Sahkan Kata Laluan
                                        </label>
                                    </div>
                                </div>

                                <p v-if="ftError" class="text-xs text-red-500">{{ ftError }}</p>
                                <button
                                    type="button"
                                    :disabled="ftProcessing || ftCode.length !== 6"
                                    class="w-full rounded-3xl bg-slate-950 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-slate-900/25 transition hover:bg-slate-900 disabled:opacity-60"
                                    @click="ftVerifyOtp"
                                >
                                    {{ ftProcessing ? 'Mengesahkan...' : 'Log Masuk' }}
                                </button>
                            </div>
                        </div>

                        <!-- ─── FORGOT ID ───────────────────────────────────── -->
                        <div v-else-if="flow === 'forgot-id'" :key="'forgot-id'" class="relative z-10 mt-6 space-y-4">
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-3xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100"
                                    @click="resetFlow()"
                                >
                                    Kembali
                                </button>
                                <h3 class="text-lg font-bold text-slate-900">Lupa Kata Laluan / ID Ahli</h3>
                            </div>

                            <!-- Step 1: IC -->
                            <div v-if="fiStep === 'ic'" class="space-y-4">
                                <p class="text-sm text-slate-600">Masukkan No Kad Pengenalan / Passport untuk mencari akaun anda.</p>
                                <div class="relative">
                                    <input
                                        id="fi_ic"
                                        v-model="fiIcNumber"
                                        type="text"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-amber-400 focus:bg-white focus:ring-0"
                                        placeholder="No Kad Pengenalan / Passport"
                                    >
                                    <label
                                        for="fi_ic"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-amber-600"
                                    >
                                        No Kad Pengenalan / Passport
                                    </label>
                                </div>
                                <p v-if="fiError" class="text-xs text-red-500">{{ fiError }}</p>
                                <button
                                    type="button"
                                    :disabled="fiProcessing"
                                    class="w-full rounded-3xl bg-amber-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-amber-900/20 transition hover:bg-amber-500 disabled:opacity-60"
                                    @click="fiLookup"
                                >
                                    {{ fiProcessing ? 'Menyemak...' : 'Seterusnya' }}
                                </button>
                            </div>

                            <!-- Step 2: Verify DOB -->
                            <div v-else-if="fiStep === 'verify'" class="space-y-4">
                                <p class="text-sm text-slate-600">Sahkan identiti anda dengan tarikh lahir.</p>
                                <div class="relative">
                                    <input
                                        id="fi_dob"
                                        v-model="fiDob"
                                        type="date"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-amber-400 focus:bg-white focus:ring-0"
                                    >
                                    <label
                                        for="fi_dob"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-amber-600"
                                    >
                                        Tarikh Lahir
                                    </label>
                                </div>
                                <p v-if="fiError" class="text-xs text-red-500">{{ fiError }}</p>
                                <button
                                    type="button"
                                    :disabled="fiProcessing"
                                    class="w-full rounded-3xl bg-amber-600 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-amber-900/20 transition hover:bg-amber-500 disabled:opacity-60"
                                    @click="fiVerify"
                                >
                                    {{ fiProcessing ? 'Mengesahkan...' : 'Sahkan' }}
                                </button>
                            </div>

                            <!-- Step 3: Result -->
                            <div v-else class="space-y-4">
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                    <p class="font-semibold">Akaun anda dijumpai!</p>

                                    <div v-if="fiMaskedEmail" class="mt-3">
                                        <p class="text-xs text-slate-500">Emel berdaftar:</p>
                                        <p class="text-lg font-bold text-slate-900">{{ fiMaskedEmail }}</p>
                                    </div>

                                    <div v-if="fiMemberNo" class="mt-2">
                                        <p class="text-xs text-slate-500">No Ahli:</p>
                                        <p class="text-lg font-bold text-slate-900">{{ fiMemberNo }}</p>
                                    </div>

                                    <div v-if="!fiMaskedEmail && !fiMemberNo" class="mt-2">
                                        <p class="text-sm text-slate-600">Akaun anda tiada emel dan no ahli berdaftar. Sila hubungi admin.</p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    :disabled="resetLinkSending"
                                    class="block w-full rounded-3xl bg-cyan-700 px-4 py-3 text-center text-base font-semibold text-white shadow-lg transition hover:bg-cyan-600 disabled:opacity-60"
                                    @click="sendResetLink"
                                >
                                    {{ resetLinkSending ? 'Menghantar...' : 'Hantar Reset Kata Laluan' }}
                                </button>

                                <button
                                    type="button"
                                    class="w-full rounded-3xl border border-slate-300 px-4 py-3 text-base font-semibold text-slate-700 transition hover:bg-slate-100"
                                    @click="resetFlow()"
                                >
                                    Kembali ke Log Masuk
                                </button>
                            </div>
                        </div>
                    </Transition>
                </section>
            </div>
        </div>
    </AuroraBackground>
</template>

<style scoped>
.flow-fade-enter-active,
.flow-fade-leave-active {
    transition: all 260ms ease;
}

.flow-fade-enter-from,
.flow-fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}
</style>

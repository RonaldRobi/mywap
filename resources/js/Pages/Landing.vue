<script setup>
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const state = ref('selection');
const ahliStep = ref(1);
const checkingIc = ref(false);
const memberCheckError = ref('');
const memberOrganization = ref(null);

const identifierError = computed(() => {
    if (state.value === 'admin_flow') {
        return form.errors.email;
    }

    if (state.value === 'ahli_flow') {
        return form.errors.ic_number;
    }

    return '';
});

const resetFlow = () => {
    form.reset('login_type', 'email', 'ic_number', 'password', 'remember');
    form.clearErrors();
    ahliStep.value = 1;
    checkingIc.value = false;
    memberCheckError.value = '';
    memberOrganization.value = null;
};

const switchToSelection = () => {
    state.value = 'selection';
    resetFlow();
};

const switchToAdmin = () => {
    resetFlow();
    state.value = 'admin_flow';
    form.login_type = 'admin';
};

const switchToAhli = () => {
    resetFlow();
    state.value = 'ahli_flow';
    form.login_type = 'member';
};

const checkMemberIc = async () => {
    form.clearErrors('ic_number');
    memberCheckError.value = '';

    if (!form.ic_number?.trim()) {
        form.setError('ic_number', 'No Kad Pengenalan / Passport diperlukan.');
        return;
    }

    checkingIc.value = true;

    try {
        const url = `${route('login.check-member')}?ic_number=${encodeURIComponent(form.ic_number)}`;
        const response = await fetch(url, {
            method: 'GET',
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        const payload = await response.json();

        if (!response.ok || !payload?.found) {
            memberCheckError.value = payload?.message || 'Ahli tidak dijumpai.';
            return;
        }

        memberOrganization.value = payload.organization;
        ahliStep.value = 2;
    } catch {
        memberCheckError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        checkingIc.value = false;
    }
};

const submit = () => {
    form.clearErrors();

    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const quickLoginRole = ref('');

const quickLogin = async () => {
    if (!quickLoginRole.value || form.processing) return;
    
    // Jangan guna form.reset() kerana ia akan kosongkan semua termasuk login_type
    form.clearErrors();
    form.password = 'password';
    form.remember = true;

    if (quickLoginRole.value === 'superadmin') {
        state.value = 'admin_flow';
        form.login_type = 'admin';
        form.email = 'superadmin@mywap.my';
    } else if (quickLoginRole.value === 'admin_pkpim') {
        state.value = 'admin_flow';
        form.login_type = 'admin';
        form.email = 'admin@mywap.my';
    } else if (quickLoginRole.value === 'member') {
        state.value = 'ahli_flow';
        form.login_type = 'member';
        form.ic_number = '980512101234';
        ahliStep.value = 2; // Lompat ke step password terus
    }
    
    // Beri sedikit masa untuk UI update state sebelum submit
    setTimeout(() => {
        submit();
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
                        <div :key="`${state}-${ahliStep}`" class="relative z-10 mt-6 space-y-4">
                            <div v-if="state === 'selection'" class="space-y-3">
                                <button
                                    type="button"
                                    class="w-full rounded-3xl bg-slate-900 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-slate-900/25 transition hover:bg-slate-800"
                                    @click="switchToAdmin"
                                >
                                    Log Masuk Admin
                                </button>

                                <button
                                    type="button"
                                    class="w-full rounded-3xl bg-emerald-700 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-600"
                                    @click="switchToAhli"
                                >
                                    Log Masuk Ahli
                                </button>
                            </div>

                            <form v-else-if="state === 'admin_flow'" class="space-y-4" @submit.prevent="submit">
                                <div class="relative">
                                    <input
                                        id="email"
                                        v-model="form.email"
                                        type="email"
                                        autocomplete="username"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-cyan-400 focus:bg-white focus:ring-0"
                                        placeholder="Email"
                                        required
                                    >
                                    <label
                                        for="email"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-cyan-600"
                                    >
                                        Email
                                    </label>
                                    <p v-if="identifierError" class="mt-1 text-xs text-red-500">{{ identifierError }}</p>
                                </div>

                                <div class="relative">
                                    <input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        autocomplete="current-password"
                                        class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-cyan-400 focus:bg-white focus:ring-0"
                                        placeholder="Password"
                                        required
                                    >
                                    <label
                                        for="password"
                                        class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-cyan-600"
                                    >
                                        Password
                                    </label>
                                    <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                                </div>

                                <label class="inline-flex items-center gap-2 text-sm text-slate-500">
                                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-400">
                                    Ingat saya
                                </label>

                                <div class="flex gap-2">
                                    <button
                                        type="button"
                                        class="rounded-3xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                        @click="switchToSelection"
                                    >
                                        Kembali
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="flex-1 rounded-3xl bg-slate-950 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-slate-900/25 transition hover:bg-slate-900 disabled:opacity-60"
                                    >
                                        {{ form.processing ? 'Memproses...' : 'Log Masuk' }}
                                    </button>
                                </div>
                            </form>

                            <div v-else class="space-y-4">
                                <div v-if="ahliStep === 1" class="space-y-4">
                                    <div class="relative">
                                        <input
                                            id="ic_number"
                                            v-model="form.ic_number"
                                            type="text"
                                            autocomplete="username"
                                            class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                            placeholder="No Kad Pengenalan / Passport"
                                            required
                                        >
                                        <label
                                            for="ic_number"
                                            class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                        >
                                            No Kad Pengenalan / Passport
                                        </label>
                                        <p v-if="identifierError" class="mt-1 text-xs text-red-500">{{ identifierError }}</p>
                                        <p v-if="memberCheckError" class="mt-1 text-xs text-red-500">{{ memberCheckError }}</p>
                                    </div>

                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded-3xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                            @click="switchToSelection"
                                        >
                                            Kembali
                                        </button>
                                        <button
                                            type="button"
                                            :disabled="checkingIc"
                                            class="flex-1 rounded-3xl bg-emerald-700 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-emerald-900/20 transition hover:bg-emerald-600 disabled:opacity-60"
                                            @click="checkMemberIc"
                                        >
                                            {{ checkingIc ? 'Menyemak...' : 'Seterusnya' }}
                                        </button>
                                    </div>
                                </div>

                                <form v-else class="space-y-4" @submit.prevent="submit">
                                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
                                        <p class="font-semibold">Anda adalah ahli {{ memberOrganization?.name }}</p>
                                        <img
                                            v-if="memberOrganization?.logo_url"
                                            :src="memberOrganization.logo_url"
                                            :alt="`Logo ${memberOrganization.name}`"
                                            class="mt-3 h-16 w-auto rounded-lg border border-emerald-200 bg-white p-2"
                                        >
                                    </div>

                                    <div class="relative">
                                        <input
                                            id="password_member"
                                            v-model="form.password"
                                            type="password"
                                            autocomplete="current-password"
                                            class="peer w-full rounded-3xl border border-slate-200/90 bg-slate-100/85 px-5 pt-5 pb-2.5 text-base text-slate-900 placeholder-transparent focus:border-emerald-400 focus:bg-white focus:ring-0"
                                            placeholder="Password"
                                            required
                                        >
                                        <label
                                            for="password_member"
                                            class="absolute left-5 top-2 text-[11px] font-semibold text-slate-400 transition-all peer-placeholder-shown:top-4 peer-placeholder-shown:text-sm peer-placeholder-shown:font-medium peer-focus:top-2 peer-focus:text-[11px] peer-focus:font-semibold peer-focus:text-emerald-600"
                                        >
                                            Password
                                        </label>
                                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                                        <p v-if="identifierError" class="mt-1 text-xs text-red-500">{{ identifierError }}</p>
                                    </div>

                                    <label class="inline-flex items-center gap-2 text-sm text-slate-500">
                                        <input v-model="form.remember" type="checkbox" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-400">
                                        Ingat saya
                                    </label>

                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="rounded-3xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                            @click="ahliStep = 1"
                                        >
                                            Kembali
                                        </button>
                                        <button
                                            type="submit"
                                            :disabled="form.processing"
                                            class="flex-1 rounded-3xl bg-slate-950 px-4 py-3.5 text-lg font-semibold text-white shadow-lg shadow-slate-900/25 transition hover:bg-slate-900 disabled:opacity-60"
                                        >
                                            {{ form.processing ? 'Memproses...' : 'Log Masuk' }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <p class="text-center text-sm text-slate-500">
                                Pengguna baru?
                                <Link :href="route('register')" class="font-semibold text-cyan-700 hover:text-cyan-600">
                                    Daftar akaun
                                </Link>
                            </p>
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

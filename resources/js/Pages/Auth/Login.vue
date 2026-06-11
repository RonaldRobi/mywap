<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import AccernityCard from '@/Components/ui/AccernityCard.vue';
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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

const flow = ref(null);
const step = ref('role');
const memberCheckProcessing = ref(false);
const memberCheckError = ref('');
const memberOrganization = ref(null);

const currentIdentifierError = computed(() => {
    if (flow.value === 'admin') {
        return form.errors.email;
    }

    if (flow.value === 'member') {
        return form.errors.ic_number;
    }

    return '';
});

const resetFlowErrors = () => {
    form.clearErrors();
    memberCheckError.value = '';
};

const goToRoleSelection = () => {
    flow.value = null;
    step.value = 'role';
    memberOrganization.value = null;
    form.reset('email', 'ic_number', 'password', 'remember', 'login_type');
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
            memberCheckError.value = payload?.message || 'Ahli tidak dijumpai.';
            return;
        }

        memberOrganization.value = payload.organization;
        step.value = 'member-password';
    } catch {
        memberCheckError.value = 'Ralat rangkaian. Sila cuba sebentar lagi.';
    } finally {
        memberCheckProcessing.value = false;
    }
};

const submit = () => {
    form.clearErrors();

    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

const quickLoginRole = ref('');
const quickLogin = () => {
    if (!quickLoginRole.value) return;
    
    form.reset();
    form.clearErrors();
    form.password = 'password';

    if (quickLoginRole.value === 'superadmin') {
        form.login_type = 'admin';
        form.email = 'superadmin@mywap.my';
    } else if (quickLoginRole.value === 'admin_pkpim') {
        form.login_type = 'admin';
        form.email = 'admin@mywap.my';
    } else if (quickLoginRole.value === 'member') {
        form.login_type = 'member';
        form.ic_number = '980512101234';
    }
    
    submit();
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

                    <!-- Quick Login for Testing -->
                    <div class="mb-6 rounded-2xl border border-yellow-500/30 bg-yellow-500/10 p-4 backdrop-blur-sm">
                        <p class="mb-2 text-xs font-bold text-yellow-200 uppercase tracking-widest flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
                            Log Masuk Pantas (Pengujian)
                        </p>
                        <div class="flex gap-2">
                            <select v-model="quickLoginRole" class="flex-1 rounded-xl border-white/10 bg-white/5 text-sm text-white focus:border-yellow-400 focus:ring-yellow-400 [&>option]:text-gray-900">
                                <option value="">Sila Pilih Peranan...</option>
                                <option value="superadmin">1. Superadmin (Urus Semua)</option>
                                <option value="admin_pkpim">2. Admin Organisasi (Sistem PKPIM)</option>
                                <option value="member">3. Ahli Biasa (Sistem ABIM)</option>
                            </select>
                            <button @click="quickLogin" :disabled="!quickLoginRole || form.processing" class="rounded-xl bg-yellow-500 px-4 py-2 text-sm font-bold text-gray-900 shadow-sm hover:bg-yellow-400 transition-all disabled:opacity-50">
                                Masuk Terus
                            </button>
                        </div>
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

                            <form v-else @submit.prevent="submit" class="space-y-4">
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
</style>

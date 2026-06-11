<script setup>
import AccernityCard from '@/Components/ui/AccernityCard.vue';
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    email: '',
    ic_number: '',
    phone: '',
    dob: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const inferredOrganization = computed(() => {
    if (!form.dob) return null;

    const dob = new Date(form.dob);
    if (Number.isNaN(dob.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const hasBirthdayPassed =
        today.getMonth() > dob.getMonth()
        || (today.getMonth() === dob.getMonth() && today.getDate() >= dob.getDate());

    if (!hasBirthdayPassed) {
        age -= 1;
    }

    return props.organizations.find((organization) => {
        const minAge = Number(organization.min_age ?? 0);
        const maxAge = organization.max_age === null ? null : Number(organization.max_age);

        if (Number.isNaN(minAge)) return false;
        if (maxAge !== null && Number.isNaN(maxAge)) return false;

        return age >= minAge && (maxAge === null || age <= maxAge);
    }) ?? null;
});
</script>

<template>
    <AuroraBackground>
        <Head title="Register" />

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-7xl flex-col px-3 py-5 sm:px-4 sm:py-6 md:px-8 md:py-10 lg:flex-row lg:items-center lg:gap-8">
            <section class="hidden flex-1 lg:block">
                <div class="max-w-xl">
                    <p class="inline-flex rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-emerald-200 backdrop-blur-sm">
                        myWAP
                    </p>
                    <h1 class="mt-5 text-4xl font-black leading-tight text-white xl:text-5xl">
                        Daftar Akaun Baru,
                        <span class="bg-gradient-to-r from-emerald-300 to-cyan-200 bg-clip-text text-transparent">siap ikut organisasi umur.</span>
                    </h1>
                    <p class="mt-4 text-sm leading-relaxed text-slate-300">
                        Isi maklumat asas. Sistem akan tetapkan PKPIM, ABIM atau WADAH secara automatik berdasarkan tarikh lahir.
                    </p>
                </div>
            </section>

            <section class="w-full lg:w-[500px]">
                <AccernityCard>
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-100">Create Account</p>
                        <h2 class="mt-1 text-2xl font-black text-white sm:text-3xl">Register to join</h2>
                        <p class="mt-1 text-sm text-slate-300">Lengkapkan butiran di bawah untuk mula gunakan platform.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="name" value="Nama Penuh" class="!text-slate-200" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.name"
                                autocomplete="name"
                                autofocus
                                placeholder="Contoh: Ahmad Firdaus"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" class="!text-slate-200" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.email"
                                autocomplete="username"
                                placeholder="nama@domain.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="ic_number" value="No IC / Passport" class="!text-slate-200" />
                                <TextInput
                                    id="ic_number"
                                    type="text"
                                    class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                    v-model="form.ic_number"
                                    placeholder="Contoh: 980512101234 / A1234567"
                                />
                                <InputError class="mt-2" :message="form.errors.ic_number" />
                            </div>

                            <div>
                                <InputLabel for="phone" value="No. Telefon" class="!text-slate-200" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                    v-model="form.phone"
                                    autocomplete="tel"
                                    placeholder="Contoh: 0123456789"
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div>
                                <InputLabel for="dob" value="Tarikh Lahir" class="!text-slate-200" />
                                <TextInput
                                    id="dob"
                                    type="date"
                                    class="mt-1 block w-full border-white/15 bg-white/10 text-white focus:border-cyan-300 focus:ring-cyan-300"
                                    v-model="form.dob"
                                    autocomplete="bday"
                                />
                                <InputError class="mt-2" :message="form.errors.dob" />
                                <p v-if="inferredOrganization" class="mt-2 text-xs font-medium text-emerald-200">
                                    Organisasi automatik: {{ inferredOrganization.name }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" class="!text-slate-200" />
                            <TextInput
                                id="password"
                                type="password"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.password"
                                autocomplete="new-password"
                                placeholder="••••••••"
                            />
                            <InputError class="mt-2" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password" class="!text-slate-200" />
                            <TextInput
                                id="password_confirmation"
                                type="password"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.password_confirmation"
                                autocomplete="new-password"
                                placeholder="••••••••"
                            />
                            <InputError class="mt-2" :message="form.errors.password_confirmation" />
                        </div>

                        <PrimaryButton
                            class="w-full justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-black/35 hover:bg-slate-900"
                            :class="{ 'opacity-25': form.processing }"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Registering...' : 'Register' }}
                        </PrimaryButton>

                        <p class="text-center text-sm text-slate-300">
                            Sudah ada akaun?
                            <Link :href="route('login')" class="font-semibold text-cyan-200 hover:text-cyan-100">
                                Log masuk
                            </Link>
                        </p>
                    </form>
                </AccernityCard>
            </section>
        </div>
    </AuroraBackground>
</template>

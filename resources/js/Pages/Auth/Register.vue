<script setup>
import AccernityCard from '@/Components/ui/AccernityCard.vue';
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
    branches: {
        type: Object,
        default: () => ({}),
    },
    referrer: {
        type: Object,
        default: null,
    },
});

const form = useForm({
    name: '',
    email: '',
    ic_number: '',
    phone: '',
    dob: '',
    branch_id: '',
    referred_by_user_id: props.referrer?.id ?? '',
});

const referredByName = ref(props.referrer?.name ?? '');
const referredByNo = ref(props.referrer?.member_no ?? '');

function parseDobFromIc(ic) {
    if (!ic) return '';
    const digits = ic.replace(/[^0-9]/g, '');
    if (digits.length < 6) return '';
    const yy = parseInt(digits.substring(0, 2));
    const mm = parseInt(digits.substring(2, 4));
    const dd = parseInt(digits.substring(4, 6));
    if (mm < 1 || mm > 12 || dd < 1 || dd > 31) return '';
    const yyyy = yy > 25 ? 1900 + yy : 2000 + yy;
    return `${yyyy}-${String(mm).padStart(2, '0')}-${String(dd).padStart(2, '0')}`;
}

function guessGenderFromIc(ic) {
    if (!ic) return '';
    const digits = ic.replace(/[^0-9]/g, '');
    if (digits.length < 12) return '';
    return parseInt(digits.slice(-1)) % 2 === 1 ? 'Lelaki' : 'Perempuan';
}

const inferredDob = computed(() => form.ic_number ? parseDobFromIc(form.ic_number) : '');
const inferredGender = computed(() => form.ic_number ? guessGenderFromIc(form.ic_number) : '');

const effectiveDob = computed(() => form.dob || inferredDob.value);

const inferredAge = computed(() => {
    const dob = effectiveDob.value;
    if (!dob) return null;

    const birthDate = new Date(dob);
    if (Number.isNaN(birthDate.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const hasBirthdayPassed =
        today.getMonth() > birthDate.getMonth()
        || (today.getMonth() === birthDate.getMonth() && today.getDate() >= birthDate.getDate());

    if (!hasBirthdayPassed) age -= 1;

    return age;
});

const inferredOrganization = computed(() => {
    const age = inferredAge.value;
    if (age === null) return null;

    return props.organizations.find((organization) => {
        const minAge = Number(organization.min_age ?? 0);
        const maxAge = organization.max_age === null ? null : Number(organization.max_age);

        if (Number.isNaN(minAge)) return false;
        if (maxAge !== null && Number.isNaN(maxAge)) return false;

        return age >= minAge && (maxAge === null || age <= maxAge);
    }) ?? null;
});

const filteredBranches = computed(() => {
    if (!inferredOrganization.value) return [];
    const orgBranches = props.branches[inferredOrganization.value.id] || [];
    return orgBranches;
});

watch(() => form.ic_number, (val) => {
    if (val && !form.dob) {
        form.dob = parseDobFromIc(val);
    }
});

const icLength = computed(() => {
    const digits = (form.ic_number || '').replace(/[^0-9]/g, '');
    return digits.length;
});

const icValid = computed(() => icLength.value === 12 || icLength.value > 12);

const submit = () => {
    form.dob = effectiveDob.value;
    form.post(route('register'));
};
</script>

<template>
    <AuroraBackground>
        <Head title="Daftar Akaun" />

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
                        Isi maklumat asas. Sistem akan tetapkan PKPIM, ABIM atau WADAH secara automatik berdasarkan tarikh lahir dari No IC.
                    </p>
                </div>
            </section>

            <section class="w-full lg:w-[500px]">
                <AccernityCard>
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-100">Langkah 1 dari 2</p>
                        <h2 class="mt-1 text-2xl font-black text-white sm:text-3xl">Daftar Akaun</h2>
                        <p class="mt-1 text-sm text-slate-300">Lengkapkan butiran di bawah. Kata laluan akan ditetapkan semasa log masuk kali pertama.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="ic_number" value="No Kad Pengenalan / Passport" class="!text-slate-200" />
                            <TextInput
                                id="ic_number"
                                type="text"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.ic_number"
                                autofocus
                                placeholder="Contoh: 980512101234 / A1234567"
                            />
                            <InputError class="mt-2" :message="form.errors.ic_number" />
                            <p v-if="icLength > 0 && icLength < 12" class="mt-1 text-xs text-amber-300">
                                Format IC Malaysia: 12 digit (tanpa sengkang). Passport: 6+ aksara.
                            </p>
                            <p v-if="icLength >= 12 && inferredDob" class="mt-1 text-xs text-emerald-200">
                                Tarikh lahir: {{ inferredDob }} &middot; Jantina: {{ inferredGender }}
                            </p>
                        </div>

                        <div>
                            <InputLabel for="name" value="Nama Penuh" class="!text-slate-200" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full border-white/15 bg-white/10 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                v-model="form.name"
                                autocomplete="name"
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
                                autocomplete="email"
                                placeholder="nama@domain.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                            <p class="mt-1 text-xs text-slate-400">Emel ini akan digunakan untuk menghantar kod OTP semasa log masuk kali pertama.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                                <p v-if="inferredDob" class="mt-1 text-xs text-slate-400">Auto dari No IC. Boleh ubah jika salah.</p>
                            </div>
                        </div>

                        <div v-if="inferredOrganization" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-200">Organisasi Dikesan</p>
                                    <p class="mt-1 text-lg font-black text-white">{{ inferredOrganization.name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-200">Yuran Tahunan</p>
                                    <p class="mt-1 text-lg font-black text-white">RM {{ Number(inferredOrganization.fee_amount).toFixed(2) }}</p>
                                </div>
                            </div>
                            <p v-if="inferredAge !== null" class="mt-2 text-xs text-emerald-100">Umur dikesan: {{ inferredAge }} tahun</p>
                        </div>

                        <div v-if="filteredBranches.length > 0 && inferredOrganization">
                            <InputLabel for="branch_id" value="Cawangan" class="!text-slate-200" />
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                class="mt-1 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-white focus:border-cyan-300 focus:ring-cyan-300"
                            >
                                <option value="">Pilih Cawangan</option>
                                <option v-for="branch in filteredBranches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}{{ branch.state ? ` - ${branch.state}` : '' }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.branch_id" />
                        </div>

                        <div v-if="inferredOrganization && filteredBranches.length === 0" class="rounded-xl border border-white/10 bg-white/5 p-3">
                            <p class="text-xs text-slate-400">Cawangan: <span class="font-semibold text-slate-300">Tidak Berkenaan</span></p>
                        </div>

                        <div v-if="inferredOrganization">
                            <InputLabel for="referred_name" value="Dirujuk Oleh (Pilihan)" class="!text-slate-200" />
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <input
                                    id="referred_name"
                                    v-model="referredByName"
                                    type="text"
                                    class="mt-1 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                    placeholder="Nama Perujuk"
                                >
                                <input
                                    v-model="referredByNo"
                                    type="text"
                                    class="mt-1 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                                    placeholder="No Ahli Perujuk"
                                >
                            </div>
                            <p v-if="props.referrer" class="mt-1 text-xs text-cyan-200">Dirujuk oleh: {{ props.referrer.name }} ({{ props.referrer.member_no }})</p>
                        </div>

                        <button
                            class="w-full justify-center rounded-xl bg-slate-950 px-4 py-3 text-base font-semibold text-white shadow-lg shadow-black/35 hover:bg-slate-900 disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Mendaftar...' : 'Daftar & Teruskan ke Bayaran' }}
                        </button>

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

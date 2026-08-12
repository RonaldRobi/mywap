<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import MemberSearch from '@/Components/MemberSearch.vue';
import MovementBranding from '@/Components/MovementBranding.vue';
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

const selectedReferrer = ref(props.referrer ? { ...props.referrer } : null);
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

const organizationLogos = computed(() =>
    props.organizations.filter((organization) => organization.logo_path)
);

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

watch(selectedReferrer, (val) => {
    if (val) {
        referredByName.value = val.name;
        referredByNo.value = val.member_no;
        form.referred_by_user_id = val.id;
    } else {
        referredByName.value = '';
        referredByNo.value = '';
        form.referred_by_user_id = '';
    }
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
    <div class="auth-canvas min-h-screen">
        <Head title="Daftar Akaun" />

        <div class="mx-auto flex min-h-screen w-full max-w-6xl items-start justify-center px-3 py-4 sm:px-4 sm:py-10 md:px-8 lg:items-center">
            <div class="grid w-full grid-cols-1 gap-3 sm:gap-6 lg:grid-cols-[1.05fr_0.95fr] lg:items-stretch">
                <section class="order-1 rounded-2xl border border-[#D5E3D8] bg-white/85 p-3 shadow-[0_6px_24px_rgba(7,21,37,0.05)] backdrop-blur-sm sm:p-6 lg:hidden">
                    <MovementBranding light part="header" />
                </section>

                <section class="order-3 hidden rounded-2xl border border-[#D5E3D8] bg-white/70 p-5 sm:p-6 lg:order-1 lg:block lg:bg-white/75 lg:p-8">
                    <MovementBranding light />
                </section>

                <section class="order-4 rounded-2xl border border-[#D5E3D8] bg-white/85 p-5 shadow-[0_6px_24px_rgba(7,21,37,0.05)] backdrop-blur-sm sm:p-6 lg:hidden">
                    <MovementBranding light part="details" />
                </section>

                <section class="order-2 rounded-2xl border border-[#D5E3D8] bg-white p-5 shadow-[0_18px_50px_rgba(7,21,37,0.10)] sm:p-7 lg:order-2 lg:p-8">
                    <div class="mb-6">
                        <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#2F6B32]">Langkah 1 dari 2</p>
                        <h2 class="mt-2 text-2xl font-black text-[#071525] sm:text-3xl">Daftar Akaun</h2>
                        <p class="mt-1 text-sm text-[#4A5A50]">Lengkapkan butiran di bawah. Kata laluan akan ditetapkan semasa log masuk kali pertama.</p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="ic_number" value="No Kad Pengenalan / Passport" class="!text-[#123D2A]" />
                            <TextInput
                                id="ic_number"
                                type="text"
                                class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                v-model="form.ic_number"
                                autofocus
                                placeholder="Contoh: 980512101234 / A1234567"
                            />
                            <InputError class="mt-2" :message="form.errors.ic_number" />
                            <p v-if="icLength > 0 && icLength < 12" class="mt-1 text-xs text-[#8A6418]">
                                Format IC Malaysia: 12 digit (tanpa sengkang). Passport: 6+ aksara.
                            </p>
                            <p v-if="icLength >= 12 && inferredDob" class="mt-1 text-xs text-[#2F6B32]">
                                Tarikh lahir: {{ inferredDob }} &middot; Jantina: {{ inferredGender }}
                            </p>
                        </div>

                        <div>
                            <InputLabel for="name" value="Nama Penuh" class="!text-[#123D2A]" />
                            <TextInput
                                id="name"
                                type="text"
                                class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                v-model="form.name"
                                autocomplete="name"
                                placeholder="Contoh: Ahmad Firdaus"
                            />
                            <InputError class="mt-2" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Email" class="!text-[#123D2A]" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                v-model="form.email"
                                autocomplete="email"
                                placeholder="nama@domain.com"
                            />
                            <InputError class="mt-2" :message="form.errors.email" />
                            <p class="mt-1 text-xs text-[#4A5A50]">Emel ini akan digunakan untuk menghantar kod OTP semasa log masuk kali pertama.</p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel for="phone" value="No. Telefon" class="!text-[#123D2A]" />
                                <TextInput
                                    id="phone"
                                    type="text"
                                    class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                    v-model="form.phone"
                                    autocomplete="tel"
                                    placeholder="Contoh: 0123456789"
                                />
                                <InputError class="mt-2" :message="form.errors.phone" />
                            </div>

                            <div>
                                <InputLabel for="dob" value="Tarikh Lahir" class="!text-[#123D2A]" />
                                <TextInput
                                    id="dob"
                                    type="date"
                                    class="mt-1 block w-full focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                                    v-model="form.dob"
                                    autocomplete="bday"
                                />
                                <InputError class="mt-2" :message="form.errors.dob" />
                                <p v-if="inferredDob" class="mt-1 text-xs text-[#4A5A50]">Auto dari No IC. Boleh ubah jika salah.</p>
                            </div>
                        </div>

                        <div v-if="inferredOrganization" class="rounded-2xl border border-[#6FBF8A] bg-[#EAF1EA] p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#2F6B32]">Organisasi Dikesan</p>
                                    <p class="mt-1 text-lg font-black text-[#071525]">{{ inferredOrganization.name }}</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-[#2F6B32]">Yuran Tahunan</p>
                                    <p class="mt-1 text-lg font-black text-[#071525]">RM {{ Number(inferredOrganization.fee_amount).toFixed(2) }}</p>
                                </div>
                            </div>
                            <p v-if="inferredAge !== null" class="mt-2 text-xs text-[#123D2A]">Umur dikesan: {{ inferredAge }} tahun</p>
                        </div>

                        <div v-if="filteredBranches.length > 0 && inferredOrganization">
                            <InputLabel for="branch_id" value="Cawangan" class="!text-[#123D2A]" />
                            <select
                                id="branch_id"
                                v-model="form.branch_id"
                                class="mt-1 block w-full rounded-xl border border-[#C8D8CB] bg-white px-4 py-3 text-[#071525] focus:border-[#2F6B32] focus:ring-[#6FBF8A]"
                            >
                                <option value="">Pilih Cawangan</option>
                                <option v-for="branch in filteredBranches" :key="branch.id" :value="branch.id">
                                    {{ branch.name }}{{ branch.state ? ` - ${branch.state}` : '' }}
                                </option>
                            </select>
                            <InputError class="mt-2" :message="form.errors.branch_id" />
                        </div>

                        <div v-if="inferredOrganization && filteredBranches.length === 0" class="rounded-xl border border-[#D5E3D8] bg-[#F4F6F1] p-3">
                            <p class="text-xs text-[#4A5A50]">Cawangan: <span class="font-semibold text-[#123D2A]">Tidak Berkenaan</span></p>
                        </div>

                        <div v-if="inferredOrganization">
                            <InputLabel for="member_search" value="Dirujuk Oleh (Pilihan)" class="!text-[#123D2A]" />
                            <MemberSearch
                                id="member_search"
                                v-model="selectedReferrer"
                                placeholder="Cari nama atau no ahli..."
                            />
                            <p v-if="selectedReferrer" class="mt-1 text-xs text-[#2F6B32]">{{ selectedReferrer.name }} ({{ selectedReferrer.member_no }})</p>
                        </div>

                        <button
                            class="w-full justify-center rounded-xl bg-[#2F6B32] px-4 py-3 text-base font-semibold text-[#F4F6F1] transition hover:bg-[#123D2A] disabled:opacity-50"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'Mendaftar...' : 'Daftar & Teruskan ke Bayaran' }}
                        </button>

                        <p class="text-center text-sm text-[#4A5A50]">
                            Sudah ada akaun?
                            <Link :href="route('login')" class="font-semibold text-[#2F6B32] hover:text-[#123D2A]">
                                Log masuk
                            </Link>
                        </p>
                    </form>
                </section>
            </div>
        </div>
    </div>
</template>

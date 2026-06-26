<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    orgPositions: {
        type: Array,
        default: () => [],
    },
    canEditIcNumber: {
        type: Boolean,
        default: false,
    },
    pendingBranchRequest: {
        type: Object,
        default: null,
    },
});


const user = usePage().props.auth.user;
const isSuperadmin = computed(() => (user?.roles ?? []).includes('Superadmin'));
const photoError = ref('');

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

const pendingBranchRequest = computed(() => user.pending_branch_request ?? null);

const form = useForm({
    name: user.name,
    email: user.email,
    ic_number: user.ic_number ?? '',
    phone: user.phone ?? '',
    dob: user.dob ?? '',
    gender: user.gender ?? '',
    marital_status: user.marital_status ?? '',
    education_level: user.education_level ?? '',
    current_profession: user.current_profession ?? '',
    industry: user.industry ?? '',
    branch_id: user.branch_id ?? '',
    locality: user.locality ?? '',
    expertise: user.expertise ?? '',
    linkedin_url: user.linkedin_url ?? '',
    profile_photo: null,
    is_public_in_directory: user.is_public_in_directory ?? true,
    address_1: user.address_1 ?? '',
    address_2: user.address_2 ?? '',
    postcode: user.postcode ?? '',
    city: user.city ?? '',
    state: user.state ?? '',
    emergency_contact_name: user.emergency_contact_name ?? '',
    emergency_contact_phone: user.emergency_contact_phone ?? '',
    position: user.position ?? '',
    topics: user.topics ?? '',
});

function guessGenderFromIc(ic) {
    if (!ic) return '';
    const digits = ic.replace(/[^0-9]/g, '');
    if (digits.length < 12) return '';
    return parseInt(digits.slice(-1)) % 2 === 1 ? 'lelaki' : 'perempuan';
}

watch(() => form.ic_number, (val) => {
    if (val) {
        if (!form.dob) form.dob = parseDobFromIc(val);
        if (!form.gender) form.gender = guessGenderFromIc(val);
    }
});

watch(() => form.postcode, async (val) => {
    if (!val || val.length !== 5) return;
    try {
        const res = await axios.get(route('postcode.lookup'), { params: { postcode: val } });
        if (res.data.found) {
            if (!form.city) form.city = res.data.city || '';
            if (!form.state) form.state = res.data.state || '';
        }
    } catch {}
});

function onProfilePhotoSelected(event) {
    const file = event.target.files[0];
    if (!file) return;

    photoError.value = '';
    form.errors.profile_photo = '';

    if (file.size > 2 * 1024 * 1024) {
        photoError.value = 'Saiz gambar mesti kurang daripada 2MB.';
        event.target.value = '';
        return;
    }

    form.profile_photo = file;
}

function submitProfile() {
    form
        .transform((data) => ({
            ...data,
            _method: 'patch',
        }))
        .post(route('profile.update'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('profile_photo');
            },
        });
}
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-black text-gray-900">
                Maklumat Profil
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Kemas kini maklumat akaun, latar belakang, dan tetapan keterlihatan direktori.
            </p>
        </header>

        <form
            @submit.prevent="submitProfile"
            class="mt-6 space-y-6"
        >
            <div class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Akaun</p>
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="name" value="Nama" />
                        <TextInput
                            id="name"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.name"
                            required
                            autofocus
                            autocomplete="name"
                        />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div>
                        <InputLabel for="email" value="Email" />
                        <TextInput
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div>
                        <InputLabel for="ic_number" value="No IC / Passport" />
                        <TextInput
                            id="ic_number"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.ic_number"
                            :readonly="!canEditIcNumber"
                        />
                        <p v-if="!canEditIcNumber" class="mt-1 text-xs text-gray-400">
                            Untuk keselamatan, kemas kini No IC/Passport perlu dibuat oleh admin.
                        </p>
                        <InputError class="mt-2" :message="form.errors.ic_number" />
                    </div>

                    <div>
                        <InputLabel for="phone" value="Telefon" />
                        <TextInput id="phone" type="text" class="mt-1 block w-full" v-model="form.phone" autocomplete="tel" />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div>
                        <InputLabel for="dob" value="Tarikh Lahir" />
                        <TextInput id="dob" type="date" class="mt-1 block w-full" v-model="form.dob" />
                        <p class="mt-1 text-xs text-gray-400">Auto-fill dari IC</p>
                        <InputError class="mt-2" :message="form.errors.dob" />
                    </div>

                    <div>
                        <InputLabel for="gender" value="Jantina" />
                        <select id="gender" v-model="form.gender" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Pilih</option>
                            <option value="lelaki">Lelaki</option>
                            <option value="perempuan">Perempuan</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.gender" />
                    </div>

                    <div>
                        <InputLabel for="marital_status" value="Status Perkahwinan" />
                        <select id="marital_status" v-model="form.marital_status" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Pilih</option>
                            <option value="bujang">Bujang</option>
                            <option value="berkahwin">Berkahwin</option>
                            <option value="bercerai">Bercerai</option>
                            <option value="duda/janda">Duda / Janda</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.marital_status" />
                    </div>
                </div>
            </div>

            <div v-if="!isSuperadmin" class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Alamat</p>
                <div class="mt-3 space-y-3">
                    <TextInput type="text" class="mt-1 block w-full" v-model="form.address_1" placeholder="Alamat baris 1" />
                    <TextInput type="text" class="mt-1 block w-full" v-model="form.address_2" placeholder="Alamat baris 2 (optional)" />
                    <div class="grid grid-cols-3 gap-3">
                        <TextInput type="text" maxlength="5" class="mt-1 block w-full" v-model="form.postcode" placeholder="Poskod" />
                        <TextInput type="text" class="mt-1 block w-full" v-model="form.city" placeholder="Bandar" />
                        <TextInput type="text" class="mt-1 block w-full" v-model="form.state" placeholder="Negeri" />
                    </div>
                </div>
            </div>

            <div v-if="!isSuperadmin" class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Kerjaya & Kepakaran</p>
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="education_level" value="Tahap Pendidikan" />
                        <TextInput id="education_level" type="text" class="mt-1 block w-full" v-model="form.education_level" />
                        <InputError class="mt-2" :message="form.errors.education_level" />
                    </div>

                    <div>
                        <InputLabel for="current_profession" value="Profesion Semasa" />
                        <TextInput id="current_profession" type="text" class="mt-1 block w-full" v-model="form.current_profession" />
                        <InputError class="mt-2" :message="form.errors.current_profession" />
                    </div>

                    <div>
                        <InputLabel for="industry" value="Industri" />
                        <TextInput id="industry" type="text" class="mt-1 block w-full" v-model="form.industry" />
                        <InputError class="mt-2" :message="form.errors.industry" />
                    </div>

                    <div>
                        <InputLabel for="expertise" value="Kepakaran (dipisah dengan koma)" />
                        <TextInput id="expertise" type="text" class="mt-1 block w-full" v-model="form.expertise" />
                        <InputError class="mt-2" :message="form.errors.expertise" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="linkedin_url" value="LinkedIn URL" />
                        <TextInput id="linkedin_url" type="url" class="mt-1 block w-full" v-model="form.linkedin_url" />
                        <InputError class="mt-2" :message="form.errors.linkedin_url" />
                    </div>

                    <div>
                        <InputLabel for="position" value="Jawatan dalam Organisasi" />
                        <select id="position" v-model="form.position" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                            <option value="">Pilih jawatan</option>
                            <option v-for="pos in orgPositions" :key="pos.id" :value="pos.name">{{ pos.name }}</option>
                        </select>
                        <p v-if="!orgPositions.length" class="mt-1 text-xs text-gray-400">Tiada jawatan disediakan. Admin boleh tambah di menu Jawatan.</p>
                        <InputError class="mt-2" :message="form.errors.position" />
                    </div>

                    <div class="md:col-span-2">
                        <InputLabel for="topics" value="Bidang Kepakaran / Topik" />
                        <textarea id="topics" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" v-model="form.topics" placeholder="Pengurusan, Kewangan, Pendidikan, IT, ..."></textarea>
                        <InputError class="mt-2" :message="form.errors.topics" />
                    </div>
                </div>
            </div>

            <div v-if="!isSuperadmin" class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Contact Kecemasan</p>
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="emergency_contact_name" value="Nama Waris / Hubungi" />
                        <TextInput id="emergency_contact_name" type="text" class="mt-1 block w-full" v-model="form.emergency_contact_name" />
                        <InputError class="mt-2" :message="form.errors.emergency_contact_name" />
                    </div>
                    <div>
                        <InputLabel for="emergency_contact_phone" value="No. Telefon" />
                        <TextInput id="emergency_contact_phone" type="text" class="mt-1 block w-full" v-model="form.emergency_contact_phone" />
                        <InputError class="mt-2" :message="form.errors.emergency_contact_phone" />
                    </div>
                </div>
            </div>

            <div v-if="!isSuperadmin" class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Komuniti</p>
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="branch_id" value="Cawangan" />
                        <div v-if="pendingBranchRequest" class="mb-3 rounded-xl bg-amber-50 border border-amber-200 px-3 py-2">
                            <p class="text-xs font-medium text-amber-800">
                                ⏳ Permohonan tukar cawangan ke <strong>{{ pendingBranchRequest.to_branch }}</strong> sedang menunggu kelulusan admin.
                            </p>
                        </div>
                        <select
                            id="branch_id"
                            v-model="form.branch_id"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        >
                            <option value="">-- Pilih Cawangan --</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                                {{ branch.name }}
                            </option>
                        </select>
                        <p v-if="!branches.length" class="mt-1 text-xs text-gray-400">Tiada cawangan tersedia untuk organisasi anda.</p>
                        <p v-else class="mt-1 text-xs text-gray-400">Tukar cawangan akan dihantar untuk kelulusan admin.</p>
                        <InputError class="mt-2" :message="form.errors.branch_id" />
                    </div>

                    <div>
                        <InputLabel for="locality" value="Lokaliti" />
                        <TextInput id="locality" type="text" class="mt-1 block w-full" v-model="form.locality" placeholder="Contoh: Shah Alam" />
                        <InputError class="mt-2" :message="form.errors.locality" />
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5 space-y-4">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Profil & Keterlihatan</p>

                <div>
                    <InputLabel for="profile_photo" value="Foto Profil" />
                    <input id="profile_photo" type="file" accept="image/*" class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm" @change="onProfilePhotoSelected" />
                    <p class="mt-1 text-xs text-gray-400">Maksimum 2MB. Format: JPEG, PNG, GIF, WebP.</p>
                    <InputError class="mt-2" :message="photoError || form.errors.profile_photo" />
                </div>

                <label
                    v-if="!isSuperadmin"
                    for="is_public_in_directory"
                    class="flex items-start gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2.5 cursor-pointer"
                >
                    <input id="is_public_in_directory" v-model="form.is_public_in_directory" type="checkbox" class="mt-0.5 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" />
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Papar profil saya dalam direktori networking</p>
                        <p class="text-xs text-gray-500">Ahli lain boleh mencari dan berhubung berdasarkan kepakaran anda.</p>
                    </div>
                </label>
                <InputError v-if="!isSuperadmin" class="mt-2" :message="form.errors.is_public_in_directory" />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-gray-700">
                    Emel anda belum disahkan.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        Klik untuk hantar semula emel pengesahan.
                    </Link>
                </p>

                <div
                    v-show="status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    Pautan pengesahan baharu telah dihantar ke emel anda.
                </div>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton :disabled="form.processing">Simpan Profil</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-emerald-700"
                    >
                        Berjaya disimpan.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>

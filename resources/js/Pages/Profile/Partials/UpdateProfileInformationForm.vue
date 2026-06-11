<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
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
    canEditIcNumber: {
        type: Boolean,
        default: false,
    },
});


const user = usePage().props.auth.user;
const isSuperadmin = computed(() => (user?.roles ?? []).includes('Superadmin'));

const form = useForm({
    name: user.name,
    email: user.email,
    ic_number: user.ic_number ?? '',
    phone: user.phone ?? '',
    dob: user.dob ?? '',
    education_level: user.education_level ?? '',
    current_profession: user.current_profession ?? '',
    industry: user.industry ?? '',
    branch_id: user.branch_id ?? '',
    locality: user.locality ?? '',
    expertise: user.expertise ?? '',
    linkedin_url: user.linkedin_url ?? '',
    profile_photo: null,
    is_public_in_directory: user.is_public_in_directory ?? true,
});

function onProfilePhotoSelected(event) {
    form.profile_photo = event.target.files[0];
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
                        <InputError class="mt-2" :message="form.errors.dob" />
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
                </div>
            </div>

            <div v-if="!isSuperadmin" class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 md:p-5">
                <p class="text-xs font-bold uppercase tracking-[0.12em] text-gray-500">Komuniti</p>
                <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <InputLabel for="branch_id" value="Cawangan" />
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
                    <InputError class="mt-2" :message="form.errors.profile_photo" />
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

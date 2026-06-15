<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import axios from 'axios';

const page = usePage();
const parsedDob = page.props.parsedDob || '';
const parsedGender = page.props.parsedGender || '';

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
    return parseInt(digits.slice(-1)) % 2 === 1 ? 'lelaki' : 'perempuan';
}

const form = useForm({
    education_level: '',
    current_profession: '',
    phone: '',
    dob: parsedDob,
    gender: parsedGender,
    marital_status: '',
    address_1: '',
    address_2: '',
    postcode: '',
    city: '',
    state: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    topics: '',
});

const postcodeLoading = ref(false);

watch(() => form.postcode, async (val) => {
    if (!val || val.length !== 5) return;
    postcodeLoading.value = true;
    try {
        const res = await axios.get(route('postcode.lookup'), { params: { postcode: val } });
        if (res.data.found) {
            form.city = res.data.city || form.city;
            form.state = res.data.state || form.state;
        }
    } catch {
    } finally {
        postcodeLoading.value = false;
    }
});

const submit = () => {
    form.post(route('member.complete-profile.store'));
};
</script>

<template>
    <Head title="Lengkapkan Profil" />

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 px-4 py-10">
        <div class="bg-white rounded-3xl p-8 max-w-2xl shadow-2xl mx-auto mt-10">
            <h1 class="text-2xl font-black text-gray-800 text-center">Lengkapkan Profil Anda</h1>
            <p class="mt-2 text-sm text-gray-500 text-center">Sila kemas kini maklumat terkini anda untuk meneruskan</p>

            <form class="mt-8 space-y-5 text-left" @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Nombor Telefon *</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="0123456789" required>
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tarikh Lahir</label>
                        <input v-model="form.dob" type="date" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0">
                        <p class="mt-1 text-xs text-gray-400">Auto-fill dari nombor IC</p>
                        <p v-if="form.errors.dob" class="mt-1 text-xs text-red-500">{{ form.errors.dob }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Jantina</label>
                        <select v-model="form.gender" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="">Pilih</option>
                            <option value="lelaki">Lelaki</option>
                            <option value="perempuan">Perempuan</option>
                        </select>
                        <p v-if="form.errors.gender" class="mt-1 text-xs text-red-500">{{ form.errors.gender }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status Perkahwinan</label>
                        <select v-model="form.marital_status" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="">Pilih</option>
                            <option value="bujang">Bujang</option>
                            <option value="berkahwin">Berkahwin</option>
                            <option value="bercerai">Bercerai</option>
                            <option value="duda/janda">Duda / Janda</option>
                        </select>
                        <p v-if="form.errors.marital_status" class="mt-1 text-xs text-red-500">{{ form.errors.marital_status }}</p>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-5">
                    <h3 class="mb-3 text-sm font-bold text-gray-700">Alamat</h3>
                    <div class="space-y-4">
                        <input v-model="form.address_1" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Alamat baris 1">
                        <input v-model="form.address_2" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Alamat baris 2 (optional)">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Poskod</label>
                                <input v-model="form.postcode" type="text" maxlength="5" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="56000">
                                <p v-if="postcodeLoading" class="mt-1 text-xs text-gray-400">Mencari...</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Bandar</label>
                                <input v-model="form.city" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Kuala Lumpur">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Negeri</label>
                                <input v-model="form.state" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Wilayah Persekutuan">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-5">
                    <h3 class="mb-3 text-sm font-bold text-gray-700">Pendidikan & Pekerjaan</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Pendidikan *</label>
                            <input v-model="form.education_level" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Ijazah Sarjana Muda" required>
                            <p v-if="form.errors.education_level" class="mt-1 text-xs text-red-500">{{ form.errors.education_level }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Profesion *</label>
                            <input v-model="form.current_profession" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Guru" required>
                            <p v-if="form.errors.current_profession" class="mt-1 text-xs text-red-500">{{ form.errors.current_profession }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Bidang Kepakaran / Topik</label>
                        <textarea v-model="form.topics" rows="2" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Pengurusan, Kewangan, Pendidikan, IT, ..."></textarea>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-5">
                    <h3 class="mb-3 text-sm font-bold text-gray-700">Contact Kecemasan</h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Nama Waris / Hubungi</label>
                            <input v-model="form.emergency_contact_name" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="Nama">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">No. Telefon</label>
                            <input v-model="form.emergency_contact_phone" type="text" class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0" placeholder="0123456789">
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="form.processing" class="w-full rounded-2xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-gray-300 transition hover:bg-gray-800 disabled:opacity-60">
                    {{ form.processing ? 'Menyimpan...' : 'Kemas Kini & Teruskan' }}
                </button>
            </form>
        </div>
    </div>
</template>

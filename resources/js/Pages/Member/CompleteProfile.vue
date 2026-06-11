<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    education_level: '',
    current_profession: '',
    phone: '',
});

const submit = () => {
    form.post(route('member.complete-profile.store'));
};
</script>

<template>
    <Head title="Lengkapkan Profil" />

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 px-4 py-10">
        <div class="bg-white rounded-3xl p-8 max-w-lg shadow-2xl mx-auto mt-10 text-center">
            <h1 class="text-2xl font-black text-gray-800">Lengkapkan Profil Anda</h1>
            <p class="mt-2 text-sm text-gray-500">Sila kemas kini maklumat terkini anda untuk meneruskan</p>

            <form class="mt-8 space-y-4 text-left" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Education Level</label>
                    <input
                        v-model="form.education_level"
                        type="text"
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0"
                        placeholder="Contoh: Ijazah Sarjana Muda"
                        required
                    >
                    <p v-if="form.errors.education_level" class="mt-1 text-xs text-red-500">{{ form.errors.education_level }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Current Profession</label>
                    <input
                        v-model="form.current_profession"
                        type="text"
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0"
                        placeholder="Contoh: Guru"
                        required
                    >
                    <p v-if="form.errors.current_profession" class="mt-1 text-xs text-red-500">{{ form.errors.current_profession }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Phone Number</label>
                    <input
                        v-model="form.phone"
                        type="text"
                        class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm focus:border-gray-500 focus:ring-0"
                        placeholder="Contoh: 0123456789"
                        required
                    >
                    <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-2xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-gray-300 transition hover:bg-gray-800 disabled:opacity-60"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Kemas Kini & Teruskan' }}
                </button>
            </form>
        </div>
    </div>
</template>

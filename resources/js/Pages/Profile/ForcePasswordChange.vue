<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
    password_confirmation: '',
});

function submit() {
    form.put(route('password.update'), {
        preserveScroll: true,
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <Head title="Tukar Kata Laluan" />

    <GuestLayout>
        <div class="space-y-5">
            <div>
                <h1 class="text-lg font-black text-gray-900">Tukar Kata Laluan</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Kata laluan sementara anda perlu ditukar sebelum meneruskan. Selepas disimpan, anda akan dibawa ke papan pemuka.
                </p>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <InputLabel for="password" value="Kata Laluan Baharu" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        autofocus
                    />
                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Sahkan Kata Laluan Baharu" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError :message="form.errors.password_confirmation" class="mt-2" />
                </div>

                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <PrimaryButton :disabled="form.processing">
                        Simpan Kata Laluan
                    </PrimaryButton>

                    <button
                        type="button"
                        class="text-sm font-semibold text-gray-500 underline underline-offset-2 hover:text-gray-900"
                        @click="logout"
                    >
                        Log keluar
                    </button>
                </div>
            </form>
        </div>
    </GuestLayout>
</template>

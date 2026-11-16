<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    ic_number: '',
    channel: 'whatsapp',
    contact: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <div class="mb-4 text-sm text-gray-600">
            Lupa kata laluan? Masukkan No IC / Passport dan pilih saluran untuk menerima pautan reset.
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-600"
        >
            {{ status }}
            <p class="mt-1 text-xs font-normal text-gray-500">
                Tidak terima pautan? Cuba semula selepas 60 saat atau hubungi admin.
            </p>
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="ic_number" value="No IC / Passport" />

                <TextInput
                    id="ic_number"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.ic_number"
                    required
                    autofocus
                    autocomplete="off"
                />

                <InputError class="mt-2" :message="form.errors.ic_number" />
                <p class="mt-1 text-xs text-gray-500">Masukkan No. IC tanpa tanda dash (-). Jika rekod ahli tidak dijumpai, sila hubungi admin.</p>
            </div>

            <div class="mt-4">
                <InputLabel value="Hantar melalui" />
                <div class="mt-2 flex gap-4 text-sm text-gray-700">
                    <label class="flex items-center gap-2"><input v-model="form.channel" type="radio" value="whatsapp"> WhatsApp</label>
                    <label class="flex items-center gap-2"><input v-model="form.channel" type="radio" value="email"> Emel</label>
                </div>
            </div>

            <div class="mt-4">
                <InputLabel for="contact" :value="form.channel === 'whatsapp' ? 'Nombor WhatsApp' : 'Emel'" />
                <TextInput
                    id="contact"
                    v-model="form.contact"
                    :type="form.channel === 'whatsapp' ? 'tel' : 'email'"
                    class="mt-1 block w-full"
                    :placeholder="form.channel === 'whatsapp' ? '+601157452119' : 'nama@example.com'"
                    required
                />
                <InputError class="mt-2" :message="form.errors.contact" />
            </div>

            <div class="mt-4 flex items-center justify-end">
                <PrimaryButton
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Hantar Pautan Reset
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Profile" />

    <AppLayout>
        <template #header>Edit Profile</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6">
            <div class="space-y-5 md:space-y-6">
                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :branches="branches"
                        :can-edit-ic-number="canEditIcNumber"
                    />
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <UpdatePasswordForm />
                </section>

                <section class="rounded-3xl border border-red-100 bg-white p-4 shadow-sm md:p-6">
                    <DeleteUserForm />
                </section>
            </div>
        </div>
    </AppLayout>
</template>

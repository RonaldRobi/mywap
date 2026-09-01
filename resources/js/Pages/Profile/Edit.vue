<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link } from '@inertiajs/vue3';

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
</script>

<template>
    <Head title="Profile" />

    <AppLayout :back-route="route('dashboard')" back-label="Kembali ke Papan Pemuka">
        <template #header>Edit Profile</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6">
            <div class="space-y-5 md:space-y-6">
                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :branches="branches"
                        :org-positions="orgPositions"
                        :can-edit-ic-number="canEditIcNumber"
                        :pending-branch-request="pendingBranchRequest"
                    />
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <UpdatePasswordForm />
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-lg font-black text-gray-900">Pembatalan Akaun</h2>
                            <p class="mt-1 text-sm text-gray-600">
                                Padam akaun dan semua data anda secara kekal.
                            </p>
                        </div>
                        <Link
                            :href="route('account.cancellation')"
                            class="inline-flex shrink-0 items-center justify-center rounded-xl bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100 transition"
                        >
                            Urus Pembatalan
                        </Link>
                    </div>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

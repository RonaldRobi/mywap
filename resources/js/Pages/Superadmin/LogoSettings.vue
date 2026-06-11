<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    systemLogoPath: {
        type: String,
        default: null,
    },
    canManageSystemLogo: {
        type: Boolean,
        default: false,
    },
    canManageOrganizationLogo: {
        type: Boolean,
        default: false,
    },
    organizations: {
        type: Array,
        default: () => [],
    },
});

const systemForm = useForm({
    system_logo: null,
});

const orgForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({ organization_logo: null }),
    ])
);

function uploadSystemLogo() {
    systemForm.post(route('superadmin.logo-settings.system.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => systemForm.reset('system_logo'),
    });
}

function uploadOrganizationLogo(organization) {
    orgForms[organization.id].post(route('superadmin.logo-settings.organization.update', organization.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => orgForms[organization.id].reset('organization_logo'),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Tetapan Logo" />

        <div class="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Tetapan Logo</h1>
                <p class="mt-1 text-sm text-gray-500">Muat naik logo sistem dan logo setiap organisasi (PKPIM, ABIM, WADAH).</p>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="!canManageSystemLogo || !canManageOrganizationLogo" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Beberapa fungsi belum aktif kerana migration belum dijalankan. Jalankan <strong>php artisan migrate</strong> untuk aktifkan penuh tetapan logo.
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Logo Sistem (myWAP)</h2>
                <p class="mt-1 text-xs text-gray-500">Cadangan saiz: 512 × 512px (nisbah 1:1), format PNG/SVG dengan latar transparen.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="systemLogoPath" :src="systemLogoPath" alt="System logo" class="h-16 w-16 object-contain">
                        <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadSystemLogo">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="systemForm.system_logo = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="systemForm.errors.system_logo" class="text-xs text-red-500">{{ systemForm.errors.system_logo }}</p>
                        <button
                            type="submit"
                            :disabled="systemForm.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ systemForm.processing ? 'Memuat naik...' : 'Simpan Logo Sistem' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Logo Organisasi</h2>
                <p class="mt-1 text-xs text-gray-500">Cadangan saiz setiap logo: 512 × 512px (min 256 × 256px), format PNG/SVG transparen.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="organization in organizations"
                        :key="organization.id"
                        class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4"
                    >
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-bold text-gray-800">{{ organization.name }}</p>
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold uppercase text-gray-500">{{ organization.slug }}</span>
                        </div>

                        <div class="mt-3 flex h-20 w-20 items-center justify-center rounded-2xl border border-gray-200 bg-white">
                            <img v-if="organization.logo_path" :src="organization.logo_path" :alt="organization.name + ' logo'" class="h-16 w-16 object-contain">
                            <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                        </div>

                        <form class="mt-3 space-y-2" @submit.prevent="uploadOrganizationLogo(organization)">
                            <input
                                type="file"
                                accept="image/*"
                                :disabled="!canManageOrganizationLogo"
                                @change="orgForms[organization.id].organization_logo = $event.target.files[0]"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <p v-if="orgForms[organization.id].errors.organization_logo" class="text-xs text-red-500">
                                {{ orgForms[organization.id].errors.organization_logo }}
                            </p>
                            <button
                                type="submit"
                                :disabled="orgForms[organization.id].processing || !canManageOrganizationLogo"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                            >
                                {{ orgForms[organization.id].processing ? 'Memuat naik...' : 'Simpan Logo ' + organization.name }}
                            </button>
                        </form>
                    </article>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

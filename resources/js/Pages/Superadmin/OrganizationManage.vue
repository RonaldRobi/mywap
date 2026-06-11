<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
    capabilities: {
        type: Object,
        default: () => ({
            logo: false,
            sort_order: false,
        }),
    },
});

const editForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({
            name: organization.name,
            color_theme: organization.color_theme,
            min_age: organization.min_age,
            max_age: organization.max_age,
            sort_order: organization.sort_order,
        }),
    ])
);

const logoForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({ organization_logo: null }),
    ])
);

const sortedOrganizations = computed(() =>
    [...props.organizations].sort((left, right) => {
        const leftOrder = left.sort_order ?? Number.MAX_SAFE_INTEGER;
        const rightOrder = right.sort_order ?? Number.MAX_SAFE_INTEGER;

        if (leftOrder !== rightOrder) return leftOrder - rightOrder;
        return left.min_age - right.min_age;
    })
);

function updateOrganization(organization) {
    editForms[organization.id].put(route('superadmin.organizations.update', organization.id), {
        preserveScroll: true,
    });
}

function updateOrganizationLogo(organization) {
    logoForms[organization.id].post(route('superadmin.organizations.logo.update', organization.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => logoForms[organization.id].reset('organization_logo'),
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Organization Management" />

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Organization Management</h1>
                <p class="mt-1 text-sm text-gray-500">Urus nama organisasi, logo, umur tier, warna tema, susunan paparan, dan semak jumlah ahli.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="!capabilities.logo || !capabilities.sort_order" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Sebahagian fungsi belum aktif kerana migration belum dijalankan sepenuhnya. Jalankan <strong>php artisan migrate</strong>.
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="organization in sortedOrganizations"
                    :key="organization.id"
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-black text-gray-800">{{ organization.name }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ organization.slug }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                            Ahli: {{ organization.member_count }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                            <img v-if="organization.logo_path" :src="organization.logo_path" :alt="organization.name + ' logo'" class="h-16 w-16 object-contain">
                            <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                        </div>

                        <form class="flex-1 space-y-2" @submit.prevent="updateOrganizationLogo(organization)">
                            <p class="text-[11px] text-gray-500">Cadangan saiz logo: <strong>512 × 512px</strong> (PNG/SVG transparen).</p>
                            <input
                                type="file"
                                accept="image/*"
                                :disabled="!capabilities.logo"
                                @change="logoForms[organization.id].organization_logo = $event.target.files[0]"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <button
                                type="submit"
                                :disabled="logoForms[organization.id].processing || !capabilities.logo"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                            >
                                {{ logoForms[organization.id].processing ? 'Memuat naik...' : 'Simpan Logo' }}
                            </button>
                            <p v-if="logoForms[organization.id].errors.organization_logo" class="text-xs text-red-500">
                                {{ logoForms[organization.id].errors.organization_logo }}
                            </p>
                        </form>
                    </div>

                    <form class="mt-5 space-y-3" @submit.prevent="updateOrganization(organization)">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Organisasi</label>
                            <input v-model="editForms[organization.id].name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <p v-if="editForms[organization.id].errors.name" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Min Umur</label>
                                <input v-model.number="editForms[organization.id].min_age" type="number" min="0" max="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Max Umur</label>
                                <input v-model.number="editForms[organization.id].max_age" type="number" min="0" max="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="Kosong = tiada had">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Tema Warna (Hex)</label>
                            <input v-model="editForms[organization.id].color_theme" type="text" placeholder="#10b981" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        </div>

                        <div v-if="capabilities.sort_order">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Susunan Paparan</label>
                            <input v-model.number="editForms[organization.id].sort_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        </div>

                        <button
                            type="submit"
                            :disabled="editForms[organization.id].processing"
                            class="w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ editForms[organization.id].processing ? 'Menyimpan...' : 'Simpan Organisasi' }}
                        </button>
                    </form>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    admins: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    organization_id: { type: Number, default: null },
    isSuperadmin: Boolean,
});

const organizationId = ref(props.organization_id);
const selectedOrganization = computed(
    () => props.organizations.find((org) => org.id === organizationId.value) ?? null,
);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    organization_id: props.organization_id ?? props.organizations[0]?.id ?? null,
});

function changeOrganization() {
    router.get(route('admin.admins.index'), {
        organization_id: props.isSuperadmin ? organizationId.value : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function createAdmin() {
    const targetOrganization = form.organization_id;

    form.post(route('admin.admins.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('name', 'email', 'password', 'password_confirmation');

            if (props.isSuperadmin && targetOrganization !== organizationId.value) {
                router.get(route('admin.admins.index'), {
                    organization_id: targetOrganization,
                }, { preserveScroll: true, replace: true });
            }
        },
    });
}
</script>

<template>
    <Head title="Pengurusan Admin" />
    <AppLayout>
        <div class="mx-auto max-w-4xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-black text-gray-900">Pengurusan Admin</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Akaun pentadbir organisasi diurus di sini. Admin Cawangan diurus di halaman Cawangan.
                    </p>
                </div>
                <div v-if="isSuperadmin" class="flex items-center gap-2">
                    <select
                        v-model="organizationId"
                        @change="changeOrganization"
                        aria-label="Pilih organisasi"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm"
                    >
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </div>
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-gray-900">Admin Semasa</h2>
                    <span v-if="selectedOrganization" class="text-xs font-semibold text-gray-400">{{ selectedOrganization.name }}</span>
                </div>

                <div v-if="admins.length" class="mt-3 divide-y divide-gray-100">
                    <div v-for="admin in admins" :key="admin.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ admin.name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ admin.email }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">Admin</span>
                            <span v-if="!admin.is_active" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">Nyahaktif</span>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-gray-400">Belum ada Admin organisasi.</p>
            </section>

            <section v-if="isSuperadmin" class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Cipta Akaun Admin Organisasi</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Akaun ini untuk mengurus panel pentadbir. Ia tiada No. Ahli, kad digital atau yuran.
                </p>

                <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="createAdmin">
                    <div class="sm:col-span-2">
                        <label for="admin-org" class="block text-[11px] font-semibold text-gray-500 mb-1">Organisasi</label>
                        <select id="admin-org" v-model="form.organization_id" class="w-full rounded-xl border-gray-200 text-sm">
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                        <p v-if="form.errors.organization_id" class="mt-1 text-xs text-red-600">{{ form.errors.organization_id }}</p>
                    </div>

                    <div>
                        <label for="admin-name" class="block text-[11px] font-semibold text-gray-500 mb-1">Nama</label>
                        <input id="admin-name" v-model="form.name" type="text" autocomplete="name" class="w-full rounded-xl border-gray-200 text-sm" />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label for="admin-email" class="block text-[11px] font-semibold text-gray-500 mb-1">Emel</label>
                        <input id="admin-email" v-model="form.email" type="email" autocomplete="email" class="w-full rounded-xl border-gray-200 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label for="admin-password" class="block text-[11px] font-semibold text-gray-500 mb-1">Kata laluan</label>
                        <input id="admin-password" v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-xl border-gray-200 text-sm" />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-600">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label for="admin-password-confirm" class="block text-[11px] font-semibold text-gray-500 mb-1">Sahkan kata laluan</label>
                        <input id="admin-password-confirm" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border-gray-200 text-sm" />
                    </div>

                    <div class="sm:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            Cipta akaun admin
                        </button>
                    </div>
                </form>
            </section>

            <p v-else class="rounded-2xl border border-gray-100 bg-white px-4 py-3 text-sm text-gray-500 shadow-sm">
                Hanya Superadmin boleh mencipta akaun Admin organisasi.
            </p>
        </div>
    </AppLayout>
</template>

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

const resetTarget = ref(null);
const resetForm = useForm({
    password: '',
    password_confirmation: '',
});

function changeOrganization() {
    const params = {};

    if (props.isSuperadmin && organizationId.value) {
        params.organization_id = organizationId.value;
    }

    router.get(route('admin.admins.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
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

function toggleAdmin(admin) {
    const action = admin.is_active ? 'Nyahaktifkan' : 'Aktifkan semula';

    if (!confirm(`${action} akaun ${admin.name}?`)) return;

    router.patch(route('admin.hub.members.toggle-active', admin.id), {}, {
        preserveScroll: true,
    });
}

function openReset(admin) {
    resetTarget.value = admin;
    resetForm.reset();
    resetForm.clearErrors();
}

function submitReset() {
    resetForm.post(route('admin.hub.members.reset-password', resetTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            resetTarget.value = null;
            resetForm.reset();
        },
    });
}

function deleteAdmin(admin) {
    if (!confirm(`Padam akaun admin ${admin.name}? Akaun akan dipindahkan ke Tong Sampah.`)) return;

    router.delete(route('admin.hub.members.destroy', admin.id), {
        preserveScroll: true,
    });
}

function roleLabel(role) {
    return role === 'org-admin' ? 'Admin' : role;
}

function roleClass(role) {
    return {
        Superadmin: 'bg-purple-100 text-purple-700',
        Admin: 'bg-slate-100 text-slate-700',
        'org-admin': 'bg-slate-100 text-slate-700',
        'Admin Cawangan': 'bg-blue-100 text-blue-700',
    }[role] ?? 'bg-gray-100 text-gray-600';
}
</script>

<template>
    <Head title="Pengurusan Admin" />
    <AppLayout>
        <div class="mx-auto max-w-5xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-black text-gray-900">Pengurusan Admin</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        <template v-if="isSuperadmin">
                            Semua akaun pentadbir (Superadmin, Admin, Admin Cawangan) diurus di sini. Penetapan cawangan Admin Cawangan masih di halaman Cawangan.
                        </template>
                        <template v-else>
                            Akaun pentadbir organisasi diurus di sini.
                        </template>
                    </p>
                </div>
                <div v-if="isSuperadmin" class="flex items-center gap-2">
                    <select
                        v-model="organizationId"
                        @change="changeOrganization"
                        aria-label="Pilih organisasi"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm"
                    >
                        <option :value="null">Semua organisasi</option>
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-gray-900">Admin Semasa</h2>
                    <span class="text-xs font-semibold text-gray-400">
                        {{ selectedOrganization?.name ?? 'Semua organisasi' }} · {{ admins.length }} akaun
                    </span>
                </div>

                <div v-if="admins.length" class="mt-3 divide-y divide-gray-100">
                    <div v-for="admin in admins" :key="admin.id" class="flex flex-col gap-3 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ admin.name }}</p>
                                <span v-if="admin.is_self" class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Anda</span>
                                <span v-if="!admin.is_active" class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-[11px] font-semibold text-red-700">Nyahaktif</span>
                            </div>
                            <p class="truncate text-xs text-gray-500">{{ admin.email }}</p>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span
                                    v-for="role in admin.roles"
                                    :key="role"
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                    :class="roleClass(role)"
                                >
                                    {{ roleLabel(role) }}
                                </span>
                                <span v-if="admin.organization_name" class="text-[11px] font-medium text-gray-400">{{ admin.organization_name }}</span>
                                <span v-if="admin.branch_name" class="text-[11px] font-medium text-gray-400">· {{ admin.branch_name }}</span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                v-if="admin.can_toggle"
                                type="button"
                                class="rounded-xl border px-3 py-1.5 text-xs font-semibold"
                                :class="admin.is_active ? 'border-amber-200 text-amber-700 hover:bg-amber-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50'"
                                @click="toggleAdmin(admin)"
                            >
                                {{ admin.is_active ? 'Nyahaktif' : 'Aktifkan' }}
                            </button>
                            <button
                                v-if="admin.can_reset_password"
                                type="button"
                                class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                @click="openReset(admin)"
                            >
                                Tukar Kata Laluan
                            </button>
                            <button
                                v-if="admin.can_delete"
                                type="button"
                                class="rounded-xl border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50"
                                @click="deleteAdmin(admin)"
                            >
                                Padam
                            </button>
                        </div>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-gray-400">Belum ada akaun admin.</p>
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

        <div v-if="resetTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="resetTarget = null">
            <div class="w-full max-w-md rounded-3xl bg-white p-5 shadow-xl">
                <h3 class="text-base font-bold text-gray-900">Tukar Kata Laluan</h3>
                <p class="mt-1 text-sm text-gray-500">{{ resetTarget.name }} · {{ resetTarget.email }}</p>

                <form class="mt-4 space-y-3" @submit.prevent="submitReset">
                    <div>
                        <label for="reset-password" class="block text-[11px] font-semibold text-gray-500 mb-1">Kata laluan baharu</label>
                        <input id="reset-password" v-model="resetForm.password" type="password" autocomplete="new-password" class="w-full rounded-xl border-gray-200 text-sm" />
                        <p v-if="resetForm.errors.password" class="mt-1 text-xs text-red-600">{{ resetForm.errors.password }}</p>
                    </div>
                    <div>
                        <label for="reset-password-confirm" class="block text-[11px] font-semibold text-gray-500 mb-1">Sahkan kata laluan</label>
                        <input id="reset-password-confirm" v-model="resetForm.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border-gray-200 text-sm" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600" @click="resetTarget = null">
                            Batal
                        </button>
                        <button type="submit" :disabled="resetForm.processing" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            Simpan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

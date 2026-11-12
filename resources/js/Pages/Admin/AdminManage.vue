<script setup>
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    admins: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    organization_id: { type: Number, default: null },
    search: { type: String, default: '' },
    isSuperadmin: Boolean,
});

const search = ref(props.search);
const organizationId = ref(props.organization_id);
const processingId = ref(null);

function applySearch() {
    router.get(route('admin.admins.index'), {
        search: search.value || undefined,
        organization_id: props.isSuperadmin ? organizationId.value : undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function assignAdmin(member) {
    if (!confirm(`Lantik ${member.name} sebagai Admin?`)) return;
    processingId.value = member.id;
    router.patch(route('admin.hub.members.role.update', member.id), { role: 'Admin' }, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}

function revokeAdmin(member) {
    if (!confirm(`Buang peranan Admin daripada ${member.name}?`)) return;
    processingId.value = member.id;
    router.patch(route('admin.hub.members.role.update', member.id), { role: 'Member' }, {
        preserveScroll: true,
        onFinish: () => { processingId.value = null; },
    });
}
</script>

<template>
    <Head title="Pengurusan Admin" />
    <AppLayout>
        <div class="mx-auto max-w-6xl space-y-5 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-black text-gray-900">Pengurusan Admin</h1>
                    <p class="mt-1 text-sm text-gray-500">Lantik atau buang peranan Admin organisasi. Admin Cawangan diurus di halaman Cawangan.</p>
                </div>
                <div v-if="isSuperadmin" class="flex items-center gap-2">
                    <select v-model="organizationId" @change="applySearch" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                    </select>
                </div>
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <h2 class="text-sm font-bold text-gray-900">Admin Semasa</h2>
                <div v-if="admins.length" class="mt-3 divide-y divide-gray-100">
                    <div v-for="admin in admins" :key="admin.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ admin.name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ admin.member_no }} · {{ admin.email }}</p>
                        </div>
                        <button @click="revokeAdmin(admin)" :disabled="processingId === admin.id" class="rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100 disabled:opacity-50">
                            Buang Admin
                        </button>
                    </div>
                </div>
                <p v-else class="mt-2 text-sm text-gray-400">Belum ada Admin organisasi.</p>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-bold text-gray-900">Cari Ahli untuk Dilantik</h2>
                    <div class="flex gap-2">
                        <input v-model="search" @keydown.enter.prevent="applySearch" type="text" placeholder="Cari nama, No Ahli, IC atau emel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm sm:w-72" />
                        <button @click="applySearch" class="rounded-xl bg-gray-900 px-4 py-2 text-xs font-semibold text-white">Cari</button>
                    </div>
                </div>

                <div class="mt-3 divide-y divide-gray-100">
                    <div v-for="member in members" :key="member.id" class="flex items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ member.name }}</p>
                            <p class="truncate text-xs text-gray-500">{{ member.member_no }} · {{ member.branch_name || 'Tiada cawangan' }} · {{ member.role }}</p>
                        </div>
                        <button v-if="member.role === 'Member'" @click="assignAdmin(member)" :disabled="processingId === member.id" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 disabled:opacity-50">
                            Lantik Admin
                        </button>
                        <span v-else class="text-xs font-semibold text-gray-400">{{ member.role }}</span>
                    </div>
                    <p v-if="!members.length" class="py-6 text-center text-sm text-gray-400">Tiada ahli dijumpai.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    logs: Object,
    organizations: Array,
    actions: Array,
    filters: Object,
});

const search = ref(props.filters?.search ?? '');
const action = ref(props.filters?.action ?? '');
const organizationId = ref(props.filters?.organization_id ?? '');
let timer = null;

function applyFilter() {
    router.get(route('superadmin.audit-log.index'), {
        search: search.value,
        action: action.value,
        organization_id: organizationId.value,
    }, { preserveState: true, preserveScroll: true });
}

watch(search, () => {
    clearTimeout(timer);
    timer = setTimeout(applyFilter, 300);
});
watch([action, organizationId], applyFilter);
</script>

<template>
    <Head title="Audit Log" />

    <AppLayout>
        <div class="mx-auto w-full max-w-7xl px-4 py-6 md:px-6">
            <div class="mb-5">
                <h1 class="text-2xl font-black tracking-tight text-gray-900">Audit Log</h1>
                <p class="mt-1 text-sm text-gray-500">Rekod tindakan admin, termasuk akaun admin cawangan dan setiausaha.</p>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                <input v-model="search" type="text" placeholder="Cari keterangan atau nama admin..." class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0" />
                <select v-model="action" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option value="">Semua tindakan</option>
                    <option v-for="item in actions" :key="item" :value="item">{{ item }}</option>
                </select>
                <select v-model="organizationId" class="rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option value="">Semua organisasi</option>
                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                </select>
            </div>

            <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div v-if="!logs.data.length" class="px-6 py-12 text-center">
                    <p class="text-sm text-gray-400">Tiada rekod audit untuk penapis ini.</p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Masa</th>
                                <th class="px-5 py-3 font-semibold">Tindakan</th>
                                <th class="px-5 py-3 font-semibold">Keterangan</th>
                                <th class="px-5 py-3 font-semibold">Oleh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="log in logs.data" :key="log.id">
                                <td class="whitespace-nowrap px-5 py-3 text-gray-500">{{ log.created_at }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700">{{ log.action }}</span>
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ log.description }}</td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-800">{{ log.performed_by ?? 'Sistem' }}</p>
                                    <p class="text-xs text-gray-400">{{ log.performed_by_email }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="logs.links?.length > 3" class="mt-4 flex flex-wrap gap-1">
                <a
                    v-for="link in logs.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-lg px-3 py-1.5 text-xs font-semibold"
                    :class="link.active ? 'bg-gray-900 text-white' : 'bg-white text-gray-600 border border-gray-200 hover:bg-gray-50'"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>

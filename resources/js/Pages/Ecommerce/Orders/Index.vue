<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    orders: {
        type: Object,
        required: true,
    },
    canManageAll: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
</script>

<template>
    <Head title="Pesanan" />

    <AppLayout>
        <template #header>Pesanan</template>

        <div class="mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="flex flex-col gap-1 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Senarai Pesanan</p>
                        <p class="mt-1 text-sm text-gray-500">
                            {{ canManageAll ? 'Paparan semua pesanan (Admin/Superadmin).' : 'Paparan pesanan anda sahaja.' }}
                        </p>
                    </div>
                </div>

                <div class="overflow-x-auto border-t border-gray-100">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">ID</th>
                                <th class="px-4 py-3 text-left">Tarikh</th>
                                <th class="px-4 py-3 text-right">Jumlah (RM)</th>
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="o in orders.data" :key="o.id" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-semibold text-gray-900">#{{ o.id }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ o.created_at }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ Number(o.total ?? 0).toFixed(2) }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                        {{ o.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('orders.show', o.id)" class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        Lihat
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!orders.data?.length">
                                <td colspan="5" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Tiada pesanan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="orders.links?.length" class="border-t border-gray-100 bg-white px-4 py-3">
                    <div class="flex flex-wrap items-center justify-center gap-2">
                        <Link
                            v-for="link in orders.links"
                            :key="link.label"
                            :href="link.url || ''"
                            class="rounded-xl px-3 py-1.5 text-sm"
                            :class="[
                                link.active ? 'bg-gray-900 text-white' : 'border border-gray-200 text-gray-700 hover:bg-gray-50',
                                !link.url ? 'pointer-events-none opacity-40' : '',
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


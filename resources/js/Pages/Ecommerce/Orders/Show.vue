<script setup>
import { computed } from 'vue';
import { Head, Link, usePage, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const isAdmin = computed(() => {
    return page.props.auth.user?.roles?.some(role => ['Admin', 'Superadmin'].includes(role));
});

const statusForm = useForm({
    status: props.order.status ?? 'pending',
    tracking_no: props.order.tracking_no ?? '',
});

function updateStatus() {
    statusForm.post(route('orders.updateStatus', props.order.id));
}
</script>

<template>
    <Head :title="`Pesanan #${order.id}`" />

    <AppLayout>
        <template #header>Pesanan</template>

        <div class="mx-auto max-w-5xl px-4 py-4 md:px-6 md:py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="lg:col-span-2 rounded-3xl border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-4 py-4">
                        <p class="text-sm font-semibold text-gray-900">Butiran Pesanan</p>
                        <p class="mt-1 text-sm text-gray-500">#{{ order.id }}</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3 text-left">Produk</th>
                                    <th class="px-4 py-3 text-right">Kuantiti</th>
                                    <th class="px-4 py-3 text-right">Harga</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="it in order.items" :key="it.id">
                                    <td class="px-4 py-3 font-semibold text-gray-900">{{ it.product?.name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-700">{{ it.quantity }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900">{{ Number(it.price ?? 0).toFixed(2) }}</td>
                                </tr>
                                <tr v-if="!order.items?.length">
                                    <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">Tiada item.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="border-t border-gray-100 px-4 py-4 flex items-center justify-between">
                        <span class="text-sm font-semibold text-gray-700">Jumlah</span>
                        <span class="text-lg font-black text-gray-900">RM{{ Number(order.total ?? 0).toFixed(2) }}</span>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6 self-start">
                    <p class="text-sm font-semibold text-gray-900">Status Pesanan</p>
                    <p class="mt-1 text-sm text-gray-500" v-if="isAdmin">Kemaskini status pesanan (Admin/Superadmin).</p>

                    <div v-if="isAdmin" class="mt-4 space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Status</label>
                            <select v-model="statusForm.status" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                <option value="pending">pending</option>
                                <option value="paid">paid</option>
                                <option value="processing">processing</option>
                                <option value="shipped">shipped</option>
                                <option value="completed">completed</option>
                                <option value="cancelled">cancelled</option>
                            </select>
                            <p v-if="statusForm.errors.status" class="mt-1 text-xs text-red-600">{{ statusForm.errors.status }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Tracking No (optional)</label>
                            <input v-model="statusForm.tracking_no" type="text" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <p v-if="statusForm.errors.tracking_no" class="mt-1 text-xs text-red-600">{{ statusForm.errors.tracking_no }}</p>
                        </div>

                        <button
                            type="button"
                            @click="updateStatus"
                            :disabled="statusForm.processing"
                            class="w-full rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ statusForm.processing ? 'Mengemaskini...' : 'Kemaskini Status' }}
                        </button>
                    </div>

                    <div v-else class="mt-4 space-y-4">
                        <div>
                            <span class="block text-xs font-semibold text-gray-500 mb-1">Status Semasa</span>
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-3.5 py-1.5 text-sm font-bold text-indigo-700 ring-1 ring-inset ring-indigo-600/20 capitalize">
                                {{ order.status }}
                            </span>
                        </div>
                        <div v-if="order.tracking_no">
                            <span class="block text-xs font-semibold text-gray-500 mb-1">No Tracking</span>
                            <span class="text-base font-bold text-gray-900 bg-gray-50 px-3 py-2 rounded-xl border border-gray-100 inline-block">{{ order.tracking_no }}</span>
                        </div>
                    </div>

                    <div class="mt-8 border-t border-gray-100 pt-5">
                        <Link :href="route('orders.index')" class="text-sm font-semibold text-gray-700 hover:text-gray-900 transition-colors bg-gray-50 hover:bg-gray-100 px-4 py-2 rounded-xl inline-flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Kembali ke Senarai
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


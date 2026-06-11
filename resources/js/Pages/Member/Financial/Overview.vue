<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    campaigns: Array,
    feeStatus: Object,
    paymentHistory: Array,
});

const payForm = useForm({});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(value);
}

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('ms-MY', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function payFee() {
    payForm.post(route('member.pay.fee'));
}
</script>

<template>
    <Head title="Yuran & Bayaran" />

    <AppLayout>
        <template #header>Yuran & Bayaran</template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Status Yuran Card -->
                <div class="md:col-span-1">
                    <div class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-sm">
                        <div class="bg-gradient-to-br from-emerald-600 to-emerald-700 p-6 text-white">
                            <h3 class="text-lg font-bold">Status Keahlian</h3>
                            <p class="mt-1 text-sm text-emerald-100">Maklumat yuran tahunan anda</p>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-sm font-medium text-gray-500">Status</span>
                                <span 
                                    class="inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider"
                                    :class="feeStatus.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                >
                                    {{ feeStatus.status === 'active' ? 'Aktif' : 'Tunggakan' }}
                                </span>
                            </div>
                            
                            <div v-if="feeStatus.status !== 'active'" class="mb-6">
                                <p class="text-sm text-gray-600 mb-2">Jumlah perlu dibayar:</p>
                                <p class="text-3xl font-black text-gray-900">{{ formatCurrency(feeStatus.amount_due) }}</p>
                                <button
                                    @click="payFee"
                                    :disabled="payForm.processing"
                                    class="mt-4 w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                                >
                                    {{ payForm.processing ? 'Memproses...' : 'Bayar Sekarang' }}
                                </button>
                            </div>
                            
                            <div class="space-y-3 border-t border-gray-50 pt-4">
                                <div class="flex justify-between text-xs">
                                    <span class="text-gray-400">Bayaran Terakhir</span>
                                    <span class="font-semibold text-gray-700">{{ formatDate(feeStatus.last_paid_at) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sejarah Transaksi -->
                <div class="md:col-span-2">
                    <div class="rounded-3xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                        <div class="border-b border-gray-50 p-6">
                            <h3 class="text-lg font-bold text-gray-900">Sejarah Transaksi</h3>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-400">
                                    <tr>
                                        <th class="px-6 py-4">Tarikh</th>
                                        <th class="px-6 py-4">Perkara</th>
                                        <th class="px-6 py-4">Jumlah</th>
                                        <th class="px-6 py-4">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <tr v-for="payment in paymentHistory" :key="payment.id" class="hover:bg-gray-50/50 transition-colors">
                                        <td class="whitespace-nowrap px-6 py-4 text-gray-500">
                                            {{ formatDate(payment.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 font-medium text-gray-900">
                                            {{ payment.payable_type === 'membership_fee' ? 'Yuran Tahunan' : 'Sumbangan/Infaq' }}
                                        </td>
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            {{ formatCurrency(payment.amount) }}
                                        </td>
                                        <td class="px-6 py-4">
                                            <span 
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                                                :class="payment.status === 'successful' ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                                            >
                                                {{ payment.status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!paymentHistory.length">
                                        <td colspan="4" class="px-6 py-10 text-center text-gray-400">
                                            Tiada rekod transaksi ditemui.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

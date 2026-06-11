<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    organizations: Array,
});

// One editing form per org, keyed by org id
const forms = Object.fromEntries(
    props.organizations.map(org => [
        org.id,
        useForm({ fee_amount: org.fee_amount }),
    ])
);

const editing = ref(null);

function save(org) {
    forms[org.id].put(route('superadmin.fees.update', org.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <Head title="Pengurusan Yuran" />

        <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">

            <!-- Header -->
            <div>
                <h1 class="text-2xl font-black text-gray-900">Tetapan Yuran Keahlian</h1>
                <p class="mt-1 text-sm text-gray-500">Tetapkan jumlah yuran tahunan bagi setiap pertubuhan.</p>
            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- Org Fee Cards -->
            <div class="grid gap-4 sm:grid-cols-3">
                <div
                    v-for="org in organizations"
                    :key="org.id"
                    class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 shadow-sm space-y-4"
                >
                    <!-- Org badge -->
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide"
                            :class="{
                                'bg-indigo-100 text-indigo-700': org.slug === 'pkpim',
                                'bg-emerald-100 text-emerald-700': org.slug === 'abim',
                                'bg-amber-100 text-amber-700': org.slug === 'wadah',
                            }"
                        >
                            {{ org.name }}
                        </span>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Had Umur</p>
                        <p class="text-sm font-semibold text-gray-700">
                            {{ org.min_age }}{{ org.max_age ? '–' + org.max_age : '+ tahun' }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Jumlah Ahli</p>
                        <p class="text-2xl font-black text-gray-800">{{ org.member_count }}</p>
                    </div>

                    <!-- Fee amount -->
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Yuran Tahunan (RM)</p>

                        <div v-if="editing !== org.id" class="flex items-center justify-between">
                            <p class="text-2xl font-black text-gray-900">RM {{ Number(org.fee_amount).toFixed(2) }}</p>
                            <button
                                @click="editing = org.id"
                                class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold"
                            >
                                Edit
                            </button>
                        </div>

                        <form v-else @submit.prevent="save(org)" class="flex items-center gap-2">
                            <input
                                v-model="forms[org.id].fee_amount"
                                type="number"
                                step="0.01"
                                min="0"
                                max="9999.99"
                                class="w-28 rounded-xl border border-gray-300 px-3 py-1.5 text-sm focus:ring-0 focus:border-gray-500"
                            />
                            <button
                                type="submit"
                                :disabled="forms[org.id].processing"
                                class="rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 disabled:opacity-50"
                            >
                                Simpan
                            </button>
                            <button type="button" @click="editing = null" class="text-xs text-gray-400 hover:text-gray-600">
                                Batal
                            </button>
                        </form>

                        <p v-if="forms[org.id].errors.fee_amount" class="mt-1 text-xs text-red-500">
                            {{ forms[org.id].errors.fee_amount }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Link to all transactions -->
            <div class="pt-2">
                <a
                    :href="route('superadmin.transactions')"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white shadow hover:bg-gray-800 transition"
                >
                    Lihat Semua Transaksi →
                </a>
            </div>
        </div>
    </AppLayout>
</template>

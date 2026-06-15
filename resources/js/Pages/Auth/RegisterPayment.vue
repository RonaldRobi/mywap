<script setup>
import AccernityCard from '@/Components/ui/AccernityCard.vue';
import AuroraBackground from '@/Components/ui/AuroraBackground.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    registration: Object,
});

const form = useForm({});

const submit = () => {
    form.post(route('register.payment.process'));
};

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
    }).format(value);
}
</script>

<template>
    <AuroraBackground>
        <Head title="Bayaran Pendaftaran" />

        <div class="relative z-10 mx-auto flex min-h-screen w-full max-w-2xl flex-col px-3 py-5 sm:px-4 sm:py-6 md:px-8 md:py-10">
            <section class="w-full">
                <AccernityCard>
                    <div class="mb-6">
                        <p class="text-xs font-semibold uppercase tracking-[0.14em] text-cyan-100">Langkah 2 dari 2</p>
                        <h2 class="mt-1 text-2xl font-black text-white sm:text-3xl">Bayaran Pendaftaran</h2>
                        <p class="mt-1 text-sm text-slate-300">Sahkan maklumat dan teruskan pembayaran yuran keahlian.</p>
                    </div>

                    <div class="space-y-4">
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-5 space-y-3">
                            <h3 class="text-sm font-bold text-white">Ringkasan Pendaftaran</h3>

                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-slate-400">Nama</p>
                                    <p class="font-semibold text-white">{{ registration.name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">No IC</p>
                                    <p class="font-semibold text-white">{{ registration.ic_number }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Emel</p>
                                    <p class="font-semibold text-white">{{ registration.email }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Telefon</p>
                                    <p class="font-semibold text-white">{{ registration.phone || '—' }}</p>
                                </div>
                            </div>

                            <div class="border-t border-white/10 pt-3 grid grid-cols-1 gap-3 text-sm">
                                <div>
                                    <p class="text-xs text-slate-400">Organisasi</p>
                                    <p class="font-semibold text-white">{{ registration.organization }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">Cawangan</p>
                                    <p class="font-semibold text-white">{{ registration.branch }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400">No Ahli</p>
                                    <p class="font-semibold text-white">{{ registration.member_no }}</p>
                                </div>
                            </div>

                            <div class="border-t border-white/10 pt-3">
                                <p class="text-xs text-slate-400">Jumlah Yuran</p>
                                <p class="text-3xl font-black text-emerald-300">{{ formatCurrency(registration.fee_amount) }}</p>
                            </div>
                        </div>

                        <button
                            type="button"
                            :disabled="form.processing"
                            class="w-full rounded-2xl bg-emerald-600 px-4 py-3.5 text-base font-bold text-white shadow-lg shadow-emerald-900/30 transition hover:bg-emerald-500 disabled:opacity-50"
                            @click="submit"
                        >
                            {{ form.processing ? 'Memproses...' : `Bayar ${formatCurrency(registration.fee_amount)}` }}
                        </button>
                    </div>
                </AccernityCard>
            </section>
        </div>
    </AuroraBackground>
</template>

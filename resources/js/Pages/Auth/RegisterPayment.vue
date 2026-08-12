<script setup>
import MovementBranding from '@/Components/MovementBranding.vue';
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
    <div class="auth-canvas min-h-screen">
        <Head title="Bayaran Pendaftaran" />

        <div class="mx-auto flex min-h-screen w-full max-w-2xl flex-col justify-center px-3 py-6 sm:px-4 sm:py-10 md:px-8">
            <section class="rounded-2xl border border-[#D5E3D8] bg-white/85 p-5 shadow-[0_6px_24px_rgba(7,21,37,0.05)] backdrop-blur-sm sm:p-6">
                <MovementBranding light part="header" />
            </section>

            <section class="mt-4 rounded-2xl border border-[#D5E3D8] bg-white p-5 shadow-[0_18px_50px_rgba(7,21,37,0.10)] sm:mt-6 sm:p-7">
                <div class="mb-6">
                    <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[#2F6B32]">Langkah 2 dari 2</p>
                    <h2 class="mt-2 text-2xl font-black text-[#071525] sm:text-3xl">Bayaran Pendaftaran</h2>
                    <p class="mt-1 text-sm text-[#4A5A50]">Sahkan maklumat dan teruskan pembayaran yuran keahlian.</p>
                </div>

                <div class="space-y-4">
                    <div class="space-y-3 rounded-2xl border border-[#D5E3D8] bg-[#F4F6F1] p-5">
                        <h3 class="text-sm font-bold text-[#123D2A]">Ringkasan Pendaftaran</h3>

                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs text-[#4A5A50]">Nama</p>
                                <p class="font-semibold text-[#071525]">{{ registration.name }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#4A5A50]">No IC</p>
                                <p class="font-semibold text-[#071525]">{{ registration.ic_number }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#4A5A50]">Emel</p>
                                <p class="font-semibold text-[#071525]">{{ registration.email }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#4A5A50]">Telefon</p>
                                <p class="font-semibold text-[#071525]">{{ registration.phone || '—' }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 border-t border-[#D5E3D8] pt-3 text-sm">
                            <div>
                                <p class="text-xs text-[#4A5A50]">Organisasi</p>
                                <p class="font-semibold text-[#071525]">{{ registration.organization }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#4A5A50]">Cawangan</p>
                                <p class="font-semibold text-[#071525]">{{ registration.branch }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-[#4A5A50]">No Ahli</p>
                                <p class="font-semibold text-[#071525]">{{ registration.member_no }}</p>
                            </div>
                        </div>

                        <div class="border-t border-[#D5E3D8] pt-3">
                            <p class="text-xs text-[#4A5A50]">Jumlah Yuran</p>
                            <p class="text-3xl font-black text-[#2F6B32]">{{ formatCurrency(registration.fee_amount) }}</p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="form.processing"
                        class="w-full rounded-2xl bg-[#2F6B32] px-4 py-3.5 text-base font-bold text-[#F4F6F1] transition hover:bg-[#123D2A] disabled:opacity-50"
                        @click="submit"
                    >
                        {{ form.processing ? 'Memproses...' : `Bayar ${formatCurrency(registration.fee_amount)}` }}
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>

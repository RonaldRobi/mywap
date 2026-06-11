<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    infaq: {
        type: Object,
        required: true,
    },
    recentDonations: {
        type: Array,
        default: () => [],
    },
});

const donateForm = useForm({ amount: 10 });

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}

function donate() {
    donateForm.post(route('member.infaq.donate', props.infaq.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="infaq.title" />

    <AppLayout>
        <template #header>Butiran Infaq</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6 space-y-5">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-black text-gray-900 md:text-2xl">{{ infaq.title }}</h1>
                <Link :href="route('member.dashboard')" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                    Kembali
                </Link>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <section class="lg:col-span-2 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                    <div class="aspect-[16/8] w-full overflow-hidden bg-gray-100">
                        <img v-if="infaq.image_path" :src="infaq.image_path" :alt="infaq.title" class="h-full w-full object-cover">
                        <div v-else class="grid h-full place-items-center text-sm text-gray-400">No Image</div>
                    </div>

                    <div class="p-4 md:p-6">
                        <div class="mb-3 flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                :class="infaq.type === 'progress' ? 'bg-emerald-100 text-emerald-700' : 'bg-indigo-100 text-indigo-700'"
                            >
                                {{ infaq.type === 'progress' ? 'Progress Program' : 'One-Off Program' }}
                            </span>
                        </div>

                        <p class="text-sm leading-relaxed text-gray-600">{{ infaq.description || 'Tiada deskripsi.' }}</p>

                        <div class="mt-4">
                            <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Sebarkan Kempen Ini</p>
                        <SocialShareButtons
                                :title="infaq.title"
                                :text="infaq.description || 'Sertai kempen infaq ini.'"
                                :url="route('share.infaq', infaq.id, true)"
                            />
                        </div>

                        <div class="mt-4 rounded-2xl bg-gray-50 p-4">
                            <template v-if="infaq.type === 'progress'">
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-semibold text-gray-700">{{ formatCurrency(infaq.collected_amount) }}</span>
                                    <span class="text-gray-400">/ {{ formatCurrency(infaq.target_amount) }}</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200">
                                    <div class="h-full rounded-full bg-emerald-500" :style="{ width: infaq.progress_percent + '%' }"></div>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">{{ infaq.progress_percent }}% terkumpul</p>
                            </template>
                            <template v-else>
                                <p class="text-sm text-gray-700">
                                    Jumlah terkumpul: <span class="font-bold">{{ formatCurrency(infaq.collected_amount) }}</span>
                                </p>
                            </template>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-4 md:p-5 shadow-sm">
                    <h2 class="text-base font-black text-gray-900">Sumbang Sekarang</h2>
                    <form class="mt-3 space-y-3" @submit.prevent="donate">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Jumlah (RM)</span>
                            <input
                                v-model.number="donateForm.amount"
                                type="number"
                                min="1"
                                step="0.01"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                required
                            >
                        </label>
                        <button
                            type="submit"
                            :disabled="donateForm.processing"
                            class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                            {{ donateForm.processing ? 'Memproses...' : 'Derma Sekarang' }}
                        </button>
                    </form>

                    <h3 class="mt-6 text-sm font-black text-gray-900">Sumbangan Terkini</h3>
                    <div class="mt-2 space-y-2">
                        <div
                            v-for="donation in recentDonations"
                            :key="donation.id"
                            class="rounded-xl bg-gray-50 px-3 py-2"
                        >
                            <p class="text-xs font-semibold text-gray-700">{{ donation.donor_name }}</p>
                            <p class="text-xs text-emerald-700 font-bold">{{ formatCurrency(donation.amount) }}</p>
                            <p class="text-[11px] text-gray-400">{{ donation.created_at }}</p>
                        </div>

                        <p v-if="!recentDonations.length" class="text-xs text-gray-500">Belum ada sumbangan.</p>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

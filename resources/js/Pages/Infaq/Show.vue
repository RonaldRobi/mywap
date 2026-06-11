<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
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
    relatedInfaqs: {
        type: Array,
        default: () => [],
    },
});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}

// Removed donateForm since it's just a link now
</script>

<template>
    <Head :title="infaq.title" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-16 md:pb-0">
        <!-- Minimal Navigation -->
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center">
                    <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-8 w-auto" />
                </Link>
                <Link href="/#infaq" class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    Kembali ke Senarai Kempen
                </Link>
            </div>
        </nav>

        <div class="mx-auto max-w-5xl px-4 py-8 md:px-6 space-y-6">
            <div class="flex flex-col lg:flex-row gap-8">
                <!-- Main Content -->
                <div class="w-full lg:w-2/3 space-y-8">
                    <!-- Infaq Details -->
                    <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                        <div v-if="infaq.image_path" class="aspect-[16/8] bg-gray-100 w-full relative">
                            <img :src="infaq.image_path" :alt="infaq.title" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-90"></div>
                            <div class="absolute bottom-4 left-6 right-6">
                                <span class="inline-flex rounded-full bg-emerald-500/90 text-white px-3 py-1 text-xs font-bold uppercase tracking-wider mb-2 backdrop-blur-sm shadow-sm">
                                    {{ infaq.type === 'progress' ? 'Kempen Mengutip Dana' : 'Derma Bebas' }}
                                </span>
                                <h1 class="text-2xl md:text-3xl font-black text-white leading-tight drop-shadow-md">{{ infaq.title }}</h1>
                            </div>
                        </div>

                        <div class="p-6 md:p-8">
                            <div class="flex flex-wrap items-center gap-4 mb-6 pb-6 border-b border-slate-100">
                                <div class="flex items-center gap-2">
                                    <div class="h-10 w-10 rounded-full bg-emerald-100 flex items-center justify-center text-emerald-600">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Penganjur</p>
                                        <p class="text-sm font-semibold text-slate-800">{{ infaq.organization_name }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <div class="h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Penyumbang</p>
                                        <p class="text-sm font-semibold text-slate-800">{{ infaq.total_donors }} Orang</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 border-l border-slate-200 pl-4">
                                    <div class="h-10 w-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide">Tempoh Berjalan</p>
                                        <p class="text-sm font-semibold text-slate-800">{{ infaq.days_running }} Hari</p>
                                    </div>
                                </div>
                            </div>

                            <h2 class="text-xl font-black text-slate-900 mb-4">Tentang Kempen Ini</h2>
                            <p class="text-base leading-relaxed text-slate-600 whitespace-pre-line">{{ infaq.description || 'Tiada maklumat lanjut buat masa ini.' }}</p>

                            <div class="mt-8 pt-6 border-t border-slate-100">
                                <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Kongsikan Kempen Ini</p>
                                <SocialShareButtons
                                    :title="infaq.title"
                                    :text="infaq.description || 'Jom menyumbang bersama kami.'"
                                    :url="route('share.infaq', infaq.id, true)"
                                />
                            </div>
                        </div>
                    </article>

                </div>

                <!-- Sidebar (Donation Form & Donors) -->
                <div class="w-full lg:w-1/3 space-y-6">
                    <div class="sticky top-24 space-y-6">
                        <!-- Donation Card -->
                        <div class="rounded-3xl border border-gray-100 bg-white p-6 md:p-8 shadow-lg shadow-emerald-900/5">
                            <div class="mb-6">
                                <p class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-1">Terkumpul</p>
                                <h2 class="text-3xl font-black text-emerald-600 mb-2">{{ formatCurrency(infaq.collected_amount) }}</h2>
                                <template v-if="infaq.type === 'progress'">
                                    <div class="flex items-center justify-between text-sm font-semibold text-slate-500 mb-2">
                                        <span>{{ infaq.progress_percent }}%</span>
                                        <span>Matlamat: {{ formatCurrency(infaq.target_amount) }}</span>
                                    </div>
                                    <div class="h-3 w-full overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-emerald-500 transition-all duration-1000" :style="{ width: infaq.progress_percent + '%' }"></div>
                                    </div>
                                </template>
                            </div>

                            <div class="space-y-4">
                                <Link
                                    :href="infaq.public_url + '/donate'"
                                    class="block w-full text-center rounded-xl bg-slate-900 px-4 py-4 text-sm font-black uppercase tracking-wider text-white transition shadow-sm hover:bg-slate-800 hover:shadow-md hover:-translate-y-0.5"
                                >
                                    Sumbang Sekarang
                                </Link>
                                <p class="text-center text-xs font-semibold text-slate-400 mt-3 flex items-center justify-center gap-1">
                                    <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path></svg>
                                    Bayaran Selamat & Terjamin
                                </p>
                            </div>
                        </div>

                        <!-- Recent Donors -->
                        <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                            <h3 class="font-black text-slate-900 mb-4 flex items-center gap-2">
                                <svg class="h-5 w-5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z"></path></svg>
                                Sumbangan Terkini
                            </h3>
                            <div class="space-y-4">
                                <div
                                    v-for="donation in recentDonations"
                                    :key="donation.id"
                                    class="flex items-center justify-between border-b border-slate-50 pb-4 last:border-0 last:pb-0"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 font-bold text-sm">
                                            {{ donation.donor_name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">{{ donation.donor_name }}</p>
                                            <p class="text-xs font-semibold text-slate-400">{{ donation.created_at }}</p>
                                        </div>
                                    </div>
                                    <p class="text-sm font-black text-emerald-600">{{ formatCurrency(donation.amount) }}</p>
                                </div>

                                <p v-if="!recentDonations.length" class="text-center text-sm font-medium text-slate-500 py-4 bg-slate-50 rounded-2xl">Jadilah penyumbang pertama!</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Related Infaq -->
            <div v-if="relatedInfaqs.length" class="mt-12 bg-white rounded-3xl border border-gray-100 p-6 md:p-8 shadow-sm">
                <h2 class="text-xl font-black text-slate-900 mb-6">Saranan Kempen Lainnya</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6">
                    <Link v-for="item in relatedInfaqs" :key="item.id" :href="item.public_url" class="group block overflow-hidden rounded-2xl border border-slate-100 bg-slate-50 hover:shadow-md transition">
                        <div class="aspect-video bg-gray-200 relative overflow-hidden">
                            <img v-if="item.image_path" :src="item.image_path" class="w-full h-full object-cover transition duration-500 group-hover:scale-110">
                        </div>
                        <div class="p-4">
                            <h3 class="font-bold text-slate-800 line-clamp-2 mb-2 group-hover:text-emerald-600 transition">{{ item.title }}</h3>
                            <p class="text-sm font-bold text-emerald-600">{{ formatCurrency(item.collected_amount) }} Terkumpul</p>
                        </div>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

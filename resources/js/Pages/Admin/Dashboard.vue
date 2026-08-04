<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ExportCsvButton from '@/Components/ExportCsvButton.vue';
import { t, locale } from '@/i18n';

const props = defineProps({
    organization: {
        type: Object,
        required: true,
    },
    overview: {
        type: Object,
        required: true,
    },
    managementLinks: {
        type: Object,
        required: true,
    },
    campaigns: {
        type: Array,
        default: () => [],
    },
});

const isManagementView = computed(() => props.organization?.slug === 'management');
const chartMax = computed(() => {
    const values = (props.overview?.program_chart ?? []).map((item) => Number(item.value ?? 0));
    return Math.max(1, ...values);
});

const createCampaignForm = useForm({
    title: '',
    description: '',
    target_amount: '',
    status: 'active',
});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

function formatTrend(value) {
    const number = Number(value ?? 0);
    const sign = number > 0 ? '+' : '';
    return `${sign}${number}%`;
}

function formatRelativeTime(value) {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    const diffInMs = Date.now() - date.getTime();
    const diffInMinutes = Math.floor(diffInMs / (1000 * 60));

    if (diffInMinutes < 1) return t('baru sahaja');
    if (diffInMinutes < 60) return t('{n} min lalu', { n: diffInMinutes });

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return t('{n} jam lalu', { n: diffInHours });

    const diffInDays = Math.floor(diffInHours / 24);
    return t('{n} hari lalu', { n: diffInDays });
}

function alertDescription(alert) {
    if (alert.key === 'pending_bookings') {
        return t('{n} tempahan masih berstatus pending.', { n: alert.count });
    }
    if (alert.key === 'fees_due') {
        return t('{n} ahli belum membuat bayaran yuran untuk tahun ini.', { n: alert.count });
    }
    if (alert.key === 'no_programs') {
        return t('Tiada program berjadual bulan ini. Pertimbangkan perancangan segera.');
    }
    return alert.description;
}

function activityTone(type = '') {
    if (type === 'payment') return 'border-emerald-100 bg-emerald-50/70';
    if (type === 'booking') return 'border-amber-100 bg-amber-50/70';
    return 'border-indigo-100 bg-indigo-50/70';
}

function submitCampaign() {
    createCampaignForm.post(route('admin.campaigns.store'), {
        preserveScroll: true,
        onSuccess: () => createCampaignForm.reset(),
    });
}
</script>

<template>
    <Head :title="locale === 'en' ? 'Admin Dashboard' : 'Dashboard Pentadbir'" />

    <AppLayout>
        <template #header>{{ t('Dashboard Pentadbir') }}</template>

        <div class="relative mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-3xl">
                <div class="absolute -top-20 -left-20 h-56 w-56 rounded-full bg-indigo-200/40 blur-3xl"></div>
                <div class="absolute top-1/2 -right-10 h-56 w-56 rounded-full bg-emerald-200/40 blur-3xl"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4">

                <section class="md:col-span-2 rounded-3xl border border-white/25 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 p-4 text-white shadow-lg sm:p-5 md:p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-indigo-200">{{ t('Paparan Utama') }}</p>
                    <h2 class="mt-2 text-xl font-black text-white sm:text-2xl">{{ organization?.name }}</h2>
                    <p class="mt-1 text-sm text-indigo-100/90">{{ t('Pengurusan ahli dan program organisasi.') }}</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">{{ t('Jumlah Ahli') }}</p>
                            <p class="mt-1 text-xl font-black text-white sm:text-2xl">{{ overview.total_members }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">{{ t('Yuran Bulan Ini') }}</p>
                            <p class="mt-1 text-xl font-black text-emerald-200">{{ formatCurrency(overview.fees_collected_month) }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">{{ t('Ahli Aktif') }}</p>
                            <p class="mt-1 text-xl font-black text-green-300 sm:text-2xl">{{ overview.active_members ?? '—' }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">{{ t('Tidak Aktif') }}</p>
                            <p class="mt-1 text-xl font-black text-amber-300 sm:text-2xl">{{ overview.inactive_members ?? '—' }}</p>
                        </div>
                        <div class="col-span-2 rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4 md:col-span-1">
                            <p class="text-xs text-indigo-100/80">{{ t('Jumlah Program') }}</p>
                            <p class="mt-1 text-xl font-black text-cyan-200 sm:text-2xl">{{ overview.total_programs }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Pengurusan Ahli') }}</p>
                    <div class="mt-3 space-y-2">
                        <Link :href="managementLinks.information_hub_manage_url" class="block rounded-xl bg-gray-900 px-3 py-2 text-center text-xs font-semibold text-white hover:bg-gray-800">
                            {{ t('Pengurusan Ahli') }}
                        </Link>
                        <Link :href="managementLinks.fees_members_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Yuran Ahli') }}
                        </Link>
                        <Link :href="managementLinks.create_program_url || managementLinks.create_event_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Tambah Program Baharu') }}
                        </Link>
                        <Link :href="managementLinks.create_program_url || managementLinks.create_event_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Urus Program') }}
                        </Link>
                        <Link v-if="isManagementView" :href="managementLinks.infaq_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Urus Infaq') }}
                        </Link>
                        <Link v-if="isManagementView && managementLinks.banners_url" :href="managementLinks.banners_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Urus Banner') }}
                        </Link>
                        <Link :href="managementLinks.information_hub_manage_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Urus Pusat Maklumat') }}
                        </Link>
                        <Link :href="managementLinks.usrah_manage_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Urus Usrah') }}
                        </Link>
                        <Link :href="managementLinks.broadcasts_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Hantar Siaran') }}
                        </Link>
                        <Link :href="managementLinks.directory_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            {{ t('Lihat Direktori') }}
                        </Link>
                        <button
                            v-if="!isManagementView"
                            @click="document.getElementById('create-campaign')?.scrollIntoView({ behavior: 'smooth' })"
                            class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            {{ t('Cipta Program') }}
                        </button>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Pecahan Program') }}</p>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="item in overview.program_chart"
                            :key="item.label"
                            class="rounded-2xl bg-gray-50 p-3"
                        >
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
                                <span>{{ t(item.label) }}</span>
                                <span>{{ item.value }}</span>
                            </div>
                            <div class="mt-1 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                <div
                                    class="h-full rounded-full bg-indigo-500"
                                    :style="{ width: ((Number(item.value || 0) / chartMax) * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Sorotan Terkini') }}</p>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">LIVE</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-emerald-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">{{ t('Ahli Baharu (30h)') }}</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.new_members_30d ?? 0 }}</p>
                            <p class="mt-1 text-[11px] font-semibold" :class="Number(overview.new_members_trend_percent) >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                {{ formatTrend(overview.new_members_trend_percent) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-indigo-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">{{ t('Program Bulan Ini') }}</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.events_this_month ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">{{ t('Kalender semasa') }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-amber-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">{{ t('Tempahan Pending') }}</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.pending_facility_bookings ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">{{ t('Perlu tindakan admin') }}</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-rose-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">{{ t('Yuran Tertunggak') }}</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.fees_due_count ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">{{ t('Tahun semasa') }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Amaran Tindakan') }}</p>
                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="(alert, index) in overview.alerts"
                            :key="index"
                            class="rounded-2xl border p-3"
                            :class="alert.type === 'high' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'"
                        >
                            <p class="text-sm font-bold" :class="alert.type === 'high' ? 'text-red-700' : 'text-amber-700'">{{ t(alert.title) }}</p>
                            <p class="mt-1 text-xs" :class="alert.type === 'high' ? 'text-red-600' : 'text-amber-700'">{{ alertDescription(alert) }}</p>
                        </div>
                        <div v-if="!(overview.alerts?.length)" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-sm font-bold text-emerald-700">{{ t('Semua Stabil') }}</p>
                            <p class="mt-1 text-xs text-emerald-700">{{ t('Tiada isu kritikal dikesan buat masa ini.') }}</p>
                        </div>
                    </div>
                </section>

                <!-- Per-Organization Member Count (Superadmin only) -->
                <section v-if="isManagementView && overview.org_member_counts?.length" class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Ahli Mengikut Organisasi') }}</p>
                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div v-for="org in overview.org_member_counts" :key="org.id" class="rounded-2xl border p-3" :style="{ borderColor: (org.color_theme ?? '#6b7280') + '40', backgroundColor: (org.color_theme ?? '#6b7280') + '10' }">
                            <p class="text-sm font-bold text-gray-900">{{ org.name }}</p>
                            <p class="mt-1 text-2xl font-black" :style="{ color: org.color_theme ?? '#6b7280' }">{{ org.member_count }}</p>
                            <p class="text-[11px] text-gray-500">{{ t('ahli berdaftar') }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Jumlah Ahli Mengikut Negeri') }}</p>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">{{ overview.members_by_state?.length ?? 0 }} {{ t('negeri') }}</span>
                            <a :href="route('admin.members.export.states')" class="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-gray-600 hover:bg-gray-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 12l-3-3m3 3l3-3M4 20h16"/></svg>
                                {{ t('Muat Turun Laporan') }}
                            </a>
                        </div>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="(item, index) in overview.members_by_state"
                            :key="item.state"
                            class="rounded-2xl border p-3"
                            :class="index % 4 === 0 ? 'border-indigo-100 bg-indigo-50/60' : index % 4 === 1 ? 'border-emerald-100 bg-emerald-50/60' : index % 4 === 2 ? 'border-amber-100 bg-amber-50/60' : 'border-rose-100 bg-rose-50/60'"
                        >
                            <p class="text-sm font-bold text-gray-900">{{ t(item.state) }}</p>
                            <p class="mt-1 text-2xl font-black" :class="index % 4 === 0 ? 'text-indigo-700' : index % 4 === 1 ? 'text-emerald-700' : index % 4 === 2 ? 'text-amber-700' : 'text-rose-700'">{{ item.count }}</p>
                            <p class="text-[11px] text-gray-500">{{ t('ahli') }}</p>
                        </div>
                    </div>
                    <p v-if="!(overview.members_by_state?.length)" class="mt-3 rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                        {{ t('Belum ada data ahli untuk dipaparkan.') }}
                    </p>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Aktiviti Terkini') }}</p>
                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="activity in overview.recent_activities"
                            :key="activity.id"
                            class="rounded-2xl border p-3"
                            :class="activityTone(activity.type)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ t(activity.title) }}</p>
                                    <p class="text-xs text-gray-600">{{ activity.description }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-500">
                                    {{ formatRelativeTime(activity.created_at) }}
                                </span>
                            </div>
                        </div>
                        <p v-if="!(overview.recent_activities?.length)" class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                            {{ t('Belum ada aktiviti direkodkan.') }}
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Senarai Ahli Keseluruhan') }}</p>
                            <p class="mt-1 text-sm text-gray-500">{{ t('Eksport senarai ahli untuk analisis dan pelaporan.') }}</p>
                        </div>
                        <ExportCsvButton :href="route('admin.members.export')" />
                    </div>
                </section>

                <section v-if="!isManagementView" id="create-campaign" class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">{{ t('Cipta Kempen') }}</p>
                    <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitCampaign">
                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">{{ t('Tajuk') }}</label>
                            <input v-model="createCampaignForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="createCampaignForm.errors.title" class="mt-1 text-xs text-red-500">{{ createCampaignForm.errors.title }}</p>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">{{ t('Jumlah Sasaran') }}</label>
                            <input v-model="createCampaignForm.target_amount" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="createCampaignForm.errors.target_amount" class="mt-1 text-xs text-red-500">{{ createCampaignForm.errors.target_amount }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">{{ t('Penerangan') }}</label>
                            <textarea v-model="createCampaignForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">{{ t('Status') }}</label>
                            <select v-model="createCampaignForm.status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                <option value="draft">{{ t('Draf') }}</option>
                                <option value="active">{{ t('Aktif') }}</option>
                                <option value="closed">{{ t('Tutup') }}</option>
                            </select>
                        </div>

                        <div class="md:col-span-1 flex items-end">
                            <button
                                type="submit"
                                :disabled="createCampaignForm.processing"
                                class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                            >
                                {{ createCampaignForm.processing ? t('Menyimpan...') : t('Simpan Kempen') }}
                            </button>
                        </div>
                    </form>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

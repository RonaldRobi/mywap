<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ExportCsvButton from '@/Components/ExportCsvButton.vue';

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
});

const isManagementView = computed(() => props.organization?.slug === 'management');
const chartMax = computed(() => {
    const values = (props.overview?.program_chart ?? []).map((item) => Number(item.value ?? 0));
    return Math.max(1, ...values);
});
const branchMax = computed(() => {
    const values = (props.overview?.top_branches ?? []).map((item) => Number(item.members_count ?? 0));
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

    if (diffInMinutes < 1) return 'baru sahaja';
    if (diffInMinutes < 60) return `${diffInMinutes} min lalu`;

    const diffInHours = Math.floor(diffInMinutes / 60);
    if (diffInHours < 24) return `${diffInHours} jam lalu`;

    const diffInDays = Math.floor(diffInHours / 24);
    return `${diffInDays} hari lalu`;
}

function branchTone(organizationName = '', index = 0) {
    const label = String(organizationName).toLowerCase();

    if (label.includes('pkpim')) {
        return index === 0
            ? 'border-indigo-200 bg-gradient-to-r from-indigo-50 to-white'
            : 'border-indigo-100 bg-indigo-50/60';
    }

    if (label.includes('wadah')) {
        return index === 0
            ? 'border-amber-200 bg-gradient-to-r from-amber-50 to-white'
            : 'border-amber-100 bg-amber-50/60';
    }

    return index === 0
        ? 'border-emerald-200 bg-gradient-to-r from-emerald-50 to-white'
        : 'border-emerald-100 bg-emerald-50/60';
}

function branchBadgeTone(organizationName = '') {
    const label = String(organizationName).toLowerCase();

    if (label.includes('pkpim')) {
        return 'bg-indigo-100 text-indigo-700';
    }

    if (label.includes('wadah')) {
        return 'bg-amber-100 text-amber-700';
    }

    return 'bg-emerald-100 text-emerald-700';
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
    <Head title="Admin Dashboard" />

    <AppLayout>
        <template #header>Dashboard Pentadbir</template>

        <div class="relative mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-3xl">
                <div class="absolute -top-20 -left-20 h-56 w-56 rounded-full bg-indigo-200/40 blur-3xl"></div>
                <div class="absolute top-1/2 -right-10 h-56 w-56 rounded-full bg-emerald-200/40 blur-3xl"></div>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3 lg:grid-cols-4">

                <section class="md:col-span-2 rounded-3xl border border-white/25 bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-900 p-4 text-white shadow-lg sm:p-5 md:p-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-indigo-200">Overview</p>
                    <h2 class="mt-2 text-xl font-black text-white sm:text-2xl">{{ organization?.name }}</h2>
                    <p class="mt-1 text-sm text-indigo-100/90">Pengurusan ahli dan program organisasi.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">Jumlah Ahli</p>
                            <p class="mt-1 text-xl font-black text-white sm:text-2xl">{{ overview.total_members }}</p>
                        </div>
                        <div class="rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4">
                            <p class="text-xs text-indigo-100/80">Yuran Bulan Ini</p>
                            <p class="mt-1 text-xl font-black text-emerald-200">{{ formatCurrency(overview.fees_collected_month) }}</p>
                        </div>
                        <div class="col-span-2 rounded-2xl border border-white/15 bg-white/10 p-3 backdrop-blur-sm sm:p-4 md:col-span-1">
                            <p class="text-xs text-indigo-100/80">Jumlah Program</p>
                            <p class="mt-1 text-xl font-black text-cyan-200 sm:text-2xl">{{ overview.total_programs }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Pengurusan</p>
                    <div class="mt-3 space-y-2">
                        <Link :href="managementLinks.create_program_url || managementLinks.create_event_url" class="block rounded-xl bg-gray-900 px-3 py-2 text-center text-xs font-semibold text-white hover:bg-gray-800">
                            Add New Program
                        </Link>
                        <Link :href="managementLinks.create_program_url || managementLinks.create_event_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Manage Program
                        </Link>
                        <Link v-if="isManagementView" :href="managementLinks.infaq_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Manage Infaq
                        </Link>
                        <Link v-if="isManagementView && managementLinks.banners_url" :href="managementLinks.banners_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Manage Banner
                        </Link>
                        <Link :href="managementLinks.information_hub_manage_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Manage Info Hub
                        </Link>
                        <Link :href="managementLinks.usrah_manage_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Manage Usrah
                        </Link>
                        <Link :href="managementLinks.broadcasts_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Send Broadcast
                        </Link>
                        <Link :href="managementLinks.directory_url" class="block rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            View Directory
                        </Link>
                        <button
                            v-if="!isManagementView"
                            @click="document.getElementById('create-campaign')?.scrollIntoView({ behavior: 'smooth' })"
                            class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Create Program
                        </button>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Program Breakdown</p>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="item in overview.program_chart"
                            :key="item.label"
                            class="rounded-2xl bg-gray-50 p-3"
                        >
                            <div class="flex items-center justify-between text-xs font-semibold text-gray-700">
                                <span>{{ item.label }}</span>
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
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Pulse Monitor</p>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">LIVE</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-emerald-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">Ahli Baharu (30h)</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.new_members_30d ?? 0 }}</p>
                            <p class="mt-1 text-[11px] font-semibold" :class="Number(overview.new_members_trend_percent) >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                {{ formatTrend(overview.new_members_trend_percent) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-indigo-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">Program Bulan Ini</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.events_this_month ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">Kalender semasa</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-amber-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">Tempahan Pending</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.pending_facility_bookings ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">Perlu tindakan admin</p>
                        </div>
                        <div class="rounded-2xl border border-gray-100 bg-gradient-to-br from-rose-50 to-white p-3">
                            <p class="text-[11px] font-semibold text-gray-500">Yuran Tertunggak</p>
                            <p class="mt-1 text-xl font-black text-gray-900">{{ overview.fees_due_count ?? 0 }}</p>
                            <p class="mt-1 text-[11px] text-gray-500">Tahun semasa</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Amaran Tindakan</p>
                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="(alert, index) in overview.alerts"
                            :key="index"
                            class="rounded-2xl border p-3"
                            :class="alert.type === 'high' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'"
                        >
                            <p class="text-sm font-bold" :class="alert.type === 'high' ? 'text-red-700' : 'text-amber-700'">{{ alert.title }}</p>
                            <p class="mt-1 text-xs" :class="alert.type === 'high' ? 'text-red-600' : 'text-amber-700'">{{ alert.description }}</p>
                        </div>
                        <div v-if="!(overview.alerts?.length)" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-3">
                            <p class="text-sm font-bold text-emerald-700">Semua Stabil</p>
                            <p class="mt-1 text-xs text-emerald-700">Tiada isu kritikal dikesan buat masa ini.</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Cawangan Aktif</p>
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">{{ overview.active_branches ?? 0 }} cawangan</span>
                    </div>
                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="(branch, index) in overview.top_branches"
                            :key="branch.id"
                            class="rounded-2xl border p-3"
                            :class="branchTone(branch.organization_name, index)"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ branch.name }}</p>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wide"
                                            :class="branchBadgeTone(branch.organization_name)"
                                        >
                                            {{ branch.organization_name }}
                                        </span>
                                        <span v-if="branch.state" class="text-[11px] text-gray-500">{{ branch.state }}</span>
                                    </div>
                                </div>
                                <p class="text-sm font-black text-indigo-700">{{ branch.members_count }} ahli</p>
                            </div>
                            <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                <div
                                    class="h-full rounded-full"
                                    :class="branch.organization_name?.toLowerCase().includes('pkpim') ? 'bg-indigo-500' : (branch.organization_name?.toLowerCase().includes('wadah') ? 'bg-amber-500' : 'bg-emerald-500')"
                                    :style="{ width: ((Number(branch.members_count || 0) / branchMax) * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                        <p v-if="!(overview.top_branches?.length)" class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                            Belum ada data cawangan untuk dipaparkan.
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-2">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Aktiviti Terkini</p>
                    <div class="mt-3 space-y-2.5">
                        <div
                            v-for="activity in overview.recent_activities"
                            :key="activity.id"
                            class="rounded-2xl border p-3"
                            :class="activityTone(activity.type)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-bold text-gray-900">{{ activity.title }}</p>
                                    <p class="text-xs text-gray-600">{{ activity.description }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-gray-500">
                                    {{ formatRelativeTime(activity.created_at) }}
                                </span>
                            </div>
                        </div>
                        <p v-if="!(overview.recent_activities?.length)" class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 p-3 text-xs text-gray-500">
                            Belum ada aktiviti direkodkan.
                        </p>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Members</p>
                            <p class="mt-1 text-sm text-gray-500">Eksport senarai ahli untuk analisis dan pelaporan.</p>
                        </div>
                        <ExportCsvButton :href="route('admin.members.export')" />
                    </div>
                </section>

                <section v-if="!isManagementView" id="create-campaign" class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm backdrop-blur-sm sm:p-5 md:col-span-3 lg:col-span-4">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Create Campaign</p>
                    <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitCampaign">
                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Title</label>
                            <input v-model="createCampaignForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="createCampaignForm.errors.title" class="mt-1 text-xs text-red-500">{{ createCampaignForm.errors.title }}</p>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Target Amount</label>
                            <input v-model="createCampaignForm.target_amount" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="createCampaignForm.errors.target_amount" class="mt-1 text-xs text-red-500">{{ createCampaignForm.errors.target_amount }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Description</label>
                            <textarea v-model="createCampaignForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Status</label>
                            <select v-model="createCampaignForm.status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>

                        <div class="md:col-span-1 flex items-end">
                            <button
                                type="submit"
                                :disabled="createCampaignForm.processing"
                                class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                            >
                                {{ createCampaignForm.processing ? 'Menyimpan...' : 'Simpan Kempen' }}
                            </button>
                        </div>
                    </form>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

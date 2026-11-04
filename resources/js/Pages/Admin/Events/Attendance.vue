<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    stats: Object,
    programs: Array,
    organizations: Array,
    filters: Object,
});

const f = reactive({
    search: props.filters?.search || '',
    org: props.filters?.org || '',
});

const activeTab = ref('all');

const tabs = computed(() => {
    const list = props.programs || [];
    return [
        { key: 'all', label: 'Semua', count: list.length },
        { key: 'completed', label: 'Telah Selesai', count: list.filter((p) => p.state === 'completed').length },
        { key: 'ongoing', label: 'Sedang Berjalan', count: list.filter((p) => p.state === 'ongoing').length },
        { key: 'upcoming', label: 'Akan Datang', count: list.filter((p) => p.state === 'upcoming').length },
    ];
});

const visiblePrograms = computed(() => {
    const list = props.programs || [];
    if (activeTab.value === 'all') return list;
    return list.filter((p) => p.state === activeTab.value);
});

const hasFilters = computed(() => !!(f.search || f.org));

function apply() {
    const data = {};
    if (f.search) data.search = f.search;
    if (f.org) data.org = f.org;
    router.get(route('admin.attendance'), data, { preserveState: true, replace: true });
}

function clearFilters() {
    f.search = '';
    f.org = '';
    router.get(route('admin.attendance'), {}, { preserveState: true, replace: true });
}

function progressPct(program) {
    if (!program.registered_count) return 0;
    return Math.round((program.attended_count / program.registered_count) * 100);
}

const stateBadge = {
    completed: { label: 'Telah Selesai', class: 'border-rose-200 bg-rose-50 text-rose-600' },
    ongoing: { label: 'Sedang Berjalan', class: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    upcoming: { label: 'Akan Datang', class: 'border-sky-200 bg-sky-50 text-sky-700' },
};

const statusColor = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-red-50 text-red-600 border-red-200',
};
</script>

<template>
    <Head title="Peserta & Kehadiran" />

    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="mb-6">
                <h1 class="text-2xl font-black text-gray-900 mb-1">Peserta &amp; Kehadiran</h1>
                <p class="text-sm text-gray-500">
                    Pilih program untuk melihat senarai peserta yang mendaftar dan status kehadiran.
                </p>
            </div>

            <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total_programs }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Program</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total_registered }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Jumlah Peserta</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ stats.total_attended }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-500">Hadir</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 shadow-sm">
                    <p class="text-2xl font-black text-amber-600">{{ stats.total_not_attended }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-500">Tidak Hadir</p>
                </div>
            </div>

            <div class="mb-5 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="f.search"
                        type="search"
                        placeholder="Cari nama program"
                        class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                        @keyup.enter="apply()"
                    />
                    <select
                        v-if="organizations.length"
                        v-model="f.org"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                    >
                        <option value="">Semua Organisasi</option>
                        <option v-for="o in organizations" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                    </select>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="apply()"
                            class="flex-1 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                        >
                            Tapis
                        </button>
                        <button
                            v-if="hasFilters"
                            type="button"
                            @click="clearFilters"
                            class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-bold transition"
                    :class="activeTab === tab.key
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="activeTab === tab.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div v-if="visiblePrograms.length === 0" class="rounded-2xl border border-dashed border-gray-200 bg-white p-10 text-center">
                <p class="text-sm font-semibold text-gray-500">Tiada program untuk dipaparkan.</p>
                <p class="mt-1 text-xs text-gray-400">Program akan muncul di sini selepas peserta mula mendaftar.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="p in visiblePrograms"
                    :key="p.id"
                    class="flex flex-col gap-3 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm transition hover:shadow-md"
                >
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="statusColor[p.status] ?? statusColor.draft">
                            {{ p.status_label }}
                        </span>
                        <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="stateBadge[p.state]?.class ?? 'border-gray-200 bg-gray-50 text-gray-600'">
                            {{ stateBadge[p.state]?.label ?? 'Program' }}
                        </span>
                    </div>

                    <div>
                        <h2 class="font-bold leading-snug text-gray-900 line-clamp-2">{{ p.title }}</h2>
                        <p class="mt-1 text-xs text-gray-500">{{ p.start_formatted || 'Tarikh belum ditetapkan' }}</p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ p.organization_name }}<span v-if="p.location_or_link"> &middot; {{ p.location_or_link }}</span>
                        </p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-gray-50 py-2">
                            <p class="text-lg font-black text-gray-900">{{ p.registered_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-gray-400">Peserta</p>
                        </div>
                        <div class="rounded-xl bg-emerald-50 py-2">
                            <p class="text-lg font-black text-emerald-600">{{ p.attended_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-emerald-500">Hadir</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 py-2">
                            <p class="text-lg font-black text-amber-600">{{ p.not_attended_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-amber-500">{{ p.is_ended ? 'Tidak Hadir' : 'Belum Hadir' }}</p>
                        </div>
                    </div>

                    <div>
                        <div class="mb-1 flex items-center justify-between text-[11px] text-gray-400">
                            <span>Kadar kehadiran</span>
                            <span class="font-bold text-gray-600">{{ progressPct(p) }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: progressPct(p) + '%' }"></div>
                        </div>
                    </div>

                    <div class="mt-auto flex flex-col gap-2 pt-1">
                        <a
                            :href="p.registrations_url"
                            class="rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-bold text-gray-700 hover:bg-gray-50"
                        >
                            Senarai Peserta ({{ p.registered_count }})
                        </a>
                        <div class="flex gap-2">
                            <a
                                :href="p.attendance_url"
                                class="flex-1 rounded-xl bg-gray-900 px-3 py-2 text-center text-xs font-bold text-white hover:bg-gray-800"
                            >
                                Urus Kehadiran
                            </a>
                            <a
                                v-if="p.qr_url"
                                :href="p.qr_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                title="Buka QR semak masuk program"
                            >
                                QR
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

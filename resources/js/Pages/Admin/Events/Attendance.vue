<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    registrations: Object, // paginator
    stats: Object,
    programs: Array,
    events: Array,
    organizations: Array,
    filters: Object,
});

// Penapis aktif. Disimpan sebagai state supaya kad statistik boleh
// menetapkan/membatalkan penapis dengan satu klik.
const f = reactive({
    search: props.filters?.search || '',
    event_id: props.filters?.event_id || '',
    org: props.filters?.org || '',
    attendance: props.filters?.attendance || '',
    payment: props.filters?.payment || '',
});

function apply(extra = {}) {
    const data = { ...f, ...extra };
    Object.keys(data).forEach((k) => {
        if (data[k] === '' || data[k] === null || data[k] === undefined) delete data[k];
    });
    router.get(route('admin.attendance'), data, { preserveState: true, replace: true });
}

const statActive = computed(() => ({
    all: !f.attendance && !f.payment,
    hadir: f.attendance === 'hadir',
    pending: f.payment === 'pending',
}));

function setStat(kind) {
    if (kind === 'all') {
        apply({ attendance: '', payment: '' });
    } else if (kind === 'hadir') {
        apply({ attendance: f.attendance === 'hadir' ? '' : 'hadir', payment: '' });
    } else {
        apply({ payment: f.payment === 'pending' ? '' : 'pending', attendance: '' });
    }
}

function selectProgram(program) {
    apply({ event_id: String(f.event_id) === String(program.id) ? '' : String(program.id) });
}

const activeEventTitle = computed(() => {
    if (!f.event_id) return null;
    const program = (props.programs || []).find((p) => String(p.id) === String(f.event_id));
    if (program) return program.title;
    return (props.events || []).find((e) => String(e.id) === String(f.event_id))?.title || null;
});

const hasFilters = computed(() => !!(f.search || f.event_id || f.org || f.attendance || f.payment));

const activeFilterChips = computed(() => {
    const chips = [];
    if (f.search) chips.push({ key: 'search', label: `Carian: "${f.search}"` });
    if (f.org) {
        const org = (props.organizations || []).find((o) => String(o.id) === String(f.org));
        chips.push({ key: 'org', label: `Organisasi: ${org ? org.name : f.org}` });
    }
    if (f.attendance === 'hadir') chips.push({ key: 'attendance', label: 'Kehadiran: Hadir' });
    if (f.attendance === 'tidak_hadir') chips.push({ key: 'attendance', label: 'Kehadiran: Tidak Hadir' });
    if (f.payment === 'pending') chips.push({ key: 'payment', label: 'Bayaran: Menunggu' });
    if (f.payment === 'paid') chips.push({ key: 'payment', label: 'Bayaran: Berjaya' });
    return chips;
});

function clearFilters() {
    f.search = '';
    f.event_id = '';
    f.org = '';
    f.attendance = '';
    f.payment = '';
    apply({ search: '', event_id: '', org: '', attendance: '', payment: '' });
}

const exportQuery = computed(() => {
    const params = new URLSearchParams();
    Object.entries(f).forEach(([key, value]) => {
        if (value) params.append(key, value);
    });
    const query = params.toString();
    return query ? `?${query}` : '';
});

function progressPct(program) {
    if (!program.registered_count) return 0;
    return Math.round((program.attended_count / program.registered_count) * 100);
}

const statusColor = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-red-50 text-red-600 border-red-200',
};
</script>

<template>
    <Head title="Dashboard Kehadiran" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex flex-wrap items-end justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl font-black text-gray-900 mb-1">Dashboard Kehadiran</h1>
                    <p class="text-sm text-gray-500">Ringkasan kehadiran mengikut program. Klik kad untuk menapis senarai peserta.</p>
                </div>
                <div class="flex gap-2">
                    <a :href="route('admin.attendance.export.excel') + exportQuery" class="rounded-xl bg-emerald-600 text-white px-4 py-2 text-sm font-semibold hover:bg-emerald-700">Export Excel</a>
                    <a :href="route('admin.attendance.export.pdf') + exportQuery" class="rounded-xl bg-red-600 text-white px-4 py-2 text-sm font-semibold hover:bg-red-700">Export PDF</a>
                </div>
            </div>

            <!-- Stats (boleh klik untuk tapis) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
                <button
                    type="button"
                    @click="setStat('all')"
                    class="rounded-3xl bg-white border p-5 text-center shadow-sm transition hover:shadow-md"
                    :class="statActive.all ? 'border-gray-900 ring-2 ring-gray-900/10' : 'border-gray-100'"
                >
                    <p class="text-3xl font-black text-gray-900">{{ stats.total_registered }}</p>
                    <p class="text-xs text-gray-400 font-semibold uppercase mt-1">Jumlah Daftar</p>
                </button>
                <button
                    type="button"
                    @click="setStat('hadir')"
                    class="rounded-3xl bg-emerald-50 border p-5 text-center shadow-sm transition hover:shadow-md"
                    :class="statActive.hadir ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-emerald-100'"
                >
                    <p class="text-3xl font-black text-emerald-600">{{ stats.total_attended }}</p>
                    <p class="text-xs text-emerald-500 font-semibold uppercase mt-1">Hadir</p>
                </button>
                <button
                    type="button"
                    @click="setStat('pending')"
                    class="rounded-3xl bg-amber-50 border p-5 text-center shadow-sm transition hover:shadow-md"
                    :class="statActive.pending ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-amber-100'"
                >
                    <p class="text-3xl font-black text-amber-600">{{ stats.total_pending_payment }}</p>
                    <p class="text-xs text-amber-500 font-semibold uppercase mt-1">Bayaran Menunggu</p>
                </button>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl bg-white border border-gray-100 p-4 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <input
                    v-model="f.search"
                    placeholder="Cari nama / no reg..."
                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                    @keyup.enter="apply()"
                />
                <select v-model="f.event_id" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Program</option>
                    <option v-for="e in events" :key="e.id" :value="String(e.id)">{{ e.title }}</option>
                </select>
                <select v-if="organizations.length" v-model="f.org" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="o in organizations" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                </select>
                <div class="flex gap-2">
                    <button @click="apply()" class="flex-1 rounded-xl bg-gray-900 text-white px-3 py-2 text-sm font-semibold">Tapis</button>
                    <button v-if="hasFilters" @click="clearFilters" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Reset</button>
                </div>
            </div>

            <!-- Ringkasan program -->
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-black text-gray-900">Program</h2>
                <span class="text-xs text-gray-400 font-semibold">{{ programs.length }} program</span>
            </div>

            <div v-if="programs.length === 0" class="rounded-3xl bg-white border border-gray-100 p-10 text-center mb-6">
                <p class="text-sm text-gray-400">Tiada program untuk penapis ini.</p>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <div
                    v-for="p in programs"
                    :key="p.id"
                    @click="selectProgram(p)"
                    class="rounded-3xl bg-white border shadow-sm p-4 flex flex-col gap-3 cursor-pointer transition hover:shadow-md"
                    :class="String(f.event_id) === String(p.id) ? 'border-indigo-500 ring-2 ring-indigo-500/20' : 'border-gray-100'"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="statusColor[p.status] ?? statusColor.draft">
                                {{ p.status_label }}
                            </span>
                        </div>
                        <span v-if="String(f.event_id) === String(p.id)" class="text-[10px] font-bold uppercase tracking-wide text-indigo-600">Dipilih</span>
                    </div>

                    <div>
                        <h3 class="font-bold text-gray-900 leading-snug line-clamp-2">{{ p.title }}</h3>
                        <p class="text-xs text-gray-500 mt-1">{{ p.start_formatted }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ p.organization_name }}<span v-if="p.location_or_link"> · {{ p.location_or_link }}</span></p>
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-2xl bg-gray-50 py-2">
                            <p class="text-lg font-black text-gray-900">{{ p.registered_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-gray-400">Daftar</p>
                        </div>
                        <div class="rounded-2xl bg-emerald-50 py-2">
                            <p class="text-lg font-black text-emerald-600">{{ p.attended_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-emerald-500">Hadir</p>
                        </div>
                        <div class="rounded-2xl bg-amber-50 py-2">
                            <p class="text-lg font-black text-amber-600">{{ p.pending_payment_count }}</p>
                            <p class="text-[10px] font-semibold uppercase text-amber-500">Menunggu</p>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between text-[11px] text-gray-400 mb-1">
                            <span>Kehadiran</span>
                            <span class="font-bold text-gray-600">{{ progressPct(p) }}%</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full rounded-full bg-emerald-500" :style="{ width: progressPct(p) + '%' }"></div>
                        </div>
                    </div>

                    <div class="mt-auto flex gap-2 pt-1">
                        <button
                            @click.stop="selectProgram(p)"
                            class="flex-1 rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-800"
                        >
                            Lihat Peserta
                        </button>
                        <a
                            :href="p.qr_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            @click.stop
                            class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            QR
                        </a>
                        <a
                            :href="p.registrations_url"
                            @click.stop
                            class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Urus
                        </a>
                    </div>
                </div>
            </div>

            <!-- Senarai peserta -->
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-black text-gray-900">
                    Peserta
                    <span v-if="activeEventTitle" class="text-sm font-semibold text-gray-400">· {{ activeEventTitle }}</span>
                </h2>
                <div v-if="activeFilterChips.length" class="flex flex-wrap items-center gap-1.5">
                    <span
                        v-for="chip in activeFilterChips"
                        :key="chip.key"
                        class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-[11px] font-semibold text-gray-600"
                    >
                        {{ chip.label }}
                    </span>
                    <button @click="clearFilters" class="text-[11px] font-bold text-indigo-600 hover:text-indigo-700">Kosongkan</button>
                </div>
            </div>

            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-x-auto">
                <table class="w-full text-sm min-w-[800px]">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3">Program</th>
                            <th class="px-4 py-3">Bayaran</th>
                            <th class="px-4 py-3">Kehadiran</th>
                            <th class="px-4 py-3">Masa</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ r.registration_no }}<span v-if="r.member_no"> · {{ r.member_no }}</span></p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.organization_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.event_title || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="r.payment_status === 'successful' || r.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ r.payment_status === 'successful' || r.payment_status === 'paid' ? 'Berjaya' : 'Menunggu' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-bold" :class="r.attended ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-gray-200 bg-gray-50 text-gray-500'">
                                    {{ r.attended ? 'Hadir' : 'Tidak Hadir' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ r.attended_at || '—' }}</td>
                        </tr>
                        <tr v-if="registrations.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">Tiada peserta untuk penapis ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="registrations.links && registrations.links.length > 3" class="flex flex-wrap justify-center gap-1 mt-4">
                <template v-for="(link, i) in registrations.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        preserve-scroll
                        class="px-3 py-1.5 rounded-lg border text-sm"
                        :class="link.active ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="px-3 py-1.5 rounded-lg border text-sm bg-gray-50 text-gray-300 border-gray-100"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

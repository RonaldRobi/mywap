<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
    stats: Object,
    registrations: Object,
    audit_trail: { type: Array, default: () => [] },
    filters: Object,
    qr_url: String,
});

const f = reactive({
    search: props.filters?.search || '',
    type: props.filters?.type || '',
    attendance: props.filters?.attendance || 'hadir',
});

const selected = ref([]);

const notAttendedLabel = computed(() => (props.event?.is_ended ? 'Tidak Hadir' : 'Belum Hadir'));

const allVisibleSelected = computed(() => {
    const rows = props.registrations?.data ?? [];
    return rows.length > 0 && selected.value.length === rows.length;
});

function toggleAll() {
    const rows = props.registrations?.data ?? [];
    selected.value = allVisibleSelected.value ? [] : rows.map((row) => row.id);
}

const tabs = computed(() => [
    { key: 'hadir', label: 'Hadir', count: props.stats.total_attended, class: 'emerald' },
    { key: 'tidak_hadir', label: notAttendedLabel.value, count: props.stats.total_not_attended, class: 'amber' },
    { key: 'all', label: 'Semua', count: props.stats.total_registered, class: 'gray' },
]);

function apply(extra = {}) {
    const data = { ...f, ...extra };
    Object.keys(data).forEach((k) => {
        if (data[k] === '' || data[k] === null || data[k] === undefined) delete data[k];
    });
    selected.value = [];
    router.get(route('admin.attendance.show', props.event.id), data, {
        preserveState: true,
        replace: true,
    });
}

function setTab(key) {
    f.attendance = key;
    apply({ attendance: key });
}

const hasFilters = computed(() => !!(f.search || f.type || f.attendance !== 'hadir'));

function clearFilters() {
    f.search = '';
    f.type = '';
    f.attendance = 'hadir';
    apply({ search: '', type: '', attendance: 'hadir' });
}

function toggle(registration) {
    router.post(
        route('admin.attendance.toggle', { event: props.event.id, registration: registration.id }),
        { present: !registration.attended },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = selected.value.filter((id) => id !== registration.id);
            },
        },
    );
}

function bulk(present) {
    if (!selected.value.length) return;

    if (present && !window.confirm(
        'Tandakan peserta terpilih sebagai hadir?\n\n'
        + 'Gunakan ini hanya untuk pembetulan. Jangan tanda semua peserta hadir sebelum program bermula — '
        + 'kehadiran sepatutnya direkod melalui imbasan QR/pautan pada hari program supaya rekod kekal tepat.',
    )) {
        return;
    }

    router.post(
        route('admin.attendance.bulk', props.event.id),
        { present, registration_ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
            },
        },
    );
}

const showAddModal = ref(false);
const addModalEl = ref(null);

const memberSearchQuery = ref('');
const memberSearchResults = ref([]);
const memberSearchLoading = ref(false);
const memberSearchOpen = ref(false);
let memberSearchTimer = null;

function onMemberSearchInput(value) {
    clearTimeout(memberSearchTimer);
    memberSearchQuery.value = value;

    if (value.trim().length < 2) {
        memberSearchResults.value = [];
        memberSearchOpen.value = false;
        return;
    }

    memberSearchLoading.value = true;
    memberSearchTimer = setTimeout(() => {
        axios.get('/api/members/search', { params: { q: value } })
            .then((res) => {
                memberSearchResults.value = res.data || [];
                memberSearchOpen.value = memberSearchResults.value.length > 0;
            })
            .catch(() => {
                memberSearchResults.value = [];
                memberSearchOpen.value = false;
            })
            .finally(() => {
                memberSearchLoading.value = false;
            });
    }, 300);
}

function pickMember(member) {
    addForm.name = member.name ?? '';
    addForm.ic_number = member.ic_number ?? '';
    addForm.phone = member.phone ?? '';
    addForm.email = member.email ?? '';
    addForm.member_no = member.member_no ?? '';
    memberSearchQuery.value = member.name ?? '';
    memberSearchOpen.value = false;
}

const addForm = useForm({
    name: '',
    ic_number: '',
    phone: '',
    email: '',
    member_no: '',
    mark_present: true,
});

function openAdd() {
    addForm.reset();
    addForm.clearErrors();
    memberSearchQuery.value = '';
    memberSearchResults.value = [];
    memberSearchOpen.value = false;
    showAddModal.value = true;
    nextTick(() => addModalEl.value?.focus());
}

function closeAdd() {
    if (addForm.processing) return;
    showAddModal.value = false;
}

function submitAdd() {
    addForm.post(route('admin.attendance.participants.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (!addForm.hasErrors) {
                showAddModal.value = false;
                addForm.reset();
            }
        },
    });
}

const scanIdentifier = ref('');
const scanInputEl = ref(null);
const scanBusy = ref(false);
const scanResult = ref(null);

function focusScan() {
    nextTick(() => scanInputEl.value?.focus());
}

async function submitScan() {
    const identifier = scanIdentifier.value.trim();
    if (!identifier || scanBusy.value) return;

    scanBusy.value = true;
    scanResult.value = null;

    try {
        const { data } = await axios.post(
            route('admin.attendance.scan', props.event.id),
            { identifier },
        );

        scanResult.value = {
            ok: true,
            name: data.registration?.name ?? '',
            memberNo: data.registration?.member_no ?? '',
        };
        scanIdentifier.value = '';
        router.reload({
            only: ['registrations', 'stats', 'audit_trail'],
            onFinish: focusScan,
        });
    } catch (error) {
        const payload = error?.response?.data ?? {};
        scanResult.value = {
            ok: false,
            message: payload.message
                || payload.errors?.identifier?.[0]
                || 'Imbasan tidak dikenali. Cuba lagi.',
        };
        scanIdentifier.value = '';
        focusScan();
    } finally {
        scanBusy.value = false;
    }
}

onMounted(focusScan);

const exportQuery = computed(() => {
    const params = new URLSearchParams({ event_id: props.event.id });
    if (f.attendance) params.set('attendance', f.attendance);
    if (f.search) params.set('search', f.search);
    return `?${params.toString()}`;
});

const closesAtLabel = computed(() => {
    if (!props.event.attendance_closes_at) return null;
    return new Date(props.event.attendance_closes_at).toLocaleString('ms-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});

const opensAtLabel = computed(() => {
    if (!props.event.attendance_opens_at) return null;
    return new Date(props.event.attendance_opens_at).toLocaleString('ms-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
});

const qrWindowStatus = computed(() => {
    if (!props.event.attendance_opens_at && !props.event.attendance_closes_at) return null;

    if (props.event.attendance_window_open) {
        return {
            label: closesAtLabel.value ? `QR boleh diimbas sehingga ${closesAtLabel.value}` : 'QR boleh diimbas sekarang',
            class: 'text-emerald-600',
        };
    }

    const opensAt = props.event.attendance_opens_at ? new Date(props.event.attendance_opens_at) : null;
    if (opensAt && opensAt.getTime() > Date.now()) {
        const range = [opensAtLabel.value, closesAtLabel.value].filter(Boolean).join(' hingga ');
        return {
            label: `QR belum dibuka — boleh diimbas ${range}`,
            class: 'text-sky-600',
        };
    }

    return {
        label: closesAtLabel.value ? `QR telah tamat tempoh (${closesAtLabel.value})` : 'QR telah tamat tempoh',
        class: 'text-rose-500',
    };
});

const methodLabel = {
    member: 'Imbas QR (ahli)',
    walkin: 'Walk-in',
    guest: 'Imbas QR (tetamu)',
    manual: 'Manual admin',
};

const typeBadge = (member) => (member
    ? 'border-sky-200 bg-sky-50 text-sky-700'
    : 'border-gray-200 bg-gray-50 text-gray-600');

function auditMeta(action) {
    return {
        attendance_manual_set: { label: 'Ditanda hadir', class: 'bg-emerald-50 text-emerald-700' },
        attendance_bulk_set: { label: 'Pukal hadir', class: 'bg-emerald-50 text-emerald-700' },
        attendance_participant_add: { label: 'Peserta ditambah', class: 'bg-sky-50 text-sky-700' },
        attendance_manual_clear: { label: 'Dibatalkan', class: 'bg-rose-50 text-rose-600' },
        attendance_bulk_clear: { label: 'Pukal dibatal', class: 'bg-rose-50 text-rose-600' },
    }[action] ?? { label: 'Kemaskini', class: 'bg-gray-50 text-gray-600' };
}
</script>

<template>
    <Head :title="`Kehadiran: ${event.title}`" />

    <AppLayout>
        <div class="mx-auto max-w-7xl px-4 py-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <a
                        :href="route('admin.attendance')"
                        class="text-xs font-semibold text-gray-500 hover:text-gray-800"
                    >
                        &larr; Kembali ke senarai program
                    </a>
                    <h1 class="mt-1 text-2xl font-black leading-snug text-gray-900">{{ event.title }}</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        {{ event.start_formatted }}<span v-if="event.location_or_link"> &middot; {{ event.location_or_link }}</span>
                    </p>
                    <p class="text-xs text-gray-400">{{ event.organization_name }}</p>
                    <p v-if="qrWindowStatus" class="mt-1 text-[11px] font-medium" :class="qrWindowStatus.class">
                        {{ qrWindowStatus.label }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a
                        :href="event.registrations_url"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Senarai Peserta
                    </a>
                    <button
                        type="button"
                        @click="openAdd"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                    >
                        Tambah Peserta
                    </button>
                    <a
                        :href="route('admin.attendance.export.excel') + exportQuery"
                        class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                    >
                        Export Excel
                    </a>
                    <a
                        :href="route('admin.attendance.export.pdf') + exportQuery"
                        class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
                    >
                        Export PDF
                    </a>
                    <a
                        v-if="qr_url"
                        :href="qr_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        QR Masuk
                    </a>
                </div>
            </div>

            <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                Kehadiran dikemas kini secara automatik apabila peserta mengesahkan kehadiran melalui QR atau pautan pada
                hari program. Peserta yang telah mendaftar tetapi belum check-in akan dipaparkan sebagai
                <strong>{{ notAttendedLabel }}</strong>.
            </div>

            <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <p class="font-semibold">Elakkan menanda semua peserta hadir secara pukal sebelum program bermula.</p>
                <p class="mt-1">
                    Kehadiran sepatutnya direkod apabila peserta mengesahkan melalui QR atau pautan pada hari program. Tanda
                    manual hanya untuk pembetulan. Menanda hadir lebih awal boleh menjadikan rekod tidak tepat dan menyukarkan
                    semakan pada hari acara.
                </p>
            </div>

            <div
                v-if="$page.props.flash?.success"
                class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700"
            >
                {{ $page.props.flash.success }}
            </div>

            <div
                v-if="$page.props.flash?.error"
                class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700"
            >
                {{ $page.props.flash.error }}
            </div>

            <div class="mb-6 grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total_registered }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Peserta</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ stats.total_attended }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-500">Hadir</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-amber-600">{{ stats.total_not_attended }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-500">{{ notAttendedLabel }}</p>
                </div>
            </div>

            <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label for="attendance-scan" class="mb-1 block text-xs font-bold text-gray-700">
                            Imbas kad ahli
                        </label>
                        <input
                            id="attendance-scan"
                            ref="scanInputEl"
                            v-model="scanIdentifier"
                            type="text"
                            autocomplete="off"
                            spellcheck="false"
                            placeholder="Scan QR kad ahli, atau taip No. ahli / No. IC / telefon"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 font-mono text-sm focus:ring-0"
                            :disabled="scanBusy"
                            @keyup.enter="submitScan"
                        />
                    </div>
                    <button
                        type="button"
                        @click="submitScan"
                        :disabled="scanBusy || !scanIdentifier.trim()"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50"
                    >
                        {{ scanBusy ? 'Merekod...' : 'Rekod Kehadiran' }}
                    </button>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">
                    Sesuai untuk USB scanner di pintu masuk. Setiap imbasan terus merekod kehadiran dan menyegarkan senarai di bawah.
                </p>
                <div
                    v-if="scanResult"
                    role="status"
                    aria-live="polite"
                    class="mt-3 rounded-xl border px-3 py-2 text-sm font-semibold"
                    :class="scanResult.ok
                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                        : 'border-rose-200 bg-rose-50 text-rose-700'"
                >
                    <template v-if="scanResult.ok">
                        Hadir direkod: {{ scanResult.name }}<span v-if="scanResult.memberNo"> &middot; {{ scanResult.memberNo }}</span>
                    </template>
                    <template v-else>{{ scanResult.message }}</template>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-xs font-bold transition"
                    :class="f.attendance === tab.key
                        ? 'border-gray-900 bg-gray-900 text-white'
                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    @click="setTab(tab.key)"
                >
                    {{ tab.label }}
                    <span
                        class="rounded-full px-1.5 py-0.5 text-[10px]"
                        :class="f.attendance === tab.key ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <input
                        v-model="f.search"
                        type="search"
                        placeholder="Cari nama, No. IC, No. ahli atau telefon"
                        class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                        @keyup.enter="apply()"
                    />
                    <select v-model="f.type" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Semua peserta</option>
                        <option value="ahli">Ahli sahaja</option>
                        <option value="bukan_ahli">Bukan ahli sahaja</option>
                    </select>
                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="apply()"
                            class="flex-1 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800"
                        >
                            Cari
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

            <div
                v-if="selected.length"
                class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-gray-900/10 bg-gray-900 px-4 py-2.5 text-white"
            >
                <p class="text-sm font-semibold">{{ selected.length }} peserta dipilih</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="bulk(true)"
                        class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-600"
                    >
                        Tanda Hadir
                    </button>
                    <button
                        type="button"
                        @click="bulk(false)"
                        class="rounded-lg border border-white/30 px-3 py-1.5 text-xs font-bold text-white hover:bg-white/10"
                    >
                        Batal Hadir
                    </button>
                    <button
                        type="button"
                        @click="selected = []"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white/70 hover:text-white"
                    >
                        Kosongkan
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <input
                                    type="checkbox"
                                    :checked="allVisibleSelected"
                                    aria-label="Pilih semua peserta"
                                    class="rounded border-gray-300"
                                    @change="toggleAll"
                                />
                            </th>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Jenis</th>
                            <th class="px-4 py-3">Kehadiran</th>
                            <th class="px-4 py-3">Masa</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <input
                                    v-model="selected"
                                    :value="r.id"
                                    type="checkbox"
                                    :aria-label="`Pilih ${r.name}`"
                                    class="rounded border-gray-300"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="font-mono text-xs text-gray-400">
                                    {{ r.registration_no }}<span v-if="r.member_no"> &middot; {{ r.member_no }}</span>
                                </p>
                                <p v-if="r.ic_number" class="text-xs text-gray-400">IC: {{ r.ic_number }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="typeBadge(r.is_member)">
                                    {{ r.is_member ? 'Ahli' : 'Bukan Ahli' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                                    :class="r.attended ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-gray-50 text-gray-500'"
                                >
                                    {{ r.attended ? 'Hadir' : notAttendedLabel }}
                                </span>
                                <p v-if="r.method" class="mt-1 text-[10px] text-gray-400">{{ methodLabel[r.method] ?? r.method }}</p>
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ r.attended_at || '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-bold transition"
                                    :class="r.attended
                                        ? 'border-rose-200 bg-white text-rose-600 hover:bg-rose-50'
                                        : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'"
                                    @click="toggle(r)"
                                >
                                    {{ r.attended ? 'Batal Hadir' : 'Tanda Hadir' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="registrations.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                Tiada peserta untuk penapis ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="registrations.links && registrations.links.length > 3" class="mt-4 flex flex-wrap justify-center gap-1">
                <template v-for="(link, i) in registrations.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        preserve-scroll
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="link.active ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    />
                    <span
                        v-else
                        v-html="link.label"
                        class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-1.5 text-sm text-gray-300"
                    />
                </template>
            </div>

            <section v-if="audit_trail.length" class="mt-8">
                <h2 class="mb-2 text-sm font-bold text-gray-800">Sejarah pembetulan kehadiran</h2>
                <div class="divide-y divide-gray-50 rounded-2xl border border-gray-100 bg-white shadow-sm">
                    <div v-for="entry in audit_trail" :key="entry.id" class="flex items-start justify-between gap-3 px-4 py-3">
                        <div class="min-w-0">
                            <p class="text-sm text-gray-700">{{ entry.description }}</p>
                            <p class="mt-0.5 text-[11px] text-gray-400">
                                {{ entry.actor || 'Sistem' }} &middot; {{ entry.created_at }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                            :class="auditMeta(entry.action).class"
                        >
                            {{ auditMeta(entry.action).label }}
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <div
            v-if="showAddModal"
            ref="addModalEl"
            tabindex="-1"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="add-participant-title"
            @click.self="closeAdd"
            @keydown.esc="closeAdd"
        >
            <div class="my-8 w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-3">
                    <div>
                        <h2 id="add-participant-title" class="text-lg font-black text-gray-900">Tambah peserta</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Untuk peserta yang hadir tanpa mendaftar. Nama dan No. IC atau telefon diperlukan.
                        </p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600" aria-label="Tutup" @click="closeAdd">
                        &times;
                    </button>
                </div>

                <form class="space-y-3" @submit.prevent="submitAdd">
                    <div class="relative">
                        <label for="add-member-search" class="mb-1 block text-xs font-bold text-gray-700">Cari ahli cawangan</label>
                        <input
                            id="add-member-search"
                            :value="memberSearchQuery"
                            type="text"
                            placeholder="Taip nama, no ahli atau emel..."
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                            autocomplete="off"
                            @input="onMemberSearchInput($event.target.value)"
                            @focus="memberSearchOpen = memberSearchResults.length > 0"
                        />
                        <div v-if="memberSearchLoading" class="absolute right-3 top-8 text-xs text-gray-400">Mencari...</div>
                        <div v-if="memberSearchOpen && memberSearchResults.length" class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-lg">
                            <button
                                v-for="member in memberSearchResults"
                                :key="member.id"
                                type="button"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-gray-50"
                                @click="pickMember(member)"
                            >
                                <span class="font-semibold text-gray-800">{{ member.name }}</span>
                                <span class="text-xs text-gray-400">{{ member.member_no }}</span>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-400">Pilih ahli untuk isi butiran secara automatik, atau isi manual di bawah.</p>
                    </div>

                    <div>
                        <label for="add-name" class="mb-1 block text-xs font-bold text-gray-700">Nama penuh</label>
                        <input
                            id="add-name"
                            v-model="addForm.name"
                            required
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                        />
                        <p v-if="addForm.errors.name" class="mt-1 text-xs text-rose-600">{{ addForm.errors.name }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="add-ic" class="mb-1 block text-xs font-bold text-gray-700">No. IC</label>
                            <input
                                id="add-ic"
                                v-model="addForm.ic_number"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                                placeholder="cth. 900101-14-1234"
                            />
                            <p v-if="addForm.errors.ic_number" class="mt-1 text-xs text-rose-600">{{ addForm.errors.ic_number }}</p>
                        </div>
                        <div>
                            <label for="add-phone" class="mb-1 block text-xs font-bold text-gray-700">No. telefon</label>
                            <input
                                id="add-phone"
                                v-model="addForm.phone"
                                type="tel"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                            />
                            <p v-if="addForm.errors.phone" class="mt-1 text-xs text-rose-600">{{ addForm.errors.phone }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label for="add-email" class="mb-1 block text-xs font-bold text-gray-700">Emel (pilihan)</label>
                            <input
                                id="add-email"
                                v-model="addForm.email"
                                type="email"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                            />
                            <p v-if="addForm.errors.email" class="mt-1 text-xs text-rose-600">{{ addForm.errors.email }}</p>
                        </div>
                        <div>
                            <label for="add-member" class="mb-1 block text-xs font-bold text-gray-700">No. ahli (pilihan)</label>
                            <input
                                id="add-member"
                                v-model="addForm.member_no"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                            />
                            <p v-if="addForm.errors.member_no" class="mt-1 text-xs text-rose-600">{{ addForm.errors.member_no }}</p>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2.5">
                        <input v-model="addForm.mark_present" type="checkbox" class="rounded border-gray-300" />
                        <span class="text-sm font-semibold text-gray-700">Tandakan hadir sekarang</span>
                    </label>

                    <div class="flex justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                            @click="closeAdd"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            :disabled="addForm.processing"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50"
                        >
                            {{ addForm.processing ? 'Menyimpan...' : 'Tambah peserta' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

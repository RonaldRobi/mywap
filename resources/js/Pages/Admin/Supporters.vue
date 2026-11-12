<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supporters: { type: Object, default: () => ({ data: [], links: [] }) },
    counts: { type: Object, default: () => ({ total: 0, with_email: 0, prospects: 0, members: 0 }) },
    isSuperadmin: Boolean,
    organizations: { type: Array, default: () => [] },
    programs: { type: Array, default: () => [] },
    defaultOrganizationId: Number,
    filters: { type: Object, default: () => ({ search: '', program_id: null, status: 'all', organization_id: null }) },
});

const search = ref(props.filters?.search ?? '');
const programId = ref(props.filters?.program_id ?? '');
const status = ref(props.filters?.status ?? 'all');
const organizationId = ref(props.filters?.organization_id ?? '');
const selectedIds = ref([]);

const form = useForm({
    name: '',
    email: '',
    phone: '',
    ic_number: '',
    notes: '',
    organization_id: props.isSuperadmin ? '' : (props.defaultOrganizationId ?? ''),
});

const importForm = useForm({
    file: null,
    organization_id: props.isSuperadmin ? '' : (props.defaultOrganizationId ?? ''),
});

const invitableRows = computed(() => (props.supporters.data ?? []).filter((row) => isInvitable(row)));
const allSelected = computed(() => invitableRows.value.length > 0 && invitableRows.value.every((row) => selectedIds.value.includes(row.id)));
const selectedCount = computed(() => selectedIds.value.length);

function isInvitable(row) {
    return row.is_active && !row.converted_at && !!row.email;
}

function sourceLabel(source) {
    if (source === 'program') return 'Program';
    if (source === 'import') return 'Import';
    if (source === 'manual') return 'Manual';
    return source || 'Manual';
}

function submitImport() {
    importForm.post(route('admin.supporters.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset('file'),
    });
}

function submit() {
    form.post(route('admin.supporters.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'phone', 'ic_number', 'notes'),
    });
}

function applyFilters() {
    selectedIds.value = [];
    router.get(route('admin.supporters.index'), {
        search: search.value,
        program_id: programId.value || undefined,
        status: status.value,
        organization_id: organizationId.value || undefined,
    }, { preserveState: true, preserveScroll: true });
}

function exportUrl(type) {
    const params = new URLSearchParams();
    const applied = props.filters ?? {};

    if (applied.search) params.set('search', applied.search);
    if (applied.program_id) params.set('program_id', applied.program_id);
    if (applied.status && applied.status !== 'all') params.set('status', applied.status);
    if (applied.organization_id) params.set('organization_id', applied.organization_id);

    const base = type === 'pdf'
        ? route('admin.supporters.export.pdf')
        : route('admin.supporters.export.excel');

    const query = params.toString();

    return query ? `${base}?${query}` : base;
}

function resetFilters() {
    search.value = '';
    programId.value = '';
    status.value = 'all';
    organizationId.value = '';
    selectedIds.value = [];
    applyFilters();
}

function toggleAll(event) {
    selectedIds.value = event.target.checked ? invitableRows.value.map((row) => row.id) : [];
}

function toggleRow(row) {
    if (selectedIds.value.includes(row.id)) {
        selectedIds.value = selectedIds.value.filter((item) => item !== row.id);
    } else {
        selectedIds.value = [...selectedIds.value, row.id];
    }
}

function inviteSelected() {
    if (!selectedIds.value.length) return;
    if (!confirm(`Hantar emel jemputan kepada ${selectedIds.value.length} pendukung terpilih?`)) return;
    router.post(route('admin.supporters.invite'), { ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = []; },
    });
}

function removeSupporter(item) {
    const label = item.name || item.email || item.phone || 'pendukung ini';
    if (!confirm(`Buang ${label} daripada senarai Pendukung?`)) return;
    router.delete(route('admin.supporters.destroy', item.id), {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = selectedIds.value.filter((id) => id !== item.id); },
    });
}
</script>

<template>
    <Head title="Pendukung" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Pendukung</template>

        <div class="mx-auto max-w-6xl px-4 py-8 md:px-6 md:py-10">
            <div class="mb-6 flex flex-col gap-3 rounded-3xl border border-emerald-100 bg-emerald-50/70 p-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-lg font-black text-emerald-900">Direktori Pendukung</h1>
                    <p class="mt-1 max-w-2xl text-sm text-emerald-800">
                        Peserta program yang bukan ahli dikumpul di sini secara automatik. Anda boleh lihat asal program, cari pendukung, dan hantar jemputan mendaftar.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs font-semibold">
                    <span class="rounded-full bg-white px-3 py-1 text-emerald-800">{{ counts.total }} pendukung</span>
                    <span class="rounded-full bg-white px-3 py-1 text-emerald-800">{{ counts.with_email }} ada emel</span>
                    <span class="rounded-full bg-white px-3 py-1 text-amber-700">{{ counts.prospects }} belum jadi ahli</span>
                    <span class="rounded-full bg-white px-3 py-1 text-sky-700">{{ counts.members }} sudah jadi ahli</span>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ $page.props.flash.error }}
            </div>

            <div class="mb-6 grid gap-4 lg:grid-cols-2">
                <form @submit.prevent="submit" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-800">Tambah Pendukung</h2>
                    <p class="mt-1 text-xs text-gray-500">Isi sekurang-kurangnya emel, No. telefon, atau No. IC.</p>

                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="supporter-name" class="mb-1 block text-xs font-semibold text-gray-500">Nama penuh</label>
                            <input id="supporter-name" v-model="form.name" type="text" required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="Nama penuh">
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label for="supporter-email" class="mb-1 block text-xs font-semibold text-gray-500">Emel (pilihan)</label>
                            <input id="supporter-email" v-model="form.email" type="email" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="nama@emel.com">
                            <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label for="supporter-phone" class="mb-1 block text-xs font-semibold text-gray-500">No. telefon</label>
                            <input id="supporter-phone" v-model="form.phone" type="tel" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="0123456789">
                            <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                        </div>
                        <div>
                            <label for="supporter-ic" class="mb-1 block text-xs font-semibold text-gray-500">No. IC</label>
                            <input id="supporter-ic" v-model="form.ic_number" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="990101011234">
                            <p v-if="form.errors.ic_number" class="mt-1 text-xs text-red-500">{{ form.errors.ic_number }}</p>
                        </div>
                        <div v-if="isSuperadmin">
                            <label for="supporter-org" class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                            <select id="supporter-org" v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                <option value="">Platform (semua)</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                            <p v-if="form.errors.organization_id" class="mt-1 text-xs text-red-500">{{ form.errors.organization_id }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="supporter-notes" class="mb-1 block text-xs font-semibold text-gray-500">Nota (pilihan)</label>
                            <input id="supporter-notes" v-model="form.notes" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" placeholder="Contoh: kenalan semasa program">
                        </div>
                    </div>

                    <button type="submit" :disabled="form.processing" class="mt-4 min-h-11 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                        {{ form.processing ? 'Menyimpan...' : 'Tambah Pendukung' }}
                    </button>
                </form>

                <form @submit.prevent="submitImport" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-bold text-gray-800">Import CSV</h2>
                    <p class="mt-1 text-xs text-gray-500">
                        Lajur diterima: name/nama, email/emel, phone/telefon, ic/ic_number. Emel tidak wajib jika ada telefon atau No. IC. Emel ahli sedia ada dilangkau automatik.
                    </p>
                    <div class="mt-3 space-y-3">
                        <div>
                            <label for="supporter-file" class="mb-1 block text-xs font-semibold text-gray-500">Fail CSV</label>
                            <input
                                id="supporter-file"
                                type="file"
                                accept=".csv,text/csv,text/plain"
                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                                @change="importForm.file = $event.target.files[0]"
                            >
                            <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-500">{{ importForm.errors.file }}</p>
                        </div>
                        <div v-if="isSuperadmin">
                            <label for="supporter-import-org" class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                            <select id="supporter-import-org" v-model="importForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200">
                                <option value="">Platform (semua)</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>
                        <button type="submit" :disabled="importForm.processing || !importForm.file" class="min-h-11 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ importForm.processing ? 'Mengimport...' : 'Import CSV' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="mb-4 rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label for="supporter-search" class="mb-1 block text-xs font-semibold text-gray-500">Cari</label>
                        <input id="supporter-search" v-model="search" type="search" placeholder="Nama, emel, telefon, atau No. IC" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" @keyup.enter="applyFilters">
                    </div>
                    <div>
                        <label for="supporter-program" class="mb-1 block text-xs font-semibold text-gray-500">Program</label>
                        <select id="supporter-program" v-model="programId" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" @change="applyFilters">
                            <option value="">Semua program</option>
                            <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.title }}{{ program.date ? ` (${program.date})` : '' }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="supporter-status" class="mb-1 block text-xs font-semibold text-gray-500">Status</label>
                        <select id="supporter-status" v-model="status" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" @change="applyFilters">
                            <option value="all">Semua</option>
                            <option value="prospect">Belum jadi ahli</option>
                            <option value="member">Sudah jadi ahli</option>
                        </select>
                    </div>
                    <div v-if="isSuperadmin">
                        <label for="supporter-filter-org" class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                        <select id="supporter-filter-org" v-model="organizationId" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-500 focus:ring-2 focus:ring-emerald-200" @change="applyFilters">
                            <option value="">Semua organisasi</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="min-h-11 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="applyFilters">Cari</button>
                    <button type="button" class="min-h-11 rounded-xl px-4 py-2 text-sm font-semibold text-gray-500 hover:bg-gray-50" @click="resetFilters">Set semula</button>
                    <a :href="exportUrl('excel')" class="inline-flex min-h-11 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100">Export Excel</a>
                    <a :href="exportUrl('pdf')" class="inline-flex min-h-11 items-center rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100">Export PDF</a>
                    <span class="text-xs text-gray-500">{{ supporters.total ?? supporters.data.length }} rekod</span>
                </div>
            </div>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-600">{{ selectedCount }} dipilih</p>
                <button
                    type="button"
                    class="min-h-11 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40"
                    :disabled="!selectedCount"
                    @click="inviteSelected"
                >
                    Jemput daftar sebagai ahli
                </button>
            </div>

            <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[980px] text-left text-sm">
                        <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="w-12 px-4 py-3">
                                    <label class="-m-2 inline-flex p-2">
                                        <span class="sr-only">Pilih semua pendukung</span>
                                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300" :checked="allSelected" :disabled="!invitableRows.length" @change="toggleAll">
                                    </label>
                                </th>
                                <th class="px-4 py-3">Nama</th>
                                <th class="px-4 py-3">Hubungan</th>
                                <th class="px-4 py-3">No. IC</th>
                                <th class="px-4 py-3">Program</th>
                                <th class="px-4 py-3">Status</th>
                                <th v-if="isSuperadmin" class="px-4 py-3">Organisasi</th>
                                <th class="px-4 py-3">Jemputan</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in supporters.data" :key="item.id" class="border-t border-gray-100 align-top">
                                <td class="px-4 py-3">
                                    <label class="-m-2 inline-flex p-2">
                                        <span class="sr-only">Pilih {{ item.name || item.email || item.phone }}</span>
                                        <input type="checkbox" class="h-4 w-4 rounded border-gray-300" :checked="selectedIds.includes(item.id)" :disabled="!isInvitable(item)" @change="toggleRow(item)">
                                    </label>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ item.name || 'Tanpa nama' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500">Sumber: {{ sourceLabel(item.source) }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    <p v-if="item.email">{{ item.email }}</p>
                                    <p v-if="item.phone" class="text-xs text-gray-500">{{ item.phone }}</p>
                                    <p v-if="!item.email && !item.phone" class="text-xs text-gray-500">Tiada emel atau telefon</p>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ item.ic_number || '-' }}</td>
                                <td class="px-4 py-3">
                                    <template v-if="item.programs.length">
                                        <p class="font-semibold text-gray-700">{{ item.programs[0].title }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500">
                                            {{ item.programs[0].date || 'Tarikh tidak dinyatakan' }}
                                            <span v-if="item.programs.length > 1">, +{{ item.programs.length - 1 }} program lain</span>
                                        </p>
                                    </template>
                                    <p v-else class="text-xs text-gray-500">Tidak berkaitan program</p>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="item.converted_at" class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700">
                                        Sudah jadi ahli
                                    </span>
                                    <span v-else class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                        Belum jadi ahli
                                    </span>
                                    <p v-if="item.converted_at" class="mt-1 text-xs text-gray-500">
                                        {{ item.converted_member || 'Ahli' }}<span v-if="item.converted_member_no">, {{ item.converted_member_no }}</span>
                                    </p>
                                    <p v-else class="mt-1 text-xs text-gray-500">Ditambah {{ item.created_at }}</p>
                                </td>
                                <td v-if="isSuperadmin" class="px-4 py-3 text-gray-600">{{ item.organization_name || 'Platform' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <p v-if="item.last_invited_at">Dijemput {{ item.last_invited_at }}</p>
                                    <p v-else>Belum dijemput</p>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button" class="min-h-9 rounded-lg px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50" @click="removeSupporter(item)">Buang</button>
                                </td>
                            </tr>
                            <tr v-if="!supporters.data.length">
                                <td :colspan="isSuperadmin ? 9 : 8" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Belum ada pendukung untuk tapisan ini. Peserta bukan ahli yang mendaftar atau hadir ke program akan muncul di sini secara automatik.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="supporters.links?.length > 3" class="mt-4 flex flex-wrap gap-1.5">
                <template v-for="link in supporters.links" :key="link.label">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        :href="link.url || undefined"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                        :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : link.url ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' : 'border-gray-100 bg-gray-50 text-gray-300'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

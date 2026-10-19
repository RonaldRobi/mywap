<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
    registrations: Object, // paginator
    organizations: Array,
    filters: Object,
});

// ─── Import Google Form (CSV/XLSX) ──────────────────────────────────────────
const importFields = [
    { key: 'name', label: 'Nama', required: true },
    { key: 'ic_number', label: 'No. IC' },
    { key: 'phone', label: 'No. Telefon' },
    { key: 'email', label: 'Emel' },
    { key: 'member_no', label: 'No. Ahli' },
];

const showImport = ref(false);
const importFile = ref(null);
const importPreview = ref(null);
const importMapping = ref({ name: null, ic_number: null, phone: null, email: null, member_no: null });
const previewing = ref(false);
const importing = ref(false);
const importError = ref('');
const importResult = ref(null);

const canCommit = computed(
    () => importPreview.value !== null && importMapping.value.name !== null && !importing.value,
);

function openImport() {
    importFile.value = null;
    importPreview.value = null;
    importMapping.value = { name: null, ic_number: null, phone: null, email: null, member_no: null };
    importError.value = '';
    importResult.value = null;
    showImport.value = true;
}

function closeImport() {
    if (importing.value) return;
    showImport.value = false;
}

function onFileChange(event) {
    importFile.value = event.target.files?.[0] ?? null;
    importPreview.value = null;
    importResult.value = null;
    importError.value = '';
}

function errorMessage(error) {
    return error?.response?.data?.errors?.file?.[0]
        ?? error?.response?.data?.errors?.mapping?.[0]
        ?? error?.response?.data?.message
        ?? 'Ralat memproses fail. Sila cuba lagi.';
}

async function previewImport() {
    if (!importFile.value) {
        importError.value = 'Sila pilih fail CSV/XLSX dahulu.';
        return;
    }

    previewing.value = true;
    importError.value = '';
    importResult.value = null;

    const form = new FormData();
    form.append('file', importFile.value);

    try {
        const { data } = await axios.post(
            route('admin.events.registrations.import.preview', props.event.id),
            form,
        );
        importPreview.value = data;
        importMapping.value = { ...data.mapping };
    } catch (error) {
        importError.value = errorMessage(error);
    } finally {
        previewing.value = false;
    }
}

async function commitImport() {
    if (!importFile.value || !importPreview.value) return;

    if (importMapping.value.name === null) {
        importError.value = 'Lajur Nama wajib dipilih.';
        return;
    }

    importing.value = true;
    importError.value = '';

    const form = new FormData();
    form.append('file', importFile.value);
    Object.entries(importMapping.value).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            form.append(`mapping[${key}]`, value);
        }
    });

    try {
        const { data } = await axios.post(
            route('admin.events.registrations.import', props.event.id),
            form,
        );
        importResult.value = data.summary;
        router.reload({ only: ['registrations'] });
    } catch (error) {
        importError.value = errorMessage(error);
    } finally {
        importing.value = false;
    }
}


const statusForm = useForm({ status: '' });

function updateStatus(reg, status) {
    if (!confirm(`Tukar status pendaftaran ${reg.registration_no} kepada "${status}"?`)) return;
    statusForm.status = status;
    statusForm.patch(route('admin.events.registrations.update', reg.id));
}

function applyFilters() {
    router.get(route('admin.events.registrations', props.event.id), {
        org: document.getElementById('f-org')?.value || '',
        status: document.getElementById('f-status')?.value || '',
        search: document.getElementById('f-search')?.value || '',
    }, { preserveState: true, replace: true });
}
</script>

<template>
    <Head :title="`Pendaftaran: ${event.title}`" />

    <AppLayout>
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between gap-3 mb-1">
                <h1 class="text-2xl font-black text-gray-900">Pendaftaran</h1>
                <button
                    type="button"
                    @click="openImport"
                    class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700"
                >
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                    </svg>
                    Import CSV
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-6">{{ event.title }}</p>

            <!-- Filters -->
            <div class="rounded-3xl bg-white border border-gray-100 p-4 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input id="f-search" :value="filters.search || ''" placeholder="Cari nama / no reg..." class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                <select id="f-status" :value="filters.status || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Status</option>
                    <option value="confirmed">Disahkan</option>
                    <option value="pending">Menunggu</option>
                    <option value="cancelled">Dibatalkan</option>
                </select>
                <select id="f-org" :value="filters.org || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <button @click="applyFilters" class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm font-semibold">Tapis</button>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="rounded-3xl bg-white border border-gray-100 p-4 text-center">
                    <p class="text-2xl font-black text-gray-900">{{ registrations.total }}</p>
                    <p class="text-xs text-gray-400 font-semibold uppercase">Jumlah</p>
                </div>
                <div class="rounded-3xl bg-emerald-50 border border-emerald-100 p-4 text-center">
                    <p class="text-2xl font-black text-emerald-600">{{ registrations.data.filter(r => r.status === 'confirmed').length }}</p>
                    <p class="text-xs text-emerald-500 font-semibold uppercase">Disahkan</p>
                </div>
                <div class="rounded-3xl bg-amber-50 border border-amber-100 p-4 text-center">
                    <p class="text-2xl font-black text-amber-600">{{ registrations.data.filter(r => r.status === 'pending').length }}</p>
                    <p class="text-xs text-amber-500 font-semibold uppercase">Menunggu</p>
                </div>
            </div>

            <!-- List -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3">Borang</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Bayaran</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="text-xs text-gray-400 font-mono">{{ r.registration_no }}<span v-if="r.member_no"> · {{ r.member_no }}</span></p>
                                <p v-if="r.phone" class="text-xs text-gray-400">{{ r.phone }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.organization_name || '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ r.form_title || '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-if="r.ticket_type" class="inline-flex rounded-full bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ r.ticket_type }}</span>
                                <a
                                    v-if="r.document_path"
                                    :href="`/storage/${r.document_path}`"
                                    target="_blank"
                                    class="block mt-1 text-xs text-indigo-600 hover:text-indigo-700 underline"
                                >
                                    📎 Lihat dokumen
                                </a>
                                <span v-else-if="!r.ticket_type" class="text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="r.payment_status === 'successful' || r.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ r.payment_status === 'successful' || r.payment_status === 'paid' ? 'Berjaya' : 'Menunggu' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs font-bold" :class="{ 'text-emerald-600': r.status === 'confirmed', 'text-amber-600': r.status === 'pending', 'text-red-500': r.status === 'cancelled' }">
                                    {{ r.status_label }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <select
                                    :value="r.status"
                                    @change="updateStatus(r, $event.target.value)"
                                    class="rounded-xl border border-gray-200 px-2 py-1.5 text-xs focus:ring-0"
                                >
                                    <option value="confirmed">Disahkan</option>
                                    <option value="pending">Menunggu</option>
                                    <option value="cancelled">Dibatalkan</option>
                                </select>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Import Google Form ─────────────────────────────────────────── -->
        <div
            v-if="showImport"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4"
            @click.self="closeImport"
        >
            <div class="my-8 w-full max-w-3xl rounded-3xl bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-gray-100 p-6">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Import Data Google Form</h2>
                        <p class="mt-1 text-xs text-gray-500">
                            Muat naik CSV/XLSX. Import berulang akan kemas kini peserta sedia ada dan tambah
                            peserta baharu — tiada rekod dipadam.
                        </p>
                    </div>
                    <button type="button" @click="closeImport" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <div class="space-y-5 p-6">
                    <div>
                        <label class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">
                            Fail CSV / XLSX
                        </label>
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            @change="onFileChange"
                            class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-bold"
                        />
                        <p class="mt-1 text-[11px] text-gray-400">
                            Dalam Google Form: Responses → ... → Download responses (.csv).
                            Pastikan kolum No. IC diformat sebagai Teks supaya angka 0 di depan tidak hilang.
                        </p>
                    </div>

                    <p v-if="importError" class="rounded-xl border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-600">
                        {{ importError }}
                    </p>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="previewImport"
                            :disabled="!importFile || previewing"
                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-bold text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        >
                            {{ previewing ? 'Menyemak...' : 'Pratonton & Padan Lajur' }}
                        </button>
                        <span v-if="importPreview" class="text-xs text-gray-500">
                            {{ importPreview.total_rows }} baris data dikesan
                        </span>
                    </div>

                    <template v-if="importPreview">
                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-800">Padanan Lajur</h3>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div v-for="field in importFields" :key="field.key">
                                    <label class="mb-1 block text-xs font-semibold text-gray-600">
                                        {{ field.label }}<span v-if="field.required" class="text-red-500"> *</span>
                                    </label>
                                    <select
                                        v-model="importMapping[field.key]"
                                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                                    >
                                        <option :value="null">— Tidak digunakan —</option>
                                        <option v-for="(header, index) in importPreview.headers" :key="index" :value="index">
                                            {{ header || `Lajur ${index + 1}` }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-sm font-bold text-gray-800">Contoh Data</h3>
                            <div class="overflow-x-auto rounded-xl border border-gray-100">
                                <table class="w-full text-xs">
                                    <thead class="bg-gray-50 text-left text-[10px] uppercase tracking-wide text-gray-400">
                                        <tr>
                                            <th v-for="(header, index) in importPreview.headers" :key="index" class="whitespace-nowrap px-3 py-2">
                                                {{ header || `Lajur ${index + 1}` }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        <tr v-for="(row, rowIndex) in importPreview.sample" :key="rowIndex">
                                            <td v-for="(header, index) in importPreview.headers" :key="index" class="whitespace-nowrap px-3 py-2 text-gray-600">
                                                {{ row[index] ?? '' }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>

                    <div v-if="importResult" class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-sm text-emerald-800">
                        <p class="font-bold">Import selesai</p>
                        <p class="mt-1">
                            {{ importResult.created }} baharu · {{ importResult.updated }} dikemas kini ·
                            {{ importResult.skipped }} dilewati
                        </p>
                        <ul v-if="importResult.errors?.length" class="mt-2 max-h-32 list-disc space-y-1 overflow-y-auto pl-5 text-xs text-amber-700">
                            <li v-for="(error, index) in importResult.errors" :key="index">{{ error }}</li>
                        </ul>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-gray-100 p-4">
                    <button type="button" @click="closeImport" class="rounded-xl px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                        {{ importResult ? 'Tutup' : 'Batal' }}
                    </button>
                    <button
                        v-if="!importResult"
                        type="button"
                        @click="commitImport"
                        :disabled="!canCommit"
                        class="rounded-xl bg-emerald-600 px-5 py-2 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                    >
                        {{ importing ? 'Mengimport...' : 'Import Sekarang' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

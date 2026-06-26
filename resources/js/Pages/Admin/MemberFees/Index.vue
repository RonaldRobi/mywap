<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ToastNotification from '@/Components/ToastNotification.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';

const props = defineProps({ members: Object, stats: Object, year: Number, years: Array, chart: Array, reconciliation: Object, organizations: Array, filters: Object });

const toast = ref(null);
const confirmDialog = ref(null);
const isSuperadmin = computed(() => props.organizations.length > 0);

// ─── Esc key to close all ──────────────────────────────────────────────
function onKeydown(e) {
    if (e.key === 'Escape') {
        exemptTarget.value = null;
        slideOver.value = null;
        importOpen.value = false;
        manualPayOpen.value = false;
    }
}
onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));

// ─── Filter ────────────────────────────────────────────────────────────
const search = ref(props.filters.search || '');
const feeStatus = ref(props.filters.fee_status || '');
const selectedYear = ref(props.year);
const selectedOrg = ref(props.filters.organization_id || '');

function applyFilters() {
    const params = { search: search.value || '', fee_status: feeStatus.value || '', year: selectedYear.value };
    if (selectedOrg.value) params.organization_id = selectedOrg.value;
    router.get(route('admin.fees.members'), params, { preserveState: true, preserveScroll: true });
}
watch(selectedYear, applyFilters);

function setFilter(status) {
    feeStatus.value = feeStatus.value === status ? '' : status;
    applyFilters();
}

const filterOptions = [
    { value: '', label: 'Semua' }, { value: 'paid', label: 'Lunas' },
    { value: 'due', label: 'Tertunggak' }, { value: 'life_member', label: 'Seumur Hidup' },
    { value: 'exempted', label: 'Dikecualikan' },
];

// ─── Chart ─────────────────────────────────────────────────────────────
const chartMax = computed(() => Math.max(...props.chart.map(c => c.total), 1));

// ─── Confirm Dialog ──────────────────────────────────────────────────
const confirmMsg = ref('');
const confirmVariant = ref('danger');
const pendingConfirm = ref(null);

function askConfirm(msg, action, variant = 'danger') {
    confirmMsg.value = msg;
    confirmVariant.value = variant;
    pendingConfirm.value = action;
    confirmDialog.value.show();
}

function onConfirmAction() {
    if (pendingConfirm.value) { pendingConfirm.value(); pendingConfirm.value = null; }
}

function onCancelAction() {
    pendingConfirm.value = null;
}

// ─── Toggle Life Member ────────────────────────────────────────────────
function toggleLifeMember(user) {
    const action = user.fee.status === 'life_member' ? 'buang status yuran seumur hidup' : 'lantik sebagai ahli seumur hidup';
    askConfirm(`Adakah anda pasti mahu ${action} untuk "${user.name}"?`, () => {
        useForm({}).post(route('admin.fees.members.life-member', user.id), {
            preserveScroll: true,
            onSuccess: () => toast.value?.success(user.fee.status === 'life_member' ? `${user.name} bukan lagi ahli seumur hidup.` : `${user.name} kini ahli seumur hidup.`),
        });
    });
}

// ─── Exempt Modal ──────────────────────────────────────────────────────
const exemptForm = useForm({ reason: '' });
const exemptTarget = ref(null);
function openExempt(user) { exemptTarget.value = user; exemptForm.reason = ''; }
function submitExempt() {
    if (!exemptTarget.value) return;
    exemptForm.post(route('admin.fees.members.exempted', exemptTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => { exemptTarget.value = null; exemptForm.reset(); },
    });
}

// ─── Slide-over (Profil Kewangan) ────────────────────────────────────
const slideOver = ref(null);
const slideTab = ref('info');
const paymentHistory = ref([]);
const activityLogs = ref([]);
const feeDetail = ref(null);
const loadingPanel = ref(false);
const editing = ref(false);
const editForm = ref({ amount: 0, notes: '', paid_at: '' });
const editSaving = ref(false);
const editError = ref('');

async function openFinancialProfile(user) {
    slideOver.value = user;
    slideTab.value = 'info';
    paymentHistory.value = [];
    activityLogs.value = [];
    feeDetail.value = null;
    editing.value = false;
    editError.value = '';
    loadingPanel.value = true;

    try {
        const feeId = user.fee?.id;
        const [resPay, resLog, resDetail] = await Promise.all([
            fetch(route('admin.fees.members.payments', user.id)),
            fetch(route('admin.fees.members.logs', user.id)),
            feeId ? fetch(route('admin.fees.members.fee-detail', [user.id, feeId])) : Promise.resolve(null),
        ]);
        paymentHistory.value = (await resPay.json()).data || [];
        activityLogs.value = (await resLog.json()).data || [];

        if (resDetail?.ok) {
            feeDetail.value = (await resDetail.json()).data;
            editForm.value = {
                amount: feeDetail.value.amount || 0,
                notes: feeDetail.value.notes || '',
                paid_at: feeDetail.value.paid_at ? feeDetail.value.paid_at.slice(0, 10) : '',
            };
        }
    } catch { /* ignore */ }
    loadingPanel.value = false;
}

function openHistory(user) {
    openFinancialProfile(user);
    slideTab.value = 'history';
}

function startEdit() {
    if (!feeDetail.value && slideOver.value?.fee?.id) {
        fetch(route('admin.fees.members.fee-detail', [slideOver.value.id, slideOver.value.fee.id]))
            .then(r => r.json())
            .then(j => {
                feeDetail.value = j.data;
                editForm.value = {
                    amount: feeDetail.value.amount || 0,
                    notes: feeDetail.value.notes || '',
                    paid_at: feeDetail.value.paid_at ? feeDetail.value.paid_at.slice(0, 10) : '',
                };
            })
            .catch(() => { editError.value = 'Gagal muat data untuk edit.'; return; });
    }
    editing.value = true;
    editError.value = '';
}

function cancelEdit() {
    editing.value = false;
    editError.value = '';
    if (feeDetail.value) {
        editForm.value = {
            amount: feeDetail.value.amount || 0,
            notes: feeDetail.value.notes || '',
            paid_at: feeDetail.value.paid_at ? feeDetail.value.paid_at.slice(0, 10) : '',
        };
    }
}

async function saveEdit() {
    if (!feeDetail.value) return;
    editSaving.value = true;
    editError.value = '';

    const form = new FormData();
    form.append('amount', editForm.value.amount);
    form.append('notes', editForm.value.notes);
    form.append('paid_at', editForm.value.paid_at || '');

    try {
        const res = await fetch(route('admin.fees.members.fee-update', [slideOver.value.id, feeDetail.value.id]), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
            body: form,
        });
        const json = await res.json();
        if (!json.success) { editError.value = 'Gagal simpan.'; return; }
        editing.value = false;
        toast.value?.success('Yuran berjaya dikemaskini.');
        await openFinancialProfile(slideOver.value);
    } catch { editError.value = 'Ralat semasa simpan.'; }
    editSaving.value = false;
}

function closePanel() {
    slideOver.value = null;
    paymentHistory.value = [];
    activityLogs.value = [];
    feeDetail.value = null;
    editing.value = false;
}

// ─── Manual Payment Modal ─────────────────────────────────────────────
const manualPayOpen = ref(false);
const manualForm = useForm({ user_id: '', year: 0, amount: '', reference: '', proof: null });
const manualSuccess = ref('');
const manualError = ref('');

function openManualPay() {
    manualForm.reset();
    manualForm.year = selectedYear.value;
    manualSuccess.value = '';
    manualError.value = '';
    manualPayOpen.value = true;
}
async function submitManualPay() {
    manualSuccess.value = '';
    manualError.value = '';
    const form = new FormData();
    form.append('user_id', manualForm.user_id);
    form.append('year', manualForm.year);
    form.append('amount', manualForm.amount);
    if (manualForm.reference) form.append('reference', manualForm.reference);
    if (manualForm.proof) form.append('proof', manualForm.proof);
    try {
        const res = await fetch(route('admin.fees.members.manual-pay'), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
            body: form,
        });
        const json = await res.json();
        if (!res.ok) { manualError.value = json.message || 'Gagal'; return; }
        manualSuccess.value = json.message;
        toast.value?.success(json.message);
        manualForm.reset();
        manualForm.year = selectedYear.value;
    } catch { manualError.value = 'Ralat semasa proses.'; }
}

// ─── Import Modal ──────────────────────────────────────────────────────
const importOpen = ref(false);
const importYear = ref(selectedYear.value);
const importFile = ref(null);
const importProof = ref(null);
const importPreview = ref(null);
const importProcessing = ref(false);
const importResult = ref(null);
const importError = ref('');

function openImport() {
    importYear.value = selectedYear.value; importFile.value = null; importProof.value = null;
    importPreview.value = null; importResult.value = null; importError.value = '';
    importOpen.value = true;
}
async function previewImport() {
    if (!importFile.value) return;
    importError.value = ''; importPreview.value = null;
    const form = new FormData(); form.append('file', importFile.value); form.append('year', importYear.value);
    try {
        const res = await fetch(route('admin.fees.members.import.preview'), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: form,
        });
        if (!res.ok) { importError.value = 'Gagal preview import.'; return; }
        importPreview.value = await res.json();
    } catch { importError.value = 'Ralat preview.'; }
}
async function processImport() {
    if (!importFile.value) return;
    importProcessing.value = true; importError.value = ''; importResult.value = null;
    const form = new FormData(); form.append('file', importFile.value); form.append('year', importYear.value);
    if (importProof.value) form.append('proof', importProof.value);
    try {
        const res = await fetch(route('admin.fees.members.import.process'), {
            method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content }, body: form,
        });
        const json = await res.json();
        if (!json.success) { importError.value = json.message || 'Gagal import.'; return; }
        importResult.value = json; importPreview.value = null;
        toast.value?.success(`Import selesai: ${json.success_count} berjaya, ${json.skip_count} skip.`);
    } catch { importError.value = 'Ralat import.'; }
    importProcessing.value = false;
}
function closeImport() {
    importOpen.value = false;
    if (importResult.value) router.reload({ only: ['members', 'stats', 'chart'] });
}

// ─── Generate Fees ────────────────────────────────────────────────────
function generateFees() {
    askConfirm(`Jana rekod yuran untuk tahun ${selectedYear.value}? Rekod akan dicipta untuk ahli yang belum ada rekod yuran.`, async () => {
        try {
            const res = await fetch(route('admin.fees.members.generate'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                },
                body: JSON.stringify({ year: selectedYear.value }),
            });
            const json = await res.json();
            if (json.success) {
                toast.value?.success(json.message);
                router.reload({ only: ['members', 'stats', 'chart'] });
            } else {
                toast.value?.error(json.message || 'Ralat.');
            }
        } catch { toast.value?.error('Ralat menjana yuran.'); }
    }, 'primary');
}

// ─── Void Payment ──────────────────────────────────────────────────────
const voidingPayment = ref(null);
function voidPayment(paymentRef, paymentId) {
    askConfirm(`Voidkan pembayaran ${paymentRef}? Tindakan ini tidak boleh diundur.`, async () => {
        try {
            const res = await fetch(route('admin.fees.members.reverse', paymentId), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content },
            });
            const json = await res.json();
            if (!json.success) { toast.value?.error(json.message); return; }
            toast.value?.success('Pembayaran berjaya divoidkan.');
            if (slideOver.value) await openFinancialProfile(slideOver.value);
        } catch { toast.value?.error('Ralat void payment.'); }
    });
}

// ─── Helpers ───────────────────────────────────────────────────────────
function formatAmount(v) { return v ? 'RM ' + Number(v).toFixed(2) : '—'; }
function statusLabel(s) { const m = { paid: 'Lunas', life_member: 'Seumur Hidup', exempted: 'Dikecualikan' }; return m[s] || 'Tertunggak'; }
function statusClass(s) { const m = { paid: 'bg-green-100 text-green-700', life_member: 'bg-blue-100 text-blue-700', exempted: 'bg-gray-100 text-gray-600', unpaid: 'bg-amber-100 text-amber-700' }; return m[s] || 'bg-amber-100 text-amber-700'; }
function previewStatusLabel(s) { return { ready: 'Sedia', already_paid: 'Sudah Bayar', exempted: 'Dikecualikan', not_found: 'Tidak Ditemui' }[s] || s; }
function previewStatusClass(s) { return { ready: 'text-green-700 bg-green-50 border-green-200', already_paid: 'text-amber-700 bg-amber-50 border-amber-200', exempted: 'text-gray-600 bg-gray-50 border-gray-200', not_found: 'text-red-700 bg-red-50 border-red-200' }[s] || ''; }
</script>

<template>
    <Head title="Pengurusan Yuran Ahli" />
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Pengurusan Yuran Ahli</template>
        <div class="mx-auto max-w-7xl px-4 py-6">

            <!-- Stats Header -->
            <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div @click="setFilter('')" class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm cursor-pointer hover:bg-gray-50 transition-colors">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total }}</p>
                    <p class="text-xs font-semibold text-gray-500">Jumlah Ahli</p>
                </div>
                <div @click="setFilter('paid')" class="rounded-2xl border border-green-100 bg-green-50 p-4 text-center shadow-sm cursor-pointer hover:bg-green-100 transition-colors">
                    <p class="text-2xl font-black text-green-700">{{ stats.paid }}</p>
                    <p class="text-xs font-semibold text-green-600">Lunas</p>
                </div>
                <div @click="setFilter('due')" class="rounded-2xl border border-amber-100 bg-amber-50 p-4 text-center shadow-sm cursor-pointer hover:bg-amber-100 transition-colors">
                    <p class="text-2xl font-black text-amber-700">{{ stats.due }}</p>
                    <p class="text-xs font-semibold text-amber-600">Tertunggak</p>
                </div>
                <div @click="setFilter('life_member')" class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-center shadow-sm cursor-pointer hover:bg-blue-100 transition-colors">
                    <p class="text-2xl font-black text-blue-700">{{ stats.life_member }}</p>
                    <p class="text-xs font-semibold text-blue-600">Seumur Hidup</p>
                </div>
                <div @click="setFilter('exempted')" class="rounded-2xl border border-gray-100 bg-gray-50 p-4 text-center shadow-sm cursor-pointer hover:bg-gray-100 transition-colors">
                    <p class="text-2xl font-black text-gray-700">{{ stats.exempted }}</p>
                    <p class="text-xs font-semibold text-gray-500">Dikecualikan</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 text-center shadow-sm">
                    <p class="text-lg font-black text-indigo-700">{{ formatAmount(stats.collected_amount) }}</p>
                    <p class="text-xs font-semibold text-indigo-600">Dikutip</p>
                </div>
            </div>

            <!-- Monthly Collection Chart -->
            <div class="mb-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500">Kutipan Bulanan {{ year }}</p>
                <div class="flex items-end gap-1.5 h-24">
                    <div v-for="(c, i) in chart" :key="i" class="flex-1 flex flex-col items-center gap-1">
                        <span class="text-[10px] font-semibold text-gray-600">{{ formatAmount(c.total) }}</span>
                        <div class="w-full rounded-t-lg transition-all" :style="{ height: (c.total / chartMax * 80) + 'px', background: c.total > 0 ? '#6366f1' : '#e5e7eb' }"></div>
                        <span class="text-[9px] text-gray-400">{{ c.month.slice(0, 3) }}</span>
                    </div>
                </div>
            </div>

            <!-- Reconciliation Report -->
            <div class="mb-4 rounded-2xl border border-indigo-100 bg-indigo-50/50 p-4 shadow-sm">
                <details class="group">
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-indigo-700 select-none">Laporan Rekonsiliasi {{ year }} ▾</summary>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div class="rounded-xl bg-white p-3 text-center border border-indigo-100">
                            <p class="text-xs text-gray-500">Sasaran Kutipan</p>
                            <p class="text-lg font-black text-gray-900">{{ formatAmount(reconciliation.expected) }}</p>
                        </div>
                        <div class="rounded-xl bg-white p-3 text-center border border-green-100">
                            <p class="text-xs text-gray-500">Terkumpul</p>
                            <p class="text-lg font-black text-green-700">{{ formatAmount(reconciliation.collected) }}</p>
                        </div>
                        <div class="rounded-xl bg-white p-3 text-center border border-amber-100">
                            <p class="text-xs text-gray-500">Tertunggak (RM)</p>
                            <p class="text-lg font-black text-amber-700">{{ formatAmount(reconciliation.outstanding) }}</p>
                        </div>
                        <div class="rounded-xl bg-white p-3 text-center border border-blue-100">
                            <p class="text-xs text-gray-500">Kadar Kutipan</p>
                            <p class="text-lg font-black text-blue-700">{{ reconciliation.rate }}%</p>
                        </div>
                    </div>
                </details>
            </div>

            <!-- Actions Row -->
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <input v-model="search" type="text" class="flex-1 min-w-[180px] rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Cari..." @keyup.enter="applyFilters">
                <select v-model="feeStatus" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0" @change="applyFilters">
                    <option v-for="opt in filterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <select v-model="selectedYear" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
                <select v-if="organizations.length" v-model="selectedOrg" @change="applyFilters" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                </select>
                <button @click="openManualPay" class="rounded-xl border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">+ Manual</button>
                <button @click="generateFees" class="rounded-xl border border-emerald-200 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">Jana Yuran</button>
                <button @click="openImport" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">Import CSV</button>
                <div class="relative group">
                    <button class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Export ▾</button>
                    <div class="absolute right-0 top-full z-20 mt-1 hidden w-40 rounded-xl border border-gray-100 bg-white shadow-lg group-hover:block">
                        <a :href="route('admin.fees.members.export.csv', { year: selectedYear })" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">CSV</a>
                        <a :href="route('admin.fees.members.export.excel', { year: selectedYear })" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">Excel</a>
                        <a :href="route('admin.fees.members.export.pdf', { year: selectedYear })" class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50">PDF</a>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="px-4 py-3">No Ahli</th><th class="px-4 py-3">Nama</th><th class="px-4 py-3">No IC</th>
                            <th class="px-4 py-3">Telefon</th><th class="px-4 py-3">Status Yuran</th><th class="px-4 py-3">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in members.data" :key="m.id" class="border-b border-gray-50 hover:bg-gray-50/50">
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">{{ m.member_no ?? '—' }}</td>
                            <td class="px-4 py-3 cursor-pointer hover:bg-indigo-50/30 rounded-lg" @click="openFinancialProfile(m)">
                                <p class="font-medium text-gray-900 hover:text-indigo-700 transition-colors">{{ m.name }}</p>
                                <p v-if="m.organization" class="text-xs text-gray-400">{{ m.organization.name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ m.ic_number ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ m.phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span :class="statusClass(m.fee.status)" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold">{{ statusLabel(m.fee.status) }}</span>
                                <p v-if="m.fee.paid_at" class="mt-0.5 text-xs text-gray-400">{{ m.fee.paid_at }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-1.5">
                                    <button @click="openHistory(m)" class="rounded-lg border border-indigo-200 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Sejarah</button>
                                    <div v-if="isSuperadmin" class="relative group">
                                        <button class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50">···</button>
                                        <div class="absolute right-0 top-full z-20 mt-1 hidden w-52 rounded-xl border border-gray-100 bg-white shadow-lg group-hover:block">
                                            <button @click="toggleLifeMember(m)" class="flex w-full items-center px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">{{ m.fee.status === 'life_member' ? 'Batal Yuran Seumur Hidup' : 'Yuran Seumur Hidup' }}</button>
                                            <button @click="openExempt(m)" class="flex w-full items-center px-4 py-2.5 text-left text-sm text-gray-700 hover:bg-gray-50">Pengecualian Yuran</button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!members.data.length"><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">Tiada ahli dijumpai.</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="members.links" class="mt-4 flex justify-center gap-1">
                <component v-for="(link, i) in members.links" :is="link.url ? Link : 'span'" :key="i" :href="link.url" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100'" v-html="link.label" />
            </div>
        </div>

        <!-- ─── Exempt Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="exemptTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="exemptTarget = null">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <h3 class="text-lg font-bold text-gray-900">Pengecualian Yuran</h3>
                    <p class="mt-1 text-sm text-gray-500">Ahli: <strong>{{ exemptTarget.name }}</strong></p>
                    <form @submit.prevent="submitExempt" class="mt-4 space-y-4">
                        <textarea v-model="exemptForm.reason" rows="2" class="w-full rounded-xl border border-gray-200 px-4 py-3 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Sebab pengecualian..." required></textarea>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="exemptTarget = null" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="exemptForm.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- ─── Manual Payment Modal ──────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="manualPayOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="manualPayOpen = false">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Bayaran Manual</h3>
                        <button @click="manualPayOpen = false" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100">&times;</button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">ID Ahli (user_id)</label>
                            <input v-model="manualForm.user_id" type="number" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400" placeholder="Masukkan ID ahli">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                            <select v-model="manualForm.year" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Jumlah (RM)</label>
                            <input v-model="manualForm.amount" type="number" step="0.01" min="0.01" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400" placeholder="50.00">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Rujukan <span class="text-xs text-gray-400">(optional)</span></label>
                            <input v-model="manualForm.reference" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400" placeholder="No rujukan">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Bukti Pembayaran <span class="text-red-500">*wajib</span></label>
                            <input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="manualForm.proof = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-600 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        </div>
                        <p v-if="manualError" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ manualError }}</p>
                        <p v-if="manualSuccess" class="rounded-xl bg-green-50 p-3 text-sm text-green-700">{{ manualSuccess }}</p>
                        <button @click="submitManualPay" :disabled="!manualForm.user_id || !manualForm.amount || !manualForm.proof" class="w-full rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-50">
                            Rekod Bayaran
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Slide-over: Profil Kewangan ──────────────────────────── -->
        <Teleport to="body">
            <div v-if="slideOver" class="fixed inset-0 z-50" @click.self="closePanel">
                <div class="fixed right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl overflow-y-auto">
                    <!-- Header -->
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-6 py-4">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ slideOver.name }}</h3>
                            <p class="text-sm text-gray-500">{{ slideOver.organization?.name }} · {{ slideOver.member_no || '—' }}</p>
                        </div>
                        <button @click="closePanel" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100">&times;</button>
                    </div>

                    <!-- Tabs -->
                    <div class="flex border-b border-gray-100 px-6">
                        <button @click="slideTab = 'info'" :class="slideTab === 'info' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-4 py-3 text-sm font-semibold transition">Ringkasan</button>
                        <button @click="slideTab = 'history'" :class="slideTab === 'history' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-4 py-3 text-sm font-semibold transition">Sejarah Pembayaran</button>
                        <button @click="slideTab = 'logs'" :class="slideTab === 'logs' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700'" class="border-b-2 px-4 py-3 text-sm font-semibold transition">Log Aktiviti</button>
                    </div>

                    <div class="p-6">
                        <!-- Loading -->
                        <div v-if="loadingPanel" class="p-6 space-y-4">
                            <div class="h-4 w-3/4 rounded bg-gray-100 animate-pulse"></div>
                            <div class="h-4 w-1/2 rounded bg-gray-100 animate-pulse"></div>
                            <div class="h-4 w-2/3 rounded bg-gray-100 animate-pulse"></div>
                        </div>

                        <!-- ═══ Ringkasan Tab ═══ -->
                        <div v-else-if="slideTab === 'info'">
                            <!-- Info Ahli -->
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="rounded-xl border border-gray-100 bg-white p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">No IC</p>
                                    <p class="text-sm font-bold text-gray-900">{{ slideOver.ic_number || '—' }}</p>
                                </div>
                                <div class="rounded-xl border border-gray-100 bg-white p-3">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">No Ahli</p>
                                    <p class="text-sm font-bold text-gray-900">{{ slideOver.member_no || '—' }}</p>
                                </div>
                            </div>

                            <!-- Status Yuran — guna data dari Inertia (slideOver.fee) -->
                            <div v-if="!slideOver.fee?.id" class="py-6 text-center text-sm text-gray-400">Tiada rekod yuran untuk tahun {{ selectedYear }}.</div>

                            <template v-else>
                                <div class="rounded-xl border border-gray-100 p-4 mb-4" :class="slideOver.fee.status === 'paid' ? 'bg-green-50 border-green-100' : 'bg-gray-50'">
                                    <div class="flex items-center justify-between mb-3">
                                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Status {{ slideOver.fee.year }}</span>
                                        <span :class="statusClass(slideOver.fee.status)" class="inline-flex rounded-full px-3 py-1 text-xs font-semibold">{{ statusLabel(slideOver.fee.status) }}</span>
                                    </div>
                                    <p v-if="slideOver.fee.notes" class="mt-1 text-xs text-gray-400">{{ slideOver.fee.notes }}</p>
                                </div>

                                <!-- Edit Mode -->
                                <div v-if="editing" class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jumlah (RM)</label>
                                        <input v-model.number="editForm.amount" type="number" step="0.01" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tarikh Bayar</label>
                                        <input v-model="editForm.paid_at" type="date" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-1">Catatan</label>
                                        <textarea v-model="editForm.notes" rows="3" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400"></textarea>
                                    </div>
                                    <p v-if="editError" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ editError }}</p>
                                    <div class="flex justify-end gap-3">
                                        <button @click="cancelEdit" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                                        <button @click="saveEdit" :disabled="editSaving" class="rounded-xl bg-indigo-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">{{ editSaving ? 'Menyimpan...' : 'Simpan' }}</button>
                                    </div>
                                </div>

                                <!-- View Mode -->
                                <div v-else class="space-y-3">
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="rounded-xl border border-gray-100 bg-white p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Jumlah</p>
                                            <p class="text-sm font-bold text-gray-900">{{ formatAmount(feeDetail?.amount ?? slideOver.fee.amount) }}</p>
                                        </div>
                                        <div class="rounded-xl border border-gray-100 bg-white p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Tarikh Bayar</p>
                                            <p class="text-sm font-bold text-gray-900">{{ slideOver.fee.paid_at || '—' }}</p>
                                        </div>
                                    </div>
                                    <div class="rounded-xl border border-gray-100 bg-white p-3">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Catatan</p>
                                        <p class="mt-0.5 text-sm text-gray-700">{{ slideOver.fee.notes || 'Tiada catatan' }}</p>
                                    </div>
                                    <button @click="startEdit" class="w-full rounded-xl border border-indigo-200 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">Edit</button>
                                </div>
                            </template>
                        </div>

                        <!-- ═══ Payment History Tab ═══ -->
                        <div v-else-if="slideTab === 'history'">
                            <div v-if="!paymentHistory.length" class="py-10 text-center text-sm text-gray-400">Tiada rekod pembayaran.</div>
                            <div v-else class="relative space-y-6">
                                <div v-for="(ph, i) in paymentHistory" :key="i" class="relative flex gap-4">
                                    <div class="flex flex-col items-center">
                                        <div class="z-10 h-3 w-3 rounded-full border-2 border-indigo-400 bg-white"></div>
                                        <div v-if="i < paymentHistory.length - 1" class="h-full w-0.5 bg-indigo-100"></div>
                                    </div>
                                    <div class="flex-1 pb-4">
                                        <div class="rounded-xl border border-gray-100 bg-gray-50 p-4">
                                            <div class="flex items-center justify-between">
                                                <p class="font-bold text-gray-900">{{ ph.year }} — {{ formatAmount(ph.amount) }}</p>
                                                <span class="text-xs text-gray-400">{{ ph.paid_at ? new Date(ph.paid_at).toLocaleDateString('ms-MY') : '—' }}</span>
                                            </div>
                                            <div class="mt-2 space-y-1 text-xs text-gray-500">
                                                <p>Rujukan: {{ ph.reference || '—' }}</p>
                                                <p>Sumber: {{ ph.source }}</p>
                                                <div class="flex items-center gap-2">
                                                    <p v-if="ph.has_proof" class="flex items-center gap-1">
                                                        <a :href="route('admin.fees.members.receipt', ph.payment_id || 0)" target="_blank" class="text-indigo-600 underline">Resit PDF</a>
                                                    </p>
                                                    <p v-else class="flex items-center gap-1 text-amber-600">Tiada bukti</p>
                                                    <button v-if="ph.status !== 'voided'" @click="voidPayment(ph.reference, ph.payment_id)" class="ml-auto rounded-lg border border-red-200 px-2 py-0.5 text-[10px] font-semibold text-red-600 hover:bg-red-50">Batalkan</button>
                                                    <span v-else class="ml-auto text-[10px] font-semibold text-red-500 bg-red-50 px-2 py-0.5 rounded-lg">Terbatal</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ═══ Activity Log Tab ═══ -->
                        <div v-else-if="slideTab === 'logs'">
                            <div v-if="!activityLogs.length" class="py-10 text-center text-sm text-gray-400">Tiada aktiviti.</div>
                            <div v-else class="space-y-3">
                                <div v-for="(log, i) in activityLogs" :key="i" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ log.action }}</p>
                                        <span class="text-[10px] text-gray-400">{{ log.created_at ? new Date(log.created_at).toLocaleDateString('ms-MY') : '—' }}</span>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-700">{{ log.description }}</p>
                                    <p v-if="log.performed_by" class="mt-1 text-[10px] text-gray-400">Oleh: {{ log.performed_by }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Import Modal ──────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="importOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeImport">
                <div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-2xl bg-white p-6 shadow-2xl">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900">Import Yuran CSV</h3>
                        <button @click="closeImport" class="rounded-xl p-2 text-gray-400 hover:bg-gray-100">&times;</button>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Tahun</label>
                            <select v-model="importYear" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400">
                                <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Fail CSV</label>
                            <input type="file" accept=".csv,.xlsx,.txt" @change="importFile = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                            <p class="mt-1 text-xs text-gray-400">Format: ic_number, no_ahli, amount (optional), reference (optional)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Bukti <span class="text-xs text-gray-400">(optional — PDF/imej)</span></label>
                            <input type="file" accept=".pdf,.png,.jpg,.jpeg" @change="importProof = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                        </div>
                        <div class="flex items-center gap-3">
                            <a :href="route('admin.fees.members.import.template')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Download Template</a>
                            <button @click="previewImport" :disabled="!importFile" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-50">Preview</button>
                        </div>
                    </div>
                    <p v-if="importError" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ importError }}</p>
                    <div v-if="importPreview" class="mt-6">
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-gray-700">Preview: {{ importPreview.ready }} akan diproses, {{ importPreview.skipped }} skip</p>
                            <button @click="processImport" :disabled="importProcessing || !importPreview.ready" class="rounded-xl bg-green-700 px-4 py-2 text-sm font-semibold text-white hover:bg-green-600 disabled:opacity-50">{{ importProcessing ? 'Memproses...' : 'Proses' }}</button>
                        </div>
                        <div class="max-h-64 overflow-y-auto rounded-xl border border-gray-100">
                            <table class="w-full text-xs">
                                <thead><tr class="border-b bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500"><th class="px-3 py-2">IC</th><th class="px-3 py-2">No Ahli</th><th class="px-3 py-2">Nama</th><th class="px-3 py-2">Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="(row, i) in importPreview.preview" :key="i" class="border-b border-gray-50">
                                        <td class="px-3 py-2 font-mono">{{ row.ic_number || '—' }}</td>
                                        <td class="px-3 py-2 font-mono">{{ row.no_ahli || '—' }}</td>
                                        <td class="px-3 py-2">{{ row.name || '—' }}</td>
                                        <td class="px-3 py-2"><span :class="previewStatusClass(row.status)" class="inline-block rounded-lg border px-2 py-0.5 text-xs font-semibold">{{ previewStatusLabel(row.status) }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div v-if="importResult" class="mt-6 space-y-3">
                        <div class="rounded-xl bg-green-50 border border-green-200 p-4">
                            <p class="font-bold text-green-800">Import Selesai!</p>
                            <div class="mt-2 space-y-1 text-sm text-green-700">
                                <p>Jumlah: {{ importResult.total_rows }} · Berjaya: <strong>{{ importResult.success_count }}</strong> · Skip: <strong>{{ importResult.skip_count }}</strong></p>
                            </div>
                        </div>
                        <div v-if="importResult.errors?.length" class="max-h-40 overflow-y-auto rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <p class="mb-1 text-xs font-semibold text-amber-800">Skipped:</p>
                            <p v-for="(err, i) in importResult.errors" :key="i" class="text-xs text-amber-700">{{ err }}</p>
                        </div>
                        <button @click="closeImport" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Tutup</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- ─── Global Components ──────────────────────────────────── -->
        <ToastNotification ref="toast" />
        <ConfirmDialog ref="confirmDialog" :message="confirmMsg" :variant="confirmVariant" @confirm="onConfirmAction" @cancel="onCancelAction" />
    </AppLayout>
</template>

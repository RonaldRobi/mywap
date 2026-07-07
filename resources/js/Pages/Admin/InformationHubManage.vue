<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    isSuperadmin: Boolean,
    defaultOrganizationId: Number,
    organizations: {
        type: Array,
        default: () => [],
    },
    members: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    branches: {
        type: Array,
        default: () => [],
    },
    orgPositions: {
        type: Array,
        default: () => [],
    },
    stats: {
        type: Object,
        default: () => ({ total: 0, paid: 0, due: 0, life_member: 0 }),
    },
    orgStats: {
        type: Array,
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', organization_id: '', role: '' }),
    },
});

// ─── Dropdown Menu ─────────────────────────────────────────────────────────

const openDropdownId = ref(null);

function toggleDropdown(id) {
    openDropdownId.value = openDropdownId.value === id ? null : id;
}

function closeDropdown() {
    openDropdownId.value = null;
}

// Close dropdown on outside click
onMounted(() => document.addEventListener('click', closeDropdown));
onUnmounted(() => document.removeEventListener('click', closeDropdown));

// Activity Log state
const activityLogs = ref([]);
const loadingLogs = ref(false);

async function fetchActivityLogs(memberId) {
    loadingLogs.value = true;
    activityLogs.value = [];
    try {
        const res = await window.axios.get(route('admin.hub.members.logs', memberId));
        activityLogs.value = res.data.data ?? [];
    } catch (e) {
        console.error('Failed to load activity logs', e);
    } finally {
        loadingLogs.value = false;
    }
}

// Member quick actions
function toggleActive(member) {
    if (!confirm(member.is_active ? 'Nyahaktifkan ahli ini?' : 'Aktifkan semula ahli ini?')) return;
    router.patch(route('admin.hub.members.toggle-active', member.id), {}, {
        preserveScroll: true,
        onSuccess: () => { openDropdownId.value = null; },
    });
}

function resetPassword(member) {
    if (!confirm('Hantar link reset kata laluan ke emel ahli?')) return;
    router.post(route('admin.hub.members.reset-password', member.id), {}, {
        preserveScroll: true,
        onSuccess: () => { openDropdownId.value = null; },
    });
}

// ─── Program Year Filter ──────────────────────────────────────────────────

const availableProgramYears = computed(() => {
    const programs = selectedMember.value?.attended_programs ?? [];
    const years = [...new Set(programs.map(p => p.year).filter(Boolean))];
    return years.sort((a, b) => b - a);
});

const filteredPrograms = computed(() => {
    const programs = selectedMember.value?.attended_programs ?? [];
    if (!programYearFilter.value) return programs;
    return programs.filter(p => p.year === parseInt(programYearFilter.value));
});

// ─── Pagination ────────────────────────────────────────────────────────────

const visiblePages = computed(() => {
    const last = props.members.last_page ?? 1;
    const cur = props.members.current_page ?? 1;
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }
    const pages = [];
    pages.push(1);
    if (cur > 3) pages.push('...');
    for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) {
        pages.push(i);
    }
    if (cur < last - 2) pages.push('...');
    pages.push(last);
    return pages;
});

function changePerPage(e) {
    const val = e.target.value;
    router.get(route('admin.hub.manage'), { ...route().params, per_page: val, page: 1 }, { preserveState: true, replace: true });
}

function jumpToPage(e) {
    const val = parseInt(e.target.value);
    if (val < 1 || val > (props.members.last_page ?? 1)) return;
    router.get(route('admin.hub.manage'), { ...route().params, page: val }, { preserveState: true, replace: true });
}

// ─── Add Member Form ─────────────────────────────────────────────────────────

const showMemberForm = ref(false);
const memberForm = useForm({
    name: '',
    email: '',
    ic_number: '',
    phone: '',
    dob: '',
    password: '',
});

const inferredOrganization = computed(() => {
    if (!memberForm.dob) return null;
    const dob = new Date(memberForm.dob);
    if (Number.isNaN(dob.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const hasBirthdayPassed = today.getMonth() > dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() >= dob.getDate());
    if (!hasBirthdayPassed) age -= 1;

    return props.organizations.find((organization) => {
        const minAge = Number(organization.min_age ?? 0);
        const maxAge = organization.max_age === null ? null : Number(organization.max_age);
        if (Number.isNaN(minAge)) return false;
        if (maxAge !== null && Number.isNaN(maxAge)) return false;
        return age >= minAge && (maxAge === null || age <= maxAge);
    }) ?? null;
});

function submitMember() {
    memberForm.post(route('superadmin.members.store'), {
        preserveScroll: true,
        onSuccess: () => {
            memberForm.reset();
            showMemberForm.value = false;
        },
    });
}

// ─── Filters & Search ────────────────────────────────────────────────────────

const searchQuery = ref(props.filters?.search ?? '');
const organizationIdFilter = ref(props.filters?.organization_id ?? '');
const roleFilter = ref(props.filters?.role ?? '');
const branchIdFilter = ref(props.filters?.branch_id ?? '');
const feeStatusFilter = ref(props.filters?.fee_status ?? '');
const registeredFrom = ref(props.filters?.registered_from ?? '');
const registeredTo = ref(props.filters?.registered_to ?? '');

let filterDebounce;

watch([searchQuery, organizationIdFilter, roleFilter, branchIdFilter, feeStatusFilter], ([newSearch, newOrg, newRole, newBranch, newFeeStatus]) => {
    clearTimeout(filterDebounce);
    filterDebounce = setTimeout(() => {
        router.get(
            route('admin.hub.manage'),
            { search: newSearch?.trim() || '', organization_id: newOrg || '', role: newRole || '', branch_id: newBranch || '', fee_status: newFeeStatus || '', registered_from: registeredFrom.value || '', registered_to: registeredTo.value || '' },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    }, 300);
});

function applyDateFilter() {
    router.get(
        route('admin.hub.manage'),
        { search: searchQuery.value?.trim() || '', organization_id: organizationIdFilter.value || '', role: roleFilter.value || '', branch_id: branchIdFilter.value || '', fee_status: feeStatusFilter.value || '', registered_from: registeredFrom.value || '', registered_to: registeredTo.value || '' },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

// ─── Update Role ─────────────────────────────────────────────────────────────

const updatingUserId = ref(null);
const showIcModal = ref(false);
const icTargetMember = ref(null);
const icForm = useForm({
    ic_number: '',
});

function updateRole(userId, newRole) {
    if (!confirm(`Sahkan penukaran peranan kepada ${newRole}?`)) return;
    updatingUserId.value = userId;

    router.patch(route('admin.hub.members.role.update', userId), { role: newRole }, {
        preserveScroll: true,
        onSuccess: () => {
            updatingUserId.value = null;
        },
        onError: (errors) => {
            console.error('Role update failed:', errors);
            updatingUserId.value = null;
            alert('Gagal menukar peranan. Sila cuba lagi.');
        },
        onFinish: () => { updatingUserId.value = null; }
    });
}

function maskedPreviewToRaw(masked) {
    if (!masked) return '';
    return String(masked).replace(/\*/g, '');
}

function openIcModal(member) {
    icTargetMember.value = member;
    icForm.reset();
    icForm.clearErrors();
    icForm.ic_number = maskedPreviewToRaw(member.ic_number);
    showIcModal.value = true;
}

function closeIcModal() {
    showIcModal.value = false;
    icTargetMember.value = null;
    icForm.reset();
    icForm.clearErrors();
}

function submitIcNumberUpdate() {
    if (!icTargetMember.value) return;

    icForm.patch(route('admin.hub.members.ic.update', icTargetMember.value.id), {
        preserveScroll: true,
        onSuccess: () => closeIcModal(),
    });
}

// ─── Profile Slide-Over Panel ────────────────────────────────────────────────

const showProfilePanel = ref(false);
const selectedMember = ref(null);
const editing = ref(false);
const panelTab = ref('peribadi');
const programYearFilter = ref('');

watch(panelTab, (newTab) => {
    if (newTab === 'aktiviti' && selectedMember.value?.id) {
        fetchActivityLogs(selectedMember.value.id);
    }
});

function viewProfile(member) {
    selectedMember.value = member;
    editing.value = false;
    panelTab.value = 'peribadi';
    showProfilePanel.value = true;
}

function handlePanelKeydown(e) {
    if (e.key === 'Escape') closeProfilePanel();
}

function closeProfilePanel() {
    showProfilePanel.value = false;
    selectedMember.value = null;
    editing.value = false;
    panelTab.value = 'peribadi';
}

const editForm = useForm({
    name: '',
    email: '',
    phone: '',
    ic_number: '',
    member_no: '',
    dob: '',
    gender: '',
    marital_status: '',
    education_level: '',
    current_profession: '',
    industry: '',
    expertise: '',
    position: '',
    topics: '',
    branch_id: '',
    locality: '',
    linkedin_url: '',
    address_1: '',
    address_2: '',
    postcode: '',
    city: '',
    state: '',
    emergency_contact_name: '',
    emergency_contact_phone: '',
    is_public_in_directory: true,
    is_branch_admin: false,
});

function startEdit() {
    if (!selectedMember.value) return;
    const m = selectedMember.value;
    editForm.name = m.name ?? '';
    editForm.email = m.email ?? '';
    editForm.phone = m.phone ?? '';
    editForm.ic_number = maskedPreviewToRaw(m.ic_number) ?? '';
    editForm.member_no = m.member_no ?? '';
    editForm.dob = m.dob ?? '';
    editForm.gender = m.gender ?? '';
    editForm.marital_status = m.marital_status ?? '';
    editForm.education_level = m.education_level ?? '';
    editForm.current_profession = m.current_profession ?? '';
    editForm.industry = m.industry ?? '';
    editForm.expertise = m.expertise ?? '';
    editForm.position = m.position ?? '';
    editForm.topics = m.topics ?? '';
    editForm.branch_id = m.branch_id ?? '';
    editForm.locality = m.locality ?? '';
    editForm.linkedin_url = m.linkedin_url ?? '';
    editForm.address_1 = m.address_1 ?? '';
    editForm.address_2 = m.address_2 ?? '';
    editForm.postcode = m.postcode ?? '';
    editForm.city = m.city ?? '';
    editForm.state = m.state ?? '';
    editForm.emergency_contact_name = m.emergency_contact_name ?? '';
    editForm.emergency_contact_phone = m.emergency_contact_phone ?? '';
    editForm.is_public_in_directory = m.is_public_in_directory ?? true;
    editForm.is_branch_admin = m.has_role_admin_cawangan ?? false;
    editing.value = true;
}

function cancelEdit() {
    editing.value = false;
    editForm.reset();
}

function submitEdit() {
    if (!selectedMember.value) return;
    editForm.patch(route('admin.members.update', selectedMember.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            editing.value = false;
        },
    });
}

// ─── Import Excel ────────────────────────────────────────────────────────────
const showImportModal = ref(false);
const importForm = useForm({
    excel_file: null,
    organization_id: '',
});
const excelFileInput = ref(null);

function triggerExcelImport() {
    showImportModal.value = true;
}

function proceedToSelectFile() {
    if (!importForm.organization_id) {
        alert("Sila pilih Organisasi terlebih dahulu.");
        return;
    }
    if (excelFileInput.value) {
        excelFileInput.value.click();
    }
}

function closeImportModal() {
    showImportModal.value = false;
    importForm.reset();
    importForm.clearErrors();
}

const importState = ref({
    processing: false,
    progressText: '',
    filename: '',
    prefix: '',
    start_row: 2,
    chunk_size: 100,
});
const importErrors = ref([]);

async function handleExcelUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    showImportModal.value = false;
    importState.value.processing = true;
    importState.value.progressText = 'Sedang memuat naik fail...';

    // 1. Upload the file to start
    const formData = new FormData();
    formData.append('excel_file', file);
    formData.append('organization_id', importForm.organization_id);

    try {
        const startRes = await window.axios.post(route('admin.hub.members.importStart'), formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        importState.value.filename = startRes.data.filename;
        importState.value.prefix = startRes.data.prefix;
        importState.value.start_row = 2;
        importErrors.value = [];

        // 2. Loop through chunks
        await processNextChunk();
    } catch (error) {
        importState.value.processing = false;
        alert('Gagal memulakan proses import. Sila pastikan saiz fail dan format betul.');
        if (excelFileInput.value) excelFileInput.value.value = null;
    }
}

async function processNextChunk() {
    importState.value.progressText = `Sedang memproses baris ke-${importState.value.start_row} hingga ${importState.value.start_row + importState.value.chunk_size - 1}...`;
    
    try {
        const res = await window.axios.post(route('admin.hub.members.importChunk'), {
            filename: importState.value.filename,
            organization_id: importForm.organization_id,
            prefix: importState.value.prefix,
            start_row: importState.value.start_row,
            chunk_size: importState.value.chunk_size
        });

        if (res.data.errors && res.data.errors.length) {
            importErrors.value.push(...res.data.errors);
        }

        const processed = res.data.processed;
        if (processed > 0) {
            importState.value.start_row += processed;
            
            if (processed < importState.value.chunk_size) {
                // Done! Reached end of file
                await finishImport();
            } else {
                // Continue next chunk
                processNextChunk();
            }
        } else {
            // Done!
            await finishImport();
        }
    } catch (error) {
        importState.value.processing = false;
        alert('Gagal memproses sebahagian data. Sila cuba lagi.');
        await finishImport();
    }
}

async function finishImport() {
    importState.value.progressText = 'Membersihkan fail sementara...';
    try {
        await window.axios.post(route('admin.hub.members.importFinish'), {
            filename: importState.value.filename
        });
    } catch (e) {}

    importState.value.processing = false;

    let msg = 'Proses import selesai!';
    if (importErrors.value.length) {
        msg += '\n\n⚠️ Amaran (' + importErrors.value.length + '):\n' + importErrors.value.slice(0, 10).join('\n');
        if (importErrors.value.length > 10) {
            msg += '\n...dan ' + (importErrors.value.length - 10) + ' lagi.';
        }
    }
    alert(msg);
    window.location.reload();
}
</script>

<template>
    <Head title="Pengurusan Ahli" />

    <AppLayout>
        <template #header>Pengurusan Ahli</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition-all duration-300">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ $page.props.flash.success }}
                </span>
            </div>

            <div v-if="$page.props.flash?.error" class="mt-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 shadow-sm transition-all duration-300">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    {{ $page.props.flash.error }}
                </span>
            </div>

            <!-- Stats Bar -->
            <div class="grid grid-cols-3 gap-3 md:gap-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-gray-900">{{ stats.total }}</p>
                    <p class="text-xs font-semibold text-gray-500 mt-0.5">Jumlah Ahli</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-emerald-700">{{ stats.aktif }}</p>
                    <p class="text-xs font-semibold text-emerald-600 mt-0.5">Ahli Aktif</p>
                </div>
                <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-gray-500">{{ stats.tidak_aktif }}</p>
                    <p class="text-xs font-semibold text-gray-400 mt-0.5">Tidak Aktif</p>
                </div>
            </div>

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-black tracking-tight text-gray-900">Senarai Ahli & Pengguna</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Urus keahlian, organisasi, dan peranan sistem.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a
                        :href="route('admin.members.export')"
                        class="inline-flex items-center gap-2 rounded-2xl bg-white border border-gray-200 px-5 py-2.5 text-sm font-bold text-gray-600 shadow-sm hover:bg-gray-50 hover:text-gray-900 transition-all hover:-translate-y-0.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Export CSV
                    </a>
                    <button
                        v-if="isSuperadmin"
                        @click="triggerExcelImport"
                        :disabled="importForm.processing"
                        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition-all hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                        {{ importForm.processing ? 'Memproses...' : 'Import Excel' }}
                    </button>
                    <input type="file" ref="excelFileInput" class="hidden" accept=".xlsx,.xls,.csv" @change="handleExcelUpload">

                    <button
                        v-if="isSuperadmin"
                        @click="showMemberForm = !showMemberForm"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5"
                    >
                        <svg v-if="!showMemberForm" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        {{ showMemberForm ? 'Tutup Borang' : 'Tambah Ahli Baharu' }}
                    </button>
                </div>
            </div>

            <!-- Add Member Form -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <section v-if="showMemberForm && isSuperadmin" class="relative rounded-3xl border border-gray-100 bg-white p-6 shadow-xl shadow-gray-200/40">
                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitMember">
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Penuh</label>
                            <input v-model="memberForm.name" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required>
                            <p v-if="memberForm.errors.name" class="mt-1 text-xs text-red-600">{{ memberForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Email</label>
                            <input v-model="memberForm.email" type="email" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required>
                            <p v-if="memberForm.errors.email" class="mt-1 text-xs text-red-600">{{ memberForm.errors.email }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">No. Telefon</label>
                            <input v-model="memberForm.phone" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all">
                            <p v-if="memberForm.errors.phone" class="mt-1 text-xs text-red-600">{{ memberForm.errors.phone }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">No IC / Passport</label>
                            <input v-model="memberForm.ic_number" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required>
                            <p v-if="memberForm.errors.ic_number" class="mt-1 text-xs text-red-600">{{ memberForm.errors.ic_number }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tarikh Lahir</label>
                            <input v-model="memberForm.dob" type="date" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required>
                            <p v-if="memberForm.errors.dob" class="mt-1 text-xs text-red-600">{{ memberForm.errors.dob }}</p>
                            <p v-if="inferredOrganization" class="mt-1.5 text-xs font-semibold text-emerald-600 flex items-center gap-1">
                                <svg class="w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                Disusun ke: {{ inferredOrganization.name }}
                            </p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Password (Opsyenal)</label>
                            <input v-model="memberForm.password" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" placeholder="Kosongkan untuk katalaluan lalai: password123">
                            <p v-if="memberForm.errors.password" class="mt-1 text-xs text-red-600">{{ memberForm.errors.password }}</p>
                        </div>
                        <div class="md:col-span-2 flex justify-end gap-3 pt-2">
                            <button type="button" @click="showMemberForm = false" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 transition-all">Batal</button>
                            <button type="submit" :disabled="memberForm.processing" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 shadow-sm transition-all disabled:opacity-60">
                                {{ memberForm.processing ? 'Menyimpan...' : 'Simpan Ahli' }}
                            </button>
                        </div>
                    </form>
                </section>
            </transition>

            <!-- Filters Section -->
            <div class="flex flex-col gap-3">
                <div class="flex flex-col md:flex-row gap-3">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input v-model="searchQuery" type="text" placeholder="Cari nama, email, no ahli, IC/passport, no telefon..." class="pl-10 w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                    </div>

                    <div v-if="isSuperadmin" class="relative md:w-48 shrink-0">
                        <select v-model="organizationIdFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                            <option value="">Semua Organisasi</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>

                    <div class="relative md:w-48 shrink-0">
                        <select v-model="roleFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                            <option value="">Semua Peranan</option>
                            <option value="Admin">Admin</option>
                            <option value="Member">Member / Ahli</option>
                        </select>
                    </div>

                    <div class="relative md:w-48 shrink-0">
                        <select v-model="feeStatusFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                            <option value="">Semua Status Yuran</option>
                            <option value="paid">Lunas</option>
                            <option value="due">Tertunggak</option>
                            <option value="life_member">Seumur Hidup</option>
                            <option value="exempted">Dikecualikan</option>
                        </select>
                    </div>

                    <div class="relative md:w-48 shrink-0">
                        <select v-model="branchIdFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                            <option value="">Semua Cawangan</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </div>

                <div class="flex flex-col md:flex-row items-center gap-3">
                    <div class="relative md:w-40 shrink-0">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Daftar Dari</label>
                        <input v-model="registeredFrom" type="date" @change="applyDateFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                    </div>
                    <div class="relative md:w-40 shrink-0">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Hingga</label>
                        <input v-model="registeredTo" type="date" @change="applyDateFilter" class="w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors">
                    </div>
                    <button v-if="registeredFrom || registeredTo" @click="registeredFrom = ''; registeredTo = ''; applyDateFilter()" class="rounded-2xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-500 hover:bg-gray-50 mt-5">
                        Kosongkan Tarikh
                    </button>
                </div>
            </div>

            <!-- Members Table -->
            <div class="rounded-3xl border border-gray-100 bg-white overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50/70 border-b border-gray-100 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-bold text-xs">No Ahli</th>
                                <th scope="col" class="px-4 py-3 font-bold text-xs">Ahli</th>
                                <th scope="col" class="px-4 py-3 font-bold text-xs">Organisasi & Yuran</th>
                                <th scope="col" class="px-4 py-3 font-bold text-xs">Peranan</th>
                                <th scope="col" class="px-4 py-3 font-bold text-xs text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="members.data.length === 0">
                                <td colspan="5" class="px-4 py-12 text-center text-gray-400">Tiada ahli dijumpai.</td>
                            </tr>
                            <tr v-for="member in members.data" :key="member.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3">
                                    <span class="text-xs font-mono font-bold text-gray-900">{{ member.member_no || '—' }}</span>
                                    <div v-if="member.original_member_no && member.original_member_no !== member.member_no" class="text-[10px] text-gray-400">Asal: {{ member.original_member_no }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 shrink-0 flex items-center justify-center rounded-full bg-gray-100 text-gray-600 font-bold border border-gray-200 text-xs">
                                            {{ (member.name ?? '?').charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-bold text-gray-900 truncate">{{ member.name }} <span v-if="!member.is_active" class="inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-semibold text-red-700 ml-1">Nyahaktif</span></div>
                                            <div class="text-xs text-gray-400 truncate">{{ member.email }}</div>
                                            <div class="flex items-center gap-2 text-[11px] text-gray-400">
                                                <span>IC: {{ member.ic_number || '-' }}</span>
                                                <span v-if="member.phone">• {{ member.phone }}</span>
                                    </div>
                                </div>
                            </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-lg px-2 py-0.5 text-xs font-semibold" :style="{ backgroundColor: (member.organization_color ?? '#6b7280') + '20', color: member.organization_color ?? '#6b7280' }">
                                        {{ member.organization_name }}
                                    </span>
                                    <div class="mt-1 flex items-center gap-1.5">
                                        <span v-if="member.fee_status === 'paid'" class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-[11px] font-semibold text-green-700">Lunas</span>
                                        <span v-else-if="member.fee_status === 'life_member'" class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-700">Seumur Hidup</span>
                                        <span v-else-if="member.fee_status === 'exempted'" class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-600">Dikecualikan</span>
                                        <span v-else class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Tertunggak</span>
                                        <span v-if="member.has_role_admin_cawangan" class="inline-flex items-center rounded-full bg-teal-100 px-2 py-0.5 text-[11px] font-semibold text-teal-700">Admin Cawangan</span>
                                        <span class="text-[11px] text-gray-400">{{ member.branch_name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <select 
                                        :value="member.role" 
                                        @change="updateRole(member.id, $event.target.value)"
                                        :disabled="updatingUserId === member.id || member.role === 'Superadmin'"
                                        class="h-7 py-0 pl-2 pr-7 rounded-lg text-xs font-bold border-gray-200 focus:border-gray-900 focus:ring-0 transition-colors cursor-pointer disabled:opacity-50"
                                        :class="{
                                            'bg-emerald-50 text-emerald-700 border-emerald-200': member.role === 'Admin',
                                            'bg-blue-50 text-blue-700 border-blue-200': member.role === 'Member',
                                            'bg-yellow-50 text-yellow-700 border-yellow-200': member.role === 'Superadmin',
                                            'bg-teal-50 text-teal-700 border-teal-200': member.role === 'Admin Cawangan',
                                        }"
                                    >
                                        <option value="Member">Ahli</option>
                                        <option value="Admin">Admin</option>
                                        <option v-if="!isSuperadmin" value="Admin Cawangan">Admin Cawangan</option>
                                        <option v-if="member.role === 'Superadmin'" value="Superadmin" disabled>Superadmin</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="relative inline-flex items-center gap-1">
                                        <button @click="viewProfile(member)" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors inline-flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Profil
                                        </button>
                                        <button @click.stop="toggleDropdown(member.id)" class="rounded-lg border border-gray-200 px-2 py-1.5 text-xs text-gray-500 hover:bg-gray-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                                        </button>
                                        <div v-if="openDropdownId === member.id" class="absolute right-0 top-full mt-1 z-30 w-44 rounded-xl border border-gray-100 bg-white py-1 shadow-lg" @click.stop>
                                            <button @click="viewProfile(member)" class="flex w-full items-center gap-2 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                Lihat Profil
                                            </button>
                                            <a v-if="member.member_no" :href="route('public.card', member.member_no)" target="_blank" class="flex w-full items-center gap-2 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                Lihat Kad Ahli
                                            </a>
                                            <button v-if="isSuperadmin" @click="openIcModal(member)" class="flex w-full items-center gap-2 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/></svg>
                                                Update IC
                                            </button>
                                            <button v-if="isSuperadmin" @click="resetPassword(member)" class="flex w-full items-center gap-2 px-4 py-2 text-xs text-gray-700 hover:bg-gray-50">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                                Reset Kata Laluan
                                            </button>
                                            <button @click="toggleActive(member)" class="flex w-full items-center gap-2 px-4 py-2 text-xs hover:bg-gray-50" :class="member.is_active ? 'text-red-600' : 'text-emerald-600'">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                                                {{ member.is_active ? 'Nyahaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <div class="flex flex-col md:flex-row items-center justify-between gap-4 pb-6">
                <div class="text-xs text-gray-500">
                    Menunjukkan
                    <span class="font-semibold text-gray-700">{{ members.from ?? 0 }}</span>
                    -
                    <span class="font-semibold text-gray-700">{{ members.to ?? 0 }}</span>
                    dari
                    <span class="font-semibold text-gray-700">{{ members.total ?? 0 }}</span>
                    ahli
                </div>

                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span>Paparan</span>
                        <select @change="changePerPage" class="rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-900 focus:ring-0">
                            <option :value="25" :selected="(filters.per_page ?? 25) == 25">25</option>
                            <option :value="50" :selected="(filters.per_page ?? 25) == 50">50</option>
                            <option :value="100" :selected="(filters.per_page ?? 25) == 100">100</option>
                        </select>
                    </div>

                    <div v-if="members.last_page > 1" class="flex items-center gap-1">
                        <Link v-if="members.prev_page_url" :href="members.prev_page_url" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-900 text-sm">‹</Link>
                        <template v-for="(page, i) in visiblePages" :key="i">
                            <span v-if="page === '...'" class="text-gray-400 px-1 text-xs">...</span>
                            <Link v-else :href="members.links?.find(l => l.label == page)?.url ?? '#'" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-xs font-bold transition-all" :class="page == members.current_page ? 'bg-gray-900 text-white shadow-sm' : 'border border-gray-200 text-gray-600 hover:border-gray-900'">{{ page }}</Link>
                        </template>
                        <Link v-if="members.next_page_url" :href="members.next_page_url" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-200 text-gray-600 hover:border-gray-900 text-sm">›</Link>
                    </div>

                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span>Lompat</span>
                        <input type="number" :min="1" :max="members.last_page" class="w-14 rounded-lg border border-gray-200 px-2 py-1 text-xs text-center focus:border-gray-900 focus:ring-0" placeholder="#" @keyup.enter="jumpToPage">
                    </div>
                </div>
            </div>

            <!-- Back navigation -->
            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-bold text-gray-500 hover:text-gray-900 transition-colors inline-block pb-6">
                    ← Kembali ke Dashboard
                </Link>
            </div>

            <Teleport to="body">
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="showIcModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-sm"
                        @click.self="closeIcModal"
                    >
                        <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-5 shadow-2xl">
                            <div class="mb-4">
                                <h3 class="text-lg font-black text-gray-900">Kemas Kini No IC / Passport</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Pengguna: <span class="font-semibold text-gray-700">{{ icTargetMember?.name || '-' }}</span>
                                </p>
                            </div>

                            <form class="space-y-3" @submit.prevent="submitIcNumberUpdate">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">No IC / Passport Baharu</label>
                                    <input
                                        v-model="icForm.ic_number"
                                        type="text"
                                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900"
                                        required
                                    >
                                    <p v-if="icForm.errors.ic_number" class="mt-1 text-xs text-red-600">{{ icForm.errors.ic_number }}</p>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        @click="closeIcModal"
                                        class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="icForm.processing"
                                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                                    >
                                        {{ icForm.processing ? 'Menyimpan...' : 'Simpan IC' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </Transition>

                <!-- Import Excel Modal -->
                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="showImportModal"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/40 p-4 backdrop-blur-sm"
                        @click.self="closeImportModal"
                    >
                        <div class="w-full max-w-md rounded-2xl border border-gray-100 bg-white p-5 shadow-2xl">
                            <div class="mb-4">
                                <h3 class="text-lg font-black text-gray-900">Import Excel</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    Pilih organisasi untuk data yang akan diimport. (Prefix no ahli akan ditambah secara automatik).
                                </p>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Organisasi</label>
                                    <select
                                        v-model="importForm.organization_id"
                                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900"
                                        required
                                    >
                                        <option value="" disabled>Sila pilih organisasi</option>
                                        <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                    </select>
                                    <p v-if="importForm.errors.organization_id" class="mt-1 text-xs text-red-600">{{ importForm.errors.organization_id }}</p>
                                </div>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button
                                        type="button"
                                        @click="closeImportModal"
                                        class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        type="button"
                                        @click="proceedToSelectFile"
                                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700"
                                    >
                                        Pilih Fail & Import
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </Transition>

            <!-- Loading Overlay for Import -->
                <Transition
                    enter-active-class="transition ease-out duration-300"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition ease-in duration-300"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="importState.processing"
                        class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-white/90 backdrop-blur-md"
                    >
                        <div class="relative flex items-center justify-center">
                            <!-- Outer spinning ring -->
                            <div class="absolute w-24 h-24 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
                            <!-- Inner static icon -->
                            <div class="absolute bg-white rounded-full p-3 shadow-sm text-indigo-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                </svg>
                            </div>
                        </div>
                        <h2 class="mt-14 text-2xl font-black text-gray-900 tracking-tight">Sedang Mengimport Data...</h2>
                        <p class="mt-2 text-indigo-600 font-bold text-lg text-center max-w-sm">
                            {{ importState.progressText }}
                        </p>
                        <p class="mt-1 text-gray-500 font-medium text-center max-w-sm leading-relaxed text-sm">
                            Sistem sedang membaca dan memasukkan data Excel anda secara berperingkat. <br><br><span class="text-gray-900 font-bold">Sila jangan tutup atau muat semula (refresh) halaman ini.</span>
                        </p>
                    </div>
                </Transition>
            </Teleport>
        </div>
    </AppLayout>

    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <!--  PROFILE SLIDE-OVER PANEL                                            -->
    <!-- ══════════════════════════════════════════════════════════════════════ -->
    <Teleport to="body">
        <div v-if="showProfilePanel" class="fixed inset-0 z-50 flex" @keydown="handlePanelKeydown" tabindex="-1">
            <Transition enter-active-class="transition-opacity duration-300" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition-opacity duration-200" leave-from-class="opacity-100" leave-to-class="opacity-0">
                <div v-show="showProfilePanel" class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="closeProfilePanel"></div>
            </Transition>
            <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-x-full" enter-to-class="translate-x-0" leave-active-class="transition duration-200 ease-in" leave-from-class="translate-x-0" leave-to-class="translate-x-full">
                <div v-show="showProfilePanel" class="relative ml-auto w-full max-w-xl bg-white shadow-2xl overflow-y-auto" @click.stop>
                    <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white px-4 py-3">
                        <h2 class="text-sm font-bold text-gray-800">Profil Ahli</h2>
                        <div class="flex items-center gap-2">
                            <button v-if="!editing" @click="startEdit" class="rounded-lg bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-800">Edit Profil</button>
                            <button @click="closeProfilePanel" class="rounded-lg p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tabs -->
                    <div v-if="selectedMember && !editing">
                        <div class="flex border-b border-gray-100 px-4 bg-gray-50/50">
                            <button @click="panelTab = 'peribadi'" class="flex items-center gap-1.5 px-3 py-3 text-xs font-semibold border-b-2 transition-colors" :class="panelTab === 'peribadi' ? 'border-gray-900 text-gray-900 bg-white' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                Peribadi
                            </button>
                            <button @click="panelTab = 'kerjaya'" class="flex items-center gap-1.5 px-3 py-3 text-xs font-semibold border-b-2 transition-colors" :class="panelTab === 'kerjaya' ? 'border-gray-900 text-gray-900 bg-white' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Kerjaya
                            </button>
                            <button @click="panelTab = 'alamat'" class="flex items-center gap-1.5 px-3 py-3 text-xs font-semibold border-b-2 transition-colors" :class="panelTab === 'alamat' ? 'border-gray-900 text-gray-900 bg-white' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Alamat
                            </button>
                            <button @click="panelTab = 'lain'" class="flex items-center gap-1.5 px-3 py-3 text-xs font-semibold border-b-2 transition-colors" :class="panelTab === 'lain' ? 'border-gray-900 text-gray-900 bg-white' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Lain
                            </button>
                            <button @click="panelTab = 'aktiviti'" class="flex items-center gap-1.5 px-3 py-3 text-xs font-semibold border-b-2 transition-colors" :class="panelTab === 'aktiviti' ? 'border-gray-900 text-gray-900 bg-white' : 'border-transparent text-gray-400 hover:text-gray-600'">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                Aktiviti
                            </button>
                        </div>

                        <div class="p-6 space-y-5 text-sm">
                            <!-- ═══ PERIBADI ═══ -->
                            <div v-if="panelTab === 'peribadi'" class="space-y-5">
                                <div class="flex items-center gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50/60">
                                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full text-xl font-bold text-white border-2 border-white shadow-sm" :style="{ background: 'linear-gradient(135deg, ' + (selectedMember.organization_color ?? '#6b7280') + ', ' + (selectedMember.organization_color ?? '#6b7280') + '88)' }">
                                        {{ (selectedMember.name ?? '?').charAt(0).toUpperCase() }}
                                    </div>
                                    <div>
                                        <p class="text-lg font-bold text-gray-900">{{ selectedMember.name }}</p>
                                        <p v-if="selectedMember.member_no" class="text-sm font-bold" :style="{ color: selectedMember.organization_color ?? '#6b7280' }">{{ selectedMember.member_no }}</p>
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold mt-1" :style="{ backgroundColor: (selectedMember.organization_color ?? '#6b7280') + '20', color: selectedMember.organization_color ?? '#6b7280' }">{{ selectedMember.organization_name }}</span>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Maklumat Asas</p>
                                    </div>
                                    <div class="p-4 grid grid-cols-2 gap-x-6 gap-y-3.5">
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">No Ahli</p><p class="text-sm font-bold text-gray-800">{{ selectedMember.member_no || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Asal No</p><p class="text-sm font-semibold text-gray-600">{{ selectedMember.original_member_no || '—' }}</p><p v-if="selectedMember.original_member_no && selectedMember.original_member_no !== selectedMember.member_no" class="text-[10px] text-gray-400">Nombor pertama semasa mendaftar</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">No IC</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.ic_number || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Jantina</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.gender || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Tarikh Lahir</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.dob || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Status Perkahwinan</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.marital_status || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Telefon</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.phone || '—' }}</p></div>
                                        <div class="col-span-2"><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Email</p><p class="text-sm font-semibold text-gray-800 break-all">{{ selectedMember.email || '—' }}</p></div>
                                    </div>
                                </div>

                                <!-- Riwayat Transit — Horizontal Timeline -->
                                <div v-if="selectedMember.transition_history && selectedMember.transition_history.length" class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Riwayat Transit</p>
                                    </div>
                                    <div class="p-4">
                                        <div class="flex items-start justify-between gap-1">
                                            <template v-for="(t, i) in selectedMember.transition_history" :key="i">
                                                <div class="flex items-center gap-1 flex-1 min-w-0">
                                                    <div class="flex flex-col items-center">
                                                        <div class="w-3 h-3 rounded-full shrink-0 ring-2 ring-white" :style="{ backgroundColor: t.color || '#6b7280' }"></div>
                                                        <div v-if="i < selectedMember.transition_history.length - 1" class="w-0.5 h-8 bg-gray-200"></div>
                                                    </div>
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ t.to }}</p>
                                                        <p class="text-[10px] text-gray-400">{{ t.date }}</p>
                                                    </div>
                                                </div>
                                                <svg v-if="i < selectedMember.transition_history.length - 1" class="w-4 h-4 shrink-0 text-gray-300 -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                                            </template>
                                        </div>
                                    </div>
                                </div>

                                <!-- Program Dihadiri — filter by year -->
                                <div v-if="selectedMember.attended_programs && selectedMember.attended_programs.length" class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50 flex items-center justify-between">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Program Dihadiri</p>
                                        <select v-model="programYearFilter" class="rounded-lg border border-gray-200 px-2 py-1 text-[11px] focus:border-gray-900 focus:ring-0">
                                            <option value="">Semua Tahun</option>
                                            <option v-for="y in availableProgramYears" :key="y" :value="y">{{ y }}</option>
                                        </select>
                                    </div>
                                    <div class="p-4 space-y-2.5 max-h-60 overflow-y-auto">
                                        <div v-for="(p, i) in filteredPrograms" :key="i" class="flex items-center gap-3">
                                            <div class="w-1.5 h-1.5 rounded-full shrink-0" :style="{ backgroundColor: p.color || '#6b7280' }"></div>
                                            <span class="text-[11px] text-gray-400 w-16 shrink-0">{{ p.date }}</span>
                                            <span class="text-xs font-semibold text-gray-800 truncate flex-1">{{ p.title }}</span>
                                            <span class="shrink-0 text-[10px] font-medium px-2 py-0.5 rounded-full" :style="{ backgroundColor: (p.color ?? '#6b7280') + '15', color: p.color ?? '#6b7280' }">{{ p.org }}</span>
                                        </div>
                                        <p v-if="!filteredPrograms.length" class="text-xs text-gray-400 text-center py-2">Tiada program untuk tahun ini.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ KERJAYA ═══ -->
                            <div v-if="panelTab === 'kerjaya'" class="space-y-4">
                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Pendidikan & Pekerjaan</p>
                                    </div>
                                    <div class="p-4 grid grid-cols-2 gap-x-6 gap-y-3.5">
                                        <div class="col-span-2"><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Pendidikan</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.education_level || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Profesion</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.current_profession || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Industri</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.industry || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Jawatan</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.position || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Cawangan</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.branch_name || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Lokaliti</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.locality || '—' }}</p></div>
                                        <div class="col-span-2"><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Kepakaran</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.expertise || '—' }}</p></div>
                                        <div class="col-span-2"><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Topik / Bidang</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.topics || '—' }}</p></div>
                                        <div class="col-span-2"><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">LinkedIn</p><p class="text-sm text-gray-800 break-all">{{ selectedMember.linkedin_url || '—' }}</p></div>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ ALAMAT ═══ -->
                            <div v-if="panelTab === 'alamat'" class="space-y-4">
                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Alamat</p>
                                    </div>
                                    <div class="p-4">
                                        <div v-if="selectedMember.address_1 || selectedMember.address_2 || selectedMember.postcode" class="text-sm text-gray-800 space-y-1">
                                            <p v-if="selectedMember.address_1">{{ selectedMember.address_1 }}</p>
                                            <p v-if="selectedMember.address_2">{{ selectedMember.address_2 }}</p>
                                            <p v-if="selectedMember.postcode || selectedMember.city || selectedMember.state">
                                                {{ [selectedMember.postcode, selectedMember.city, selectedMember.state].filter(Boolean).join(', ') }}
                                            </p>
                                        </div>
                                        <p v-else class="text-sm text-gray-400 italic">Tiada alamat direkodkan.</p>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ LAIN ═══ -->
                            <div v-if="panelTab === 'lain'" class="space-y-4">
                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Contact Kecemasan</p>
                                    </div>
                                    <div class="p-4 grid grid-cols-2 gap-x-6 gap-y-3.5">
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Nama</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.emergency_contact_name || '—' }}</p></div>
                                        <div><p class="text-[10px] uppercase tracking-wide text-gray-400 mb-0.5">Telefon</p><p class="text-sm font-semibold text-gray-800">{{ selectedMember.emergency_contact_phone || '—' }}</p></div>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Yuran</p>
                                    </div>
                                    <div class="p-4 flex items-center justify-between">
                                        <div>
                                            <span v-if="selectedMember.fee_status === 'paid'" class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Lunas</span>
                                            <span v-else-if="selectedMember.fee_status === 'life_member'" class="inline-flex rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">Seumur Hidup</span>
                                            <span v-else-if="selectedMember.fee_status === 'exempted'" class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Dikecualikan</span>
                                            <span v-else class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Tertunggak</span>
                                        </div>
                                        <Link :href="route('admin.fees.members')" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Urus Yuran →</Link>
                                    </div>
                                </div>

                                <div class="rounded-xl border border-gray-100 bg-white overflow-hidden">
                                    <div class="px-4 py-2.5 border-b border-gray-50 bg-gray-50/50">
                                        <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-500">Keterlihatan Direktori</p>
                                    </div>
                                    <div class="p-4">
                                        <span v-if="selectedMember.is_public_in_directory" class="inline-flex rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Papar</span>
                                        <span v-else class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">Disembunyikan</span>
                                    </div>
                                </div>
                            </div>

                            <!-- ═══ AKTIVITI ═══ -->
                            <div v-if="panelTab === 'aktiviti'" class="space-y-4">
                                <div v-if="loadingLogs" class="py-10 text-center text-sm text-gray-400">
                                    <div class="h-4 w-3/4 rounded bg-gray-100 animate-pulse mx-auto mb-2"></div>
                                    <div class="h-4 w-1/2 rounded bg-gray-100 animate-pulse mx-auto"></div>
                                </div>
                                <div v-else-if="!activityLogs.length" class="py-10 text-center text-sm text-gray-400">Tiada aktiviti direkodkan.</div>
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

                    <!-- Edit Mode -->
                    <div v-if="selectedMember && editing" class="p-5 text-sm">
                        <form @submit.prevent="submitEdit" class="space-y-5">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Akaun</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama</label>
                                        <input v-model="editForm.name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" required>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Email</label>
                                        <input v-model="editForm.email" type="email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">No Ahli <span v-if="!isSuperadmin" class="text-gray-300">(readonly)</span></label>
                                        <input v-model="editForm.member_no" :readonly="!isSuperadmin" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0 disabled:bg-gray-50 disabled:text-gray-400">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">No IC</label>
                                        <input v-model="editForm.ic_number" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Telefon</label>
                                        <input v-model="editForm.phone" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tarikh Lahir</label>
                                        <input v-model="editForm.dob" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Jantina</label>
                                        <select v-model="editForm.gender" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                            <option value="">Pilih</option>
                                            <option value="lelaki">Lelaki</option>
                                            <option value="perempuan">Perempuan</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Status Perkahwinan</label>
                                        <select v-model="editForm.marital_status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                            <option value="">Pilih</option>
                                            <option value="bujang">Bujang</option>
                                            <option value="berkahwin">Berkahwin</option>
                                            <option value="bercerai">Bercerai</option>
                                            <option value="duda/janda">Duda / Janda</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input v-model="editForm.is_public_in_directory" type="checkbox" class="rounded border-gray-300 text-gray-900 focus:ring-0">
                                            <span class="text-xs text-gray-600">Papar dalam direktori</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Pendidikan & Kerjaya</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Pendidikan</label>
                                        <input v-model="editForm.education_level" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Profesion</label>
                                        <input v-model="editForm.current_profession" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Industri</label>
                                        <input v-model="editForm.industry" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cawangan</label>
                                        <select v-model="editForm.branch_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                            <option value="">Pilih</option>
                                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Jawatan</label>
                                        <select v-model="editForm.position" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                            <option value="">Pilih</option>
                                            <option v-for="p in orgPositions" :key="p.id" :value="p.name">{{ p.name }}</option>
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Lokaliti</label>
                                        <input v-model="editForm.locality" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Shah Alam">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kepakaran</label>
                                        <input v-model="editForm.expertise" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Pisah dengan koma">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Bidang / Topik</label>
                                        <textarea v-model="editForm.topics" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Pengurusan, Kewangan, ..."></textarea>
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">LinkedIn URL</label>
                                        <input v-model="editForm.linkedin_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Alamat</p>
                                <div class="space-y-3">
                                    <input v-model="editForm.address_1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Alamat baris 1">
                                    <input v-model="editForm.address_2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Alamat baris 2 (optional)">
                                    <div class="grid grid-cols-3 gap-3">
                                        <input v-model="editForm.postcode" maxlength="5" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Poskod">
                                        <input v-model="editForm.city" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Bandar">
                                        <input v-model="editForm.state" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Negeri">
                                    </div>
                                </div>
                            </div>

                            <div>
                                <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-3">Contact Kecemasan</p>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama</label>
                                        <input v-model="editForm.emergency_contact_name" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="Nama waris / hubungi">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Telefon</label>
                                        <input v-model="editForm.emergency_contact_phone" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0" placeholder="0123456789">
                                    </div>
                                </div>
                            </div>

                            <div v-if="isSuperadmin" class="p-4 rounded-2xl border border-teal-100 bg-teal-50/50">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <input v-model="editForm.is_branch_admin" type="checkbox" class="rounded border-teal-300 text-teal-600 focus:ring-teal-500" />
                                    <div>
                                        <p class="text-sm font-semibold text-teal-800">Lantik sebagai Admin Cawangan</p>
                                        <p class="text-xs text-teal-600 mt-0.5">Ahli akan menjadi admin untuk cawangan yang dipilih di atas.</p>
                                    </div>
                                </label>
                            </div>

                            <div v-if="editForm.errors" class="space-y-1">
                                <p v-for="(err, key) in editForm.errors" :key="key" class="text-xs text-red-600">{{ err }}</p>
                            </div>

                            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                                <button type="button" @click="cancelEdit" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                                <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-gray-900 px-5 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                                    {{ editForm.processing ? 'Menyimpan...' : 'Simpan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </div>
    </Teleport>
</template>

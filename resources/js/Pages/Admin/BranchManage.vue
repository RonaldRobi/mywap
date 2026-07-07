<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    organizations: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const isSuperadmin = computed(() =>
    page.props.auth.user?.roles?.includes('Superadmin')
);

// ── Stats ──────────────────────────────────────────────────────────────────────
const stats = computed(() => {
    let total = 0, active = 0, withAdmin = 0;
    props.organizations.forEach(org => {
        org.branches.forEach(b => {
            total++;
            if (b.is_active) active++;
            if (b.admins && b.admins.length > 0) withAdmin++;
        });
    });
    return { total, active, withAdmin, withoutAdmin: total - withAdmin };
});

// ── Search & Filter ────────────────────────────────────────────────────────────
const searchQuery = ref('');
const stateFilter = ref('');

const filteredOrganizations = computed(() => {
    const query = searchQuery.value.toLowerCase().trim();
    const state = stateFilter.value;

    return props.organizations.map(org => {
        const filteredBranches = org.branches.filter(b => {
            const matchesSearch = !query || b.name.toLowerCase().includes(query);
            const matchesState = !state || b.state === state;
            return matchesSearch && matchesState;
        });
        return { ...org, branches: filteredBranches };
    }).filter(org => org.branches.length > 0 || (!query && !state));
});

// All unique states from all branches
const allStates = computed(() => {
    const states = new Set();
    props.organizations.forEach(org => {
        org.branches.forEach(b => {
            if (b.state) states.add(b.state);
        });
    });
    return [...states].sort();
});

// ── Collapse/Expand ────────────────────────────────────────────────────────────
const collapsedOrgs = reactive({});

function toggleOrg(orgId) {
    collapsedOrgs[orgId] = !collapsedOrgs[orgId];
}

// ── Add Branch ────────────────────────────────────────────────────────────────
const showAddModal = ref(false);
const selectedOrgId = ref(null);

const addForm = useForm({
    organization_id: '',
    name: '',
    state: '',
    address: '',
    phone: '',
    email: '',
    is_active: true,
});

function openAddModal(orgId) {
    selectedOrgId.value = orgId;
    addForm.reset();
    addForm.organization_id = orgId;
    showAddModal.value = true;
}

function submitAdd() {
    addForm.post(route('branches.store'), {
        preserveScroll: true,
        onSuccess: () => { showAddModal.value = false; addForm.reset(); },
    });
}

// ── Edit Branch ────────────────────────────────────────────────────────────────
const editingBranch = ref(null);
const editForm = useForm({
    name: '',
    state: '',
    address: '',
    phone: '',
    email: '',
    is_active: true,
});

const logoForm = useForm({ branch_logo: null });

function openEditDrawer(branch) {
    editingBranch.value = branch;
    editForm.name = branch.name;
    editForm.state = branch.state ?? '';
    editForm.address = branch.address ?? '';
    editForm.phone = branch.phone ?? '';
    editForm.email = branch.email ?? '';
    editForm.is_active = branch.is_active;
    logoForm.reset();
}

function submitEdit() {
    editForm.put(route('branches.update', editingBranch.value.id), {
        preserveScroll: true,
        onSuccess: () => { editingBranch.value = null; },
    });
}

function submitLogo() {
    logoForm.post(route('branches.logo.update', editingBranch.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { logoForm.reset(); },
    });
}

function deleteLogo() {
    if (!confirm('Padam logo cawangan ini?')) return;
    useForm({}).delete(route('branches.logo.destroy', editingBranch.value.id), {
        preserveScroll: true,
        onSuccess: () => { editingBranch.value.logo_path = null; },
    });
}

// ── Confirm Delete Modal ───────────────────────────────────────────────────────
const confirmDeleteBranch = ref(null);

function openDeleteConfirm(branch) {
    confirmDeleteBranch.value = branch;
}

function submitDeleteBranch() {
    const branch = confirmDeleteBranch.value;
    useForm({}).delete(route('branches.destroy', branch.id), {
        preserveScroll: true,
        onSuccess: () => { confirmDeleteBranch.value = null; editingBranch.value = null; },
        onError: () => { confirmDeleteBranch.value = null; },
    });
}

// ── Admin Management ───────────────────────────────────────────────────────────
const managingAdminsBranch = ref(null);
const adminSearchQuery = ref('');
const adminSearchResults = ref([]);
const adminSearchLoading = ref(false);
let adminSearchTimer = null;

watch(adminSearchQuery, (val) => {
    clearTimeout(adminSearchTimer);
    if (!val || val.length < 2) {
        adminSearchResults.value = [];
        return;
    }
    adminSearchTimer = setTimeout(() => searchBranchMembers(val), 300);
});

function openAdminManage(branch) {
    managingAdminsBranch.value = branch;
    adminSearchQuery.value = '';
    adminSearchResults.value = [];
}

function searchBranchMembers(val) {
    if (!managingAdminsBranch.value) return;
    adminSearchLoading.value = true;
    axios.get('/api/members/search', { params: { q: val, branch_id: managingAdminsBranch.value.id } })
        .then((res) => {
            const existingIds = new Set((managingAdminsBranch.value?.admins || []).map(a => a.id));
            adminSearchResults.value = (res.data || []).filter(m => !existingIds.has(m.id));
        })
        .catch((err) => {
            console.error('Admin search failed:', err);
            adminSearchResults.value = [];
        })
        .finally(() => {
            adminSearchLoading.value = false;
        });
}

function assignAdmin(member) {
    useForm({ user_id: member.id }).post(
        route('branches.admins.store', managingAdminsBranch.value.id),
        { preserveScroll: true }
    );
    adminSearchQuery.value = '';
    adminSearchResults.value = [];
}

function removeAdmin(admin) {
    useForm({}).delete(
        route('branches.admins.destroy', { branch: managingAdminsBranch.value.id, user: admin.id }),
        { preserveScroll: true }
    );
}

// ── Malaysian states for quick-fill ────────────────────────────────────────────
const malaysianStates = [
    'Selangor', 'Kuala Lumpur', 'Johor', 'Kedah', 'Kelantan', 'Melaka',
    'Negeri Sembilan', 'Pahang', 'Perak', 'Perlis', 'Pulau Pinang',
    'Sabah', 'Sarawak', 'Terengganu', 'Putrajaya', 'Labuan',
];
</script>

<template>
    <Head title="Pengurusan Cawangan" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Pengurusan Cawangan</template>

        <div class="mx-auto max-w-7xl px-4 py-8 md:px-6 md:py-10">

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <!-- Page header -->
            <div class="mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Pengurusan Cawangan</h2>
                <p class="mt-1 text-sm text-gray-500">Tambah, kemaskini atau padam cawangan di bawah organisasi anda.</p>
            </div>

            <!-- Stats bar -->
            <div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Jumlah</p>
                    <p class="text-2xl font-black text-gray-900 mt-0.5">{{ stats.total }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-emerald-600 uppercase tracking-wide">Aktif</p>
                    <p class="text-2xl font-black text-emerald-700 mt-0.5">{{ stats.active }}</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-indigo-500 uppercase tracking-wide">Ada Admin</p>
                    <p class="text-2xl font-black text-indigo-600 mt-0.5">{{ stats.withAdmin }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/50 px-4 py-3 shadow-sm">
                    <p class="text-xs font-semibold text-amber-600 uppercase tracking-wide">Tiada Admin</p>
                    <p class="text-2xl font-black text-amber-700 mt-0.5">{{ stats.withoutAdmin }}</p>
                </div>
            </div>

            <!-- Search / filter bar -->
            <div class="mb-8 flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                    <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari cawangan..."
                        class="w-full rounded-2xl border border-gray-200 pl-10 pr-4 py-2.5 text-sm placeholder:text-gray-400 focus:border-indigo-400 focus:ring-0 outline-none transition-colors"
                    />
                </div>
                <select
                    v-model="stateFilter"
                    class="rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors min-w-[160px]"
                >
                    <option value="">Semua Negeri</option>
                    <option v-for="s in allStates" :key="s" :value="s">{{ s }}</option>
                </select>
            </div>

            <!-- No results -->
            <div v-if="filteredOrganizations.length === 0" class="rounded-3xl border border-dashed border-gray-200 py-16 text-center">
                <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <p class="mt-3 text-sm text-gray-400 font-medium">Tiada cawangan ditemui.</p>
                <button @click="searchQuery = ''; stateFilter = ''" class="mt-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800">
                    Kosongkan carian
                </button>
            </div>

            <!-- Per-organisation groups -->
            <div class="space-y-10">
                <div v-for="org in filteredOrganizations" :key="org.id">
                    <!-- Org header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <img v-if="org.logo_path" :src="org.logo_path" :alt="org.name" class="h-9 w-9 rounded-xl object-contain border border-gray-100 bg-gray-50 p-1" />
                            <div v-else class="h-9 w-9 rounded-xl flex items-center justify-center text-white text-sm font-black shrink-0" :style="`background: ${org.color_theme ?? '#6366f1'}`">
                                {{ org.name.charAt(0) }}
                            </div>
                            <div>
                                <h3 class="text-lg font-black text-gray-900">{{ org.name }}</h3>
                                <p class="text-xs text-gray-400 font-medium">{{ org.branches.length }} cawangan</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                @click="toggleOrg(org.id)"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-500 hover:bg-gray-50 transition-colors"
                            >
                                <svg
                                    class="w-3.5 h-3.5 transition-transform duration-200"
                                    :class="collapsedOrgs[org.id] ? '-rotate-90' : ''"
                                    fill="none" viewBox="0 0 24 24" stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                                {{ collapsedOrgs[org.id] ? 'Kembang' : 'Kecut' }}
                            </button>
                            <button
                                @click="openAddModal(org.id)"
                                class="inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 transition-colors"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Tambah Cawangan
                            </button>
                        </div>
                    </div>

                    <!-- Branches grid -->
                    <div v-if="!collapsedOrgs[org.id]" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div
                            v-for="branch in org.branches"
                            :key="branch.id"
                            class="relative flex flex-col gap-3 bg-white rounded-3xl border border-gray-100 shadow-sm p-5 hover:shadow-md transition-shadow"
                        >
                            <!-- Active badge -->
                            <span
                                class="absolute top-4 right-4 text-[10px] font-bold px-2 py-0.5 rounded-full"
                                :class="branch.is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ branch.is_active ? 'Aktif' : 'Tidak Aktif' }}
                            </span>

                            <!-- Logo / initial -->
                            <div class="flex items-center gap-3">
                                <div class="h-12 w-12 rounded-2xl overflow-hidden border border-gray-100 bg-gray-50 flex items-center justify-center shrink-0">
                                    <img v-if="branch.logo_path" :src="branch.logo_path" :alt="branch.name" class="h-full w-full object-contain p-1" />
                                    <img v-else-if="org.logo_path" :src="org.logo_path" :alt="org.name" class="h-full w-full object-contain p-1 opacity-40" />
                                    <span v-else class="text-lg font-black text-gray-300">{{ branch.name.charAt(0) }}</span>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-bold text-gray-900 truncate">{{ branch.name }}</p>
                                    <p v-if="branch.state" class="text-xs text-gray-500 truncate">{{ branch.state }}</p>
                                </div>
                            </div>

                            <!-- Contact info -->
                            <div class="space-y-1.5 text-xs text-gray-500">
                                <div v-if="branch.phone" class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
                                    {{ branch.phone }}
                                </div>
                                <div v-if="branch.email" class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    {{ branch.email }}
                                </div>
                                <div v-if="branch.address" class="flex items-start gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-gray-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    <span class="line-clamp-2">{{ branch.address }}</span>
                                </div>
                            </div>

                            <!-- Admin section -->
                            <div class="border-t border-gray-50 pt-2.5">
                                <div v-if="branch.admins && branch.admins.length > 0" class="space-y-1.5">
                                    <div v-for="admin in branch.admins" :key="admin.id" class="flex items-center gap-2">
                                        <div class="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center text-[10px] font-black text-indigo-600 shrink-0">
                                            {{ admin.name.charAt(0) }}
                                        </div>
                                        <span class="text-xs text-gray-700 font-medium truncate">{{ admin.name }}</span>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="text-xs text-amber-600 font-medium">Tiada admin</span>
                                </div>
                            </div>

                            <!-- Actions row -->
                            <div class="flex items-center justify-between pt-2 border-t border-gray-50">
                                <Link :href="route('admin.hub.manage', { branch_id: branch.id })" class="text-xs text-gray-400 font-medium hover:text-indigo-600 transition-colors">{{ branch.member_count }} ahli</Link>
                                <div class="flex items-center gap-1.5">
                                    <button
                                        @click="openAdminManage(branch)"
                                        class="text-xs font-semibold text-emerald-600 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-2.5 py-1.5 rounded-xl transition-colors"
                                    >
                                        Admin
                                    </button>
                                    <button
                                        @click="openEditDrawer(branch)"
                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-xl transition-colors"
                                    >
                                        Edit
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state inside org -->
                        <div v-if="!org.branches.length" class="col-span-full rounded-3xl border border-dashed border-gray-200 py-10 text-center text-sm text-gray-400">
                            Belum ada cawangan. Klik "Tambah Cawangan" untuk mula.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Add Branch Modal ──────────────────────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="showAddModal = false">
                <div class="w-full max-w-lg bg-white rounded-3xl shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                        <h3 class="text-base font-bold text-gray-900">Tambah Cawangan Baharu</h3>
                        <button @click="showAddModal = false" class="p-2 rounded-xl text-gray-400 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <form @submit.prevent="submitAdd" class="px-6 py-5 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nama Cawangan <span class="text-red-500">*</span></label>
                            <input v-model="addForm.name" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" placeholder="e.g. Selangor" />
                            <p v-if="addForm.errors.name" class="mt-1 text-xs text-red-500">{{ addForm.errors.name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Negeri</label>
                            <select v-model="addForm.state" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors">
                                <option value="">-- Pilih Negeri --</option>
                                <option v-for="s in malaysianStates" :key="s" :value="s">{{ s }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Telefon</label>
                            <input v-model="addForm.phone" type="text" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" placeholder="03-XXXX XXXX" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Emel</label>
                            <input v-model="addForm.email" type="email" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" placeholder="cawangan@organisasi.com" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Alamat</label>
                            <textarea v-model="addForm.address" rows="2" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors resize-none" placeholder="Alamat penuh cawangan..."></textarea>
                        </div>
                        <div class="flex items-center gap-3 pt-2">
                            <button type="submit" :disabled="addForm.processing" class="flex-1 rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 transition-colors">
                                {{ addForm.processing ? 'Menyimpan...' : 'Tambah Cawangan' }}
                            </button>
                            <button type="button" @click="showAddModal = false" class="flex-1 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                Batal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </transition>

        <!-- ── Edit Branch Drawer ──────────────────────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div v-if="editingBranch" class="fixed inset-0 z-50 flex justify-end" @click.self="editingBranch = null">
                <div class="w-full max-w-md bg-white h-full shadow-2xl flex flex-col overflow-y-auto" @click.stop>
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100 shrink-0">
                        <h3 class="text-base font-bold text-gray-900">Edit — {{ editingBranch.name }}</h3>
                        <button @click="editingBranch = null" class="p-2 rounded-xl text-gray-400 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="flex-1 px-6 py-5 space-y-6">
                        <!-- Logo section -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Logo Cawangan</p>
                            <div class="flex items-center gap-4 mb-3">
                                <div class="relative h-16 w-16 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center overflow-hidden">
                                    <img v-if="editingBranch.logo_path" :src="editingBranch.logo_path" class="h-full w-full object-contain p-1" />
                                    <span v-else class="text-2xl font-black text-gray-200">{{ editingBranch.name.charAt(0) }}</span>
                                    <button
                                        v-if="editingBranch.logo_path"
                                        type="button"
                                        @click="deleteLogo"
                                        class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-red-500 text-white flex items-center justify-center hover:bg-red-600 transition-colors shadow-sm"
                                        title="Padam logo"
                                    >
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500">Logo khusus cawangan. Jika tiada, logo organisasi digunakan.</p>
                            </div>
                            <form @submit.prevent="submitLogo" class="flex items-center gap-2">
                                <input type="file" accept="image/*" @change="logoForm.branch_logo = $event.target.files[0]" class="flex-1 text-xs file:mr-2 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700" />
                                <button type="submit" :disabled="!logoForm.branch_logo || logoForm.processing" class="rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50 transition-colors">
                                    Muat Naik
                                </button>
                            </form>
                            <p v-if="logoForm.errors.branch_logo" class="mt-1 text-xs text-red-500">{{ logoForm.errors.branch_logo }}</p>
                        </div>

                        <!-- Edit details form -->
                        <form @submit.prevent="submitEdit" class="space-y-4">
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Maklumat Cawangan</p>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Nama Cawangan <span class="text-red-500">*</span></label>
                                <input v-model="editForm.name" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" />
                                <p v-if="editForm.errors.name" class="mt-1 text-xs text-red-500">{{ editForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Negeri</label>
                                <select v-model="editForm.state" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors">
                                    <option value="">-- Pilih Negeri --</option>
                                    <option v-for="s in malaysianStates" :key="s" :value="s">{{ s }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Telefon</label>
                                <input v-model="editForm.phone" type="text" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Emel</label>
                                <input v-model="editForm.email" type="email" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-500 mb-1">Alamat</label>
                                <textarea v-model="editForm.address" rows="2" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors resize-none"></textarea>
                            </div>
                            <label class="flex items-center gap-3 cursor-pointer rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3">
                                <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                <span class="text-sm font-semibold text-gray-700">Cawangan Aktif</span>
                            </label>

                            <!-- Admin section in drawer -->
                            <div class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Admin Cawangan</p>
                                    <button
                                        type="button"
                                        @click="openAdminManage(editingBranch); editingBranch = null"
                                        class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition-colors"
                                    >
                                        Urus Admin
                                    </button>
                                </div>
                                <div v-if="editingBranch.admins && editingBranch.admins.length > 0" class="space-y-2">
                                    <div v-for="admin in editingBranch.admins" :key="admin.id" class="flex items-center gap-2 text-sm">
                                        <div class="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center text-[10px] font-black text-indigo-600 shrink-0">
                                            {{ admin.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-800 truncate">{{ admin.name }}</p>
                                            <p class="text-xs text-gray-400 truncate">{{ admin.email }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else class="flex items-center gap-2 text-xs text-amber-600">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-medium">Belum ada admin dilantik</span>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" :disabled="editForm.processing" class="flex-1 rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 transition-colors">
                                    {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                                <button type="button" @click="openDeleteConfirm(editingBranch)" class="rounded-2xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition-colors">
                                    Padam
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ── Admin Management Modal ──────────────────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="managingAdminsBranch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="managingAdminsBranch = null">
                <div class="w-full max-w-md bg-white rounded-3xl shadow-2xl" @click.stop>
                    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
                        <div>
                            <h3 class="text-base font-bold text-gray-900">Admin Cawangan</h3>
                            <p class="text-xs text-gray-500 mt-0.5">{{ managingAdminsBranch.name }}</p>
                        </div>
                        <button @click="managingAdminsBranch = null" class="p-2 rounded-xl text-gray-400 hover:bg-gray-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        <!-- Current admins -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Admin Dilantik</p>
                            <div v-if="managingAdminsBranch.admins && managingAdminsBranch.admins.length > 0" class="space-y-2">
                                <div v-for="admin in managingAdminsBranch.admins" :key="admin.id" class="flex items-center justify-between rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <div class="h-7 w-7 rounded-full bg-indigo-100 flex items-center justify-center text-xs font-black text-indigo-600 shrink-0">
                                            {{ admin.name.charAt(0) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-gray-800 truncate">{{ admin.name }}</p>
                                            <p class="text-xs text-gray-400 truncate">{{ admin.email }}</p>
                                        </div>
                                    </div>
                                    <button
                                        @click="removeAdmin(admin)"
                                        class="shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-500 transition-colors ml-2"
                                        title="Buang admin"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div v-else class="rounded-xl border border-dashed border-gray-200 py-6 text-center">
                                <p class="text-xs text-gray-400">Belum ada admin dilantik untuk cawangan ini.</p>
                            </div>
                        </div>

                        <!-- Add admin search -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">Lantik Admin</p>
                            <div class="relative">
                                <input
                                    v-model="adminSearchQuery"
                                    type="text"
                                    placeholder="Cari nama atau no ahli..."
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-indigo-400 focus:ring-0 outline-none transition-colors"
                                />
                                <div v-if="adminSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="w-4 h-4 animate-spin text-gray-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                </div>
                                <ul
                                    v-if="adminSearchResults.length > 0"
                                    class="absolute z-50 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto"
                                >
                                    <li
                                        v-for="member in adminSearchResults"
                                        :key="member.id"
                                        @click="assignAdmin(member)"
                                        class="flex cursor-pointer items-center justify-between px-3 py-2.5 text-sm hover:bg-gray-50 transition-colors"
                                    >
                                        <span class="font-medium text-gray-800">{{ member.name }}</span>
                                        <span class="text-xs text-gray-400 font-mono">{{ member.member_no }}</span>
                                    </li>
                                </ul>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Hanya ahli dalam cawangan ini boleh dilantik.</p>
                        </div>
                    </div>

                    <div class="px-6 py-4 border-t border-gray-100">
                        <button @click="managingAdminsBranch = null" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- ── Confirm Delete Modal ────────────────────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="confirmDeleteBranch" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="confirmDeleteBranch = null">
                <div class="w-full max-w-sm bg-white rounded-3xl shadow-2xl" @click.stop>
                    <div class="px-6 py-5 text-center">
                        <div class="mx-auto h-12 w-12 rounded-full bg-red-100 flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Padam Cawangan</h3>
                        <p class="mt-2 text-sm text-gray-500">
                            Anda pasti mahu memadam cawangan <strong>"{{ confirmDeleteBranch.name }}"</strong>? Cawangan yang mempunyai ahli tidak boleh dipadam. Tindakan ini tidak boleh dibatalkan.
                        </p>
                    </div>
                    <div class="flex items-center gap-3 px-6 pb-5">
                        <button @click="confirmDeleteBranch = null" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                        <button @click="submitDeleteBranch" class="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                            Padam
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

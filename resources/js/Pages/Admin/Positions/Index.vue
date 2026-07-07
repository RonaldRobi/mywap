<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ positions: Array, categories: Array });
const page = usePage();

const COLOR_PRESETS = [
    '#6366f1', '#8b5cf6', '#d946ef', '#ec4899', '#f43f5e',
    '#ef4444', '#f97316', '#eab308', '#22c55e', '#14b8a6',
    '#06b6d4', '#3b82f6', '#64748b',
];
const CATEGORY_OPTIONS = ['Kepimpinan', 'Pentadbiran', 'Pengurusan', 'Teknikal', 'Keahlian'];

const search = ref('');
const categoryFilter = ref('');
const activeFilter = ref('all');
const editing = ref(null);
const showAddForm = ref(false);
const confirmDialog = ref(null);
const confirmAction = ref(null);
const confirmMessage = ref('');

const addForm = useForm({ name: '', description: '', category: '', parent_id: '', color: '', display_order: 0 });
const editForm = useForm({ name: '', description: '', category: '', parent_id: '', color: '', display_order: 0 });

const rootPositions = computed(() => props.positions.filter(p => !p.parent_id));

function childrenOf(parentId) {
    return props.positions.filter(p => p.parent_id === parentId);
}

function descendantCount(pos) {
    let count = 0;
    const children = childrenOf(pos.id);
    for (const child of children) {
        count += 1 + descendantCount(child);
    }
    return count;
}

const filteredRoots = computed(() => {
    let result = rootPositions.value;

    if (search.value) {
        const q = search.value.toLowerCase();
        result = result.filter(pos => {
            const match = pos.name.toLowerCase().includes(q)
                || (pos.description && pos.description.toLowerCase().includes(q));
            if (match) return true;
            const children = childrenOf(pos.id);
            return children.some(c => c.name.toLowerCase().includes(q)
                || (c.description && c.description.toLowerCase().includes(q)));
        });
    }

    if (categoryFilter.value) {
        result = result.filter(pos => {
            if (pos.category === categoryFilter.value) return true;
            const children = childrenOf(pos.id);
            return children.some(c => c.category === categoryFilter.value);
        });
    }

    if (activeFilter.value === 'active') {
        result = result.filter(pos => {
            if (pos.is_active) return true;
            const children = childrenOf(pos.id);
            return children.some(c => c.is_active);
        });
    } else if (activeFilter.value === 'inactive') {
        result = result.filter(pos => {
            if (!pos.is_active) return true;
            const children = childrenOf(pos.id);
            return children.some(c => !c.is_active);
        });
    }

    return result;
});

function childMatchesFilter(childId) {
    const child = props.positions.find(p => p.id === childId);
    if (!child) return false;
    if (search.value) {
        const q = search.value.toLowerCase();
        if (!child.name.toLowerCase().includes(q)
            && !(child.description && child.description.toLowerCase().includes(q)))
            return false;
    }
    if (categoryFilter.value && child.category !== categoryFilter.value) return false;
    if (activeFilter.value === 'active' && !child.is_active) return false;
    if (activeFilter.value === 'inactive' && child.is_active) return false;
    return true;
}

const allCategories = computed(() => CATEGORY_OPTIONS.filter(c => props.categories.includes(c) || props.positions.some(p => p.category === c)));

const parentOptions = computed(() => {
    const opts = [{ value: '', label: 'Tiada (Root)' }];
    for (const pos of rootPositions.value) {
        opts.push({ value: pos.id, label: pos.name });
        for (const child of childrenOf(pos.id)) {
            if (editing.value === child.id) continue;
            opts.push({ value: child.id, label: `— ${child.name}` });
        }
        if (editing.value === pos.id) continue;
    }
    return opts;
});

function submit() {
    addForm.post(route('positions.store'), {
        preserveScroll: true,
        onSuccess: () => { addForm.reset(); showAddForm.value = false; },
    });
}

function startEdit(pos) {
    editing.value = pos.id;
    editForm.name = pos.name;
    editForm.description = pos.description || '';
    editForm.category = pos.category || '';
    editForm.parent_id = pos.parent_id || '';
    editForm.color = pos.color || '';
    editForm.display_order = pos.display_order;
}

function saveEdit(pos) {
    editForm.put(route('positions.update', pos.id), {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; editForm.reset(); },
    });
}

function cancelEdit() {
    editing.value = null;
    editForm.reset();
}

function confirmDelete(pos) {
    confirmMessage.value = `Padam jawatan "${pos.name}"?`;
    const count = descendantCount(pos);
    const msg = count > 0
        ? `Tindakan ini akan memadam ${count} jawatan di bawahnya.`
        : 'Tindakan ini tidak boleh dibatalkan.';
    confirmMessage.value = `${confirmMessage.value}\n${msg}`;
    confirmAction.value = () => {
        router.delete(route('positions.destroy', pos.id), { preserveScroll: true });
    };
    confirmDialog.value?.show();
}

function toggleActive(pos) {
    router.patch(route('positions.toggle', pos.id), {}, { preserveScroll: true });
}

function moveUp(pos) {
    const siblings = pos.parent_id ? childrenOf(pos.parent_id) : rootPositions.value;
    const idx = siblings.findIndex(s => s.id === pos.id);
    if (idx <= 0) return;
    const orders = siblings.map(s => s.display_order);
    const temp = orders[idx];
    orders[idx] = orders[idx - 1];
    orders[idx - 1] = temp;
    const updates = siblings.map((s, i) => ({ id: s.id, display_order: orders[i] }));
    router.post(route('positions.reorder'), { positions: updates }, { preserveScroll: true });
}

function moveDown(pos) {
    const siblings = pos.parent_id ? childrenOf(pos.parent_id) : rootPositions.value;
    const idx = siblings.findIndex(s => s.id === pos.id);
    if (idx < 0 || idx >= siblings.length - 1) return;
    const orders = siblings.map(s => s.display_order);
    const temp = orders[idx];
    orders[idx] = orders[idx + 1];
    orders[idx + 1] = temp;
    const updates = siblings.map((s, i) => ({ id: s.id, display_order: orders[i] }));
    router.post(route('positions.reorder'), { positions: updates }, { preserveScroll: true });
}

function getCategoryBadgeClass(category) {
    const map = {
        'Kepimpinan': 'bg-purple-100 text-purple-700',
        'Pentadbiran': 'bg-blue-100 text-blue-700',
        'Pengurusan': 'bg-indigo-100 text-indigo-700',
        'Teknikal': 'bg-cyan-100 text-cyan-700',
        'Keahlian': 'bg-green-100 text-green-700',
    };
    return map[category] || 'bg-gray-100 text-gray-700';
}

const flashVisible = ref(true);

function dismissFlash() {
    flashVisible.value = false;
}
</script>

<template>
    <Head title="Jawatan Organisasi" />
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Jawatan Organisasi</template>
        <div class="mx-auto max-w-4xl px-4 py-6">
            <div v-if="$page.props.flash?.success && flashVisible" class="mb-4 flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm">
                <span>{{ $page.props.flash.success }}</span>
                <button @click="dismissFlash" class="ml-3 shrink-0 text-emerald-400 hover:text-emerald-600">&times;</button>
            </div>

            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">Senarai Jawatan</h3>
                        <p class="mt-1 text-xs text-gray-400">Jawatan ini akan dipaparkan dalam borang profil ahli.</p>
                    </div>
                    <button @click="showAddForm = !showAddForm" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Tambah Jawatan
                    </button>
                </div>

                <div v-if="showAddForm" class="mt-5 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">
                    <form @submit.prevent="submit" class="space-y-3">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Jawatan</label>
                                <input v-model="addForm.name" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Nama jawatan" required>
                                <InputError :message="addForm.errors.name" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                                <select v-model="addForm.category" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                    <option value="">Tiada kategori</option>
                                    <option v-for="cat in CATEGORY_OPTIONS" :key="cat" :value="cat">{{ cat }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Deskripsi</label>
                            <textarea v-model="addForm.description" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Skop tanggungjawab jawatan ini"></textarea>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Parent (Hierarki)</label>
                                <select v-model="addForm.parent_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                    <option value="">Tiada (Root)</option>
                                    <option v-for="opt in parentOptions" :key="opt.value" :value="opt.value" v-if="opt.value">{{ opt.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Turutan</label>
                                <input v-model="addForm.display_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Warna</label>
                                <div class="flex flex-wrap gap-1.5">
                                    <button v-for="c in COLOR_PRESETS" :key="c" type="button" @click="addForm.color = c" :class="['h-7 w-7 rounded-full border-2', addForm.color === c ? 'border-gray-900 ring-2 ring-gray-900/20' : 'border-transparent']" :style="{ backgroundColor: c }"></button>
                                    <button v-if="addForm.color" type="button" @click="addForm.color = ''" class="h-7 w-7 rounded-full border border-gray-300 text-xs text-gray-500 hover:bg-gray-100">&#10005;</button>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-1">
                            <button type="button" @click="showAddForm = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                            <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:opacity-60">Simpan</button>
                        </div>
                    </form>
                </div>

                <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input v-model="search" class="w-full rounded-xl border border-gray-200 py-2.5 pl-9 pr-3 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Cari jawatan...">
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <button @click="activeFilter = 'all'" :class="['rounded-lg px-3 py-1.5 text-xs font-semibold', activeFilter === 'all' ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Semua</button>
                        <button @click="activeFilter = 'active'" :class="['rounded-lg px-3 py-1.5 text-xs font-semibold', activeFilter === 'active' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Aktif</button>
                        <button @click="activeFilter = 'inactive'" :class="['rounded-lg px-3 py-1.5 text-xs font-semibold', activeFilter === 'inactive' ? 'bg-gray-500 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200']">Tak Aktif</button>
                    </div>
                </div>

                <div v-if="allCategories.length > 0" class="mt-3 flex flex-wrap gap-1.5">
                    <button @click="categoryFilter = ''" :class="['rounded-full px-3 py-1 text-xs font-semibold', categoryFilter === '' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200']">Semua</button>
                    <template v-for="cat in allCategories" :key="cat">
                        <button @click="categoryFilter = (categoryFilter === cat ? '' : cat)" :class="['rounded-full px-3 py-1 text-xs font-semibold', categoryFilter === cat ? 'ring-2 ring-offset-1' : 'bg-gray-100 text-gray-500 hover:bg-gray-200', getCategoryBadgeClass(cat)]">{{ cat }}</button>
                    </template>
                </div>

                <div class="mt-5 space-y-2">
                    <template v-for="pos in filteredRoots" :key="pos.id">
                        <div :class="['rounded-xl border transition-colors', !pos.is_active ? 'border-gray-100 bg-gray-50/50' : 'border-gray-100 bg-white', editing === pos.id ? 'ring-2 ring-indigo-200' : '']">
                            <template v-if="editing === pos.id">
                                <div class="p-4">
                                    <form @submit.prevent="saveEdit(pos)" class="space-y-3">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Jawatan</label>
                                                <input v-model="editForm.name" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" required>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                                                <select v-model="editForm.category" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                                    <option value="">Tiada</option>
                                                    <option v-for="cat in CATEGORY_OPTIONS" :key="cat" :value="cat">{{ cat }}</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="mb-1 block text-xs font-semibold text-gray-500">Deskripsi</label>
                                            <textarea v-model="editForm.description" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0"></textarea>
                                        </div>
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-500">Parent (Hierarki)</label>
                                                <select v-model="editForm.parent_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                                    <option value="">Tiada (Root)</option>
                                                    <option v-for="opt in parentOptions" v-if="opt.value && opt.value !== pos.id" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-500">Turutan</label>
                                                <input v-model="editForm.display_order" type="number" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold text-gray-500">Warna</label>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <button v-for="c in COLOR_PRESETS" :key="c" type="button" @click="editForm.color = c" :class="['h-7 w-7 rounded-full border-2', editForm.color === c ? 'border-gray-900 ring-2 ring-gray-900/20' : 'border-transparent']" :style="{ backgroundColor: c }"></button>
                                                    <button v-if="editForm.color" type="button" @click="editForm.color = ''" class="h-7 w-7 rounded-full border border-gray-300 text-xs text-gray-500 hover:bg-gray-100">&#10005;</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex justify-end gap-2 pt-1">
                                            <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                                            <button type="submit" :disabled="editForm.processing" class="rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">Simpan</button>
                                        </div>
                                    </form>
                                </div>
                            </template>

                            <template v-else>
                                <div class="flex items-center gap-3 px-4 py-3">
                                    <span v-if="pos.color" class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: pos.color }"></span>
                                    <span v-else class="h-3 w-3 shrink-0 rounded-full bg-gray-200"></span>

                                    <span class="flex-1 text-sm font-medium" :class="pos.is_active ? 'text-gray-700' : 'text-gray-400 line-through'">{{ pos.name }}</span>

                                    <span v-if="pos.category" :class="['rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide', getCategoryBadgeClass(pos.category)]">{{ pos.category }}</span>

                                    <span :class="['inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold', pos.member_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-400']">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                        {{ pos.member_count }}
                                    </span>

                                    <span v-if="!pos.is_active" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-500">Tidak Aktif</span>

                                    <div class="flex items-center gap-1">
                                        <button @click="moveUp(pos)" title="Naik" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                        </button>
                                        <button @click="moveDown(pos)" title="Turun" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                        </button>
                                        <button @click="toggleActive(pos)" :title="pos.is_active ? 'Nyahaktifkan' : 'Aktifkan'" class="rounded-md p-1" :class="pos.is_active ? 'text-gray-400 hover:bg-gray-100 hover:text-gray-600' : 'text-emerald-500 hover:bg-emerald-50'">
                                            <svg v-if="pos.is_active" xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        </button>
                                        <button @click="startEdit(pos)" class="rounded-md p-1 text-gray-400 hover:bg-gray-100 hover:text-indigo-600" title="Edit">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </button>
                                        <button @click="confirmDelete(pos)" class="rounded-md p-1 text-gray-400 hover:bg-red-50 hover:text-red-500" title="Padam">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="pos.description" class="border-t border-gray-50 px-4 pb-2 pt-1">
                                    <p class="text-xs leading-relaxed text-gray-500">{{ pos.description }}</p>
                                </div>

                                <div v-if="childrenOf(pos.id).length" class="border-t border-gray-50 pb-1 pl-6 pr-2 pt-1">
                                    <div v-for="child in childrenOf(pos.id)" :key="child.id" :class="['mb-1 flex items-center gap-3 rounded-lg px-3 py-2 transition-colors', !child.is_active ? 'bg-gray-50/50' : 'bg-gray-50/30']">
                                        <span v-if="child.color" class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: child.color }"></span>
                                        <span v-else class="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-300"></span>

                                        <div class="flex-1 text-xs" :class="child.is_active ? 'font-medium text-gray-600' : 'text-gray-400 line-through'">{{ child.name }}</div>

                                        <span v-if="child.category" :class="['rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide', getCategoryBadgeClass(child.category)]">{{ child.category }}</span>

                                        <span :class="['inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-semibold', child.member_count > 0 ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-400']">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                            {{ child.member_count }}
                                        </span>

                                        <div class="flex items-center gap-0.5">
                                            <button @click="moveUp(child)" title="Naik" class="rounded p-0.5 text-gray-400 hover:bg-gray-200/60 hover:text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/></svg>
                                            </button>
                                            <button @click="moveDown(child)" title="Turun" class="rounded p-0.5 text-gray-400 hover:bg-gray-200/60 hover:text-gray-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                            </button>
                                            <button @click="toggleActive(child)" :title="child.is_active ? 'Nyahaktifkan' : 'Aktifkan'" class="rounded p-0.5" :class="child.is_active ? 'text-gray-400 hover:bg-gray-200/60 hover:text-gray-600' : 'text-emerald-500 hover:bg-emerald-50'">
                                                <svg v-if="child.is_active" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            </button>
                                            <button @click="startEdit(child)" class="rounded p-0.5 text-gray-400 hover:bg-gray-200/60 hover:text-indigo-600" title="Edit">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                            </button>
                                            <button @click="confirmDelete(child)" class="rounded p-0.5 text-gray-400 hover:bg-red-50 hover:text-red-500" title="Padam">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                    <p v-if="!childrenOf(pos.id).filter(childMatchesFilter).length && (search || categoryFilter)" class="py-2 text-center text-xs text-gray-400">Tiada sub-jawatan yang sepadan.</p>
                                </div>
                            </template>
                        </div>
                    </template>

                    <p v-if="!props.positions.length" class="py-8 text-center text-sm text-gray-400">
                        Belum ada jawatan. Tekan <span class="font-semibold">"Tambah Jawatan"</span> untuk bermula.
                    </p>
                    <p v-else-if="!filteredRoots.length" class="py-8 text-center text-sm text-gray-400">
                        Tiada jawatan sepadan dengan carian.
                    </p>
                </div>
            </div>

            <div class="mt-4 text-center text-[10px] text-gray-400">
                Jumlah: {{ props.positions.length }} jawatan ({{ props.positions.filter(p => p.is_active).length }} aktif)
            </div>
        </div>
    </AppLayout>

    <ConfirmDialog ref="confirmDialog" :message="confirmMessage" variant="danger" confirm-text="Ya, Padam" cancel-text="Batal" @confirm="confirmAction?.()" />
</template>

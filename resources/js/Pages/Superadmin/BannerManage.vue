<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    banners: { type: Array, default: () => [] },
});

const form = useForm({
    organization_id: null,
    title: '',
    display_order: 1,
    link_url: '',
    link_target: '_blank',
    is_active: true,
    banner_image: null,
});

const seedDemoForm = useForm({});

const editingId = ref(null);
const editForm = useForm({
    organization_id: null,
    title: '',
    display_order: 1,
    link_url: '',
    link_target: '_blank',
    is_active: true,
    banner_image: null,
});

const showEditPanel = ref(false);

const stats = computed(() => {
    const total = props.banners.length;
    const active = props.banners.filter(b => b.is_active).length;
    const global = props.banners.filter(b => !b.organization_id).length;
    const org = props.banners.filter(b => b.organization_id).length;
    return { total, active, inactive: total - active, global, org };
});

const imagePreviewUrl = ref(null);
const imageFileName = ref('');
const imageFileSize = ref('');
const imageFileDims = ref('');
const editImagePreviewUrl = ref(null);
const editImageFileName = ref('');
const editImageFileSize = ref('');
const editImageFileDims = ref('');

function formatSize(bytes) {
    if (!bytes) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function handleFileSelect(event, mode = 'create') {
    const file = event.target.files?.[0];
    if (!file) return;
    if (mode === 'create') {
        form.banner_image = file;
        showFilePreview(file, 'create');
    } else {
        editForm.banner_image = file;
        showFilePreview(file, 'edit');
    }
}

function showFilePreview(file, mode) {
    const url = URL.createObjectURL(file);
    if (mode === 'create') {
        imagePreviewUrl.value = url;
        imageFileName.value = file.name;
        imageFileSize.value = formatSize(file.size);
    } else {
        editImagePreviewUrl.value = url;
        editImageFileName.value = file.name;
        editImageFileSize.value = formatSize(file.size);
    }
    const img = new Image();
    img.onload = () => {
        const dims = `${img.naturalWidth}×${img.naturalHeight} px`;
        if (mode === 'create') imageFileDims.value = dims;
        else editImageFileDims.value = dims;
    };
    img.src = url;
}

function clearFile(mode = 'create') {
    if (mode === 'create') {
        form.banner_image = null;
        imagePreviewUrl.value = null;
        imageFileName.value = '';
        imageFileSize.value = '';
        imageFileDims.value = '';
    } else {
        editForm.banner_image = null;
        editImagePreviewUrl.value = null;
        editImageFileName.value = '';
        editImageFileSize.value = '';
        editImageFileDims.value = '';
    }
}

function handleDrop(e, mode = 'create') {
    e.preventDefault();
    e.stopPropagation();
    const file = e.dataTransfer?.files?.[0];
    if (file && file.type.startsWith('image/')) {
        if (mode === 'create') {
            form.banner_image = file;
            showFilePreview(file, 'create');
        } else {
            editForm.banner_image = file;
            showFilePreview(file, 'edit');
        }
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
}

function submit() {
    form.post(route('superadmin.banners.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('organization_id', 'title', 'display_order', 'is_active', 'banner_image');
            form.link_url = '';
            form.link_target = '_blank';
            clearFile('create');
        },
    });
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.organization_id = item.organization_id;
    editForm.title = item.title;
    editForm.display_order = item.display_order;
    editForm.link_url = item.link_url || '';
    editForm.link_target = item.link_target || '_blank';
    editForm.is_active = !!item.is_active;
    editForm.banner_image = null;
    clearFile('edit');
    showEditPanel.value = true;
}

function cancelEdit() {
    editingId.value = null;
    showEditPanel.value = false;
    editForm.reset();
    clearFile('edit');
}

function saveEdit(item) {
    editForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('superadmin.banners.update', item.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => cancelEdit(),
        });
}

function remove(item) {
    if (!confirm('Padam banner ini?')) return;
    useForm({}).delete(route('superadmin.banners.destroy', item.id), { preserveScroll: true });
}

function seedDemoBanners() {
    if (!confirm('Jana semula demo banner untuk semua organisasi?')) return;
    seedDemoForm.post(route('superadmin.banners.seed'), { preserveScroll: true });
}

const dropZoneActive = ref(false);
</script>

<template>
    <Head title="Berita Bergambar Management" />

    <AppLayout>
        <template #header>Berita Bergambar Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <!-- Flash Message -->
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- ═══ STATS BAR ═══ -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Jumlah</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ stats.total }}</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider">Aktif</p>
                    <p class="mt-1 text-2xl font-black text-emerald-700">{{ stats.active }}</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-gray-50/60 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wider">Tidak Aktif</p>
                    <p class="mt-1 text-2xl font-black text-gray-600">{{ stats.inactive }}</p>
                </div>
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold text-indigo-600 uppercase tracking-wider">Global</p>
                    <p class="mt-1 text-2xl font-black text-indigo-700">{{ stats.global }}</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50/60 p-4 shadow-sm">
                    <p class="text-[11px] font-semibold text-amber-600 uppercase tracking-wider">Mengikut Org</p>
                    <p class="mt-1 text-2xl font-black text-amber-700">{{ stats.org }}</p>
                </div>
            </div>

            <!-- ═══ ADD BANNER SECTION ═══ -->
            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-gray-800">Tambah Banner Baru</h2>
                    <button
                        type="button"
                        @click="seedDemoBanners"
                        :disabled="seedDemoForm.processing"
                        class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                    >
                        {{ seedDemoForm.processing ? 'Menjana...' : 'Seed Demo Banners' }}
                    </button>
                </div>

                <form class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submit">
                    <!-- Left column: fields -->
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk Banner</label>
                            <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="ct: Minggu Ukhuwah Nasional" required>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                            <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                <option :value="null">Global (Semua organisasi)</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Display Order</label>
                                <input v-model.number="form.display_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                            <div class="flex items-end pb-2">
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input id="is_active_banner" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    Aktifkan banner
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Link URL <span class="text-gray-400 font-normal">(optional)</span></label>
                            <div class="flex gap-2">
                                <input v-model="form.link_url" type="url" class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://example.com/promo">
                                <select v-model="form.link_target" class="w-24 rounded-xl border border-gray-200 px-2 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                    <option value="_blank">_blank</option>
                                    <option value="_self">_self</option>
                                </select>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-400">Biarkan kosong jika banner tiada link. _blank buka tab baru, _self buka tab sama.</p>
                            <p v-if="form.errors.link_url" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.link_url }}</p>
                        </div>
                    </div>

                    <!-- Right column: upload -->
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Imej Banner</label>
                        <div
                            @drop="handleDrop($event, 'create')"
                            @dragover="handleDragOver"
                            @dragenter="dropZoneActive = true"
                            @dragleave="dropZoneActive = false"
                            class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 transition-colors cursor-pointer"
                            :class="dropZoneActive ? 'border-gray-500 bg-gray-50' : (imagePreviewUrl ? 'border-emerald-300 bg-emerald-50/30' : 'border-gray-200 hover:border-gray-400 bg-gray-50/50')"
                        >
                            <template v-if="imagePreviewUrl">
                                <div class="relative w-full">
                                    <img :src="imagePreviewUrl" alt="Preview" class="w-full aspect-[21/9] object-cover rounded-lg border border-gray-200">
                                    <button type="button" @click="clearFile('create')" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center text-xs font-bold shadow-md hover:bg-red-600 transition">✕</button>
                                </div>
                                <div class="mt-2 w-full text-left">
                                    <p class="text-xs font-semibold text-gray-700 truncate">{{ imageFileName }}</p>
                                    <p class="text-[11px] text-gray-400">
                                        <span v-if="imageFileSize">{{ imageFileSize }}</span>
                                        <span v-if="imageFileDims"> · {{ imageFileDims }}</span>
                                    </p>
                                </div>
                            </template>
                            <template v-else>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-10 h-10 text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <p class="text-sm font-medium text-gray-500">Letak imej di sini</p>
                                <p class="mt-0.5 text-[11px] text-gray-400">atau klik untuk pilih fail</p>
                            </template>
                            <input type="file" accept="image/*" @change="e => handleFileSelect(e, 'create')" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                        <p class="mt-1.5 text-[11px] text-gray-400">Saiz disyorkan: 1200×514px (nisbah 21:9). Format: JPG, PNG, WebP, SVG. Maks 5MB.</p>
                        <p v-if="form.errors.banner_image" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.banner_image }}</p>
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60 transition">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Banner' }}
                        </button>
                    </div>
                </form>
            </section>

            <!-- ═══ BANNER LIST ═══ -->
            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-black text-gray-800">Senarai Banner ({{ banners.length }})</h2>
                </div>

                <div v-if="!banners.length" class="py-12 text-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm font-semibold text-gray-500">Tiada banner lagi. Muat naik banner pertama anda.</p>
                </div>

                <div v-else class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="item in banners" :key="item.id" class="group relative rounded-2xl border border-gray-100 bg-white overflow-hidden shadow-sm hover:shadow-md transition-all duration-200">
                        <!-- Thumbnail with 21:9 ratio -->
                        <div class="relative aspect-[21/9] w-full overflow-hidden bg-gray-100">
                            <img :src="item.image_path" :alt="item.title" class="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                            <div class="absolute inset-0 bg-gradient-to-t from-black/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <!-- Overlay actions on hover -->
                            <div class="absolute inset-0 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button @click="startEdit(item)" class="rounded-xl bg-white/90 backdrop-blur-sm px-3 py-2 text-xs font-semibold text-gray-800 shadow-sm hover:bg-white transition">Edit</button>
                                <button @click="remove(item)" class="rounded-xl bg-red-500/90 backdrop-blur-sm px-3 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-500 transition">Padam</button>
                            </div>
                            <!-- Status badge -->
                            <div class="absolute top-2 right-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide shadow-sm" :class="item.is_active ? 'bg-emerald-500 text-white' : 'bg-gray-400 text-white'">
                                    {{ item.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <!-- Link indicator -->
                            <div v-if="item.link_url" class="absolute top-2 left-2">
                                <span class="inline-flex items-center gap-1 rounded-full bg-blue-500/80 text-white px-2 py-0.5 text-[10px] font-semibold shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                                    Link
                                </span>
                            </div>
                        </div>
                        <!-- Info -->
                        <div class="p-3">
                            <p class="text-sm font-bold text-gray-800 truncate">{{ item.title }}</p>
                            <div class="mt-1 flex items-center gap-2 text-[11px] text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                    {{ item.organization_name }}
                                </span>
                                <span>·</span>
                                <span>#{{ item.display_order }}</span>
                                <span v-if="item.link_url" class="truncate max-w-[120px] text-gray-400" :title="item.link_url">{{ item.link_url }}</span>
                            </div>
                        </div>
                    </article>
                </div>
            </section>

            <!-- Back link -->
            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
            </div>
        </div>

        <!-- ═══ EDIT PANEL (Slide-over) ═══ -->
        <Teleport to="body">
            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showEditPanel" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-50" @click="cancelEdit"></div>
            </transition>

            <transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="translate-x-full opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-full opacity-0"
            >
                <div v-if="showEditPanel" class="fixed top-0 right-0 bottom-0 w-full max-w-lg bg-white shadow-2xl z-50 overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-lg font-black text-gray-800">Edit Banner</h2>
                            <button @click="cancelEdit" class="p-2 rounded-xl hover:bg-gray-100 text-gray-500 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <form @submit.prevent="saveEdit({ id: editingId })" class="space-y-4">
                            <!-- Current Image Preview -->
                            <div v-if="!editImagePreviewUrl" class="rounded-xl overflow-hidden border border-gray-200 bg-gray-50">
                                <img v-if="banners.find(b => b.id === editingId)?.image_path" :src="banners.find(b => b.id === editingId)?.image_path" alt="Current" class="w-full aspect-[21/9] object-cover">
                            </div>

                            <!-- Upload new -->
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Tukar Imej <span class="text-gray-400">(optional)</span></label>
                                <div
                                    @drop="handleDrop($event, 'edit')"
                                    @dragover="handleDragOver"
                                    class="relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-4 transition-colors cursor-pointer"
                                    :class="editImagePreviewUrl ? 'border-emerald-300 bg-emerald-50/30' : 'border-gray-200 hover:border-gray-400 bg-gray-50/50'"
                                >
                                    <template v-if="editImagePreviewUrl">
                                        <div class="relative w-full">
                                            <img :src="editImagePreviewUrl" alt="Preview" class="w-full aspect-[21/9] object-cover rounded-lg border border-gray-200">
                                            <button type="button" @click="clearFile('edit')" class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-red-500 text-white flex items-center justify-center text-xs font-bold shadow-md hover:bg-red-600 transition">✕</button>
                                        </div>
                                        <div class="mt-2 w-full text-left">
                                            <p class="text-xs font-semibold text-gray-700 truncate">{{ editImageFileName }}</p>
                                            <p class="text-[11px] text-gray-400">
                                                <span v-if="editImageFileSize">{{ editImageFileSize }}</span>
                                                <span v-if="editImageFileDims"> · {{ editImageFileDims }}</span>
                                            </p>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 text-gray-300 mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <p class="text-xs text-gray-500">Letak imej baru atau klik untuk pilih</p>
                                    </template>
                                    <input type="file" accept="image/*" @change="e => handleFileSelect(e, 'edit')" class="absolute inset-0 opacity-0 cursor-pointer">
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                                <input v-model="editForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                                <select v-model="editForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                    <option :value="null">Global</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Display Order</label>
                                    <input v-model.number="editForm.display_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                </div>
                                <div class="flex items-end pb-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                        Aktif
                                    </label>
                                </div>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Link URL <span class="text-gray-400">(optional)</span></label>
                                <div class="flex gap-2">
                                    <input v-model="editForm.link_url" type="url" class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://example.com">
                                    <select v-model="editForm.link_target" class="w-24 rounded-xl border border-gray-200 px-2 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                        <option value="_blank">_blank</option>
                                        <option value="_self">_self</option>
                                    </select>
                                </div>
                                <p v-if="editForm.errors.link_url" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.link_url }}</p>
                            </div>

                            <p v-if="editForm.errors.banner_image" class="text-xs font-semibold text-red-600">{{ editForm.errors.banner_image }}</p>

                            <div class="flex gap-3 pt-2">
                                <button type="submit" :disabled="editForm.processing" class="flex-1 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60 transition">
                                    {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                                <button type="button" @click="cancelEdit" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>
        </Teleport>
    </AppLayout>
</template>

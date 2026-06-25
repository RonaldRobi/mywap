<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Modal from '@/Components/Modal.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const shareItem = ref(null);
function openShare(item) { shareItem.value = item; }
function closeShare() { shareItem.value = null; }

const sharePreviewUrl = computed(() => {
    if (!shareItem.value) return '';
    return route('share.infaq', shareItem.value.slug, true);
});

const qrUrl = computed(() => {
    if (!shareItem.value) return '';
    return route('superadmin.infaq.qr', shareItem.value.slug);
});

const linkCopied = ref(false);
async function copyShareLink() {
    try {
        await navigator.clipboard.writeText(sharePreviewUrl.value);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    } catch {
        const ta = document.createElement('textarea');
        ta.value = sharePreviewUrl.value;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        linkCopied.value = true;
        setTimeout(() => { linkCopied.value = false; }, 2000);
    }
}

function downloadQr() {
    if (!shareItem.value) return;
    const link = document.createElement('a');
    link.href = qrUrl.value;
    link.download = `qr-infaq-${shareItem.value.slug}.png`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    infaqItems:    { type: Array, default: () => [] },
});

// ─── Create form ─────────────────────────────────────────────────────────────
const createForm = useForm({
    organization_id: '',
    title:           '',
    description:     '',
    type:            'one_off',
    target_amount:   '',
    is_active:       true,
    display_order:   1,
    infaq_image:     null,
});

const imagePreview = ref(null);

function onImageChange(e) {
    const file = e.target.files[0];
    createForm.infaq_image = file ?? null;
    imagePreview.value = file ? URL.createObjectURL(file) : null;
}

function submitCreate() {
    createForm.post(route('superadmin.infaq.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            imagePreview.value = null;
        },
    });
}

// ─── Edit forms ──────────────────────────────────────────────────────────────
const editingId    = ref(null);
const editForms    = {};

function getEditForm(item) {
    if (!editForms[item.id]) {
        editForms[item.id] = useForm({
            organization_id: item.organization_id ?? '',
            title:           item.title,
            description:     item.description ?? '',
            type:            item.type,
            target_amount:   item.target_amount ?? '',
            is_active:       item.is_active,
            display_order:   item.display_order,
            infaq_image:     null,
        });
    }
    return editForms[item.id];
}

function startEdit(item) { editingId.value = item.id; }
function cancelEdit()    { editingId.value = null; }

function onEditImageChange(e, item) {
    getEditForm(item).infaq_image = e.target.files[0] ?? null;
}

function submitEdit(item) {
    getEditForm(item).put(route('superadmin.infaq.update', item.slug), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null; },
    });
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const deleteForms = {};
function getDeleteForm(id) {
    if (!deleteForms[id]) deleteForms[id] = useForm({});
    return deleteForms[id];
}
function deleteItem(item) {
    if (!confirm(`Padam infaq "${item.title}"?`)) return;
    getDeleteForm(item.id).delete(route('superadmin.infaq.destroy', item.slug), {
        preserveScroll: true,
    });
}

// ─── Seed demo ────────────────────────────────────────────────────────────────
const seedForm = useForm({});
function seedDemo() {
    if (!confirm('Jana 5 demo infaq? Data sedia ada dengan tajuk yang sama akan dikemas kini.')) return;
    seedForm.post(route('superadmin.infaq.seed'), { preserveScroll: true });
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function formatMYR(val) {
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(val ?? 0);
}
</script>

<template>
    <Head title="Urus Infaq" />
    <AppLayout>
        <template #header>Urus Infaq</template>

        <div class="mx-auto max-w-6xl px-4 py-6 md:px-6">

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success" class="mb-5 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="mb-5 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div class="space-y-8">

                <!-- ── Create / Seed panel ─────────────────────────────────── -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-lg font-black text-gray-900">Tambah Infaq Baru</h2>
                            <p class="text-sm text-gray-500 mt-0.5">Cipta kempen derma untuk ahli.</p>
                        </div>
                        <button
                            @click="seedDemo"
                            :disabled="seedForm.processing"
                            class="inline-flex items-center gap-2 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-60 shrink-0"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            Seed Demo Infaq
                        </button>
                    </div>

                    <form @submit.prevent="submitCreate" enctype="multipart/form-data" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Title -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Tajuk *</label>
                                <input v-model="createForm.title" type="text" placeholder="cth: Infaq Masjid Al-Iman"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition"/>
                                <p v-if="createForm.errors.title" class="mt-1 text-xs text-red-500">{{ createForm.errors.title }}</p>
                            </div>

                            <!-- Description -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Penerangan</label>
                                <textarea v-model="createForm.description" rows="2" placeholder="Huraian ringkas infaq ini..."
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition resize-none"></textarea>
                            </div>

                            <!-- Organisation -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Organisasi</label>
                                <select v-model="createForm.organization_id"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition">
                                    <option value="">Global (semua org)</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                            </div>

                            <!-- Type -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Jenis Infaq *</label>
                                <select v-model="createForm.type"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition">
                                    <option value="one_off">One-Off (Derma Bebas)</option>
                                    <option value="progress">Progress Bar (Ada Sasaran)</option>
                                </select>
                            </div>

                            <!-- Target Amount (only for progress) -->
                            <div v-if="createForm.type === 'progress'">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Sasaran (RM) *</label>
                                <input v-model="createForm.target_amount" type="number" min="1" step="0.01" placeholder="cth: 50000"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition"/>
                                <p v-if="createForm.errors.target_amount" class="mt-1 text-xs text-red-500">{{ createForm.errors.target_amount }}</p>
                            </div>

                            <!-- Display order -->
                            <div :class="createForm.type === 'progress' ? '' : ''">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Susunan</label>
                                <input v-model="createForm.display_order" type="number" min="1" max="9999"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition"/>
                            </div>

                            <!-- Is active -->
                            <div class="flex items-center gap-2 pt-5">
                                <input type="checkbox" id="create-active" v-model="createForm.is_active" class="rounded accent-emerald-600"/>
                                <label for="create-active" class="text-sm text-gray-700">Aktif (papar kepada ahli)</label>
                            </div>

                            <!-- Image upload -->
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Gambar Infaq</label>
                                <div class="flex items-start gap-4">
                                    <div v-if="imagePreview" class="shrink-0">
                                        <img :src="imagePreview" class="h-20 w-32 rounded-xl object-cover border border-gray-200"/>
                                    </div>
                                    <div class="flex-1">
                                        <input type="file" accept="image/*" @change="onImageChange"
                                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-emerald-700"/>
                                        <p v-if="createForm.errors.infaq_image" class="mt-1 text-xs text-red-500">{{ createForm.errors.infaq_image }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" :disabled="createForm.processing"
                                class="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60">
                                {{ createForm.processing ? 'Menyimpan...' : 'Tambah Infaq' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- ── Infaq list ───────────────────────────────────────────── -->
                <section>
                    <h2 class="mb-4 text-base font-black text-gray-900">
                        Senarai Infaq
                        <span class="ml-1 text-sm font-medium text-gray-400">({{ infaqItems.length }})</span>
                    </h2>

                    <div v-if="!infaqItems.length" class="rounded-2xl border border-gray-100 bg-white px-4 py-10 text-center text-sm text-gray-500">
                        Tiada infaq lagi. Tambah atau seed demo di atas.
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="item in infaqItems"
                            :key="item.id"
                            class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm"
                        >
                            <!-- ── View mode ──────────────────────────────── -->
                            <div v-if="editingId !== item.id" class="flex flex-col gap-4 p-4 sm:flex-row sm:items-start">
                                <!-- Image -->
                                <div class="shrink-0">
                                    <img v-if="item.image_path" :src="item.image_path" :alt="item.title"
                                        class="h-20 w-28 rounded-xl object-cover border border-gray-100"/>
                                    <div v-else class="flex h-20 w-28 items-center justify-center rounded-xl bg-gray-100 text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4-4a3 3 0 014 0l4 4m-4-8h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Info -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-start gap-2">
                                        <p class="font-bold text-gray-900 leading-snug">{{ item.title }}</p>
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                            :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                                            {{ item.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                        </span>
                                        <span class="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">
                                            {{ item.type === 'one_off' ? 'One-Off' : 'Progress' }}
                                        </span>
                                        <span class="inline-flex rounded-full bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-500">
                                            {{ item.organization_name }}
                                        </span>
                                    </div>
                                    <p v-if="item.description" class="mt-1 text-xs text-gray-500 line-clamp-2">{{ item.description }}</p>

                                    <!-- Progress bar for progress type -->
                                    <div v-if="item.type === 'progress'" class="mt-2">
                                        <div class="flex items-center justify-between text-xs text-gray-600 mb-1">
                                            <span class="font-semibold">{{ formatMYR(item.collected_amount) }}</span>
                                            <span class="text-gray-400">/ {{ formatMYR(item.target_amount) }}</span>
                                        </div>
                                        <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                                            <div class="h-2 rounded-full bg-emerald-500 transition-all duration-500"
                                                :style="{ width: item.progress_percent + '%' }"></div>
                                        </div>
                                        <p class="mt-0.5 text-[10px] text-gray-400">{{ item.progress_percent }}% terkumpul</p>
                                    </div>
                                    <div v-else class="mt-2 text-xs text-gray-600">
                                        Terkumpul: <span class="font-semibold">{{ formatMYR(item.collected_amount) }}</span>
                                        <span class="ml-2 text-gray-400">{{ item.donations_count }} penderma</span>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex shrink-0 gap-2 sm:flex-col">
                                    <button @click="openShare(item)"
                                        class="rounded-xl border border-emerald-200 px-3 py-1.5 text-xs font-semibold text-emerald-600 transition hover:bg-emerald-50">
                                        Kongsi
                                    </button>
                                    <button @click="startEdit(item)"
                                        class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50">
                                        Edit
                                    </button>
                                    <button @click="deleteItem(item)"
                                        :disabled="getDeleteForm(item.id).processing"
                                        class="rounded-xl border border-red-100 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50 disabled:opacity-60">
                                        Padam
                                    </button>
                                </div>
                            </div>

                            <!-- ── Edit mode ───────────────────────────────── -->
                            <div v-else class="p-4 bg-gray-50">
                                <h4 class="mb-3 text-sm font-bold text-gray-800">Edit: {{ item.title }}</h4>
                                <form @submit.prevent="submitEdit(item)" enctype="multipart/form-data" class="space-y-3">
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Tajuk *</label>
                                            <input v-model="getEditForm(item).title" type="text"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none"/>
                                            <p v-if="getEditForm(item).errors.title" class="mt-1 text-xs text-red-500">{{ getEditForm(item).errors.title }}</p>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Penerangan</label>
                                            <textarea v-model="getEditForm(item).description" rows="2"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none resize-none"></textarea>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Organisasi</label>
                                            <select v-model="getEditForm(item).organization_id"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none">
                                                <option value="">Global</option>
                                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Jenis</label>
                                            <select v-model="getEditForm(item).type"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none">
                                                <option value="one_off">One-Off</option>
                                                <option value="progress">Progress Bar</option>
                                            </select>
                                        </div>
                                        <div v-if="getEditForm(item).type === 'progress'">
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Sasaran (RM)</label>
                                            <input v-model="getEditForm(item).target_amount" type="number" min="1" step="0.01"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none"/>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Susunan</label>
                                            <input v-model="getEditForm(item).display_order" type="number" min="1"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0 outline-none"/>
                                        </div>
                                        <div class="flex items-center gap-2 pt-4">
                                            <input type="checkbox" :id="'edit-active-' + item.id" v-model="getEditForm(item).is_active" class="rounded accent-emerald-600"/>
                                            <label :for="'edit-active-' + item.id" class="text-sm text-gray-700">Aktif</label>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Ganti Gambar (pilihan)</label>
                                            <input type="file" accept="image/*" @change="(e) => onEditImageChange(e, item)"
                                                class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-emerald-700"/>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2 pt-1">
                                        <button type="button" @click="cancelEdit"
                                            class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-600 transition hover:bg-gray-100">
                                            Batal
                                        </button>
                                        <button type="submit" :disabled="getEditForm(item).processing"
                                            class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-60">
                                            {{ getEditForm(item).processing ? 'Menyimpan...' : 'Simpan' }}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <!-- Share Modal -->
        <Modal :show="!!shareItem" @close="closeShare" maxWidth="md">
            <div class="p-6">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900">Kongsi Kempen</h3>
                    <button @click="closeShare" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <div v-if="shareItem" class="text-center">
                    <!-- QR Code -->
                    <div class="mx-auto bg-white p-2 border border-gray-200 rounded-xl inline-block shadow-sm mb-4">
                        <img :src="qrUrl" alt="QR Code" class="w-40 h-40"/>
                    </div>
                    
                    <h4 class="font-bold text-gray-900 mb-1">{{ shareItem.title }}</h4>
                    
                    <!-- Share Link with Copy -->
                    <div class="mt-4 flex items-center justify-between rounded-xl bg-gray-50 border border-gray-200 p-2 mb-3">
                        <span class="truncate text-sm text-gray-600 px-2">{{ sharePreviewUrl }}</span>
                        <button
                            @click="copyShareLink"
                            class="shrink-0 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="linkCopied ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600 hover:bg-gray-300'"
                        >
                            {{ linkCopied ? 'Disalin!' : 'Salin' }}
                        </button>
                    </div>

                    <!-- Download QR Button -->
                    <button
                        @click="downloadQr"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:bg-slate-800 mb-5"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Muat Turun QR
                    </button>
                    
                    <!-- Social Buttons -->
                    <div class="border-t border-gray-100 pt-5">
                        <SocialShareButtons 
                            :title="shareItem.title" 
                            :url="sharePreviewUrl"
                            text="Sumbang untuk kempen ini!" 
                        />
                    </div>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>

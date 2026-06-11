<script setup>
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    isSuperadmin: Boolean,
    defaultOrganizationId: Number,
    organizations: {
        type: Array,
        default: () => [],
    },
    libraryItems: {
        type: Array,
        default: () => [],
    },
});

const libraryForm = useForm({
    organization_id: props.defaultOrganizationId,
    title: '',
    description: '',
    category: 'Umum',
    pdf_file: null,
    cover_image: null,
});

const editingLibraryId = ref(null);
const libraryEditForm = useForm({
    title: '',
    description: '',
    category: 'Umum',
    pdf_file: null,
    cover_image: null,
});

function submitLibrary() {
    libraryForm.post(route('admin.hub.library.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => libraryForm.reset('title', 'description', 'category', 'pdf_file', 'cover_image'),
    });
}

function onPdfSelected(event) {
    libraryForm.pdf_file = event.target.files[0];
}

function onCoverSelected(event) {
    libraryForm.cover_image = event.target.files[0];
}

function startEditLibrary(item) {
    editingLibraryId.value = item.id;
    libraryEditForm.title = item.title ?? '';
    libraryEditForm.description = item.description ?? '';
    libraryEditForm.category = item.category ?? 'Umum';
    libraryEditForm.pdf_file = null;
    libraryEditForm.cover_image = null;
}

function cancelEditLibrary() {
    editingLibraryId.value = null;
    libraryEditForm.reset();
}

function onEditPdfSelected(event) {
    libraryEditForm.pdf_file = event.target.files[0];
}

function onEditCoverSelected(event) {
    libraryEditForm.cover_image = event.target.files[0];
}

function submitEditLibrary(item) {
    libraryEditForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.hub.library.update', item.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => cancelEditLibrary(),
        });
}

function removeLibraryItem(id) {
    if (!confirm('Pastikan anda mahu memadam dokumen PDF ini secara kekal?')) return;
    router.delete(route('admin.hub.library.destroy', id), { preserveScroll: true });
}

const showUploadForm = ref(false);
</script>

<template>
    <Head title="Pengurusan Pustaka" />

    <AppLayout>
        <template #header>Pengurusan Pustaka</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition-all duration-300">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ $page.props.flash.success }}
                </span>
            </div>

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-black tracking-tight text-gray-900">Pustaka Digital</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Urus senarai dokumen PDF, SOP, dan kitab bacaan untuk paparan semua ahli.</p>
                </div>
                <button
                    v-if="isSuperadmin"
                    @click="showUploadForm = !showUploadForm"
                    class="inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5"
                >
                    <svg v-if="!showUploadForm" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" /></svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    {{ showUploadForm ? 'Tutup Borang' : 'Muat Naik Dokumen PDF' }}
                </button>
            </div>

            <!-- Upload PDF Form -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
                <section v-if="showUploadForm && isSuperadmin" class="relative rounded-3xl border border-gray-100 bg-white p-6 shadow-xl shadow-gray-200/40">
                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitLibrary">
                        <div v-if="isSuperadmin" class="md:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Organisasi</label>
                            <select v-model="libraryForm.organization_id" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tajuk Buku / Dokumen</label>
                            <input v-model="libraryForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold" required>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kategori</label>
                            <input v-model="libraryForm.category" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" placeholder="Contoh: Umum / Tarbiah / Modul / Pekeliling">
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Deskripsi Ringkas</label>
                            <textarea v-model="libraryForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all"></textarea>
                        </div>

                        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 hover:bg-gray-50 transition-colors">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-700">Fail PDF</label>
                            <input type="file" accept="application/pdf" @change="onPdfSelected" class="w-full text-sm file:mr-4 file:rounded-xl file:border-0 file:bg-gray-900 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white hover:file:bg-gray-800 transition-all file:cursor-pointer mt-1">
                            <p v-if="libraryForm.errors.pdf_file" class="mt-2 text-xs font-semibold text-red-500">{{ libraryForm.errors.pdf_file }}</p>
                        </div>

                        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50/50 hover:bg-gray-50 transition-colors">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-700">Imej Muka Hadapan (Cover Image)</label>
                            <input type="file" accept="image/*" @change="onCoverSelected" class="w-full text-sm file:mr-4 file:rounded-xl file:border-0 file:bg-gray-200 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-gray-900 hover:file:bg-gray-300 transition-all file:cursor-pointer mt-1">
                            <p v-if="libraryForm.errors.cover_image" class="mt-2 text-xs font-semibold text-red-500">{{ libraryForm.errors.cover_image }}</p>
                            <p v-else class="mt-2 text-[10px] text-gray-400 font-semibold uppercase tracking-wide">*(Opsional)* Format Gambar (JPG/PNG). Nisbah sesuai 3:4.</p>
                        </div>

                        <div class="md:col-span-2 flex justify-end gap-3 mt-2 border-t border-gray-100 pt-5">
                            <button type="button" @click="showUploadForm = false" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 transition-all">Batal</button>
                            <button type="submit" :disabled="libraryForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 hover:-translate-y-0.5 transition-all disabled:opacity-60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" /></svg>
                                {{ libraryForm.processing ? 'Sedang Memuat Naik...' : 'Mula Muat Naik PDF' }}
                            </button>
                        </div>
                    </form>
                </section>
            </transition>

            <!-- Book List Grid -->
            <section>
                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <!-- Iterating items -->
                    <article v-for="item in libraryItems" :key="item.id" class="group relative flex flex-col rounded-3xl bg-white border border-gray-100 shadow-[0_2px_15px_-3px_rgba(6,81,237,0.05)] transition-all duration-300 hover:shadow-xl hover:-translate-y-1 hover:border-gray-200 overflow-hidden">
                        
                        <!-- Inline Edit Form (Replaces entire card display while editing) -->
                        <div v-if="editingLibraryId === item.id" class="p-5 flex flex-col h-full bg-gray-50 absolute inset-0 z-10 overflow-y-auto w-full">
                            <form class="space-y-4" @submit.prevent="submitEditLibrary(item)">
                                <h3 class="font-black text-gray-900 border-b border-gray-200 pb-2">Edit Dokumen</h3>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Tajuk Dokument</label>
                                    <input v-model="libraryEditForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900" required>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Kategori</label>
                                    <input v-model="libraryEditForm.category" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900">
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Deskripsi</label>
                                    <textarea v-model="libraryEditForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-gray-900 drop-shadow-sm"></textarea>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Kemaskini Fail PDF (Opsional)</label>
                                    <input type="file" accept="application/pdf" @change="onEditPdfSelected" class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-semibold">
                                    <p v-if="libraryEditForm.errors.pdf_file" class="mt-2 text-[11px] font-semibold text-red-500">{{ libraryEditForm.errors.pdf_file }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-gray-500">Kemaskini Cover (Opsional)</label>
                                    <input type="file" accept="image/*" @change="onEditCoverSelected" class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-2 file:py-1 file:text-xs file:font-semibold">
                                    <p v-if="libraryEditForm.errors.cover_image" class="mt-2 text-[11px] font-semibold text-red-500">{{ libraryEditForm.errors.cover_image }}</p>
                                </div>
                                <div class="flex items-center gap-2 pt-2 mt-auto">
                                    <button type="submit" :disabled="libraryEditForm.processing" class="flex-1 rounded-xl bg-gray-900 px-3 py-2.5 text-xs font-bold text-white hover:bg-gray-800 transition-colors disabled:opacity-60 text-center">
                                        {{ libraryEditForm.processing ? 'Menyimpan...' : 'Kemaskini' }}
                                    </button>
                                    <button type="button" @click="cancelEditLibrary" class="flex-1 rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors text-center">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Normal Card Display -->
                        <template v-else>
                            <!-- Cover Image Area -->
                            <div class="relative aspect-[3/4] w-full bg-gray-100 overflow-hidden">
                                <img v-if="item.cover_image_path" :src="item.cover_image_path" alt="cover" class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <div v-else class="flex h-full w-full flex-col items-center justify-center bg-gradient-to-br from-gray-100 to-gray-200 p-6 text-center shadow-inner">
                                    <svg class="mb-3 h-12 w-12 text-gray-300 drop-shadow-sm" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                                    <span class="text-xs font-bold uppercase tracking-widest text-gray-400">PDF Document</span>
                                </div>
                                
                                <!-- Category Badge floating -->
                                <div class="absolute top-4 left-4 z-10 flex">
                                    <span class="inline-flex items-center rounded-lg bg-white/90 backdrop-blur px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-gray-900 shadow-sm">
                                        {{ item.category || 'Umum' }}
                                    </span>
                                </div>
                                
                                <!-- Superadmin Action Buttons floating on hover -->
                                <div class="absolute top-4 right-4 z-10 flex flex-col gap-2 opacity-0 transform translate-x-4 transition-all duration-300 group-hover:opacity-100 group-hover:translate-x-0">
                                    <button @click="startEditLibrary(item)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/90 backdrop-blur shadow-sm border border-gray-100 text-gray-700 hover:text-gray-900 hover:bg-white transition-colors" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </button>
                                    <button @click="removeLibraryItem(item.id)" class="flex h-9 w-9 items-center justify-center rounded-xl bg-red-50/90 backdrop-blur shadow-sm border border-red-100 text-red-600 hover:bg-red-100 hover:text-red-700 transition-colors" title="Delete">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Bottom Details Area -->
                            <div class="flex flex-1 flex-col p-5 bg-white">
                                <h3 class="text-base font-black text-gray-900 leading-tight line-clamp-2" :title="item.title">{{ item.title }}</h3>
                                <p class="mt-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">{{ item.organization_name }}</p>
                                
                                <p class="mt-3 text-sm text-gray-600 line-clamp-2 leading-relaxed flex-1">{{ item.description || 'Tiada deskripsi disediakan.' }}</p>
                                
                                <div class="mt-5 pt-4 border-t border-gray-100 w-full flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ item.created_at?.split(' ')[0] }}</span>
                                    <a :href="item.file_path" target="_blank" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-50 px-3 py-1.5 text-xs font-bold text-gray-700 hover:bg-gray-900 hover:text-white transition-colors group/btn">
                                        <svg class="h-4 w-4 opacity-50 transition-opacity group-hover/btn:opacity-100 text-red-500" fill="currentColor" viewBox="0 0 24 24"><path d="M11.363 2c4.155 0 2.637 6 2.637 6s6-1.65 6 2.457v11.543h-16v-20h7.363zm.826-2h-10.189v24h20v-14.386c0-2.391-6.648-9.614-9.811-9.614zm4.811 13h-4v1h3v1h-3v1h4v1h-5v-5h5v1zm-7 1c0 .552-.448 1-1 1h-2v-2h2c.552 0 1 .448 1 1zm-1-2h-3v5h1v-2h1.493c1.071 0 1.507-.947 1.507-1.464 0-1.042-.71-1.536-1.036-1.536zm-3.085-3.011c.712-.047 1.879-.379 2.085-1.554.12-.684-.257-1.435-1.054-1.435h-.946v3h.423c.316 0 .61-.005.811-.011zm-.415-4v5l1.638-.016c.866-.008 1.831-.383 1.831-2.029 0-1.848-1.583-1.954-2.583-1.954h-2v-1h1.114z"/></svg>
                                        Buka PDF
                                    </a>
                                </div>
                            </div>
                        </template>
                    </article>
                    
                    <div v-if="!libraryItems.length" class="col-span-full rounded-3xl border border-dashed border-gray-200 bg-white/50 px-4 py-20 text-center">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                            <svg class="h-8 w-8 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" /></svg>
                        </div>
                        <h3 class="mt-4 text-sm font-bold text-gray-900">Tiada Dokumen</h3>
                        <p class="mt-1 text-xs text-gray-500">Tiada buku atau dokumen rasmi dijumpa dalam pangkalan data.</p>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

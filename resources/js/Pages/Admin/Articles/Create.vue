<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { ref } from 'vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

const form = useForm({
    organization_id: '',
    title: '',
    excerpt: '',
    content: '',
    cover_image: null,
    is_published: true,
    is_featured: false,
    categories: [],
    tags: '',
    gallery: [],
});

const galleryPreview = ref([]);

function handleCover(e) {
    form.cover_image = e.target.files?.[0] ?? null;
}

function handleGallery(e) {
    const files = Array.from(e.target.files || []);
    form.gallery = files;
    galleryPreview.value = files.map(f => URL.createObjectURL(f));
}

function toggleCategory(id) {
    const idx = form.categories.indexOf(id);
    if (idx > -1) {
        form.categories.splice(idx, 1);
    } else {
        form.categories.push(id);
    }
}

function submit() {
    form.post(route('admin.articles.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('title', 'excerpt', 'content', 'cover_image', 'tags', 'gallery'),
    });
}
</script>

<template>
    <Head title="Tulis Artikel" />

    <AppLayout :back-route="route('admin.articles.index')" back-label="Kembali ke Senarai">
        <template #header>Tulis Artikel Baru</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Maklumat Artikel</h2>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Target Organisasi</label>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Umum (Semua)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                        <div v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Ringkasan</label>
                        <textarea v-model="form.excerpt" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Ringkasan pendek artikel..."></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kandungan</label>
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <QuillEditor v-model:content="form.content" contentType="html" toolbar="full" class="min-h-[400px]" />
                        </div>
                        <div v-if="form.errors.content" class="mt-1 text-xs text-red-600">{{ form.errors.content }}</div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Media</h2>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Gambar Cover</label>
                        <input type="file" accept="image/*" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleCover">
                        <div v-if="form.errors.cover_image" class="mt-1 text-xs text-red-600">{{ form.errors.cover_image }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Galeri Gambar</label>
                        <input type="file" accept="image/*" multiple class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleGallery">
                        <p class="mt-1 text-xs text-gray-400">Boleh pilih banyak gambar sekali gus.</p>
                        <div v-if="galleryPreview.length" class="mt-2 flex flex-wrap gap-2">
                            <div v-for="(src, i) in galleryPreview" :key="i" class="relative h-20 w-20 rounded-lg border border-gray-200 overflow-hidden">
                                <img :src="src" class="h-full w-full object-cover" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Kategori & Tag</h2>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                        <div v-if="categories.length" class="flex flex-wrap gap-2">
                            <button type="button" v-for="cat in categories" :key="cat.id" @click="toggleCategory(cat.id)"
                                :class="[
                                    'rounded-full px-3 py-1.5 text-xs font-semibold border transition',
                                    form.categories.includes(cat.id)
                                        ? 'bg-emerald-50 border-emerald-300 text-emerald-700'
                                        : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'
                                ]"
                            >
                                {{ cat.name }}
                            </button>
                        </div>
                        <p v-else class="text-xs text-gray-400">Belum ada kategori. Admin boleh tambah kategori di DatabaseSeeder.</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tag (pisahkan dengan koma)</label>
                        <input v-model="form.tags" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Cth: tarbiyah, belia, kepimpinan">
                        <p class="mt-1 text-xs text-gray-400">Tag baru akan dicipta secara automatik.</p>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Penerbitan</h2>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_published" type="checkbox" class="rounded border-gray-300">
                            Terbitkan sekarang
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300">
                            Featured (tampil di atas)
                        </label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Artikel' }}
                        </button>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>

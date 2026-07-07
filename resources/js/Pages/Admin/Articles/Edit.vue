<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { ref } from 'vue';

const props = defineProps({
    article: { type: Object, required: true },
    organizations: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
});

const form = useForm({
    organization_id: props.article.organization_id ?? '',
    title: props.article.title,
    excerpt: props.article.excerpt ?? '',
    content: props.article.content ?? '',
    cover_image: null,
    is_published: !!props.article.is_published,
    is_featured: !!props.article.is_featured,
    categories: props.article.categories?.map(c => c.id) ?? [],
    tags: props.article.tags?.map(t => t.name).join(', ') ?? '',
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
    form
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.articles.update', props.article.id), {
            preserveScroll: true,
            forceFormData: true,
        });
}

function deleteGalleryImage(mediaId) {
    if (!confirm('Padam gambar ini?')) return;
    useForm({}).delete(route('admin.articles.media.destroy', mediaId), { preserveScroll: true });
}
</script>

<template>
    <Head :title="'Edit: ' + article.title" />

    <AppLayout :back-route="route('admin.articles.index')" back-label="Kembali ke Senarai">
        <template #header>Edit Artikel</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

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
                        <textarea v-model="form.excerpt" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
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
                        <img v-if="article.cover_image_path && !form.cover_image" :src="article.cover_image_path" class="mb-2 h-32 w-full max-w-xs rounded-xl border border-gray-200 object-cover" />
                        <input type="file" accept="image/*" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleCover">
                        <div v-if="form.errors.cover_image" class="mt-1 text-xs text-red-600">{{ form.errors.cover_image }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Galeri Gambar</label>

                        <div v-if="article.media?.length" class="mb-3 flex flex-wrap gap-2">
                            <div v-for="media in article.media" :key="media.id" class="relative h-24 w-24 rounded-lg border border-gray-200 overflow-hidden group">
                                <img :src="media.path" class="h-full w-full object-cover" />
                                <button type="button" @click="deleteGalleryImage(media.id)" class="absolute top-1 right-1 h-6 w-6 rounded-full bg-red-600 text-white text-xs opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                    &times;
                                </button>
                                <p v-if="media.caption" class="absolute bottom-0 left-0 right-0 bg-black/50 text-white text-[10px] px-1 py-0.5 truncate">{{ media.caption }}</p>
                            </div>
                        </div>

                        <input type="file" accept="image/*" multiple class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleGallery">
                        <p class="mt-1 text-xs text-gray-400">Tambah gambar baru ke galeri.</p>
                        <div v-if="galleryPreview.length" class="mt-2 flex flex-wrap gap-2">
                            <div v-for="(src, i) in galleryPreview" :key="i" class="h-20 w-20 rounded-lg border border-gray-200 overflow-hidden">
                                <img :src="src" class="h-full w-full object-cover" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Kategori & Tag</h2>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                        <div class="flex flex-wrap gap-2">
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
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tag (pisahkan dengan koma)</label>
                        <input v-model="form.tags" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Cth: tarbiyah, belia, kepimpinan">
                    </div>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Penerbitan</h2>

                    <div class="flex items-center gap-6">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_published" type="checkbox" class="rounded border-gray-300">
                            Terbitkan
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300">
                            Featured
                        </label>
                    </div>

                    <div class="pt-2 flex items-center gap-3">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 transition disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Kemas Kini Artikel' }}
                        </button>
                        <Link :href="route('admin.articles.index')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                            Batal
                        </Link>
                    </div>
                </section>
            </form>
        </div>
    </AppLayout>
</template>

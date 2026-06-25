<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';
import { ref } from 'vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    articles: { type: Object, default: () => ({ data: [] }) },
});

const postForm = useForm({
    organization_id: '',
    title: '',
    excerpt: '',
    content: '',
    cover_image: null,
    is_published: true,
});

const editingId = ref(null);
const editForm = useForm({
    organization_id: '',
    title: '',
    excerpt: '',
    content: '',
    cover_image: null,
    is_published: true,
});

function submitPost() {
    postForm.post(route('admin.articles.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => postForm.reset('title', 'excerpt', 'content', 'cover_image', 'is_published'),
    });
}

function startEdit(article) {
    editingId.value = article.id;
    editForm.organization_id = article.organization_id ?? '';
    editForm.title = article.title;
    editForm.excerpt = article.excerpt ?? '';
    editForm.content = article.content ?? '';
    editForm.cover_image = null;
    editForm.is_published = !!article.is_published;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(article) {
    editForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.articles.update', article.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelEdit(),
    });
}

function removePost(article) {
    if (!confirm('Padam artikel ini?')) return;
    useForm({}).delete(route('admin.articles.destroy', article.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Manage Artikel" />

    <AppLayout>
        <template #header>Manage Artikel</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Cipta Artikel Baru</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitPost">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Target Organisasi</label>
                        <select v-model="postForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Umum (Semua)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                        <div v-if="postForm.errors.organization_id" class="mt-1 text-xs text-red-600">{{ postForm.errors.organization_id }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="postForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                        <div v-if="postForm.errors.title" class="mt-1 text-xs text-red-600">{{ postForm.errors.title }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Ringkasan</label>
                        <textarea v-model="postForm.excerpt" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                        <div v-if="postForm.errors.excerpt" class="mt-1 text-xs text-red-600">{{ postForm.errors.excerpt }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kandungan</label>
                        <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                            <QuillEditor v-model:content="postForm.content" contentType="html" toolbar="full" class="min-h-[300px]" />
                        </div>
                        <div v-if="postForm.errors.content" class="mt-1 text-xs text-red-600">{{ postForm.errors.content }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cover Image</label>
                        <input type="file" accept="image/*" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="postForm.cover_image = $event.target.files?.[0] ?? null">
                        <div v-if="postForm.errors.cover_image" class="mt-1 text-xs text-red-600">{{ postForm.errors.cover_image }}</div>
                    </div>

                    <div class="flex items-center gap-2 mt-6">
                        <input id="publish_now" v-model="postForm.is_published" type="checkbox" class="rounded border-gray-300">
                        <label for="publish_now" class="text-sm text-gray-600">Terbitkan sekarang</label>
                        <div v-if="postForm.errors.is_published" class="mt-1 text-xs text-red-600">{{ postForm.errors.is_published }}</div>
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="postForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                            {{ postForm.processing ? 'Menyimpan...' : 'Simpan Artikel' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Artikel</h2>

                <div class="mt-4 space-y-4">
                    <article v-for="article in articles.data" :key="article.id" class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <template v-if="editingId === article.id">
                            <form class="grid grid-cols-1 gap-2 md:grid-cols-2" @submit.prevent="saveEdit(article)">
                                <select v-model="editForm.organization_id" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option value="">Umum (Semua)</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <input v-model="editForm.title" type="text" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <textarea v-model="editForm.excerpt" rows="2" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs" placeholder="Ringkasan..."></textarea>
                                <div class="md:col-span-2 bg-white rounded-lg border border-gray-200 overflow-hidden">
                                    <QuillEditor v-model:content="editForm.content" contentType="html" toolbar="full" class="min-h-[300px]" />
                                </div>
                                <img v-if="article.cover_image_path && !editForm.cover_image" :src="article.cover_image_path" class="md:col-span-2 h-32 w-full rounded-lg border border-gray-200 object-cover">
                                <input type="file" accept="image/*" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs" @change="editForm.cover_image = $event.target.files?.[0] ?? null">
                                <label class="md:col-span-2 flex items-center gap-2 text-xs text-gray-600"><input v-model="editForm.is_published" type="checkbox" class="rounded border-gray-300"> Terbitkan</label>
                                <div class="md:col-span-2 flex items-center gap-2">
                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">{{ article.organization?.name || 'Umum' }}</span>
                                <span :class="article.is_published ? 'text-emerald-700' : 'text-amber-700'">{{ article.is_published ? 'Published' : 'Draft' }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-black text-gray-900">{{ article.title }}</h3>
                            <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ article.excerpt || 'Tiada ringkasan' }}</p>
                            <p class="mt-1 text-xs text-gray-400">Penulis: {{ article.author?.name }} • {{ article.published_at ? new Date(article.published_at).toLocaleDateString() : '-' }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <button @click="startEdit(article)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                <button @click="removePost(article)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                            </div>
                        </template>
                    </article>

                    <p v-if="!articles.data.length" class="text-sm text-gray-500">Belum ada artikel.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

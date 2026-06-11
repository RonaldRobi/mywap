<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    isSuperadmin: Boolean,
    defaultOrganizationId: Number,
    categories: { type: Array, default: () => [] },
    posts: { type: Object, default: () => ({ data: [] }) },
    targetOrganizations: { type: Array, default: () => [] },
});

const categoryForm = useForm({
    name: '',
    icon: '',
});

const postForm = useForm({
    organization_id: '',
    news_category_id: '',
    title: '',
    excerpt: '',
    content: '',
    cover_image: null,
    is_published: true,
});

const editingId = ref(null);
const editForm = useForm({
    organization_id: '',
    news_category_id: '',
    title: '',
    excerpt: '',
    content: '',
    cover_image: null,
    is_published: true,
});

function submitCategory() {
    categoryForm.post(route('admin.news.categories.store'), {
        preserveScroll: true,
        onSuccess: () => categoryForm.reset(),
    });
}

function submitPost() {
    postForm.post(route('admin.news.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => postForm.reset('title', 'excerpt', 'content', 'cover_image', 'is_published'),
    });
}

function startEdit(post) {
    editingId.value = post.id;
    editForm.organization_id = post.organization_id ?? '';
    editForm.news_category_id = post.category_id ?? '';
    editForm.title = post.title;
    editForm.excerpt = post.excerpt ?? '';
    editForm.content = post.content ?? '';
    editForm.cover_image = null;
    editForm.is_published = !!post.is_published;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(post) {
    editForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.news.update', post.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelEdit(),
    });
}

function removePost(post) {
    if (!confirm('Padam info ini?')) return;
    useForm({}).delete(route('admin.news.destroy', post.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Manage Info Terkini" />

    <AppLayout>
        <template #header>Manage Info Terkini</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Tambah Kategori</h2>
                <form class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-3" @submit.prevent="submitCategory">
                    <input v-model="categoryForm.name" type="text" placeholder="Contoh: Teknologi" class="rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                    <input v-model="categoryForm.icon" type="text" placeholder="Icon (opsyenal)" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <button type="submit" :disabled="categoryForm.processing" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Tambah</button>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Cipta Info Terkini</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitPost">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Target Organisasi</label>
                        <select v-model="postForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            <option v-for="org in targetOrganizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                        <select v-model="postForm.news_category_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="">Umum</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="postForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Ringkasan</label>
                        <textarea v-model="postForm.excerpt" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kandungan</label>
                        <textarea v-model="postForm.content" rows="6" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cover Image</label>
                        <input type="file" accept="image/*" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="postForm.cover_image = $event.target.files?.[0] ?? null">
                    </div>

                    <div class="flex items-center gap-2 mt-6">
                        <input id="publish_now" v-model="postForm.is_published" type="checkbox" class="rounded border-gray-300">
                        <label for="publish_now" class="text-sm text-gray-600">Terbitkan sekarang</label>
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="postForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">
                            {{ postForm.processing ? 'Menyimpan...' : 'Simpan Info' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Info Terkini</h2>

                <div class="mt-4 space-y-4">
                    <article v-for="post in posts.data" :key="post.id" class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <template v-if="editingId === post.id">
                            <form class="grid grid-cols-1 gap-2 md:grid-cols-2" @submit.prevent="saveEdit(post)">
                                <select v-model="editForm.organization_id" class="rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option value="">Semua</option>
                                    <option v-for="org in targetOrganizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <select v-model="editForm.news_category_id" class="rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option value="">Umum</option>
                                    <option v-for="category in categories" :key="category.id" :value="category.id">{{ category.name }}</option>
                                </select>
                                <input v-model="editForm.title" type="text" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <textarea v-model="editForm.excerpt" rows="2" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs"></textarea>
                                <textarea v-model="editForm.content" rows="4" class="md:col-span-2 rounded-lg border border-gray-200 px-3 py-2 text-xs" required></textarea>
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
                                <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-700">{{ post.category_name }}</span>
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">{{ post.organization_name }}</span>
                                <span :class="post.is_published ? 'text-emerald-700' : 'text-amber-700'">{{ post.is_published ? 'Published' : 'Draft' }}</span>
                            </div>
                            <h3 class="mt-2 text-base font-black text-gray-900">{{ post.title }}</h3>
                            <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ post.excerpt || 'Tiada ringkasan' }}</p>
                            <p class="mt-1 text-xs text-gray-400">Penulis: {{ post.author_name }} • {{ post.published_at || '-' }}</p>
                            <div class="mt-3 flex items-center gap-2">
                                <button @click="startEdit(post)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                <button @click="removePost(post)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                            </div>
                        </template>
                    </article>

                    <p v-if="!posts.data.length" class="text-sm text-gray-500">Belum ada info terkini.</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

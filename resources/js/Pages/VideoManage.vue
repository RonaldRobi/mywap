<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    organizations: { type: Array, default: () => [] },
    videos: { type: Array, default: () => [] },
});

const form = useForm({
    organization_id: null,
    title: '',
    youtube_url: '',
});

const editingId = ref(null);
const editForm = useForm({
    organization_id: null,
    title: '',
    youtube_url: '',
});

function submit() {
    form.post(route('admin.videos.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('organization_id', 'title', 'youtube_url'),
    });
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.organization_id = item.organization_id;
    editForm.title = item.title;
    editForm.youtube_url = item.youtube_url;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(item) {
    editForm
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('admin.videos.update', item.id), {
            preserveScroll: true,
            onSuccess: () => cancelEdit(),
        });
}

function remove(item) {
    if (!confirm('Padam video ini?')) return;
    useForm({}).delete(route('admin.videos.destroy', item.id), { preserveScroll: true });
}

function previewThumbnail(url) {
    const match = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{11})/);
    if (match) {
        return `https://img.youtube.com/vi/${match[1]}/hqdefault.jpg`;
    }
    return null;
}
</script>

<template>
    <Head title="Video Management" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Video Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>
            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Tambah Video</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <div v-if="organizations.length">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi (Kosongkan untuk global)</label>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option :value="null">Global (Semua organisasi)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.title" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Link YouTube</label>
                        <input v-model="form.youtube_url" type="url" placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.youtube_url" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.youtube_url }}</p>
                        <img v-if="previewThumbnail(form.youtube_url)" :src="previewThumbnail(form.youtube_url)" class="mt-2 h-20 rounded-lg object-cover border border-gray-200">
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Video' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Video</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="item in videos" :key="item.id" class="rounded-2xl border border-gray-100 bg-white p-3">
                        <img :src="item.thumbnail_url" :alt="item.title" class="aspect-video w-full rounded-xl object-cover border border-gray-200">

                        <template v-if="editingId === item.id">
                            <form class="mt-3 space-y-2" @submit.prevent="saveEdit(item)">
                                <input v-model="editForm.title" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <select v-if="organizations.length" v-model="editForm.organization_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option :value="null">Global</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <input v-model="editForm.youtube_url" type="url" placeholder="https://www.youtube.com/watch?v=..." class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <p v-if="editForm.errors.youtube_url" class="text-[11px] font-semibold text-red-600">{{ editForm.errors.youtube_url }}</p>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <div class="mt-3">
                                <p class="text-sm font-bold text-gray-800">{{ item.title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.organization_name }} · {{ item.created_at }}</p>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <button @click="startEdit(item)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                <button @click="remove(item)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                            </div>
                        </template>
                    </article>

                    <div v-if="!videos.length" class="col-span-full rounded-2xl border-2 border-dashed border-gray-200 px-4 py-12 text-center">
                        <p class="text-sm font-semibold text-gray-700">Tiada video lagi.</p>
                        <p class="mt-1 text-xs text-gray-400">Tambah video pertama anda.</p>
                    </div>
                </div>
            </section>

            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
    banners: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    organization_id: null,
    title: '',
    display_order: 1,
    is_active: true,
    banner_image: null,
});

const seedDemoForm = useForm({});

const editingId = ref(null);
const editForm = useForm({
    organization_id: null,
    title: '',
    display_order: 1,
    is_active: true,
    banner_image: null,
});

function submit() {
    form.post(route('superadmin.banners.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('organization_id', 'title', 'display_order', 'is_active', 'banner_image'),
    });
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.organization_id = item.organization_id;
    editForm.title = item.title;
    editForm.display_order = item.display_order;
    editForm.is_active = !!item.is_active;
    editForm.banner_image = null;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
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
</script>

<template>
    <Head title="Berita Bergambar Management" />

    <AppLayout>
        <template #header>Berita Bergambar Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-lg font-black text-gray-800">Tambah Banner</h2>
                    <button
                        type="button"
                        @click="seedDemoBanners"
                        :disabled="seedDemoForm.processing"
                        class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-60"
                    >
                        {{ seedDemoForm.processing ? 'Menjana...' : 'Seed Demo Banners' }}
                    </button>
                </div>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi (Kosongkan untuk global)</label>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option :value="null">Global (Semua organisasi)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Display Order</label>
                        <input v-model.number="form.display_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input id="is_active_banner" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <label for="is_active_banner" class="text-sm text-gray-600">Aktifkan banner</label>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Imej Banner</label>
                        <input type="file" accept="image/*" @change="form.banner_image = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                        <p v-if="form.errors.banner_image" class="mt-2 text-xs font-semibold text-red-600">{{ form.errors.banner_image }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Banner' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Banner</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="item in banners" :key="item.id" class="rounded-2xl border border-gray-100 bg-white p-3">
                        <img :src="item.image_path" :alt="item.title" class="aspect-[4/5] w-full rounded-xl object-cover border border-gray-200">

                        <template v-if="editingId === item.id">
                            <form class="mt-3 space-y-2" @submit.prevent="saveEdit(item)">
                                <input v-model="editForm.title" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <select v-model="editForm.organization_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option :value="null">Global</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <input v-model.number="editForm.display_order" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <label class="flex items-center gap-2 text-xs text-gray-600"><input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300"> Aktif</label>
                                <input type="file" accept="image/*" @change="editForm.banner_image = $event.target.files[0]" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <p v-if="editForm.errors.banner_image" class="text-[11px] font-semibold text-red-600">{{ editForm.errors.banner_image }}</p>
                                <div class="flex gap-2">
                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <div class="mt-3">
                                <p class="text-sm font-bold text-gray-800">{{ item.title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.organization_name }} · Order {{ item.display_order }}</p>
                                <span class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">{{ item.is_active ? 'Aktif' : 'Tidak aktif' }}</span>
                            </div>
                            <div class="mt-3 flex items-center gap-2">
                                <button @click="startEdit(item)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                <button @click="remove(item)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                            </div>
                        </template>
                    </article>
                </div>
            </section>

            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
            </div>
        </div>
    </AppLayout>
</template>

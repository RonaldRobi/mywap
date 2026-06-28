<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    popups: {
        type: Array,
        default: () => [],
    },
});

function toBoolString(val) {
    return val ? '1' : '0';
}

const form = useForm({
    title: '',
    content: '',
    image: null,
    button_text: '',
    button_url: '',
    button_text_2: '',
    button_url_2: '',
    popup_size: 'md',
    is_active: true,
    display_order: 1,
    start_at: '',
    end_at: '',
});

const editingId = ref(null);
const editForm = useForm({
    title: '',
    content: '',
    image: null,
    button_text: '',
    button_url: '',
    button_text_2: '',
    button_url_2: '',
    popup_size: 'md',
    is_active: true,
    display_order: 1,
    start_at: '',
    end_at: '',
});

function submit() {
    form.is_active = toBoolString(form.is_active);
    form.post(route('admin.popups.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('title', 'content', 'image', 'button_text', 'button_url', 'button_text_2', 'button_url_2', 'popup_size', 'is_active', 'display_order', 'start_at', 'end_at'),
    });
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.title = item.title;
    editForm.content = item.content ?? '';
    editForm.button_text = item.button_text ?? '';
    editForm.button_url = item.button_url ?? '';
    editForm.button_text_2 = item.button_text_2 ?? '';
    editForm.button_url_2 = item.button_url_2 ?? '';
    editForm.popup_size = item.popup_size ?? 'md';
    editForm.is_active = !!item.is_active;
    editForm.display_order = item.display_order;
    editForm.start_at = item.start_at ?? '';
    editForm.end_at = item.end_at ?? '';
    editForm.image = null;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
}

function saveEdit(item) {
    editForm.is_active = toBoolString(editForm.is_active);
    editForm
        .transform((data) => ({
            ...data,
            _method: 'put',
        }))
        .post(route('admin.popups.update', item.id), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => cancelEdit(),
        });
}

function remove(item) {
    if (!confirm('Padam popup ini?')) return;
    useForm({}).delete(route('admin.popups.destroy', item.id), { preserveScroll: true });
}

const sizeLabels = {
    sm: 'Kecil',
    md: 'Sederhana',
    lg: 'Besar',
};
</script>

<template>
    <Head title="Popup Management" />

    <AppLayout>
        <template #header>Popup Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Tambah Popup</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.title" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Saiz Popup</label>
                        <select v-model="form.popup_size" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="sm">Kecil</option>
                            <option value="md">Sederhana</option>
                            <option value="lg">Besar</option>
                        </select>
                        <p v-if="form.errors.popup_size" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.popup_size }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kandungan (Teks)</label>
                        <textarea v-model="form.content" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Imej Popup</label>
                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="form.image = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                        <p class="mt-1.5 text-[11px] text-gray-400">Saiz disyorkan: 800×400px (nisbah 2:1) — muat dalam popup tanpa stretch. Format: JPG, PNG, WebP. Maks 5MB.</p>
                        <p v-if="form.errors.image" class="mt-2 text-xs font-semibold text-red-600">{{ form.errors.image }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Teks Butang 1</label>
                        <input v-model="form.button_text" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.button_text" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.button_text }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">URL Butang 1</label>
                        <input v-model="form.button_url" type="url" placeholder="https://..." class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.button_url" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.button_url }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Teks Butang 2 (Optional)</label>
                        <input v-model="form.button_text_2" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.button_text_2" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.button_text_2 }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">URL Butang 2</label>
                        <input v-model="form.button_url_2" type="url" placeholder="https://..." class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.button_url_2" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.button_url_2 }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Display Order</label>
                        <input v-model.number="form.display_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.display_order" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.display_order }}</p>
                    </div>
                    <div class="flex items-center gap-2 mt-6">
                        <input id="is_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <label for="is_active" class="text-sm text-gray-600">Aktifkan popup</label>
                        <p v-if="form.errors.is_active" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.is_active }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Mula (kosongkan untuk tanpa had)</label>
                        <input v-model="form.start_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.start_at" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.start_at }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tamat (kosongkan untuk tanpa had)</label>
                        <input v-model="form.end_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        <p v-if="form.errors.end_at" class="mt-1 text-xs font-semibold text-red-600">{{ form.errors.end_at }}</p>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Popup' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Popup</h2>
                <div class="mt-4 space-y-3">
                    <article v-for="item in popups" :key="item.id" class="rounded-2xl border border-gray-100 bg-white p-4">
                        <template v-if="editingId === item.id">
                            <form class="space-y-3" @submit.prevent="saveEdit(item)">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                                        <input v-model="editForm.title" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                        <p v-if="editForm.errors.title" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.title }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Saiz</label>
                                        <select v-model="editForm.popup_size" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                            <option value="sm">Kecil</option>
                                            <option value="md">Sederhana</option>
                                            <option value="lg">Besar</option>
                                        </select>
                                        <p v-if="editForm.errors.popup_size" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.popup_size }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kandungan</label>
                                        <textarea v-model="editForm.content" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs"></textarea>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Butang 1</label>
                                        <div class="flex gap-2">
                                            <input v-model="editForm.button_text" type="text" placeholder="Teks" class="w-1/2 rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                            <input v-model="editForm.button_url" type="url" placeholder="URL" class="w-1/2 rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        </div>
                                        <p v-if="editForm.errors.button_text" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.button_text }}</p>
                                        <p v-if="editForm.errors.button_url" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.button_url }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Butang 2</label>
                                        <div class="flex gap-2">
                                            <input v-model="editForm.button_text_2" type="text" placeholder="Teks" class="w-1/2 rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                            <input v-model="editForm.button_url_2" type="url" placeholder="URL" class="w-1/2 rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        </div>
                                        <p v-if="editForm.errors.button_text_2" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.button_text_2 }}</p>
                                        <p v-if="editForm.errors.button_url_2" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.button_url_2 }}</p>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Display Order</label>
                                        <input v-model.number="editForm.display_order" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        <p v-if="editForm.errors.display_order" class="mt-1 text-xs font-semibold text-red-600">{{ editForm.errors.display_order }}</p>
                                    </div>
                                    <div class="flex items-end gap-4">
                                        <label class="flex items-center gap-2 text-xs text-gray-600">
                                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300"> Aktif
                                        </label>
                                        <p v-if="editForm.errors.is_active" class="text-xs font-semibold text-red-600">{{ editForm.errors.is_active }}</p>
                                        <label class="text-xs text-gray-500">Mula:
                                            <input v-model="editForm.start_at" type="datetime-local" class="rounded-lg border border-gray-200 px-2 py-1 text-xs ml-1">
                                        </label>
                                        <p v-if="editForm.errors.start_at" class="text-xs font-semibold text-red-600">{{ editForm.errors.start_at }}</p>
                                        <label class="text-xs text-gray-500">Tamat:
                                            <input v-model="editForm.end_at" type="datetime-local" class="rounded-lg border border-gray-200 px-2 py-1 text-xs ml-1">
                                        </label>
                                        <p v-if="editForm.errors.end_at" class="text-xs font-semibold text-red-600">{{ editForm.errors.end_at }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Gambar Baru (biarkan kosong jika nak kekal gambar lama)</label>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="editForm.image = $event.target.files[0]" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                        <p v-if="editForm.errors.image" class="text-[11px] font-semibold text-red-600 mt-1">{{ editForm.errors.image }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-2 mt-2">
                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <div class="flex items-start gap-4">
                                <img v-if="item.image_path" :src="item.image_path" :alt="item.title" class="w-24 h-16 rounded-xl object-cover border border-gray-200 shrink-0">
                                <div v-else class="w-24 h-16 rounded-xl bg-gray-100 flex items-center justify-center text-xs text-gray-400 border border-gray-200 shrink-0">Tiada gambar</div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-sm font-bold text-gray-800">{{ item.title }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500">{{ item.organization_name }} · Order {{ item.display_order }} · Saiz: {{ sizeLabels[item.popup_size] || item.popup_size }}</p>
                                        </div>
                                        <span class="shrink-0 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">{{ item.is_active ? 'Aktif' : 'Tidak aktif' }}</span>
                                    </div>
                                    <p v-if="item.content" class="mt-1 text-xs text-gray-600 line-clamp-2">{{ item.content }}</p>
                                    <div v-if="item.button_text" class="mt-1.5 flex gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-700">{{ item.button_text }}</span>
                                        <span v-if="item.button_text_2" class="inline-flex items-center gap-1 rounded-full bg-gray-50 px-2 py-0.5 text-[10px] font-medium text-gray-600">{{ item.button_text_2 }}</span>
                                    </div>
                                    <div v-if="item.start_at || item.end_at" class="mt-1 text-[10px] text-gray-400">
                                        {{ item.start_at ? 'Mula: ' + new Date(item.start_at).toLocaleDateString('ms-MY') : '' }}
                                        {{ item.end_at ? '· Tamat: ' + new Date(item.end_at).toLocaleDateString('ms-MY') : '' }}
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <button @click="startEdit(item)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                    <button @click="remove(item)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                                </div>
                            </div>
                        </template>
                    </article>
                    <p v-if="!popups.length" class="py-8 text-center text-sm text-gray-400">Tiada popup lagi. Buat popup pertama anda!</p>
                </div>
            </section>

            <div>
                <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
            </div>
        </div>
    </AppLayout>
</template>

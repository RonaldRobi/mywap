<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    isSuperadmin: Boolean,
    defaultOrganizationId: Number,
    organizations: {
        type: Array,
        default: () => [],
    },
    facilities: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    organization_id: props.defaultOrganizationId,
    name: '',
    description: '',
    location: '',
    type: 'hourly',
    price_per_unit: 0,
    capacity: null,
    image: null,
    gallery: [],
    is_active: true,
});

const editingId = ref(null);
const existingMedia = ref([]);
const editForm = useForm({
    organization_id: props.defaultOrganizationId,
    name: '',
    description: '',
    location: '',
    type: 'hourly',
    price_per_unit: 0,
    capacity: null,
    image: null,
    gallery: [],
    delete_media: [],
    is_active: true,
});

function selectGallery(e) {
    form.gallery = Array.from(e.target.files);
}

function selectEditGallery(e) {
    editForm.gallery = [...editForm.gallery, ...Array.from(e.target.files)];
}

function removeEditGalleryFile(index) {
    editForm.gallery.splice(index, 1);
}

function submit() {
    form.post(route('admin.facilities.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('name', 'description', 'location', 'type', 'price_per_unit', 'capacity', 'image', 'is_active');
            form.gallery = [];
        },
    });
}

function startEdit(item) {
    editingId.value = item.id;
    existingMedia.value = item.media || [];
    editForm.organization_id = item.organization_id;
    editForm.name = item.name;
    editForm.description = item.description ?? '';
    editForm.location = item.location ?? '';
    editForm.type = item.type;
    editForm.price_per_unit = item.price_per_unit;
    editForm.capacity = item.capacity;
    editForm.image = null;
    editForm.gallery = [];
    editForm.delete_media = [];
    editForm.is_active = !!item.is_active;
}

function cancelEdit() {
    editingId.value = null;
    existingMedia.value = [];
    editForm.reset();
    editForm.gallery = [];
    editForm.delete_media = [];
}

function deleteExistingMedia(mediaId) {
    editForm.delete_media.push(mediaId);
    existingMedia.value = existingMedia.value.filter(m => m.id !== mediaId);
}

function saveEdit(item) {
    editForm.put(route('admin.facilities.update', item.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => cancelEdit(),
    });
}

function removeItem(item) {
    if (!confirm('Padam ruang ini?')) return;
    useForm({}).delete(route('admin.facilities.destroy', item.id), { preserveScroll: true });
}

const allImages = computed(() => {
    return (item) => {
        const images = [];
        if (item.image_path) images.push({ id: 'cover', path: item.image_path, caption: null });
        (item.media || []).forEach(m => images.push(m));
        return images;
    };
});
</script>

<template>
    <Head title="Facility Management" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Facility Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Tambah Ruang</h2>

                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submit">
                    <div v-if="isSuperadmin">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Ruang</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Lokasi</label>
                        <input v-model="form.location" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Jenis Tempahan</label>
                        <select v-model="form.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="hourly">Hourly</option>
                            <option value="daily">Daily</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Harga per Unit (RM)</label>
                        <input v-model.number="form.price_per_unit" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kapasiti</label>
                        <input v-model.number="form.capacity" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                    </div>

                    <div class="flex items-center gap-2 mt-6">
                        <input id="facility_active" v-model="form.is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                        <label for="facility_active" class="text-sm text-gray-600">Aktif</label>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Deskripsi</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Imej Cover</label>
                        <input type="file" accept="image/*" @change="form.image = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Galeri</label>
                        <input type="file" accept="image/*" multiple @change="selectGallery" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                        <p class="mt-1 text-xs text-gray-400">{{ form.gallery.length }} fail dipilih</p>
                    </div>

                    <div class="md:col-span-2">
                        <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan Ruang' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Ruang</h2>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="item in facilities" :key="item.id" class="rounded-2xl border border-gray-100 bg-white p-4">
                        <div v-if="allImages(item).length" class="mb-3 overflow-hidden rounded-xl border border-gray-200">
                            <div class="grid gap-px bg-gray-200" :class="allImages(item).length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                                <img
                                    v-for="(img, i) in allImages(item).slice(0, 4)"
                                    :key="img.id"
                                    :src="img.path"
                                    :alt="item.name"
                                    class="object-cover"
                                    :class="i === 0 && allImages(item).length === 3 ? 'row-span-2 h-full' : allImages(item).length === 1 ? 'aspect-video w-full' : 'aspect-square w-full'"
                                >
                            </div>
                            <div v-if="allImages(item).length > 4" class="bg-gray-100 px-3 py-1.5 text-center text-xs text-gray-500">
                                +{{ allImages(item).length - 4 }} lagi
                            </div>
                        </div>

                        <template v-if="editingId === item.id">
                            <form class="space-y-2" @submit.prevent="saveEdit(item)">
                                <select v-if="isSuperadmin" v-model="editForm.organization_id" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <input v-model="editForm.location" type="text" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <select v-model="editForm.type" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <option value="hourly">Hourly</option>
                                    <option value="daily">Daily</option>
                                </select>
                                <input v-model.number="editForm.price_per_unit" type="number" step="0.01" min="0" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" required>
                                <input v-model.number="editForm.capacity" type="number" min="1" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <label class="flex items-center gap-2 text-xs text-gray-600"><input v-model="editForm.is_active" type="checkbox" class="rounded border-gray-300"> Aktif</label>
                                <textarea v-model="editForm.description" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs"></textarea>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-2 space-y-1">
                                    <p class="text-[11px] font-semibold text-gray-500">Cover Imej</p>
                                    <input type="file" accept="image/*" @change="editForm.image = $event.target.files[0]" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                </div>

                                <div class="rounded-lg border border-gray-100 bg-gray-50 p-2 space-y-1">
                                    <p class="text-[11px] font-semibold text-gray-500">Galeri Sedia Ada</p>
                                    <div v-if="existingMedia.length" class="flex flex-wrap gap-1">
                                        <div v-for="m in existingMedia" :key="m.id" class="group relative">
                                            <img :src="m.path" class="h-12 w-12 rounded-lg border border-gray-200 object-cover">
                                            <button @click.prevent="deleteExistingMedia(m.id)" class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[10px] text-white opacity-0 group-hover:opacity-100">×</button>
                                        </div>
                                    </div>
                                    <p v-else class="text-xs text-gray-400">Tiada</p>

                                    <p class="text-[11px] font-semibold text-gray-500">Tambah Galeri</p>
                                    <input type="file" accept="image/*" multiple @change="selectEditGallery" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                    <div v-if="editForm.gallery.length" class="flex flex-wrap gap-1">
                                        <span v-for="(_, i) in editForm.gallery" :key="i" class="inline-flex items-center gap-0.5 rounded-lg bg-gray-200 px-1.5 py-0.5 text-[10px]">
                                            Imej {{ i + 1 }}
                                            <button @click.prevent="removeEditGalleryFile(i)" class="text-red-600">×</button>
                                        </span>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2">
                                    <button type="submit" :disabled="editForm.processing" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.organization_name }}</p>
                            <h3 class="mt-1 text-base font-black text-gray-800">{{ item.name }}</h3>
                            <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ item.description || '—' }}</p>
                            <p class="mt-2 text-xs text-gray-500">Lokasi: <span class="font-semibold text-gray-700">{{ item.location || '—' }}</span></p>
                            <p class="text-xs text-gray-500">Jenis: <span class="font-semibold text-gray-700">{{ item.type }}</span></p>
                            <p class="text-xs text-gray-500">Harga: <span class="font-semibold text-gray-700">RM {{ Number(item.price_per_unit).toFixed(2) }}</span></p>
                            <p class="text-xs text-gray-500">Kapasiti: <span class="font-semibold text-gray-700">{{ item.capacity || '—' }}</span></p>
                            <span class="mt-2 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="item.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-600'">
                                {{ item.is_active ? 'Aktif' : 'Tidak aktif' }}
                            </span>
                            <div class="mt-3 flex items-center gap-2">
                                <button @click="startEdit(item)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Edit</button>
                                <button @click="removeItem(item)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Delete</button>
                            </div>
                        </template>
                    </article>
                </div>
            </section>

            <div>
                <Link :href="route('admin.facility-bookings.index')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">Lihat Tempahan Ruang →</Link>
            </div>
        </div>
    </AppLayout>
</template>

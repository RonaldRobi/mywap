<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

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
    is_active: true,
});

const editingId = ref(null);
const editForm = useForm({
    organization_id: props.defaultOrganizationId,
    name: '',
    description: '',
    location: '',
    type: 'hourly',
    price_per_unit: 0,
    capacity: null,
    image: null,
    is_active: true,
});

function submit() {
    form.post(route('admin.facilities.store'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('name', 'description', 'location', 'type', 'price_per_unit', 'capacity', 'image', 'is_active'),
    });
}

function startEdit(item) {
    editingId.value = item.id;
    editForm.organization_id = item.organization_id;
    editForm.name = item.name;
    editForm.description = item.description ?? '';
    editForm.location = item.location ?? '';
    editForm.type = item.type;
    editForm.price_per_unit = item.price_per_unit;
    editForm.capacity = item.capacity;
    editForm.image = null;
    editForm.is_active = !!item.is_active;
}

function cancelEdit() {
    editingId.value = null;
    editForm.reset();
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
</script>

<template>
    <Head title="Facility Management" />

    <AppLayout>
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
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Imej Ruang</label>
                        <input type="file" accept="image/*" @change="form.image = $event.target.files[0]" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
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
                        <img v-if="item.image_path" :src="item.image_path" :alt="item.name" class="mb-3 aspect-video w-full rounded-xl border border-gray-200 object-cover">

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
                                <input type="file" accept="image/*" @change="editForm.image = $event.target.files[0]" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs">
                                <div class="flex items-center gap-2">
                                    <button type="submit" :disabled="editForm.processing" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Update</button>
                                    <button type="button" @click="cancelEdit" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Cancel</button>
                                </div>
                            </form>
                        </template>

                        <template v-else>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ item.organization_name }}</p>
                            <h3 class="mt-1 text-base font-black text-gray-800">{{ item.name }}</h3>
                            <p class="mt-1 text-sm text-gray-600">{{ item.description || '—' }}</p>
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

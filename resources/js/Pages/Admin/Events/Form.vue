<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: { type: Object, default: null },
    organizations: Array,
    isSuperadmin: Boolean,
    statuses: Array,
    categories: Array,
});

const isEditing = !!props.event;

const form = useForm({
    title: props.event?.title ?? '',
    description: props.event?.description ?? '',
    type: props.event?.type ?? 'physical',
    status: props.event?.status ?? 'published',
    category: props.event?.category ?? 'muktamar',
    location_or_link: props.event?.location_or_link ?? '',
    start_time: props.event?.start_time ?? '',
    end_time: props.event?.end_time ?? '',
    organization_id: props.event?.organization_id ?? '',
    organization_ids: props.event?.organization_ids ?? [],
    featured_image: null,
});

function toggleOrg(id) {
    if (form.organization_ids.includes(id)) {
        form.organization_ids = form.organization_ids.filter(v => v !== id);
    } else {
        form.organization_ids.push(id);
    }
}

function submit() {
    // Backend jangkakan `organizations` (pivot organisasi terlibat).
    const payload = {
        ...form.data(),
        organizations: form.organization_ids,
    };

    if (isEditing) {
        router.put(route('admin.events.update', props.event.id), payload, {
            forceFormData: true,
            onSuccess: () => form.reset('featured_image'),
        });
    } else {
        router.post(route('admin.events.store'), payload, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
}
</script>

<template>
    <Head :title="isEditing ? `Edit Event: ${event.title}` : 'Cipta Event'" />

    <AppLayout>
        <div class="max-w-3xl mx-auto px-4 py-8">
            <h1 class="text-2xl font-black text-gray-900 mb-6">{{ isEditing ? 'Edit Event' : 'Cipta Event' }}</h1>

            <div v-if="$page.props.flash?.error" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 mb-4">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="Object.keys($page.props.errors).length" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700 mb-4">
                <p class="font-semibold mb-1">Terdapat ralat:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <div v-if="form.hasErrors" class="rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-700 mb-4">
                Sila semak borang — ada medan yang belum lengkap atau tidak sah.
            </div>

            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tajuk *</label>
                    <input v-model="form.title" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="cth. Muktamar & Ijtimak Nasional 2027" />
                    <p v-if="form.errors.title" class="text-xs text-red-500 mt-1">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Penerangan</label>
                    <textarea v-model="form.description" rows="4" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Penerangan program..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Kategori</label>
                        <select v-model="form.category" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                            <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Status</label>
                        <select v-model="form.status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Jenis</label>
                        <select v-model="form.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                            <option value="physical">Fizikal</option>
                            <option value="online">Dalam Talian</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Lokasi / Pautan</label>
                        <input v-model="form.location_or_link" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Alamat / URL" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Mula *</label>
                        <input v-model="form.start_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                        <p v-if="form.errors.start_time" class="text-xs text-red-500 mt-1">{{ form.errors.start_time }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Tamat *</label>
                        <input v-model="form.end_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                        <p v-if="form.errors.end_time" class="text-xs text-red-500 mt-1">{{ form.errors.end_time }}</p>
                    </div>
                </div>

                <!-- Owner org (superadmin only) -->
                <div v-if="isSuperadmin">
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Pemilik (Organisasi)</label>
                    <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Semua Organisasi</option>
                        <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </div>

                <!-- Organisasi terlibat -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Organisasi Terlibat</label>
                    <p class="text-xs text-gray-400 mb-2">Pilih organisasi yang terlibat dalam event ini (ahli mereka boleh melihat & mendaftar).</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <label
                            v-for="o in organizations"
                            :key="o.id"
                            class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm cursor-pointer transition"
                            :class="form.organization_ids.includes(o.id) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <input type="checkbox" :checked="form.organization_ids.includes(o.id)" @change="toggleOrg(o.id)" class="rounded border-gray-300 text-indigo-600" />
                            {{ o.name }}
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Featured Image</label>
                    <p class="text-xs text-gray-400 mb-2">Gambar ini jadi preview apabila event dikongsi di WhatsApp, Facebook atau Telegram.</p>
                    <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" @change="form.featured_image = $event.target.files?.[0] ?? null" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                    <div v-if="isEditing && event.featured_image_url" class="mt-2">
                        <img :src="event.featured_image_url" class="h-28 rounded-lg object-cover" alt="Featured semasa" />
                        <p class="text-xs text-gray-400 mt-1">Gambar semasa. Biarkan kosong untuk kekalkan.</p>
                    </div>
                </div>

                <div class="flex gap-3 pt-2">
                    <button @click="submit" :disabled="form.processing" class="flex-1 rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Kemas Kini Event' : 'Cipta Event') }}
                    </button>
                    <a :href="route('admin.events.index')" class="rounded-2xl border border-gray-200 px-6 py-3 text-sm font-semibold text-gray-600 hover:bg-gray-50">Batal</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

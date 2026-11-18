<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: { type: Object, default: null },
    organizations: Array,
    isSuperadmin: Boolean,
    isBranchAdmin: Boolean,
    branch: { type: Object, default: null },
    visibilities: { type: Array, default: () => [] },
    statuses: Array,
    categories: Array,
    formsCount: { type: Number, default: 0 },
});

// Cipta borang terus dari halaman ini. Selepas disimpan, admin dibawa ke
// halaman Urus program supaya boleh terus kongsi pautan/QR pendaftaran.
const createFormUrl = computed(() => {
    if (!props.event) return route('admin.forms.create');
    return route('admin.forms.create', {
        event_id: props.event.id,
        back_to: route('admin.events.show', props.event.id),
    });
});

const isEditing = !!props.event;

function datePart(value) {
    return value?.slice(0, 10) ?? '';
}

function timePart(value) {
    return value?.slice(11, 16) ?? '';
}

const existingStart = props.event?.start_time ?? '';
const existingEnd = props.event?.end_time ?? '';
const initialStartDate = datePart(existingStart);
const initialEndDate = datePart(existingEnd) || initialStartDate;
const uploadError = ref('');
const featuredPreview = ref(props.event?.featured_image_url ?? '');
const galleryPreviews = ref((props.event?.poster_urls ?? []).slice(1));

const form = useForm({
    title: props.event?.title ?? '',
    description: props.event?.description ?? '',
    type: props.event?.type ?? 'physical',
    status: props.event?.status ?? 'published',
    category: props.event?.category ?? 'muktamar',
    visibility: props.event?.visibility ?? (props.isBranchAdmin ? 'branch_only' : 'open'),
    location_or_link: props.event?.location_or_link ?? '',
    maps_url: props.event?.maps_url ?? '',
    start_date: initialStartDate,
    start_clock: timePart(existingStart),
    end_date: initialEndDate,
    end_clock: timePart(existingEnd),
    multi_day: initialStartDate !== initialEndDate,
    organization_id: props.event?.organization_id ?? '',
    organization_ids: props.event?.organization_ids ?? [],
    featured_image: null,
    gallery_images: [],
});

function startsWithHttp(value) {
    return typeof value === 'string' && /^https?:\/\//i.test(value.trim());
}

function toggleOrg(id) {
    if (form.organization_ids.includes(id)) {
        form.organization_ids = form.organization_ids.filter(v => v !== id);
    } else {
        form.organization_ids.push(id);
    }
}

const invalidTimes = computed(() => {
    if (!form.start_date || !form.start_clock || !form.end_date || !form.end_clock) return false;
    return new Date(`${resolvedEndDate.value}T${form.end_clock}`).getTime()
        <= new Date(`${form.start_date}T${form.start_clock}`).getTime();
});

const resolvedEndDate = computed(() => {
    if (
        !form.multi_day
        && form.end_clock === '00:00'
        && form.start_clock > '00:00'
        && form.start_date === form.end_date
    ) {
        const nextDate = new Date(`${form.start_date}T00:00:00`);
        nextDate.setDate(nextDate.getDate() + 1);
        const pad = (value) => String(value).padStart(2, '0');
        return `${nextDate.getFullYear()}-${pad(nextDate.getMonth() + 1)}-${pad(nextDate.getDate())}`;
    }

    return form.multi_day ? form.end_date : form.start_date;
});

watch(() => form.start_date, (value) => {
    if (!form.multi_day) form.end_date = value;
});

watch(() => form.multi_day, (value) => {
    if (!value) form.end_date = form.start_date;
});

function validateImage(file) {
    if (!file) return true;
    if (file.size > 2 * 1024 * 1024) {
        uploadError.value = `${file.name} melebihi had 2MB.`;
        return false;
    }
    return true;
}

function handleFeaturedChange(event) {
    const file = event.target.files?.[0] ?? null;
    uploadError.value = '';
    if (!validateImage(file)) {
        event.target.value = '';
        form.featured_image = null;
        return;
    }
    form.featured_image = file;
    featuredPreview.value = file ? URL.createObjectURL(file) : props.event?.featured_image_url ?? '';
}

function handleGalleryChange(event) {
    const files = Array.from(event.target.files ?? []);
    uploadError.value = '';
    if (files.some((file) => !validateImage(file))) {
        event.target.value = '';
        form.gallery_images = [];
        galleryPreviews.value = (props.event?.poster_urls ?? []).slice(1);
        return;
    }
    form.gallery_images = files;
    galleryPreviews.value = files.map((file) => URL.createObjectURL(file));
}

function submit() {
    if (invalidTimes.value) return;

    // Backend jangkakan `organizations` (pivot organisasi terlibat).
    form.transform((data) => {
        const payload = { ...data, organizations: data.organization_ids || [] };
        delete payload.organization_ids;
        // Jangan hantar featured_image kosong (elak ralat "failed to upload").
        payload.start_time = `${data.start_date}T${data.start_clock}`;
        payload.end_time = `${resolvedEndDate.value}T${data.end_clock}`;
        delete payload.start_date;
        delete payload.start_clock;
        delete payload.end_date;
        delete payload.end_clock;
        delete payload.multi_day;
        if (!payload.featured_image) {
            delete payload.featured_image;
        }
        if (!payload.gallery_images?.length) {
            delete payload.gallery_images;
        }
        // Multipart PUT tidak di-parse oleh PHP — guna POST + method spoofing.
        if (isEditing) {
            payload._method = 'PUT';
        }
        return payload;
    });

    const url = isEditing
        ? route('admin.events.update', props.event.id)
        : route('admin.events.store');

    form.post(url, {
        forceFormData: true,
        onSuccess: () => (isEditing ? form.reset('featured_image', 'gallery_images') : form.reset()),
    });
}
</script>

<template>
    <Head :title="isEditing ? `Edit Program & Acara: ${event.title}` : 'Cipta Program & Acara'" />

    <AppLayout>
        <div class="mx-auto w-full max-w-6xl px-4 py-8">
            <!-- Header + actions -->
            <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ isEditing ? 'Edit Program & Acara' : 'Cipta Program & Acara' }}</h1>
                    <p class="mt-0.5 text-sm text-gray-500">Isi butiran program, jadual, poster dan organisasi terlibat.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="route('admin.events.index')" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Batal</a>
                    <button
                        @click="submit"
                        :disabled="form.processing || invalidTimes"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>

            <div v-if="$page.props.flash?.error" class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="Object.keys($page.props.errors).length" class="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="mb-1 font-semibold">Terdapat ralat:</p>
                <ul class="list-inside list-disc space-y-0.5">
                    <li v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <div v-if="form.hasErrors" class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">
                Sila semak borang — ada medan yang belum lengkap atau tidak sah.
            </div>

            <!-- 2-column workspace -->
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,380px)]">
                <!-- LEFT: main details -->
                <div class="space-y-6">
                    <div class="space-y-5 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Maklumat Program</h2>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Tajuk *</label>
                            <input v-model="form.title" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-300 focus:ring-0" placeholder="cth. Muktamar & Ijtimak Nasional 2027" />
                            <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Penerangan</label>
                            <textarea v-model="form.description" rows="5" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-300 focus:ring-0" placeholder="Penerangan program..."></textarea>
                        </div>
                    </div>

                    <div class="space-y-5 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Jadual &amp; Lokasi</h2>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Kategori</label>
                                <select v-model="form.category" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                                    <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Status</label>
                                <select v-model="form.status" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Jenis</label>
                                <select v-model="form.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                                    <option value="physical">Fizikal</option>
                                    <option value="online">Dalam Talian</option>
                                </select>
                            </div>

                            <!-- Online: continue link -->
                            <div v-if="form.type === 'online'">
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Link Dalam Talian</label>
                                <input v-model="form.location_or_link" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-300 focus:ring-0" placeholder="https://meet.google.com/..." />
                                <p class="mt-1 text-[11px] text-gray-400">Pautan untuk peserta sertai program (Zoom, Google Meet, dll).</p>
                                <a
                                    v-if="startsWithHttp(form.location_or_link)"
                                    :href="form.location_or_link"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-700"
                                >
                                    Buka pautan ↗
                                </a>
                            </div>

                            <!-- Physical: venue name -->
                            <div v-else>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Nama Tempat</label>
                                <input v-model="form.location_or_link" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-300 focus:ring-0" placeholder="cth. Dewan Tun Hussein Onn, Kuala Lumpur" />
                            </div>
                        </div>

                        <!-- Physical: Google Maps link -->
                        <div v-if="form.type === 'physical'">
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Link Google Maps (Opsional)</label>
                            <input v-model="form.maps_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-300 focus:ring-0" placeholder="https://maps.app.goo.gl/..." />
                            <p class="mt-1 text-[11px] text-gray-400">Di Google Maps: klik <strong>Kongsi</strong> → <strong>Salin pautan</strong>. Pautan ini jadi butang “Arah ke Venue” untuk peserta.</p>
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <a
                                    v-if="form.location_or_link"
                                    :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(form.location_or_link)"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 hover:text-gray-700"
                                >
                                    Cari tempat ini di Google Maps ↗
                                </a>
                                <a
                                    v-if="startsWithHttp(form.maps_url)"
                                    :href="form.maps_url"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-600 hover:text-emerald-700"
                                >
                                    Buka Google Maps ↗
                                </a>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-indigo-100 bg-indigo-50/60 p-4 space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Tarikh program *</label>
                                <input v-model="form.start_date" type="date" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:ring-0" />
                                <p class="mt-1 text-xs text-gray-500">Pilih tarikh sebenar program berlangsung, bukan tarikh mencipta rekod.</p>
                                <p v-if="form.errors.start_time" class="mt-1 text-xs text-red-500">{{ form.errors.start_time }}</p>
                            </div>

                            <label class="flex min-h-[44px] items-center gap-3 text-sm font-semibold text-gray-700">
                                <input v-model="form.multi_day" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                Program lebih daripada sehari
                            </label>

                            <div v-if="form.multi_day">
                                <label class="mb-1 block text-sm font-semibold text-gray-700">Tarikh program tamat *</label>
                                <input v-model="form.end_date" type="date" :min="form.start_date" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:ring-0" />
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">Masa mula *</label>
                                    <input v-model="form.start_clock" type="time" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:ring-0" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-semibold text-gray-700">Masa tamat *</label>
                                    <input v-model="form.end_clock" type="time" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm focus:ring-0" />
                                </div>
                            </div>
                            <p v-if="!form.multi_day && form.end_clock === '00:00' && form.start_clock > '00:00'" class="text-xs text-indigo-700">
                                12:00 AM akan dikira sebagai tengah malam hari berikutnya.
                            </p>
                            <p v-if="form.errors.end_time" class="text-xs text-red-500">{{ form.errors.end_time }}</p>
                        </div>

                        <p v-if="invalidTimes" class="text-xs text-red-500">Tarikh dan masa tamat mesti selepas tarikh dan masa mula.</p>

                        <p class="rounded-xl border border-sky-100 bg-sky-50 px-3 py-2 text-xs text-sky-800">
                            Pendaftaran dibuka sebaik program diterbitkan dan ditutup <strong>24 jam selepas</strong> program tamat.
                            QR kehadiran pula hanya boleh diimbas <strong>24 jam sebelum</strong> program bermula hingga <strong>24 jam selepas</strong> tamat.
                        </p>
                    </div>
                </div>

                <!-- RIGHT: form CTA + organisations + poster -->
                <div class="space-y-6">
                    <div v-if="isEditing" class="space-y-3 rounded-3xl border border-indigo-100 bg-indigo-50/60 p-6 shadow-sm">
                        <div class="flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-bold uppercase tracking-wide text-indigo-700">Borang Pendaftaran</h2>
                                <p class="mt-1 text-sm text-gray-600">
                                    Peserta mendaftar melalui borang ini. Kalau program ini belum ada borang, klik butang di bawah untuk buat sekarang.
                                </p>
                                <p class="mt-1 text-xs font-semibold" :class="formsCount ? 'text-emerald-600' : 'text-amber-600'">
                                    {{ formsCount ? `${formsCount} borang sudah dicipta.` : 'Belum ada borang pendaftaran.' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a :href="createFormUrl" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-indigo-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                {{ formsCount ? 'Tambah Borang Baharu' : 'Cipta Borang Pendaftaran' }}
                            </a>
                            <a :href="route('admin.events.show', event.id)" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 hover:bg-indigo-50">
                                Urus Borang
                            </a>
                        </div>
                        <p class="text-[11px] text-gray-400">
                            Selepas borang dicipta, kongsi pautan atau QR pendaftaran kepada peserta.
                        </p>
                    </div>

                    <div class="space-y-4 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Organisasi &amp; Siapa Boleh Sertai</h2>

                        <div v-if="isBranchAdmin" class="rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-500">Cawangan penganjur</p>
                            <p class="mt-1 text-sm font-bold text-gray-900">{{ branch?.name || 'Cawangan anda' }}</p>
                        </div>

                        <div v-if="isSuperadmin">
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Pemilik (Organisasi)</label>
                            <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                                <option value="">Semua Organisasi</option>
                                <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                            </select>
                        </div>

                        <div v-if="!isBranchAdmin">
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Organisasi Terlibat</label>
                            <p class="mb-2 text-xs text-gray-400">Ahli organisasi yang dipilih boleh melihat &amp; mendaftar.</p>
                            <div class="grid grid-cols-1 gap-2">
                                <label
                                    v-for="o in organizations"
                                    :key="o.id"
                                    class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm transition"
                                    :class="form.organization_ids.includes(o.id) ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'"
                                >
                                    <input type="checkbox" :checked="form.organization_ids.includes(o.id)" @change="toggleOrg(o.id)" class="rounded border-gray-300 text-indigo-600" />
                                    {{ o.name }}
                                </label>
                            </div>
                        </div>

                        <div v-if="isBranchAdmin || event?.branch_id">
                            <label class="mb-1 block text-sm font-semibold text-gray-700">Siapa boleh lihat &amp; daftar</label>
                            <select v-model="form.visibility" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                                <option v-for="v in visibilities" :key="v.value" :value="v.value">{{ v.label }}</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400">
                                Pilih "Ahli cawangan sahaja" untuk menilai penyertaan ahli cawangan. Pilih "Terbuka kepada semua" jika program ingin dibuka kepada cawangan lain dan awam.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Poster Program</h2>
                        <p class="text-xs text-gray-400">Setiap fail maksimum 2MB. Cadangan saiz: 1080 × 1350 px (nisbah 4:5). Featured image digunakan sebagai poster utama program.</p>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Featured image</label>
                            <input type="file" accept="image/jpg,image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleFeaturedChange" />
                            <div v-if="featuredPreview" class="mx-auto aspect-[4/5] w-full max-w-[220px] overflow-hidden rounded-xl border border-gray-100 bg-gray-50">
                                <img :src="featuredPreview" class="h-full w-full object-contain" alt="Pratonton featured image" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Gallery poster</label>
                            <input type="file" multiple accept="image/jpg,image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" @change="handleGalleryChange" />
                            <p class="text-xs text-gray-400">Boleh pilih beberapa poster. Ia tidak mengubah featured image.</p>
                            <div v-if="galleryPreviews.length" class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                                <div v-for="(preview, index) in galleryPreviews" :key="preview" class="aspect-[4/5] overflow-hidden rounded-lg border border-gray-100 bg-gray-50">
                                    <img :src="preview" :alt="`Pratonton gallery ${index + 1}`" class="h-full w-full object-cover" />
                                </div>
                            </div>
                        </div>
                        <p v-if="uploadError" class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700">{{ uploadError }}</p>
                    </div>
                </div>
            </div>

            <!-- Bottom actions -->
            <div class="mt-6 flex items-center justify-between">
                <a :href="route('admin.events.index')" class="text-sm text-gray-500 hover:text-gray-700">← Kembali</a>
                <button
                    @click="submit"
                    :disabled="form.processing || invalidTimes"
                    class="rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                >
                    {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Kemas Kini Program' : 'Cipta Program') }}
                </button>
            </div>
        </div>
    </AppLayout>
</template>

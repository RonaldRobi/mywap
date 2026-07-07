<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, ref } from 'vue';

const props = defineProps({
    facility: {
        type: Object,
        required: true,
    },
    bookings: {
        type: Array,
        default: () => [],
    },
    myBookings: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    start_datetime: '',
    end_datetime: '',
});

const estimatedPrice = computed(() => {
    if (!form.start_datetime || !form.end_datetime) return null;
    const start = new Date(form.start_datetime);
    const end = new Date(form.end_datetime);
    if (end <= start) return null;
    const diffMinutes = Math.ceil((end.getTime() - start.getTime()) / 60000);
    if (diffMinutes <= 0) return null;
    const price = Number(props.facility.price_per_unit);
    if (props.facility.type === 'daily') {
        const units = Math.ceil(diffMinutes / 1440);
        return units * price;
    }
    const units = Math.ceil(diffMinutes / 60);
    return units * price;
});

const lightboxImage = ref(null);

function openLightbox(src) {
    lightboxImage.value = src;
}

function closeLightbox() {
    lightboxImage.value = null;
}

const allGalleryImages = computed(() => {
    const images = [];
    (props.facility.media || []).forEach(m => images.push({ path: m.path, caption: m.caption }));
    return images;
});

function submitBooking() {
    form.post(route('member.facilities.book', props.facility.id), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="`Tempahan - ${facility.name}`" />

    <AppLayout>
        <template #header>Tempahan Ruang</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <nav class="flex items-center gap-2 text-sm">
                <Link :href="route('member.facilities.index')" class="font-semibold text-gray-400 hover:text-gray-600 transition-colors">Senarai Ruang</Link>
                <svg class="h-3.5 w-3.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="font-semibold text-gray-900">{{ facility.name }}</span>
            </nav>

            <div class="relative overflow-hidden rounded-2xl bg-gray-900">
                <img
                    v-if="facility.image_path"
                    :src="facility.image_path"
                    :alt="facility.name"
                    class="absolute inset-0 h-full w-full object-cover opacity-50"
                >
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-gray-900/30 to-gray-900/10" />
                <div class="relative z-10 p-6 md:p-8">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">
                            {{ facility.organization_name }}
                        </span>
                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide backdrop-blur-sm"
                              :class="facility.type === 'daily' ? 'bg-indigo-400/30 text-indigo-100' : 'bg-blue-400/30 text-blue-100'">
                            {{ facility.type === 'daily' ? 'Daily' : 'Hourly' }}
                        </span>
                    </div>
                    <h1 class="text-2xl font-black text-white md:text-3xl">{{ facility.name }}</h1>
                    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-gray-300">{{ facility.description || 'Tiada deskripsi.' }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="lg:col-span-2 space-y-6">
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-gray-900">Maklumat Ruang</h2>
                        <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm md:grid-cols-4">
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Lokasi</p>
                                    <p class="font-semibold text-gray-800">{{ facility.location || '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Kapasiti</p>
                                    <p class="font-semibold text-gray-800">{{ facility.capacity ? `${facility.capacity} orang` : '—' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Harga</p>
                                    <p class="font-semibold text-gray-800">RM {{ Number(facility.price_per_unit).toFixed(2) }}</p>
                                    <p class="text-[11px] text-gray-400">per {{ facility.type === 'daily' ? 'hari' : 'jam' }}</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-2">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Jenis</p>
                                    <p class="font-semibold text-gray-800 capitalize">{{ facility.type }}</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section v-if="allGalleryImages.length" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-gray-900">Galeri</h2>
                        <div class="mt-4 grid grid-cols-2 gap-2 md:grid-cols-3">
                            <button
                                v-for="(img, i) in allGalleryImages"
                                :key="i"
                                @click="openLightbox(img.path)"
                                class="group relative aspect-video overflow-hidden rounded-xl border border-gray-100 bg-gray-50"
                            >
                                <img :src="img.path" :alt="img.caption || `${facility.name} - ${i + 1}`" class="h-full w-full object-cover transition-transform group-hover:scale-105">
                                <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10" />
                                <div v-if="img.caption" class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent px-3 py-2">
                                    <p class="text-xs text-white">{{ img.caption }}</p>
                                </div>
                            </button>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-gray-900">Slot Sedia Ada</h2>
                        <p class="mt-1 text-xs text-gray-500">Paparan slot umum (pending/approved) untuk elak pertindihan masa tempahan.</p>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                        <th class="px-3 py-2">Mula</th>
                                        <th class="px-3 py-2">Tamat</th>
                                        <th class="px-3 py-2">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="booking in bookings" :key="booking.id" class="border-b border-gray-50">
                                        <td class="px-3 py-2.5 text-gray-600">{{ booking.start_datetime }}</td>
                                        <td class="px-3 py-2.5 text-gray-600">{{ booking.end_datetime }}</td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                                :class="booking.booking_status === 'approved'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : 'bg-amber-100 text-amber-700'"
                                            >
                                                {{ booking.booking_status }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="bookings.length === 0">
                                        <td colspan="3" class="px-3 py-6 text-center text-sm text-gray-400">Tiada tempahan lagi.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-gray-900">Tempahan Saya Untuk Ruang Ini</h2>
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead>
                                    <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                        <th class="px-3 py-2">Mula</th>
                                        <th class="px-3 py-2">Tamat</th>
                                        <th class="px-3 py-2">Harga</th>
                                        <th class="px-3 py-2">Status</th>
                                        <th class="px-3 py-2">Catatan Admin</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="booking in myBookings" :key="booking.id" class="border-b border-gray-50">
                                        <td class="px-3 py-2.5 text-gray-600">{{ booking.start_datetime || '-' }}</td>
                                        <td class="px-3 py-2.5 text-gray-600">{{ booking.end_datetime || '-' }}</td>
                                        <td class="px-3 py-2.5 font-semibold text-gray-700">RM {{ Number(booking.total_price).toFixed(2) }}</td>
                                        <td class="px-3 py-2.5">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                                :class="booking.booking_status === 'approved'
                                                    ? 'bg-emerald-100 text-emerald-700'
                                                    : booking.booking_status === 'rejected'
                                                        ? 'bg-red-100 text-red-700'
                                                        : 'bg-amber-100 text-amber-700'"
                                            >
                                                {{ booking.booking_status }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 text-xs text-gray-500">{{ booking.admin_remarks || '—' }}</td>
                                    </tr>
                                    <tr v-if="myBookings.length === 0">
                                        <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-400">Belum ada tempahan untuk ruang ini.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>

                <aside class="space-y-6">
                    <section class="sticky top-24 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="text-lg font-black text-gray-900">Buat Tempahan</h2>
                        <form class="mt-4 space-y-4" @submit.prevent="submitBooking">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500">Masa Mula</label>
                                <input v-model="form.start_datetime" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-gray-500 focus:ring-0" required>
                                <p v-if="form.errors.start_datetime" class="mt-1 text-xs text-red-600">{{ form.errors.start_datetime }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-500">Masa Tamat</label>
                                <input v-model="form.end_datetime" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm focus:border-gray-500 focus:ring-0" required>
                                <p v-if="form.errors.end_datetime" class="mt-1 text-xs text-red-600">{{ form.errors.end_datetime }}</p>
                            </div>

                            <div v-if="estimatedPrice !== null" class="flex items-center justify-between rounded-xl bg-gray-50 px-4 py-3">
                                <span class="text-xs font-semibold text-gray-500">Anggaran Harga</span>
                                <span class="text-base font-black text-gray-900">RM {{ estimatedPrice.toFixed(2) }}</span>
                            </div>

                            <button type="submit" :disabled="form.processing" class="w-full rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                                {{ form.processing ? 'Menghantar...' : 'Hantar Tempahan' }}
                            </button>
                        </form>
                    </section>
                </aside>
            </div>

            <div class="pb-4">
                <Link :href="route('member.facilities.index')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-400 hover:text-gray-600 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Kembali ke Senarai Ruang
                </Link>
            </div>
        </div>
    </AppLayout>

    <Teleport to="body">
        <div v-if="lightboxImage" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm" @click="closeLightbox">
            <button @click="closeLightbox" class="absolute right-4 top-4 rounded-full bg-white/10 p-2 text-white hover:bg-white/20">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
            <img :src="lightboxImage" class="max-h-[85vh] max-w-[90vw] rounded-2xl object-contain shadow-2xl" @click.stop>
        </div>
    </Teleport>
</template>

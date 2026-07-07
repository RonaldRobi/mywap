<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { nextTick, onMounted, ref, computed } from 'vue';

const props = defineProps({
    facilities: {
        type: Array,
        default: () => [],
    },
    myBookings: {
        type: Array,
        default: () => [],
    },
    historyFilters: {
        type: Object,
        default: () => ({ status: '' }),
    },
    jumpToHistory: {
        type: Boolean,
        default: false,
    },
});

const historyStatus = ref(props.historyFilters?.status ?? '');

function applyHistoryFilter() {
    router.get(route('member.facilities.index'), {
        view: 'history',
        history_status: historyStatus.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function cardImages(facility) {
    const images = [];
    if (facility.image_path) images.push({ path: facility.image_path, caption: null });
    (facility.media || []).forEach(m => images.push(m));
    return images;
}

onMounted(async () => {
    if (!props.jumpToHistory) return;
    await nextTick();
    const target = document.getElementById('booking-history');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>

<template>
    <Head title="Tempahan Ruang" />

    <AppLayout>
        <template #header>Tempahan Ruang</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <div class="mb-6">
                <h1 class="text-2xl font-black text-gray-900">Senarai Ruang</h1>
                <p class="mt-1 text-sm text-gray-500">Semua ahli boleh lihat ruang aktif merentas organisasi dan membuat tempahan.</p>
            </div>

            <div v-if="facilities.length === 0" class="rounded-2xl border border-gray-100 bg-white p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <p class="mt-3 text-sm font-medium text-gray-500">Tiada ruang aktif tersedia buat masa ini.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="facility in facilities"
                    :key="facility.id"
                    class="group flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-shadow hover:shadow-md"
                >
                    <div v-if="cardImages(facility).length" class="relative overflow-hidden bg-gray-50">
                        <div class="grid gap-px bg-gray-100" :class="cardImages(facility).length === 1 ? 'grid-cols-1' : 'grid-cols-2'">
                            <img
                                v-for="(img, i) in cardImages(facility).slice(0, 4)"
                                :key="i"
                                :src="img.path"
                                :alt="`${facility.name} - ${i + 1}`"
                                class="object-cover"
                                :class="i === 0 && cardImages(facility).length === 3 ? 'row-span-2 h-full' : cardImages(facility).length === 1 ? 'aspect-video w-full' : 'aspect-square w-full'"
                            >
                        </div>
                        <span v-if="cardImages(facility).length > 4" class="absolute bottom-2 right-2 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-semibold text-white">
                            +{{ cardImages(facility).length - 4 }}
                        </span>
                    </div>

                    <div class="flex flex-1 flex-col p-5">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500">
                                {{ facility.organization_name }}
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide"
                                  :class="facility.type === 'daily' ? 'bg-indigo-50 text-indigo-600' : 'bg-blue-50 text-blue-600'">
                                {{ facility.type === 'daily' ? 'Daily' : 'Hourly' }}
                            </span>
                        </div>

                        <h2 class="mt-2 text-lg font-black text-gray-900 group-hover:text-gray-700 transition-colors">{{ facility.name }}</h2>
                        <p class="mt-1.5 line-clamp-2 text-sm leading-relaxed text-gray-500">{{ facility.description || 'Tiada deskripsi.' }}</p>

                        <div class="mt-4 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span class="truncate">{{ facility.location || '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                <span>{{ facility.capacity ? `${facility.capacity} org` : '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="font-semibold text-gray-700">RM {{ Number(facility.price_per_unit).toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span>per {{ facility.type === 'daily' ? 'hari' : 'jam' }}</span>
                            </div>
                        </div>

                        <div class="mt-auto pt-4">
                            <Link
                                :href="route('member.facilities.show', facility.id)"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-gray-800"
                            >
                                Lihat & Tempah
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </Link>
                        </div>
                    </div>
                </article>
            </div>

            <section id="booking-history" class="mt-10 rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h2 class="text-lg font-black text-gray-900">Sejarah Tempahan Saya</h2>
                <p class="mt-1 text-xs text-gray-500">20 rekod terkini tempahan anda.</p>

                <div class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</label>
                        <select v-model="historyStatus" class="w-44 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <option value="">Semua</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    <button @click="applyHistoryFilter" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Tapis
                    </button>
                </div>

                <div class="mt-5 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-xs uppercase text-gray-500">
                                <th class="px-3 py-2">Ruang</th>
                                <th class="px-3 py-2">Organisasi</th>
                                <th class="px-3 py-2">Mula</th>
                                <th class="px-3 py-2">Tamat</th>
                                <th class="px-3 py-2">Harga</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2">Catatan Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="booking in myBookings" :key="booking.id" class="border-b border-gray-50">
                                <td class="px-3 py-2.5 font-semibold text-gray-700">
                                    <Link :href="route('member.facilities.show', booking.facility_id)" class="hover:text-gray-900 hover:underline">
                                        {{ booking.facility_name }}
                                    </Link>
                                </td>
                                <td class="px-3 py-2.5 text-gray-600">{{ booking.organization_name || '-' }}</td>
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
                                <td colspan="7" class="px-3 py-6 text-center text-sm text-gray-400">Belum ada sejarah tempahan.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

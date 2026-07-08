<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    event: { type: Object, required: true },
    comments: { type: Array, default: () => [] },
    relatedEvents: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperadmin = computed(() => {
    const roles = user.value?.roles ?? [];
    return roles.includes('Superadmin') || roles.includes('Admin');
});

const typeConfig = {
    physical: { label: 'Fizikal', classes: 'bg-emerald-100 text-emerald-700' },
    online: { label: 'Dalam Talian', classes: 'bg-sky-100 text-sky-700' },
};

const rsvpConfig = {
    going: { label: 'Hadir', classes: 'bg-emerald-100 text-emerald-700' },
    maybe: { label: 'Mungkin', classes: 'bg-amber-100 text-amber-700' },
    declined: { label: 'Tidak', classes: 'bg-red-100 text-red-600' },
    attended: { label: 'Hadir ✓', classes: 'bg-emerald-100 text-emerald-700' },
};

const isPast = computed(() => new Date(props.event.start_time) < new Date());

// ─── Countdown ────────────────────────────────────────────────────────────

const countdown = ref('');
let countdownTimer = null;

function updateCountdown() {
    if (isPast.value) {
        countdown.value = 'Telah bermula';
        return;
    }
    const now = new Date();
    const start = new Date(props.event.start_time);
    const diff = start - now;
    if (diff <= 0) { countdown.value = 'Sedang berlangsung'; return; }

    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const mins = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));

    if (days > 0) countdown.value = `${days} hari lagi`;
    else if (hours > 0) countdown.value = `${hours} jam ${mins} minit lagi`;
    else countdown.value = `${mins} minit lagi`;
}

updateCountdown();
countdownTimer = setInterval(updateCountdown, 60000);

// ─── Image Lightbox ───────────────────────────────────────────────────────

const lightboxOpen = ref(false);

function openLightbox() { lightboxOpen.value = true; }
function closeLightbox() { lightboxOpen.value = false; }

// ─── Google Maps ──────────────────────────────────────────────────────────

const mapsUrl = computed(() => {
    if (props.event.type !== 'physical' || !props.event.location_or_link) return null;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(props.event.location_or_link)}`;
});

// ─── RSVP ─────────────────────────────────────────────────────────────────

const submitting = ref(false);

function submitRsvp(status) {
    if (submitting.value) return;
    submitting.value = true;
    router.post(
        route('events.rsvp', { event: props.event.id }),
        { status },
        {
            preserveScroll: true,
            onSuccess: () => { submitting.value = false; },
            onError: () => { submitting.value = false; },
        }
    );
}

// ─── Comments ─────────────────────────────────────────────────────────────

const commentForm = useForm({
    content: '',
    anonymous_name: '',
});

function submitComment() {
    commentForm.post(route('events.comments.store', props.event.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset('content'),
    });
}

// ─── Edit Modal ───────────────────────────────────────────────────────────

// Convert ISO string to datetime-local value (YYYY-MM-DDTHH:mm)
function toDatetimeLocal(iso) {
    if (!iso) return '';
    return iso.slice(0, 16);
}

const editModalOpen = ref(false);
const editForm = useForm({
    organization_id: props.event.organization?.id ?? '',
    title: props.event.title,
    description: props.event.description ?? '',
    type: props.event.type,
    location_or_link: props.event.location_or_link ?? '',
    start_time: toDatetimeLocal(props.event.start_time),
    end_time: toDatetimeLocal(props.event.end_time),
    featured_image: null,
    _method: 'PUT',
});

function openEditModal() {
    // Re-sync fields with latest event data each time the modal opens
    editForm.organization_id = props.event.organization?.id ?? '';
    editForm.title = props.event.title;
    editForm.description = props.event.description ?? '';
    editForm.type = props.event.type;
    editForm.location_or_link = props.event.location_or_link ?? '';
    editForm.start_time = toDatetimeLocal(props.event.start_time);
    editForm.end_time = toDatetimeLocal(props.event.end_time);
    editForm.featured_image = null;
    editForm.clearErrors();
    editModalOpen.value = true;
}

function closeEditModal() {
    editModalOpen.value = false;
}

function submitEdit() {
    editForm.post(route('events.update', { event: props.event.id }), {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
}

// ─── Share URL ────────────────────────────────────────────────────────────

function eventShareUrl() {
    return route('share.event', props.event?.id, true);
}
</script>

<template>
    <Head :title="event.title" />

    <AppLayout>
        <template #header>Program &amp; Acara</template>

        <div class="max-w-5xl mx-auto px-4 md:px-6 py-6 md:py-10 space-y-8">

            <!-- ─── Hero / Poster ───────────────────────────────────────────── -->
            <div
                class="relative bg-gray-900 rounded-3xl overflow-hidden shadow-2xl cursor-pointer"
                @click="openLightbox"
            >
                <div class="flex items-center justify-center w-full min-h-[300px] max-h-[75vh] bg-gray-900">
                    <img
                        :src="event.featured_image_url"
                        :alt="event.title"
                        class="w-full h-full max-h-[75vh] object-contain"
                        loading="eager"
                    />
                </div>
                <div class="absolute inset-0 bg-gradient-to-t from-gray-900/80 via-transparent to-transparent pointer-events-none"></div>

                <!-- Org Badge -->
                <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-md px-3 py-1.5 rounded-xl border border-white/20 shadow-sm flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: event.organization.color_theme }"></span>
                    <span class="text-[11px] font-black uppercase tracking-wider text-gray-900">{{ event.organization.name }}</span>
                </div>

                <!-- Type Badge -->
                <div class="absolute bottom-4 left-4">
                    <span
                        class="inline-flex text-[11px] font-bold px-3 py-1.5 rounded-full shadow-sm"
                        :class="typeConfig[event.type]?.classes"
                    >
                        {{ typeConfig[event.type]?.label }}
                    </span>
                </div>

                <!-- Expand hint -->
                <div class="absolute top-4 right-4 bg-black/40 text-white/70 rounded-full p-1.5 backdrop-blur-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                    </svg>
                </div>
            </div>

            <!-- ─── Title ────────────────────────────────────────────────────── -->
            <div>
                <h1 class="text-3xl md:text-4xl font-black text-gray-900 leading-tight">{{ event.title }}</h1>
            </div>

            <!-- ─── Info Bar ─────────────────────────────────────────────────── -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-3">
                <!-- Date -->
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide text-[11px]">Masa</p>
                        <p class="text-sm font-bold text-gray-900">{{ event.start_formatted }}</p>
                        <p v-if="event.end_time" class="text-xs font-medium text-gray-500">
                            ⏱️ Tamat: {{ new Date(event.end_time).toLocaleString('ms-MY', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: 'numeric', hour12: true }) }}
                        </p>
                    </div>
                </div>

                <!-- Countdown (upcoming only) -->
                <div v-if="!isPast" class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide text-[11px]">Countdown</p>
                        <p class="text-sm font-bold text-amber-600">{{ countdown }}</p>
                    </div>
                </div>

                <!-- Location / Link -->
                <div v-if="event.location_or_link" class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <svg v-if="event.type === 'physical'" xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide text-[11px]">
                            {{ event.type === 'physical' ? 'Lokasi' : 'Pautan' }}
                        </p>
                        <p class="text-sm font-medium text-gray-700 break-words">{{ event.location_or_link }}</p>
                        <!-- Maps button (physical) -->
                        <a
                            v-if="mapsUrl"
                            :href="mapsUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 mt-2 rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            Buka di Google Maps
                        </a>
                        <!-- Open link button (online) -->
                        <a
                            v-else
                            :href="event.location_or_link.startsWith('http') ? event.location_or_link : 'https://' + event.location_or_link"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1.5 mt-2 rounded-xl bg-sky-50 border border-sky-200 px-3.5 py-2 text-xs font-bold text-sky-700 hover:bg-sky-100 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Buka Pautan
                        </a>
                    </div>
                </div>

                <!-- RSVP count -->
                <div class="flex items-start gap-3">
                    <div class="shrink-0 w-9 h-9 rounded-xl bg-gray-50 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-500 uppercase tracking-wide text-[11px]">Kehadiran</p>
                        <p class="text-sm font-bold text-gray-900">{{ event.rsvp_count }} ahli akan hadir</p>
                    </div>
                </div>
            </div>

            <!-- ─── Description ──────────────────────────────────────────────── -->
            <div v-if="event.description" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-black text-gray-900 mb-4">Penerangan Program</h2>
                <div class="prose prose-gray max-w-none text-gray-700 whitespace-pre-line leading-relaxed">{{ event.description }}</div>
            </div>

            <!-- ─── RSVP ─────────────────────────────────────────────────────── -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-black text-gray-900 mb-4">Konfirmasi Kehadiran</h2>

                <!-- Current RSVP status -->
                <div v-if="event.my_rsvp" class="mb-4 text-sm text-gray-500">
                    Status semasa:
                    <span :class="['ml-1 font-semibold px-2.5 py-1 rounded-full text-xs', rsvpConfig[event.my_rsvp]?.classes]">
                        {{ rsvpConfig[event.my_rsvp]?.label }}
                    </span>
                </div>

                <div v-if="!isSuperadmin" class="grid grid-cols-3 gap-2 max-w-md">
                    <button
                        @click="submitRsvp('going')"
                        :disabled="submitting"
                        :class="[
                            'flex flex-col items-center gap-1.5 p-4 rounded-2xl border-2 text-xs font-semibold transition-all',
                            event.my_rsvp === 'going'
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700 shadow-sm'
                                : 'border-gray-100 hover:border-emerald-300 hover:bg-emerald-50 text-gray-600'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Hadir
                    </button>
                    <button
                        @click="submitRsvp('maybe')"
                        :disabled="submitting"
                        :class="[
                            'flex flex-col items-center gap-1.5 p-4 rounded-2xl border-2 text-xs font-semibold transition-all',
                            event.my_rsvp === 'maybe'
                                ? 'border-amber-400 bg-amber-50 text-amber-700 shadow-sm'
                                : 'border-gray-100 hover:border-amber-300 hover:bg-amber-50 text-gray-600'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Mungkin
                    </button>
                    <button
                        @click="submitRsvp('declined')"
                        :disabled="submitting"
                        :class="[
                            'flex flex-col items-center gap-1.5 p-4 rounded-2xl border-2 text-xs font-semibold transition-all',
                            event.my_rsvp === 'declined'
                                ? 'border-red-400 bg-red-50 text-red-700 shadow-sm'
                                : 'border-gray-100 hover:border-red-300 hover:bg-red-50 text-gray-600'
                        ]"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Tidak
                    </button>
                </div>

                <div v-else class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-700">
                    Akaun pentadbir menggunakan mod pengurusan kehadiran (QR + senarai peserta hadir) di bawah.
                </div>
            </div>

            <!-- ─── Quick Actions ────────────────────────────────────────────── -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                <h2 class="text-lg font-black text-gray-900">Tindakan</h2>
                <div class="flex flex-wrap gap-3">
                    <!-- Google Calendar -->
                    <a
                        :href="event.google_calendar_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                        </svg>
                        Tambah ke Google Calendar
                    </a>

                    <!-- Maps (physical) -->
                    <a
                        v-if="mapsUrl"
                        :href="mapsUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Buka di Google Maps
                    </a>
                </div>

                <!-- Share -->
                <div class="pt-2">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">Kongsi Program</p>
                    <SocialShareButtons
                        :title="event.title"
                        :text="event.organization?.name || 'Program komuniti'"
                        :url="eventShareUrl()"
                    />
                </div>
            </div>

            <!-- ─── Admin Section ────────────────────────────────────────────── -->
            <div v-if="isSuperadmin" class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6 space-y-4">
                <h2 class="text-lg font-black text-gray-900">Pengurusan Program</h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <!-- Edit button -->
                    <button
                        @click="openEditModal"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-700 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit Program
                    </button>
                    <a
                        :href="route('events.qr', { event: event.id })"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/>
                        </svg>
                        Papar QR Kehadiran
                    </a>
                    <a
                        :href="route('events.print', { event: event.id })"
                        target="_blank"
                        class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                        </svg>
                        Cetak Senarai Kehadiran
                    </a>
                </div>

                <div class="bg-white border border-gray-100 rounded-xl overflow-hidden">
                    <div class="font-bold text-sm text-gray-700 p-3 border-b border-gray-100">Senarai Kehadiran</div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead>
                                <tr class="text-gray-400 border-b border-gray-50">
                                    <th class="px-3 py-2 text-left font-semibold">#</th>
                                    <th class="px-3 py-2 text-left font-semibold">Nama</th>
                                    <th class="px-3 py-2 text-left font-semibold">E-mel</th>
                                    <th class="px-3 py-2 text-left font-semibold">Telefon</th>
                                    <th class="px-3 py-2 text-left font-semibold">Masa Hadir</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(att, i) in event.attendance || []" :key="i" class="border-b border-gray-50 hover:bg-gray-50">
                                    <td class="px-3 py-2">{{ i + 1 }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-700">{{ att.name }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ att.email }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ att.phone || '—' }}</td>
                                    <td class="px-3 py-2 text-gray-500">{{ att.attended_at || '—' }}</td>
                                </tr>
                                <tr v-if="!event.attendance || event.attendance.length === 0">
                                    <td colspan="5" class="px-3 py-8 text-center text-gray-400">Tiada kehadiran direkodkan.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ─── Comments ─────────────────────────────────────────────────── -->
            <section class="bg-white rounded-3xl border border-gray-100 shadow-sm p-5 md:p-6">
                <h2 class="text-lg font-black text-gray-900 mb-4">Komen</h2>

                <form class="space-y-3 mb-6 bg-gray-50 p-4 rounded-2xl border border-gray-100" @submit.prevent="submitComment">
                    <p class="text-xs font-medium text-gray-500">
                        Tinggalkan komen anda
                        <template v-if="user"> sebagai <span class="font-bold text-gray-700">{{ user.name }}</span></template>
                        <template v-else>(boleh sebagai anonim)</template>:
                    </p>

                    <div v-if="!user">
                        <input
                            v-model="commentForm.anonymous_name"
                            type="text"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0"
                            placeholder="Nama anda (Pilihan)"
                        />
                    </div>

                    <div>
                        <textarea
                            v-model="commentForm.content"
                            rows="3"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0"
                            placeholder="Tulis komen anda di sini..."
                            required
                        ></textarea>
                        <p v-if="commentForm.errors.content" class="text-xs text-red-600 mt-1">{{ commentForm.errors.content }}</p>
                    </div>

                    <div class="flex justify-end">
                        <button
                            type="submit"
                            :disabled="commentForm.processing"
                            class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                        >
                            {{ commentForm.processing ? 'Menghantar...' : 'Hantar Komen' }}
                        </button>
                    </div>
                </form>

                <div class="space-y-3">
                    <article v-for="comment in comments" :key="comment.id" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between mb-1.5">
                            <p class="text-sm font-bold text-gray-800">{{ comment.user_name }}</p>
                            <p class="text-[11px] font-medium text-gray-400">{{ comment.created_at }}</p>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed">{{ comment.content }}</p>
                    </article>

                    <p v-if="!comments.length" class="text-sm text-gray-400 text-center py-6 border-2 border-dashed border-gray-100 rounded-2xl">
                        Belum ada komen. Jadilah yang pertama!
                    </p>
                </div>
            </section>

            <!-- ─── Related Events ───────────────────────────────────────────── -->
            <section v-if="relatedEvents.length" class="space-y-4">
                <h2 class="text-xl font-black text-gray-900">Program Lain</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <Link
                        v-for="rel in relatedEvents"
                        :key="rel.id"
                        :href="route('events.show', rel.slug)"
                        class="group bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden hover:-translate-y-0.5 hover:shadow-md transition-all"
                    >
                        <div class="aspect-[16/9] bg-gray-50 overflow-hidden">
                            <img
                                :src="rel.featured_image_url"
                                :alt="rel.title"
                                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                loading="lazy"
                            />
                        </div>
                        <div class="p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">
                                {{ rel.organization.name }}
                            </p>
                            <p class="text-sm font-bold text-gray-800 line-clamp-2 leading-snug">{{ rel.title }}</p>
                            <p class="text-[11px] font-medium text-gray-500 mt-1.5">
                                {{ rel.start_formatted }}
                            </p>
                        </div>
                    </Link>
                </div>
            </section>

            <!-- ─── Back Link ────────────────────────────────────────────────── -->
            <div class="pt-2 pb-8">
                <Link :href="route('events.index')" class="inline-flex items-center gap-1.5 text-sm font-semibold text-gray-500 hover:text-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Kembali ke Program & Acara
                </Link>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!--  IMAGE LIGHTBOX MODAL                                              -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="lightboxOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/90 backdrop-blur-sm p-4"
                    @click.self="closeLightbox"
                >
                    <button
                        @click="closeLightbox"
                        class="absolute top-4 right-4 flex h-10 w-10 items-center justify-center rounded-full bg-black/40 text-white hover:bg-black/60 transition-colors"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    <img
                        :src="event.featured_image_url"
                        :alt="event.title"
                        class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl"
                        @click.self="closeLightbox"
                    />
                </div>
            </Transition>
        </Teleport>
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <!--  EDIT PROGRAM MODAL                                                -->
        <!-- ════════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="editModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm"
                    @click.self="closeEditModal"
                >
                    <div class="w-full max-w-2xl rounded-3xl border border-white/50 bg-white/95 shadow-2xl overflow-hidden max-h-[90vh] flex flex-col">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4 shrink-0">
                            <h3 class="text-base font-black text-gray-800">Edit Program</h3>
                            <button @click="closeEditModal" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form class="space-y-4 p-5 overflow-y-auto" @submit.prevent="submitEdit">
                            <!-- Organization (Superadmin only) -->
                            <div v-if="organizations.length" class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Organisasi</label>
                                <select v-model="editForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                    <option value="">Semua Organisasi</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <p v-if="editForm.errors.organization_id" class="text-xs text-red-500">{{ editForm.errors.organization_id }}</p>
                            </div>

                            <!-- Title -->
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Tajuk *</label>
                                <input v-model="editForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" required>
                                <p v-if="editForm.errors.title" class="text-xs text-red-500">{{ editForm.errors.title }}</p>
                            </div>

                            <!-- Type + Location -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Jenis *</label>
                                    <select v-model="editForm.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                        <option value="physical">Fizikal</option>
                                        <option value="online">Dalam Talian</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Lokasi / Pautan</label>
                                    <input v-model="editForm.location_or_link" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                                    <p v-if="editForm.errors.location_or_link" class="text-xs text-red-500">{{ editForm.errors.location_or_link }}</p>
                                </div>
                            </div>

                            <!-- Start + End Time -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Masa Mula *</label>
                                    <input v-model="editForm.start_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" required>
                                    <p v-if="editForm.errors.start_time" class="text-xs text-red-500">{{ editForm.errors.start_time }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Masa Tamat *</label>
                                    <input v-model="editForm.end_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0" required>
                                    <p v-if="editForm.errors.end_time" class="text-xs text-red-500">{{ editForm.errors.end_time }}</p>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Penerangan</label>
                                <textarea v-model="editForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0"></textarea>
                                <p v-if="editForm.errors.description" class="text-xs text-red-500">{{ editForm.errors.description }}</p>
                            </div>

                            <!-- Featured Image -->
                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Gambar Utama (biarkan kosong untuk kekalkan)</label>
                                <input
                                    type="file"
                                    accept="image/jpg,image/jpeg,image/png,image/webp"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                    @change="editForm.featured_image = $event.target.files?.[0] ?? null"
                                >
                                <p v-if="editForm.errors.featured_image" class="text-xs text-red-500">{{ editForm.errors.featured_image }}</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="closeEditModal" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                    Batal
                                </button>
                                <button type="submit" :disabled="editForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-700 transition-colors disabled:opacity-60 disabled:cursor-not-allowed">
                                    {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

    </AppLayout>
</template>

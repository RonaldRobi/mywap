<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

// ─── Props ──────────────────────────────────────────────────────────────────

const props = defineProps({
    events: Object, // Laravel paginator object
    tab: {
        type: String,
        default: 'upcoming',
    },
    filters: {
        type: Object,
        default: () => ({ search: '', type: '' }),
    },
    organizations: {
        type: Array,
        default: () => [],
    },
    attendedEvents: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperadmin = computed(() => {
    const roles = user.value?.roles ?? [];
    return roles.includes('Superadmin') || roles.includes('Admin');
});

// ─── RSVP Modal state ────────────────────────────────────────────────────────

const selectedEvent = ref(null);
const modalOpen     = ref(false);

function openModal(event) {
    selectedEvent.value = event;
    modalOpen.value = true;
}

function closeModal() {
    modalOpen.value = false;
    // Small delay so the close animation finishes before clearing data
    setTimeout(() => { selectedEvent.value = null; }, 200);
}

// ─── RSVP action ─────────────────────────────────────────────────────────────

const submitting = ref(false);

function submitRsvp(status) {
    if (!selectedEvent.value || submitting.value) return;
    submitting.value = true;

    router.post(
        route('events.rsvp', { event: selectedEvent.value.id }),
        { status },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeModal();
                submitting.value = false;
            },
            onError: () => { submitting.value = false; },
        }
    );
}

function eventShareUrl(event) {
    return route('share.event', event?.id, true);
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const typeConfig = {
    physical: { label: 'Fizikal', classes: 'bg-emerald-100 text-emerald-700' },
    online:   { label: 'Dalam Talian', classes: 'bg-sky-100 text-sky-700' },
};

const rsvpConfig = {
    going:    { label: 'Hadir',   classes: 'bg-emerald-100 text-emerald-700' },
    maybe:    { label: 'Mungkin', classes: 'bg-amber-100 text-amber-700'    },
    declined: { label: 'Tidak',   classes: 'bg-red-100 text-red-600'         },
    attended: { label: 'Hadir ✓', classes: 'bg-emerald-100 text-emerald-700' },
};

// ─── Create Program Modal (Admin/Superadmin) ───────────────────────────────

const createModalOpen = ref(false);
const createProgramForm = useForm({
    organization_id: '',
    title: '',
    description: '',
    type: 'physical',
    location_or_link: '',
    start_time: '',
    end_time: '',
    featured_image: null,
});

function openCreateProgramModal() {
    createModalOpen.value = true;
}

function closeCreateProgramModal() {
    createModalOpen.value = false;
}

function submitCreateProgram() {
    createProgramForm.post(route('events.store'), {
        preserveScroll: true,
        onSuccess: () => {
            createProgramForm.reset();
            closeCreateProgramModal();
        },
    });
}
// ─── Filters & Search ────────────────────────────────────────────────────────
const searchQuery = ref(props.filters?.search ?? '');
const typeFilter  = ref(props.filters?.type ?? '');

function customDebounce(fn, wait) {
    let timer;
    return function (...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), wait);
    };
}

watch([searchQuery, typeFilter], customDebounce(([newSearch, newType]) => {
    router.get(
        route('events.index'),
        { tab: props.tab, search: newSearch, type: newType },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}, 300));
</script>

<template>
    <Head title="Program & Acara" />

    <AppLayout>
        <template #header>Program &amp; Acara</template>

        <div class="max-w-7xl mx-auto px-4 md:px-6 py-6 md:py-10">

            <!-- Page Header & Actions Row -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
                <div class="space-y-1">
                    <h2 class="text-3xl font-black tracking-tight text-gray-900">
                        {{ isSuperadmin ? 'Pengurusan Program' : 'Program & Acara' }}
                    </h2>
                    <p class="text-sm font-medium text-gray-500">
                        {{ tab === 'past' ? 'Senarai program yang telah berlalu.' : 'Ketahui dan sertai program yang akan datang.' }}
                    </p>
                </div>
                
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Tab Switcher -->
                    <div class="flex items-center rounded-2xl bg-gray-100/80 p-1 border border-gray-200">
                        <button 
                            @click="router.get(route('events.index'), { tab: 'upcoming', search: searchQuery, type: typeFilter }, { preserveState: true })"
                            :class="[
                                'px-4 py-2 text-sm font-bold rounded-xl transition-all',
                                tab === 'upcoming' ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-900/5' : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Akan Datang
                        </button>
                        <button 
                            @click="router.get(route('events.index'), { tab: 'past', search: searchQuery, type: typeFilter }, { preserveState: true })"
                            :class="[
                                'px-4 py-2 text-sm font-bold rounded-xl transition-all',
                                tab === 'past' ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-900/5' : 'text-gray-500 hover:text-gray-700'
                            ]"
                        >
                            Telah Berlalu
                        </button>
                    </div>

                    <!-- Add Button -->
                    <button
                        v-if="isSuperadmin"
                        @click="openCreateProgramModal"
                        class="inline-flex items-center gap-2 rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Tambah Program
                    </button>
                </div>
            </div>

            <!-- Filters Row -->
            <div class="flex flex-col sm:flex-row gap-3 mb-10">
                <!-- Search Box -->
                <div class="relative flex-1 max-w-sm">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari nama atau tajuk program..."
                        class="pl-10 w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors"
                    >
                    <button 
                        v-if="searchQuery" 
                        @click="searchQuery = ''" 
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Type Selector -->
                <div class="relative max-w-[180px]">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                    </div>
                    <select
                        v-model="typeFilter"
                        class="pl-10 w-full rounded-2xl border-gray-200 text-sm focus:border-gray-900 focus:ring-gray-900 shadow-sm transition-colors text-gray-700 bg-white"
                    >
                        <option value="">Semua Format</option>
                        <option value="physical">Fizikal</option>
                        <option value="online">Dalam Talian</option>
                    </select>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="events.data.length === 0" class="bg-white rounded-3xl border border-dashed border-gray-200 shadow-[0_2px_10px_-3px_rgba(6,81,237,0.03)] p-16 text-center">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50 border border-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="font-semibold text-gray-600">Tiada program dijadualkan</p>
                <p class="text-sm text-gray-400 mt-1">Program baharu akan muncul di sini apabila dijadualkan.</p>
            </div>

            <!-- Event Grid: 1 col → 2 col (sm) → 3 col (lg) -->
            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <article
                    v-for="event in events.data"
                    :key="event.id"
                    class="group relative bg-white rounded-3xl shadow-[0_4px_20px_-4px_rgba(0,0,0,0.04)] ring-1 ring-gray-100 overflow-hidden
                           hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-200/40 transition-all duration-300 cursor-pointer flex flex-col"
                    @click="openModal(event)"
                >
                    <!-- Featured Image -->
                    <div class="relative aspect-[4/3] overflow-hidden bg-gray-50 shrink-0">
                        <img
                            :src="event.featured_image_url"
                            :alt="event.title"
                            class="w-full h-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                            :class="tab === 'past' ? 'grayscale opacity-75 group-hover:grayscale-0 group-hover:opacity-100' : ''"
                            loading="lazy"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-gray-900/60 via-gray-900/10 to-transparent opacity-60"></div>

                        <!-- Organization Badge -->
                        <div class="absolute top-4 left-4 bg-white/95 backdrop-blur-md px-2.5 py-1.5 rounded-xl border border-white/20 shadow-sm flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" :style="{ backgroundColor: event.organization.color_theme }"></span>
                            <span class="text-[10px] font-black uppercase tracking-wider text-gray-900">{{ event.organization.name }}</span>
                        </div>

                        <!-- Date Overlay (Bottom Left) -->
                        <div class="absolute bottom-4 left-4">
                            <div class="bg-white/95 backdrop-blur-md rounded-2xl p-2.5 flex items-center gap-3 shadow-sm border border-white/20">
                                <div class="flex flex-col items-center justify-center bg-gray-50 rounded-xl w-10 h-10 border border-gray-100">
                                    <span class="text-[10px] font-bold text-gray-400 leading-none uppercase">{{ new Date(event.start_time).toLocaleString('ms-MY', { month: 'short' }) }}</span>
                                    <span class="text-base font-black text-gray-900 leading-none mt-0.5">{{ new Date(event.start_time).getDate() }}</span>
                                </div>
                                <div class="flex flex-col justify-center">
                                    <span class="text-[10px] font-bold uppercase text-gray-400 tracking-wider">Masa</span>
                                    <span class="text-xs font-black text-gray-900">
                                        {{ new Date(event.start_time).toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true }) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- My RSVP badge (top-right) -->
                        <span
                            v-if="event.my_rsvp"
                            :class="['absolute top-4 right-4 inline-flex items-center text-[10px] uppercase tracking-wider font-black px-3 py-1.5 rounded-xl shadow-sm border border-white/20 backdrop-blur-md',
                                     rsvpConfig[event.my_rsvp]?.classes]"
                        >
                            {{ rsvpConfig[event.my_rsvp]?.label }}
                        </span>
                    </div>

                    <div class="p-5 flex flex-col flex-1">
                        <div class="flex-1 space-y-3">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="font-bold text-gray-900 text-[15px] leading-snug line-clamp-2">
                                    {{ event.title }}
                                </h3>
                                <span
                                    :class="['shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-xl border opacity-80',
                                            typeConfig[event.type]?.classes ?? 'bg-gray-50 border-gray-100 text-gray-400']"
                                    :title="typeConfig[event.type]?.label"
                                >
                                    <svg v-if="event.type === 'physical'" xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </span>
                            </div>

                            <!-- Location -->
                            <p v-if="event.location_or_link" class="flex items-center gap-2 text-xs font-medium text-gray-500 line-clamp-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                {{ event.location_or_link }}
                            </p>
                        </div>

                        <!-- Footer -->
                        <div class="flex items-center justify-between pt-4 border-t border-gray-100 mt-4">
                            <div class="flex -space-x-1.5">
                                <div class="w-6 h-6 rounded-full bg-gray-100 ring-2 ring-white border border-gray-200 flex items-center justify-center shadow-sm z-10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="flex items-center text-[10px] font-bold text-gray-400 bg-gray-50 uppercase tracking-wider pl-3 pr-2 py-1 rounded-r-lg ring-1 ring-inset ring-gray-100">
                                    {{ event.rsvp_count }} Hadir
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-1.5 font-bold text-sm group-hover:gap-2 transition-all"
                                 :style="{ color: tab === 'past' ? '#6b7280' : event.organization.color_theme }">
                                {{ tab === 'past' ? 'Lihat' : (isSuperadmin ? 'Urus' : 'Sertai') }}
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- Pagination -->
            <div v-if="events.last_page > 1" class="flex justify-center mt-12 gap-2">
                <a
                    v-for="link in events.links"
                    :key="link.label"
                    :href="link.url ?? '#'"
                    v-html="link.label"
                    :class="[
                        'inline-flex items-center justify-center h-10 min-w-10 px-3 rounded-2xl text-sm font-bold transition-all',
                        link.active
                            ? 'bg-gray-900 text-white shadow-md'
                            : link.url
                                ? 'bg-white border text-gray-600 hover:border-gray-900 shadow-sm'
                                : 'bg-transparent text-gray-300 pointer-events-none',
                    ]"
                />
            </div>

            <!-- Attended Programs Section (for Member) -->
            <div v-if="attendedEvents.length > 0" class="mt-12">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Program yang Telah Dihadiri</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <article
                        v-for="event in attendedEvents"
                        :key="event.id"
                        class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
                    >
                        <div class="p-5 space-y-2">
                            <span
                                class="inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-0.5 rounded-full"
                                :style="{ backgroundColor: event.organization.color_theme + '22', color: event.organization.color_theme }"
                            >
                                {{ event.organization.name }}
                            </span>
                            <h3 class="font-bold text-gray-800 text-sm leading-snug line-clamp-2">
                                {{ event.title }}
                            </h3>
                            <div class="space-y-1 pt-1">
                                <p class="flex items-center gap-1.5 text-xs text-gray-500">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    {{ event.start_formatted }}
                                </p>
                                <p v-if="event.location_or_link" class="flex items-center gap-1.5 text-xs text-gray-400 truncate">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-300 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    {{ event.location_or_link }}
                                </p>
                                <p class="flex items-center gap-1.5 text-xs text-emerald-600">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Hadir pada {{ event.attended_at }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!--  CREATE PROGRAM MODAL                                            -->
        <!-- ════════════════════════════════════════════════════════════════ -->
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
                    v-if="createModalOpen"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm"
                    @click.self="closeCreateProgramModal"
                >
                    <div class="w-full max-w-2xl rounded-3xl border border-white/50 bg-white/95 shadow-2xl overflow-hidden">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                            <h3 class="text-base font-black text-gray-800">Add New Program</h3>
                            <button @click="closeCreateProgramModal" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form class="space-y-4 p-5" @submit.prevent="submitCreateProgram">
                            <div v-if="isSuperadmin && organizations.length" class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Organization</label>
                                <select v-model="createProgramForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    <option value="">Semua</option>
                                    <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                                </select>
                                <p v-if="createProgramForm.errors.organization_id" class="text-xs text-red-500">{{ createProgramForm.errors.organization_id }}</p>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Title *</label>
                                <input v-model="createProgramForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                                <p v-if="createProgramForm.errors.title" class="text-xs text-red-500">{{ createProgramForm.errors.title }}</p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Type *</label>
                                    <select v-model="createProgramForm.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                        <option value="physical">Fizikal</option>
                                        <option value="online">Dalam Talian</option>
                                    </select>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Lokasi / Link</label>
                                    <input v-model="createProgramForm.location_or_link" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">Start Time *</label>
                                    <input v-model="createProgramForm.start_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                                    <p v-if="createProgramForm.errors.start_time" class="text-xs text-red-500">{{ createProgramForm.errors.start_time }}</p>
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xs font-semibold text-gray-500">End Time *</label>
                                    <input v-model="createProgramForm.end_time" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required>
                                    <p v-if="createProgramForm.errors.end_time" class="text-xs text-red-500">{{ createProgramForm.errors.end_time }}</p>
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Description</label>
                                <textarea v-model="createProgramForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                            </div>

                            <div class="space-y-1">
                                <label class="text-xs font-semibold text-gray-500">Featured Image</label>
                                <input
                                    type="file"
                                    accept="image/*"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                    @change="createProgramForm.featured_image = $event.target.files?.[0] ?? null"
                                >
                                <p v-if="createProgramForm.errors.featured_image" class="text-xs text-red-500">{{ createProgramForm.errors.featured_image }}</p>
                            </div>

                            <div class="flex justify-end gap-2 pt-2">
                                <button type="button" @click="closeCreateProgramModal" class="rounded-xl border border-gray-200 px-4 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" :disabled="createProgramForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                    {{ createProgramForm.processing ? 'Saving...' : 'Save Program' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!--  RSVP MODAL (glassmorphism)                                     -->
        <!-- ════════════════════════════════════════════════════════════════ -->
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
                    v-if="modalOpen && selectedEvent"
                    class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4
                           bg-gray-900/40 backdrop-blur-sm"
                    @click.self="closeModal"
                >
                    <Transition
                        enter-active-class="transition ease-out duration-200"
                        enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-active-class="transition ease-in duration-150"
                        leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                        leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    >
                        <div
                            v-if="modalOpen"
                            class="w-full max-w-md bg-white/90 backdrop-blur-md rounded-3xl shadow-2xl
                                   border border-white/50 overflow-hidden"
                        >
                            <!-- Modal header image -->
                            <div class="relative h-36 bg-gray-100 overflow-hidden">
                                <img
                                    :src="selectedEvent.featured_image_url"
                                    :alt="selectedEvent.title"
                                    class="w-full h-full object-cover"
                                />
                                <div class="absolute inset-0 bg-gradient-to-t from-black/40 to-transparent"></div>

                                <!-- Close button -->
                                <button
                                    @click="closeModal"
                                    class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full bg-black/30 text-white hover:bg-black/50 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>

                                <span
                                    class="absolute bottom-3 left-3 inline-flex text-[11px] font-bold px-2.5 py-1 rounded-full"
                                    :class="typeConfig[selectedEvent.type]?.classes"
                                >
                                    {{ typeConfig[selectedEvent.type]?.label }}
                                </span>
                            </div>

                            <!-- Modal body -->
                            <div class="p-5 space-y-4">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 mb-1">
                                        {{ selectedEvent.organization.name }}
                                    </p>
                                    <h3 class="text-lg font-bold text-gray-800 leading-snug">{{ selectedEvent.title }}</h3>
                                </div>

                                <!-- Event meta -->
                                <div class="space-y-1.5 text-xs text-gray-500">
                                    <p class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        {{ selectedEvent.start_formatted }}
                                    </p>
                                    <p v-if="selectedEvent.location_or_link" class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ selectedEvent.location_or_link }}
                                    </p>
                                    <p class="flex items-center gap-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        {{ selectedEvent.rsvp_count }} ahli akan hadir
                                    </p>
                                </div>

                                <div class="pt-1">
                                    <p class="mb-2 text-xs font-semibold text-gray-500">Kongsi Program</p>
                                    <SocialShareButtons
                                        compact
                                        :title="selectedEvent.title"
                                        :text="selectedEvent.organization?.name || 'Program komuniti'"
                                        :url="eventShareUrl(selectedEvent)"
                                    />
                                </div>

                                <!-- Current RSVP status -->
                                <div v-if="selectedEvent.my_rsvp" class="text-xs text-gray-500">
                                    Status semasa:
                                    <span :class="['ml-1 font-semibold px-2 py-0.5 rounded-full', rsvpConfig[selectedEvent.my_rsvp]?.classes]">
                                        {{ rsvpConfig[selectedEvent.my_rsvp]?.label }}
                                    </span>
                                </div>

                                <!-- RSVP Pill Buttons -->
                                <div v-if="!isSuperadmin" class="pt-1">
                                    <p class="text-xs font-semibold text-gray-500 mb-2">Pilih Status Kehadiran:</p>
                                    <div class="grid grid-cols-3 gap-2">
                                        <button
                                            @click="submitRsvp('going')"
                                            :disabled="submitting"
                                            :class="[
                                                'flex flex-col items-center gap-1 p-3 rounded-2xl border-2 text-xs font-semibold transition-all',
                                                selectedEvent.my_rsvp === 'going'
                                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                                    : 'border-gray-100 hover:border-emerald-300 hover:bg-emerald-50 text-gray-600'
                                            ]"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Hadir
                                        </button>
                                        <button
                                            @click="submitRsvp('maybe')"
                                            :disabled="submitting"
                                            :class="[
                                                'flex flex-col items-center gap-1 p-3 rounded-2xl border-2 text-xs font-semibold transition-all',
                                                selectedEvent.my_rsvp === 'maybe'
                                                    ? 'border-amber-400 bg-amber-50 text-amber-700'
                                                    : 'border-gray-100 hover:border-amber-300 hover:bg-amber-50 text-gray-600'
                                            ]"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Mungkin
                                        </button>
                                        <button
                                            @click="submitRsvp('declined')"
                                            :disabled="submitting"
                                            :class="[
                                                'flex flex-col items-center gap-1 p-3 rounded-2xl border-2 text-xs font-semibold transition-all',
                                                selectedEvent.my_rsvp === 'declined'
                                                    ? 'border-red-400 bg-red-50 text-red-700'
                                                    : 'border-gray-100 hover:border-red-300 hover:bg-red-50 text-gray-600'
                                            ]"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Tidak
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="space-y-2">
                                    <div class="rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700">
                                        Akaun pentadbir menggunakan mod pengurusan kehadiran (QR + senarai peserta hadir).
                                    </div>
                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 mb-2">
                                        <a
                                            :href="route('events.qr', { event: selectedEvent.id })"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-emerald-700"
                                        >
                                            Papar QR Kehadiran
                                        </a>
                                        <a
                                            :href="route('events.print', { event: selectedEvent.id })"
                                            target="_blank"
                                            class="inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 px-4 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        >
                                            Senarai Kehadiran
                                        </a>
                                    </div>
                                    <div class="bg-white border border-gray-100 rounded-xl p-3">
                                        <div class="font-semibold text-xs text-gray-700 mb-2">Senarai Kehadiran Program</div>
                                        <table class="min-w-full text-xs">
                                            <thead>
                                                <tr class="text-gray-400">
                                                    <th class="px-2 py-1 text-left">#</th>
                                                    <th class="px-2 py-1 text-left">Nama</th>
                                                    <th class="px-2 py-1 text-left">E-mel</th>
                                                    <th class="px-2 py-1 text-left">Telefon</th>
                                                    <th class="px-2 py-1 text-left">Masa Hadir</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(att, i) in selectedEvent.attendance || []" :key="i">
                                                    <td class="px-2 py-1">{{ i + 1 }}</td>
                                                    <td class="px-2 py-1">{{ att.name }}</td>
                                                    <td class="px-2 py-1">{{ att.email }}</td>
                                                    <td class="px-2 py-1">{{ att.phone || '—' }}</td>
                                                    <td class="px-2 py-1">{{ att.attended_at || '—' }}</td>
                                                </tr>
                                                <tr v-if="!selectedEvent.attendance || selectedEvent.attendance.length === 0">
                                                    <td colspan="5" class="px-2 py-4 text-center text-gray-400">Tiada kehadiran direkodkan.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Google Calendar button -->
                                <a
                                    :href="selectedEvent.google_calendar_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="flex items-center justify-center gap-2 w-full py-2.5 rounded-xl
                                           border border-gray-200 text-sm font-medium text-gray-600
                                           hover:bg-gray-50 transition-colors"
                                >
                                    <!-- Google Calendar icon (simplified) -->
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-blue-500" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/>
                                    </svg>
                                    Tambah ke Google Calendar
                                </a>
                            </div>
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

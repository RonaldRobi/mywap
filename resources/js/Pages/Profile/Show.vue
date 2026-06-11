<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

// ─── Props (passed by ProfileController::show) ──────────────────────────────

const props = defineProps({
    profileUser: {
        type: Object,
        required: true,
    },
    history: {
        type: Array,
        default: () => [],
    },
    attendedPrograms: {
        type: Array,
        default: () => [],
    },
});

// ─── Dynamic theming  ────────────────────────────────────────────────────────

const themeMap = {
    pkpim: {
        coverGradient: 'from-indigo-500 to-indigo-700',
        badge:         'bg-indigo-100 text-indigo-700',
        dot:           'bg-indigo-500',
        avatarRing:    'ring-indigo-400',
        nodeBg:        'bg-indigo-500',
    },
    abim: {
        coverGradient: 'from-emerald-500 to-teal-600',
        badge:         'bg-emerald-100 text-emerald-700',
        dot:           'bg-emerald-500',
        avatarRing:    'ring-emerald-400',
        nodeBg:        'bg-emerald-500',
    },
    wadah: {
        coverGradient: 'from-amber-500 to-orange-500',
        badge:         'bg-amber-100 text-amber-700',
        dot:           'bg-amber-500',
        avatarRing:    'ring-amber-400',
        nodeBg:        'bg-amber-500',
    },
};

const orgSlug = computed(() => props.profileUser?.organization?.slug ?? 'abim');
const theme   = computed(() => themeMap[orgSlug.value] ?? themeMap['abim']);
const isSuperadmin = computed(() => {
    const roles = props.profileUser?.roles ?? [];
    return roles.includes('Superadmin') || roles.includes('Admin');
});

// ─── Org-specific node colour for each timeline card  ────────────────────────

function nodeClassForSlug(slug) {
    return themeMap[slug]?.nodeBg ?? 'bg-gray-400';
}

// ─── Initials avatar  ────────────────────────────────────────────────────────

const initials = computed(() =>
    (props.profileUser?.name ?? 'U')
        .split(' ')
        .slice(0, 2)
        .map(w => w[0].toUpperCase())
        .join('')
);
</script>

<template>
    <Head title="Profil Saya" />

    <AppLayout>
        <template #header>Profil &amp; Perjalanan Keahlian</template>

        <div class="max-w-2xl mx-auto px-4 pb-10 md:px-6">

            <!-- ════════════════════════════════════════════════════════════ -->
            <!--  PROFILE HEADER CARD                                        -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6 mt-4">

                <!-- Cover gradient band -->
                <div :class="['h-28 md:h-36 bg-gradient-to-r', theme.coverGradient, 'relative']">
                    <!-- Decorative circles -->
                    <div class="absolute -bottom-6 -right-6 w-24 h-24 rounded-full bg-white/10 blur-xl pointer-events-none"></div>
                    <div class="absolute top-4 left-4 w-12 h-12 rounded-full bg-white/10 blur-lg pointer-events-none"></div>
                </div>

                <!-- Avatar + info -->
                <div class="px-5 pb-5">
                    <!-- Avatar — overlaps the cover -->
                    <div class="relative -mt-10 mb-3 flex items-end justify-between">
                        <div
                            :class="[
                                'flex h-20 w-20 items-center justify-center rounded-full',
                                'bg-white border-4 border-white shadow-md',
                                'ring-2', theme.avatarRing,
                                'text-2xl font-bold text-gray-800',
                            ]"
                            :style="{ background: `linear-gradient(135deg, ${profileUser.organization?.color_theme ?? '#10b981'}22, #fff)` }"
                        >
                            {{ initials }}
                        </div>

                        <!-- Edit profile button -->
                        <Link
                            :href="route('profile.edit')"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                            </svg>
                            Edit Profil
                        </Link>
                    </div>

                    <!-- Name & org badge -->
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-800 leading-tight">
                                {{ profileUser.name }}
                            </h2>
                            <span
                                v-if="profileUser.organization"
                                :class="['inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full', theme.badge]"
                            >
                                <span :class="['w-1.5 h-1.5 rounded-full', theme.dot]"></span>
                                {{ profileUser.organization.name }}
                            </span>
                        </div>
                        <p class="text-sm text-gray-500">{{ profileUser.email }}</p>
                    </div>

                    <!-- Meta chips row -->
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span v-if="profileUser.phone" class="inline-flex items-center gap-1.5 text-xs text-gray-500 bg-gray-50 border border-gray-100 px-2.5 py-1 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            {{ profileUser.phone }}
                        </span>
                        <span v-if="profileUser.dob && !isSuperadmin" class="inline-flex items-center gap-1.5 text-xs text-gray-500 bg-gray-50 border border-gray-100 px-2.5 py-1 rounded-lg">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z"/>
                            </svg>
                            {{ profileUser.dob }} ({{ profileUser.age }} tahun)
                        </span>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!--  PROGRAM YANG TELAH DIHADIRI                                -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div class="mb-7">
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4 px-1">
                    Program Yang Telah Dihadiri
                </h3>

                <div
                    v-if="attendedPrograms.length === 0"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-6 text-center"
                >
                    <p class="text-sm font-medium text-gray-500">Belum ada rekod kehadiran program.</p>
                    <p class="text-xs text-gray-400 mt-1">Imbas QR program semasa hadir untuk rekod automatik.</p>
                </div>

                <div v-else class="space-y-3">
                    <div
                        v-for="item in attendedPrograms"
                        :key="item.id"
                        class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-gray-800 leading-snug line-clamp-2">
                                    {{ item.event.title }}
                                </p>
                                <p class="mt-1 text-xs text-gray-500">{{ item.event.start_formatted }}</p>
                                <p v-if="item.event.location_or_link" class="mt-0.5 text-xs text-gray-400 line-clamp-1">
                                    {{ item.event.location_or_link }}
                                </p>
                            </div>

                            <span
                                class="shrink-0 inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-full"
                                :style="{
                                    backgroundColor: (item.event.organization?.color_theme ?? '#10b981') + '22',
                                    color: item.event.organization?.color_theme ?? '#10b981',
                                }"
                            >
                                Hadir
                            </span>
                        </div>

                        <p class="mt-2 text-[11px] text-gray-400">Direkod pada: {{ item.attended_at_human ?? '—' }}</p>
                    </div>
                </div>
            </div>

            <!-- ════════════════════════════════════════════════════════════ -->
            <!--  JOURNEY TIMELINE                                            -->
            <!-- ════════════════════════════════════════════════════════════ -->
            <div>
                <h3 class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4 px-1">
                    Perjalanan Keahlian
                </h3>

                <!-- Empty state -->
                <div
                    v-if="history.length === 0"
                    class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center"
                >
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-sm font-medium text-gray-500">Tiada rekod transisi lagi.</p>
                    <p class="text-xs text-gray-400 mt-1">Rekod akan muncul secara automatik apabila anda mencapai usia peralihan.</p>
                </div>

                <!-- Timeline list -->
                <div v-else class="relative pl-6">

                    <!-- Continuous vertical line -->
                    <div class="absolute left-[11px] top-2 bottom-2 w-0.5 bg-gray-200 rounded-full"></div>

                    <div
                        v-for="(record, index) in history"
                        :key="record.id"
                        class="relative mb-5 last:mb-0"
                    >
                        <!-- Timeline node dot -->
                        <div
                            :class="[
                                'absolute -left-6 top-4 flex h-4 w-4 items-center justify-center rounded-full ring-2 ring-white',
                                nodeClassForSlug(record.to_organization.slug),
                            ]"
                        >
                            <!-- Pulse ring for the latest transition -->
                            <span
                                v-if="index === history.length - 1"
                                :class="[
                                    'absolute inline-flex h-full w-full rounded-full opacity-60 animate-ping',
                                    nodeClassForSlug(record.to_organization.slug),
                                ]"
                            ></span>
                        </div>

                        <!-- Card -->
                        <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm w-full">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">

                                    <!-- "First join" vs "transition" label -->
                                    <p class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 mb-1">
                                        {{ record.from_organization ? 'Transisi' : 'Pertama Menyertai' }}
                                    </p>

                                    <!-- Main headline -->
                                    <p class="font-semibold text-gray-800 text-sm leading-snug">
                                        <template v-if="record.from_organization">
                                            Beralih dari
                                            <span
                                                class="font-bold"
                                                :style="{ color: record.from_organization.color_theme }"
                                            >{{ record.from_organization.name }}</span>
                                            ke
                                            <span
                                                class="font-bold"
                                                :style="{ color: record.to_organization.color_theme }"
                                            >{{ record.to_organization.name }}</span>
                                        </template>
                                        <template v-else>
                                            Menyertai
                                            <span
                                                class="font-bold"
                                                :style="{ color: record.to_organization.color_theme }"
                                            >{{ record.to_organization.name }}</span>
                                        </template>
                                    </p>

                                    <!-- Timestamp -->
                                    <p class="mt-1.5 flex items-center gap-1 text-xs text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                        </svg>
                                        {{ record.transitioned_at_human }}
                                    </p>
                                </div>

                                <!-- NGO badge pill -->
                                <span
                                    class="shrink-0 inline-flex items-center text-[11px] font-bold px-2.5 py-1 rounded-full"
                                    :style="{
                                        backgroundColor: record.to_organization.color_theme + '22',
                                        color: record.to_organization.color_theme,
                                    }"
                                >
                                    {{ record.to_organization.name }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- "Present" end cap -->
                    <div class="relative pl-0 mt-5 flex items-center gap-3">
                        <div
                            :class="['flex h-4 w-4 -ml-6 shrink-0 items-center justify-center rounded-full ring-2 ring-white', theme.dot]"
                        >
                            <span :class="['absolute inline-flex h-4 w-4 rounded-full opacity-40 animate-ping', theme.dot]"></span>
                        </div>
                        <span class="text-xs font-semibold text-gray-400">Sekarang — {{ profileUser.organization?.name }}</span>
                    </div>
                </div>
            </div>

        </div>
    </AppLayout>
</template>

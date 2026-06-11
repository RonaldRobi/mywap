<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RainbowButton from '@/Components/ui/RainbowButton.vue';

// ─── Shared auth props (injected by HandleInertiaRequests) ───────────────────

const page = usePage();
const user = computed(() => page.props.auth?.user);
const org  = computed(() => user.value?.organization);
const isSuperadmin = computed(() => (user.value?.roles ?? []).includes('Superadmin'));

// ─── Dynamic theme map (mirrors AppLayout) ───────────────────────────────────

const themeMap = {
    pkpim: {
        gradient:   'from-indigo-50 to-indigo-100/60',
        badge:      'bg-indigo-100 text-indigo-700',
        dot:        'bg-indigo-500',
        iconBg:     'bg-indigo-100 text-indigo-600',
        statusRing: 'ring-indigo-300',
        progressBar:'bg-indigo-500',
    },
    abim: {
        gradient:   'from-emerald-50 to-emerald-100/60',
        badge:      'bg-emerald-100 text-emerald-700',
        dot:        'bg-emerald-500',
        iconBg:     'bg-emerald-100 text-emerald-600',
        statusRing: 'ring-emerald-300',
        progressBar:'bg-emerald-500',
    },
    wadah: {
        gradient:   'from-amber-50 to-amber-100/60',
        badge:      'bg-amber-100 text-amber-700',
        dot:        'bg-amber-500',
        iconBg:     'bg-amber-100 text-amber-600',
        statusRing: 'ring-amber-300',
        progressBar:'bg-amber-500',
    },
};

const theme = computed(() => themeMap[org.value?.slug] ?? themeMap['abim']);

// ─── Placeholder data (replace with Inertia-passed props from controller) ────

const upcomingEvent = {
    title:  'Bengkel Kepimpinan Pemuda',
    date:   'Sabtu, 22 Mac 2026',
    time:   '8:00 pagi – 5:00 petang',
    venue:  'Dewan Seminar ABIM, KL',
    slots:  42,
};

const membershipStats = {
    status:      'Aktif',
    memberSince: '2021',
    renewalDate: '31 Dis 2026',
    progress:    72, // percent through current year
};

const quickStats = [
    { label: 'Program Dihadiri', value: '14', icon: 'calendar' },
    { label: 'Ahli Baharu',      value: '3',  icon: 'users'    },
    { label: 'Dokumen',          value: '28', icon: 'document' },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>Dashboard</template>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!--  BENTO GRID                                                     -->
        <!--  Mobile-first: 1 col → 3 col (md) → 4 col (lg)                 -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <div class="p-4 md:p-6 max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-5">

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  CARD 1 · Welcome / Identity                          -->
                <!--  Mobile: full width  |  Desktop: spans 2 columns      -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div
                    :class="[
                        'col-span-1 md:col-span-2',
                        'relative overflow-hidden',
                        'bg-gradient-to-br', theme.gradient,
                        'rounded-2xl border border-gray-100 shadow-sm p-6',
                    ]"
                >
                    <!-- Decorative blob -->
                    <div class="pointer-events-none absolute -right-8 -top-8 w-36 h-36 rounded-full bg-white/30 blur-2xl"></div>

                    <div class="relative flex flex-col gap-4">
                        <!-- Org badge -->
                        <span
                            v-if="org"
                            :class="['inline-flex items-center gap-1.5 self-start text-xs font-semibold px-2.5 py-1 rounded-full', theme.badge]"
                        >
                            <span :class="['w-1.5 h-1.5 rounded-full', theme.dot]"></span>
                            {{ org.name }}
                        </span>

                        <!-- Greeting -->
                        <div>
                            <p class="text-xs font-medium text-gray-400 mb-0.5 uppercase tracking-widest">Selamat datang</p>
                            <h2 class="text-2xl md:text-3xl font-bold text-gray-800 leading-tight">
                                {{ user?.name ?? 'Ahli' }}
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                <template v-if="isSuperadmin">
                                    Anda sedang log masuk sebagai pentadbir sistem
                                    <strong class="text-gray-700">{{ org?.name ?? '—' }}</strong>.
                                </template>
                                <template v-else>
                                    Anda sedang log masuk sebagai ahli
                                    <strong class="text-gray-700">{{ org?.name ?? '—' }}</strong>.
                                </template>
                            </p>
                        </div>

                        <!-- Quick action buttons -->
                        <div class="mt-1 flex flex-wrap gap-2">
                            <RainbowButton
                                v-if="isSuperadmin"
                                class="h-9 gap-1.5 rounded-xl px-3"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Urus Program
                            </RainbowButton>
                            <button
                                v-else
                                :class="['inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl text-white transition-opacity hover:opacity-90', theme.dot.replace('bg-', 'bg-')]"
                                :style="{ backgroundColor: org?.color_theme ?? '#10b981' }"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                </svg>
                                Daftar Program
                            </button>
                            <button class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-xl bg-white/70 text-gray-700 border border-gray-200 hover:bg-white transition-colors">
                                Lihat Profil
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  CARD 2 · Upcoming Program                            -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Program Akan Datang</p>
                        <span :class="['flex h-7 w-7 items-center justify-center rounded-lg text-sm', theme.iconBg]">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </span>
                    </div>

                    <div class="flex-1 space-y-1.5">
                        <h3 class="text-sm font-bold text-gray-800 leading-snug">{{ upcomingEvent.title }}</h3>
                        <p class="text-xs text-gray-500">{{ upcomingEvent.date }}</p>
                        <p class="text-xs text-gray-400">{{ upcomingEvent.time }}</p>
                        <p class="text-xs text-gray-400 truncate">📍 {{ upcomingEvent.venue }}</p>
                    </div>

                    <div class="flex items-center justify-between mt-auto pt-2 border-t border-gray-50">
                        <span class="text-xs text-gray-400">{{ upcomingEvent.slots }} tempat lagi</span>
                        <RainbowButton v-if="isSuperadmin" class="h-8 rounded-lg px-3 text-[11px] font-semibold">
                            Urus
                        </RainbowButton>
                        <button
                            v-else
                            class="text-xs font-semibold text-white px-3 py-1.5 rounded-lg transition-opacity hover:opacity-90"
                            :style="{ backgroundColor: org?.color_theme ?? '#10b981' }"
                        >
                            Daftar
                        </button>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  CARD 3 · Membership Status                           -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div v-if="!isSuperadmin" class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex flex-col gap-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Status Keahlian</p>

                    <!-- Status circle -->
                    <div class="flex flex-col items-center gap-3 py-2">
                        <div :class="['relative flex h-20 w-20 items-center justify-center rounded-full bg-white ring-4', theme.statusRing]">
                            <!-- Progress SVG ring (72%) -->
                            <svg class="absolute inset-0 w-20 h-20 -rotate-90" viewBox="0 0 80 80">
                                <circle cx="40" cy="40" r="34" fill="none" stroke="#f3f4f6" stroke-width="6"/>
                                <circle
                                    cx="40" cy="40" r="34" fill="none"
                                    :stroke="org?.color_theme ?? '#10b981'" stroke-width="6"
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${2 * Math.PI * 34 * membershipStats.progress / 100} ${2 * Math.PI * 34}`"
                                />
                            </svg>
                            <div class="text-center z-10">
                                <p class="text-[10px] font-bold text-gray-800 leading-none">{{ membershipStats.progress }}%</p>
                            </div>
                        </div>

                        <div class="text-center">
                            <span class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ membershipStats.status }}
                            </span>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-xs text-gray-500 border-t border-gray-50 pt-3">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ahli sejak</span>
                            <span class="font-semibold text-gray-700">{{ membershipStats.memberSince }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Pembaharuan</span>
                            <span class="font-semibold text-gray-700">{{ membershipStats.renewalDate }}</span>
                        </div>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  QUICK STATS ROW  (3 mini cards, each col-span-1)     -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div
                    v-for="stat in quickStats"
                    :key="stat.label"
                    class="col-span-1 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 flex items-center gap-4"
                >
                    <!-- Icon -->
                    <div :class="['flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', theme.iconBg]">
                        <!-- Calendar -->
                        <template v-if="stat.icon === 'calendar'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </template>
                        <!-- Users -->
                        <template v-else-if="stat.icon === 'users'">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                        </template>
                        <!-- Document -->
                        <template v-else>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </template>
                    </div>

                    <div class="min-w-0">
                        <p class="text-2xl font-bold text-gray-800 leading-none">{{ stat.value }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ stat.label }}</p>
                    </div>
                </div>

                <!-- ══════════════════════════════════════════════════════ -->
                <!--  CARD 5 · Lifecycle Timeline  (full width on lg)      -->
                <!-- ══════════════════════════════════════════════════════ -->
                <div v-if="!isSuperadmin" class="col-span-1 md:col-span-3 lg:col-span-4 bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-4">Perjalanan Keahlian — Seamless Age Transition</p>

                    <div class="flex items-stretch gap-0 overflow-x-auto pb-1">
                        <!-- Tier: PKPIM -->
                        <div class="flex-1 min-w-28 flex flex-col items-center gap-2 px-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 flex items-center justify-center">
                                <span class="text-indigo-600 text-xs font-bold">&lt;20</span>
                            </div>
                            <div class="text-center">
                                <p class="text-xs font-bold text-gray-700">PKPIM</p>
                                <p class="text-[10px] text-gray-400">Pelajar</p>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-indigo-100 mt-1">
                                <div class="h-1.5 rounded-full bg-indigo-500" style="width:100%"></div>
                            </div>
                        </div>

                        <!-- Connector arrow -->
                        <div class="flex items-center px-1 text-gray-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>

                        <!-- Tier: ABIM (active example) -->
                        <div class="flex-1 min-w-28 flex flex-col items-center gap-2 px-3">
                            <div
                                class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center ring-2 ring-emerald-400 ring-offset-2"
                            >
                                <span class="text-emerald-600 text-xs font-bold">20-29</span>
                            </div>
                            <div class="text-center">
                                <p class="text-xs font-bold text-gray-700">ABIM</p>
                                <p class="text-[10px] text-gray-400">Pemuda</p>
                                <span class="text-[9px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-semibold">Semasa</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-emerald-100 mt-1">
                                <div class="h-1.5 rounded-full bg-emerald-500" style="width:72%"></div>
                            </div>
                        </div>

                        <!-- Connector arrow -->
                        <div class="flex items-center px-1 text-gray-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>

                        <!-- Tier: WADAH -->
                        <div class="flex-1 min-w-28 flex flex-col items-center gap-2 px-3 opacity-50">
                            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                <span class="text-amber-600 text-xs font-bold">30+</span>
                            </div>
                            <div class="text-center">
                                <p class="text-xs font-bold text-gray-700">WADAH</p>
                                <p class="text-[10px] text-gray-400">Veteran</p>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-amber-100 mt-1"></div>
                        </div>
                    </div>

                    <p class="mt-3 text-[11px] text-gray-400 text-center">
                        Transisi dilakukan secara automatik pada hari jadi anda — tiada pendaftaran semula diperlukan.
                    </p>
                </div>

            </div>
        </div>
    </AppLayout>
</template>


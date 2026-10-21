<script setup>
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    organizations: { type: Array, default: () => [] },
    chartMembers: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    isOwnOrganization: { type: Boolean, default: false },
});

const page = usePage();

const currentTab = computed(() => {
    const q = page.url.includes('?') ? page.url.split('?')[1] : '';
    return new URLSearchParams(q).get('tab') || 'info';
});

function goTab(tab) {
    if (tab === currentTab.value) return;
    router.get(route('org.info'), { org: props.organization.slug, tab }, {
        preserveState: true,
        preserveScroll: true,
    });
}

function selectOrganization(org) {
    if (org.slug === props.organization.slug) return;
    router.get(route('org.info'), { org: org.slug, tab: currentTab.value }, {
        preserveState: true,
        preserveScroll: true,
    });
}

const accent = computed(() => props.organization.color_theme || '#10b981');
const accentSoft = computed(() => hexToRgba(accent.value, 0.12));
const accentLine = computed(() => hexToRgba(accent.value, 0.28));

const selectedSummary = computed(
    () => props.organizations.find((o) => o.slug === props.organization.slug) || {}
);

const ageRangeLabel = computed(() => {
    const o = props.organization;
    if (o.max_age === null || o.max_age === undefined) return `${o.min_age}+ tahun`;
    return `${o.min_age}–${o.max_age} tahun`;
});

const socialLinks = computed(() => {
    const o = props.organization;
    return [
        { key: 'website_url', label: 'Laman Web', href: o.website_url },
        { key: 'facebook_url', label: 'Facebook', href: o.facebook_url },
        { key: 'instagram_url', label: 'Instagram', href: o.instagram_url },
        { key: 'twitter_url', label: 'X (Twitter)', href: o.twitter_url },
        { key: 'youtube_url', label: 'YouTube', href: o.youtube_url },
        { key: 'tiktok_url', label: 'TikTok', href: o.tiktok_url },
    ].filter((s) => s.href);
});

function normalizeSocialUrl(url) {
    if (!url) return url;
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
}

function hexToRgba(hex, alpha) {
    const clean = (hex || '#10b981').replace('#', '');
    if (clean.length !== 6) return `rgba(16, 185, 129, ${alpha})`;
    const r = parseInt(clean.slice(0, 2), 16);
    const g = parseInt(clean.slice(2, 4), 16);
    const b = parseInt(clean.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

function initials(name) {
    return (name || '?').trim().charAt(0).toUpperCase();
}

const featuredMember = computed(() => props.chartMembers[0] || null);
const otherMembers = computed(() => props.chartMembers.slice(1));
const viewingMember = ref(null);

function openMemberPreview(member) {
    if (!member) return;
    viewingMember.value = member;
}

const socialIconMap = {
    website_url: 'M12 21a9 9 0 100-18 9 9 0 000 18zm0 0c2.5-2.4 4-5.6 4-9s-1.5-6.6-4-9m0 18c-2.5-2.4-4-5.6-4-9s1.5-6.6 4-9M3.6 9h16.8M3.6 15h16.8',
    facebook_url: 'M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z',
    instagram_url: 'M16 11.37A4 4 0 1112.63 8 4 4 0 0116 11.37zM17.5 6.5h.01M7 2h10a5 5 0 015 5v10a5 5 0 01-5 5H7a5 5 0 01-5-5V7a5 5 0 015-5z',
    twitter_url: 'M4 4l7.2 9.3L4.4 20h2.1l5.6-5.7L16.8 20H20l-7.5-9.7L19.4 4h-2.1l-5.1 5.2L8.2 4H4z',
    youtube_url: 'M22.54 6.42a2.78 2.78 0 00-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 00-1.94 2A29 29 0 001 11.75a29 29 0 00.46 5.33A2.78 2.78 0 003.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 001.94-2 29 29 0 00.46-5.25 29 29 0 00-.46-5.33zM9.75 15.02V8.48l5.75 3.27-5.75 3.27z',
    tiktok_url: 'M9 12a4 4 0 104 4V4a5 5 0 005 5',
};

// ─── Description (Maklumat) ────────────────────────────────────────────────
const editingDescription = ref(false);
const descriptionForm = useForm({ description: props.organization.description || '' });

function saveDescription() {
    descriptionForm.put(route('org.info.update'), {
        preserveScroll: true,
        onSuccess: () => { editingDescription.value = false; },
    });
}

// ─── Carta Organisasi ──────────────────────────────────────────────────────
const showMemberModal = ref(false);
const editingMember = ref(null);
const confirmDeleteMember = ref(null);

const emptyMemberForm = { name: '', position: '', email: '', display_order: 0, image: null };
const memberForm = useForm({ ...emptyMemberForm });

function openAddMember() {
    editingMember.value = null;
    memberForm.reset();
    Object.assign(memberForm, { ...emptyMemberForm });
    showMemberModal.value = true;
}

function openEditMember(member) {
    editingMember.value = member;
    memberForm.reset();
    Object.assign(memberForm, {
        name: member.name,
        position: member.position,
        email: member.email || '',
        display_order: member.display_order || 0,
        image: null,
    });
    showMemberModal.value = true;
}

function submitMember() {
    if (editingMember.value) {
        memberForm
            .transform((data) => ({ ...data, _method: 'put' }))
            .post(route('org.chart.update', editingMember.value.id), {
                preserveScroll: true,
                onSuccess: () => {
                    showMemberModal.value = false;
                    editingMember.value = null;
                    memberForm.reset();
                },
            });
    } else {
        memberForm.post(route('org.chart.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                showMemberModal.value = false;
                memberForm.reset();
            },
        });
    }
}

function requestDeleteMember(member) {
    confirmDeleteMember.value = member;
}

function confirmDelete() {
    const member = confirmDeleteMember.value;
    useForm({}).delete(route('org.chart.destroy', member.id), {
        preserveScroll: true,
        onSuccess: () => { confirmDeleteMember.value = null; },
        onError: () => { confirmDeleteMember.value = null; },
    });
}
</script>

<template>
    <Head :title="`Info ${organization.name}`" />

    <AppLayout>
        <template #header>Info Organisasi</template>

        <div class="mx-auto max-w-6xl px-4 py-5 md:px-6 md:py-7">
            <!-- ─── Page intro + org switcher ───────────────────────────── -->
            <div class="flex flex-col gap-1">
                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-700">Direktori Gerakan</p>
                <h1 class="text-2xl font-black tracking-tight text-[#071525] md:text-3xl">Kenali Organisasi Anda</h1>
                <p class="max-w-2xl text-sm leading-relaxed text-gray-500">
                    Pilih organisasi untuk melihat maklumat dan carta kepimpinan PKPIM, ABIM dan WADAH.
                </p>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <button
                    v-for="item in organizations"
                    :key="item.slug"
                    type="button"
                    @click="selectOrganization(item)"
                    class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border bg-white p-3.5 text-left shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md"
                    :class="item.slug === organization.slug ? 'border-transparent ring-2' : 'border-gray-100'"
                    :style="{
                        '--accent': item.color_theme || '#10b981',
                        boxShadow: item.slug === organization.slug ? `0 12px 28px -14px ${hexToRgba(item.color_theme || '#10b981', 0.65)}` : undefined,
                        '--tw-ring-color': hexToRgba(item.color_theme || '#10b981', 0.55),
                    }"
                >
                    <div
                        class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl p-1.5"
                        :style="{ backgroundColor: hexToRgba(item.color_theme || '#10b981', 0.12) }"
                    >
                        <img v-if="item.logo_path" :src="item.logo_path" :alt="item.name" class="h-full w-full object-contain">
                        <span v-else class="text-lg font-black" :style="{ color: item.color_theme || '#10b981' }">{{ initials(item.name) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <p class="truncate text-sm font-bold text-[#071525]">{{ item.name }}</p>
                            <span
                                v-if="item.is_current"
                                class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                :style="{ backgroundColor: hexToRgba(item.color_theme || '#10b981', 0.14), color: item.color_theme || '#10b981' }"
                            >
                                Anda
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-gray-500">
                            {{ item.max_age === null || item.max_age === undefined ? `${item.min_age}+ tahun` : `${item.min_age}–${item.max_age} tahun` }}
                            · {{ item.member_count }} ahli
                        </p>
                    </div>
                    <span
                        class="grid h-6 w-6 shrink-0 place-items-center rounded-full transition"
                        :class="item.slug === organization.slug ? 'text-white' : 'bg-gray-100 text-gray-400 group-hover:bg-gray-200'"
                        :style="item.slug === organization.slug ? { backgroundColor: item.color_theme || '#10b981' } : undefined"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </button>
            </div>

            <!-- ─── Hero ─────────────────────────────────────────────────── -->
            <section
                class="relative mt-5 overflow-hidden rounded-[28px] p-6 text-white shadow-[0_24px_60px_-24px_rgba(7,21,37,0.55)] md:p-8"
                :style="{ background: `linear-gradient(135deg, #071525 0%, #123D2A 55%, ${accent} 145%)` }"
            >
                <div class="pointer-events-none absolute -right-16 -top-20 h-64 w-64 rounded-full border border-white/10"></div>
                <div class="pointer-events-none absolute -bottom-24 right-16 h-56 w-56 rounded-full bg-white/5 blur-2xl"></div>
                <div class="pointer-events-none absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 22px 22px;"></div>

                <div class="relative flex flex-col gap-6 md:flex-row md:items-center">
                    <div class="grid h-24 w-24 shrink-0 place-items-center overflow-hidden rounded-3xl border border-white/20 bg-white/95 p-3 shadow-lg md:h-28 md:w-28">
                        <img v-if="organization.logo_path" :src="organization.logo_path" :alt="`Logo ${organization.name}`" class="h-full w-full object-contain">
                        <span v-else class="text-4xl font-black" :style="{ color: accent }">{{ initials(organization.name) }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-3xl font-black tracking-tight md:text-4xl">{{ organization.name }}</h2>
                            <span v-if="isOwnOrganization" class="rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white backdrop-blur">
                                Organisasi Anda
                            </span>
                        </div>
                        <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-white/75">
                            <span class="inline-flex items-center gap-1.5">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-4a3 3 0 11-3-3" /></svg>
                                {{ selectedSummary.member_count ?? 0 }} ahli berdaftar
                            </span>
                            <span class="h-1 w-1 rounded-full bg-white/40"></span>
                            <span>{{ ageRangeLabel }}</span>
                        </p>
                        <p v-if="organization.description" class="mt-3 max-w-2xl overflow-hidden text-sm leading-relaxed text-white/70" style="display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;">
                            {{ organization.description }}
                        </p>
                    </div>

                    <div v-if="socialLinks.length" class="flex flex-wrap gap-2 md:max-w-[180px] md:justify-end">
                        <a
                            v-for="link in socialLinks"
                            :key="link.key"
                            :href="normalizeSocialUrl(link.href)"
                            target="_blank"
                            rel="noopener noreferrer"
                            :title="link.label"
                            class="grid h-10 w-10 place-items-center rounded-xl border border-white/15 bg-white/10 text-white/85 backdrop-blur transition hover:bg-white/20 hover:text-white"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="socialIconMap[link.key]" />
                            </svg>
                        </a>
                    </div>
                </div>

                <div class="relative mt-6 grid grid-cols-2 gap-3 sm:max-w-md">
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/55">Jumlah Ahli</p>
                        <p class="mt-1 text-xl font-black">{{ selectedSummary.member_count ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3 backdrop-blur">
                        <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/55">Barisan Kepimpinan</p>
                        <p class="mt-1 text-xl font-black">{{ chartMembers.length }}</p>
                    </div>
                </div>
            </section>

            <!-- ─── Tab Navigation ───────────────────────────────────────── -->
            <div class="mt-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-1.5 rounded-2xl border border-gray-100 bg-white p-1.5 shadow-sm">
                    <button
                        type="button"
                        @click="goTab('info')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
                        :class="currentTab === 'info' ? 'bg-[#071525] text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M12 21a9 9 0 100-18 9 9 0 000 18z" /></svg>
                        Maklumat
                    </button>
                    <button
                        type="button"
                        @click="goTab('chart')"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition-colors"
                        :class="currentTab === 'chart' ? 'bg-[#071525] text-white shadow-sm' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 17h18M7 7v10m10-10v10M8 17l8-10" /></svg>
                        Carta Organisasi
                    </button>
                </div>

                <p v-if="!isOwnOrganization" class="text-xs font-medium text-gray-400">
                    Paparan organisasi lain — pengurusan hanya untuk {{ organization.name }} oleh adminnya.
                </p>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!--  TAB: MAKLUMAT                                            -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <section v-if="currentTab === 'info'" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2 md:p-7">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-9 w-9 place-items-center rounded-xl" :style="{ backgroundColor: accentSoft, color: accent }">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10" /></svg>
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-[0.14em] text-gray-400">Tentang Organisasi</h2>
                        </div>
                        <button
                            v-if="canManage"
                            type="button"
                            @click="editingDescription = !editingDescription"
                            class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-50"
                        >
                            {{ editingDescription ? 'Batal' : 'Edit' }}
                        </button>
                    </div>

                    <form v-if="editingDescription" @submit.prevent="saveDescription" class="mt-4 space-y-3">
                        <textarea
                            v-model="descriptionForm.description"
                            rows="6"
                            class="w-full resize-none rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none transition-colors focus:border-emerald-400 focus:ring-0"
                            placeholder="Taip penerangan organisasi di sini..."
                        ></textarea>
                        <InputError :message="descriptionForm.errors.description" />
                        <button
                            type="submit"
                            :disabled="descriptionForm.processing"
                            class="rounded-xl bg-[#071525] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#123D2A] disabled:opacity-60"
                        >
                            {{ descriptionForm.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </form>

                    <p v-else-if="organization.description" class="mt-4 whitespace-pre-line text-[15px] leading-7 text-gray-700">
                        {{ organization.description }}
                    </p>
                    <div v-else class="mt-4 rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center">
                        <p class="text-sm text-gray-500">
                            {{ canManage ? 'Belum ada penerangan. Klik Edit untuk tambah maklumat organisasi.' : 'Tiada penerangan buat masa ini.' }}
                        </p>
                    </div>

                    <div v-if="socialLinks.length" class="mt-6 border-t border-gray-100 pt-5">
                        <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-gray-400">Hubungi & Media Sosial</h3>
                        <div class="mt-3 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <a
                                v-for="link in socialLinks"
                                :key="link.key"
                                :href="normalizeSocialUrl(link.href)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-3.5 py-3 text-sm font-semibold text-gray-700 transition-colors hover:border-gray-200 hover:bg-white hover:text-gray-900"
                            >
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-gray-500 shadow-sm">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="socialIconMap[link.key]" />
                                    </svg>
                                </span>
                                <span class="truncate">{{ link.label }}</span>
                                <svg class="ml-auto h-3.5 w-3.5 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 17L17 7M9 7h8v8" /></svg>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h3 class="text-xs font-bold uppercase tracking-[0.14em] text-gray-400">Maklumat Ringkas</h3>
                        <dl class="mt-3 space-y-3">
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500">Had Umur</dt>
                                <dd class="text-sm font-bold text-[#071525]">{{ ageRangeLabel }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500">Jumlah Ahli</dt>
                                <dd class="text-sm font-bold text-[#071525]">{{ selectedSummary.member_count ?? 0 }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-gray-50 px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500">Barisan Kepimpinan</dt>
                                <dd class="text-sm font-bold text-[#071525]">{{ chartMembers.length }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="rounded-3xl border p-5" :style="{ borderColor: accentLine, backgroundColor: accentSoft }">
                        <p class="text-sm font-bold" :style="{ color: accent }">Ahli {{ organization.name }}?</p>
                        <p class="mt-1.5 text-xs leading-relaxed text-gray-600">
                            Semua ahli boleh melihat info dan carta organisasi ketiga-tiga peringkat gerakan.
                        </p>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!--  TAB: CARTA ORGANISASI                                     -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <section v-else class="mt-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-[#071525] md:text-2xl">Carta Organisasi {{ organization.name }}</h2>
                        <p class="mt-1 text-sm text-gray-500">Barisan kepimpinan utama — tekan emel untuk hubungi.</p>
                    </div>
                    <button
                        v-if="canManage"
                        type="button"
                        @click="openAddMember"
                        class="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 transition-colors hover:bg-emerald-100"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
                        Tambah Ahli
                    </button>
                </div>

                <template v-if="chartMembers.length">
                    <!-- Featured leader -->
                    <div class="mt-4 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                        <div class="flex flex-col sm:flex-row">
                            <div
                                class="relative aspect-[4/5] w-full shrink-0 cursor-zoom-in overflow-hidden sm:w-56 md:w-64"
                                :style="{ backgroundColor: accentSoft }"
                                role="button"
                                tabindex="0"
                                :aria-label="`Lihat gambar ${featuredMember.name}`"
                                @click="openMemberPreview(featuredMember)"
                                @keydown.enter="openMemberPreview(featuredMember)"
                            >
                                <img
                                    v-if="featuredMember.image_path"
                                    :src="featuredMember.image_path"
                                    :alt="featuredMember.name"
                                    class="h-full w-full object-contain"
                                >
                                <div v-else class="grid h-full min-h-[280px] w-full place-items-center text-6xl font-black" :style="{ color: accent }">
                                    {{ initials(featuredMember.name) }}
                                </div>
                                <span class="absolute left-3 top-3 rounded-full bg-[#071525]/85 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white backdrop-blur">
                                    Pimpinan Utama
                                </span>
                                <span class="absolute bottom-3 right-3 grid h-8 w-8 place-items-center rounded-full bg-white/90 text-gray-600 shadow-sm">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                </span>
                                <div v-if="canManage" class="absolute right-2 top-2 flex gap-1.5" @click.stop>
                                    <button type="button" @click="openEditMember(featuredMember)" class="grid h-8 w-8 place-items-center rounded-xl bg-white/95 text-gray-600 shadow-sm hover:text-gray-900" title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                    </button>
                                    <button type="button" @click="requestDeleteMember(featuredMember)" class="grid h-8 w-8 place-items-center rounded-xl bg-white/95 text-red-500 shadow-sm hover:text-red-600" title="Padam">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                    </button>
                                </div>
                            </div>
                            <div class="flex flex-1 flex-col justify-center p-6 md:p-8">
                                <p class="text-[11px] font-bold uppercase tracking-[0.18em]" :style="{ color: accent }">{{ featuredMember.position }}</p>
                                <h3 class="mt-1.5 text-2xl font-black tracking-tight text-[#071525] md:text-3xl">{{ featuredMember.name }}</h3>
                                <p class="mt-2 max-w-lg text-sm leading-relaxed text-gray-500">
                                    Menerajui {{ organization.name }} bersama barisan kepimpinan organisasi.
                                </p>
                                <a
                                    v-if="featuredMember.email"
                                    :href="`mailto:${featuredMember.email}`"
                                    class="mt-4 inline-flex w-fit items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                    :style="{ backgroundColor: accent }"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    Hubungi
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Rest of chart -->
                    <div v-if="otherMembers.length" class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        <div
                            v-for="member in otherMembers"
                            :key="member.id"
                            class="group relative cursor-zoom-in overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md"
                            role="button"
                            tabindex="0"
                            :aria-label="`Lihat ${member.name}`"
                            @click="openMemberPreview(member)"
                            @keydown.enter="openMemberPreview(member)"
                        >
                            <div v-if="canManage" class="absolute right-2 top-2 z-10 flex gap-1.5" @click.stop>
                                <button type="button" @click="openEditMember(member)" class="grid h-7 w-7 place-items-center rounded-lg bg-white/95 text-gray-600 shadow-sm hover:text-gray-900" title="Edit">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                </button>
                                <button type="button" @click="requestDeleteMember(member)" class="grid h-7 w-7 place-items-center rounded-lg bg-white/95 text-red-500 shadow-sm hover:text-red-600" title="Padam">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </div>

                            <div class="relative aspect-[4/5] w-full overflow-hidden" :style="{ backgroundColor: accentSoft }">
                                <img
                                    v-if="member.image_path"
                                    :src="member.image_path"
                                    :alt="member.name"
                                    class="h-full w-full object-contain"
                                >
                                <div v-else class="grid h-full w-full place-items-center text-4xl font-black" :style="{ color: accent }">
                                    {{ initials(member.name) }}
                                </div>
                                <span class="absolute bottom-2 right-2 grid h-7 w-7 place-items-center rounded-full bg-white/90 text-gray-600 opacity-0 shadow-sm transition group-hover:opacity-100">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
                                </span>
                            </div>

                            <div class="p-3.5 text-center">
                                <p class="truncate text-sm font-bold text-[#071525]" :title="member.name">{{ member.name }}</p>
                                <p class="mt-0.5 truncate text-xs font-medium text-gray-500" :title="member.position">{{ member.position }}</p>
                                <a
                                    v-if="member.email"
                                    :href="`mailto:${member.email}`"
                                    class="mt-2 inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-semibold"
                                    :style="{ backgroundColor: accentSoft, color: accent }"
                                    :title="`Emel ${member.name}`"
                                    @click.stop
                                >
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                    Hubungi
                                </a>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-else class="mt-4 rounded-3xl border border-dashed border-gray-200 bg-white px-6 py-14 text-center">
                    <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl" :style="{ backgroundColor: accentSoft, color: accent }">
                        <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6-4a3 3 0 11-3-3" /></svg>
                    </div>
                    <p class="mt-3 text-base font-bold text-[#071525]">Carta organisasi belum tersedia</p>
                    <p class="mx-auto mt-1 max-w-md text-sm text-gray-500">
                        {{ canManage ? 'Klik butang Tambah Ahli untuk muat naik gambar, nama, jawatan dan emel kepimpinan.' : 'Pihak pentadbir organisasi belum menambah sebarang entri buat masa ini.' }}
                    </p>
                </div>
            </section>

            <!-- ── Member Preview Modal ──────────────────────────────────── -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="viewingMember"
                    class="fixed inset-0 z-50 flex items-center justify-center bg-[#071525]/70 p-4 backdrop-blur-sm"
                    @click.self="viewingMember = null"
                >
                    <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl" @click.stop>
                        <button
                            type="button"
                            class="absolute right-3 top-3 z-10 grid h-9 w-9 place-items-center rounded-full bg-white/90 text-gray-600 shadow-sm transition hover:bg-white hover:text-gray-900"
                            @click="viewingMember = null"
                            aria-label="Tutup"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>

                        <div class="max-h-[70vh] w-full overflow-hidden" :style="{ backgroundColor: accentSoft }">
                            <img
                                v-if="viewingMember.image_path"
                                :src="viewingMember.image_path"
                                :alt="viewingMember.name"
                                class="mx-auto block w-full object-contain" style="max-height:70vh"
                            >
                            <div v-else class="grid aspect-[4/5] w-full place-items-center text-7xl font-black" :style="{ color: accent }">
                                {{ initials(viewingMember.name) }}
                            </div>
                        </div>

                        <div class="p-6 text-center">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em]" :style="{ color: accent }">{{ viewingMember.position }}</p>
                            <h3 class="mt-1.5 text-2xl font-black tracking-tight text-[#071525]">{{ viewingMember.name }}</h3>
                            <a
                                v-if="viewingMember.email"
                                :href="`mailto:${viewingMember.email}`"
                                class="mt-4 inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                                :style="{ backgroundColor: accent }"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Hubungi
                            </a>
                        </div>
                    </div>
                </div>
            </transition>

            <!-- ── Add/Edit Member Modal ──────────────────────────────────── -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showMemberModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="showMemberModal = false">
                    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl" @click.stop>
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                            <h3 class="text-base font-bold text-[#071525]">
                                {{ editingMember ? 'Edit Ahli Carta' : 'Tambah Ahli Carta' }}
                            </h3>
                            <button @click="showMemberModal = false" class="rounded-xl p-2 text-gray-400 transition-colors hover:bg-gray-100">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <form @submit.prevent="submitMember" class="space-y-4 px-6 py-5">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Gambar</label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    @change="memberForm.image = $event.target.files[0]"
                                    class="w-full text-xs file:mr-2 file:rounded-xl file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                                >
                                <p v-if="memberForm.errors.image" class="mt-1 text-xs text-red-500">{{ memberForm.errors.image }}</p>
                                <p class="mt-1 text-[11px] text-gray-400">JPG, PNG atau WEBP (maksimum 2MB).</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Nama <span class="text-red-500">*</span></label>
                                <input v-model="memberForm.name" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm outline-none transition-colors focus:border-emerald-400 focus:ring-0" placeholder="Nama penuh" />
                                <p v-if="memberForm.errors.name" class="mt-1 text-xs text-red-500">{{ memberForm.errors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Jawatan <span class="text-red-500">*</span></label>
                                <input v-model="memberForm.position" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm outline-none transition-colors focus:border-emerald-400 focus:ring-0" placeholder="cth. Yang Dipertua" />
                                <p v-if="memberForm.errors.position" class="mt-1 text-xs text-red-500">{{ memberForm.errors.position }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Emel (Mailto)</label>
                                <input v-model="memberForm.email" type="email" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm outline-none transition-colors focus:border-emerald-400 focus:ring-0" placeholder="nama@contoh.com" />
                                <p v-if="memberForm.errors.email" class="mt-1 text-xs text-red-500">{{ memberForm.errors.email }}</p>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" :disabled="memberForm.processing" class="flex-1 rounded-2xl bg-[#071525] px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-[#123D2A] disabled:opacity-60">
                                    {{ memberForm.processing ? 'Menyimpan...' : (editingMember ? 'Kemas Kini' : 'Tambah') }}
                                </button>
                                <button type="button" @click="showMemberModal = false" class="flex-1 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                                    Batal
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </transition>

            <!-- ── Confirm Delete Modal ───────────────────────────────────── -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="confirmDeleteMember" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm" @click.self="confirmDeleteMember = null">
                    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" @click.stop>
                        <h3 class="text-base font-bold text-[#071525]">Padam entri carta?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Anda pasti mahu memadam <strong>"{{ confirmDeleteMember.name }}"</strong> daripada Carta Organisasi? Tindakan ini tidak boleh dibatalkan.
                        </p>
                        <div class="mt-5 flex items-center gap-3">
                            <button @click="confirmDelete" class="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-red-700">
                                Ya, Padam
                            </button>
                            <button @click="confirmDeleteMember = null" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-colors hover:bg-gray-50">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </AppLayout>
</template>

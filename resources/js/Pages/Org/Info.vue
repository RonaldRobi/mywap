<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    chartMembers: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
});

const page = usePage();
const currentTab = computed(() => {
    const url = page.url;
    const q = url.includes('?') ? url.split('?')[1] : '';
    const params = new URLSearchParams(q);
    return params.get('tab') || 'info';
});

function goTab(tab) {
    if (tab === currentTab.value) return;
    router.get(route('org.info'), { tab }, { preserveState: true, preserveScroll: true });
}

// ─── Description (Maklumat) ────────────────────────────────────────────────
const editingDescription = ref(false);
const descriptionForm = useForm({ description: props.organization.description || '' });

function saveDescription() {
    descriptionForm.put(route('org.info.update'), {
        preserveScroll: true,
        onSuccess: () => { editingDescription.value = false; },
    });
}

const socialLinks = computed(() => {
    const o = props.organization;
    return [
        { key: 'website_url', label: 'Laman Web', href: o.website_url },
        { key: 'facebook_url', label: 'Facebook', href: o.facebook_url },
        { key: 'instagram_url', label: 'Instagram', href: o.instagram_url },
        { key: 'twitter_url', label: 'X (Twitter)', href: o.twitter_url },
        { key: 'youtube_url', label: 'YouTube', href: o.youtube_url },
        { key: 'tiktok_url', label: 'TikTok', href: o.tiktok_url },
    ].filter(s => s.href);
});

function normalizeSocialUrl(url) {
    if (!url) return url;
    return /^https?:\/\//i.test(url) ? url : `https://${url}`;
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
            .transform((data) => ({
                ...data,
                _method: 'put',
            }))
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
        <template #header>Info {{ organization.name }}</template>

        <div class="mx-auto max-w-7xl px-4 py-5 md:px-6 md:py-6">
            <!-- ─── Tab Navigation ─────────────────────────────────────────── -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-2 bg-white rounded-2xl border border-gray-100 p-1.5 shadow-sm">
                    <button
                        type="button"
                        @click="goTab('info')"
                        :class="[
                            'rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                            currentTab === 'info'
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                        ]"
                    >
                        Maklumat
                    </button>
                    <button
                        type="button"
                        @click="goTab('chart')"
                        :class="[
                            'rounded-xl px-4 py-2 text-sm font-semibold transition-colors',
                            currentTab === 'chart'
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                        ]"
                    >
                        Carta Organisasi
                    </button>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!--  TAB: MAKLUMAT                                            -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <section v-if="currentTab === 'info'" class="mt-5">
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm md:p-8">
                    <div class="flex flex-col items-start gap-6 md:flex-row md:items-center">
                        <div
                            v-if="organization.logo_path"
                            class="grid h-28 w-28 shrink-0 place-items-center overflow-hidden rounded-3xl border border-gray-100 bg-gray-50 p-3"
                        >
                            <img :src="organization.logo_path" :alt="`Logo ${organization.name}`" class="h-full w-full object-contain">
                        </div>
                        <div v-else class="grid h-28 w-28 shrink-0 place-items-center rounded-3xl bg-emerald-50 text-3xl font-black text-emerald-700">
                            {{ organization.name.charAt(0) }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <h1 class="text-2xl font-black text-gray-900 md:text-3xl">{{ organization.name }}</h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Persatuan induk ahli myWAP anda.
                            </p>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mt-8">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Tentang Organisasi</h2>
                            <button
                                v-if="canManage"
                                type="button"
                                @click="editingDescription = !editingDescription"
                                class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                {{ editingDescription ? 'Batal' : 'Edit' }}
                            </button>
                        </div>

                        <form v-if="editingDescription" @submit.prevent="saveDescription" class="mt-3 space-y-3">
                            <textarea
                                v-model="descriptionForm.description"
                                rows="5"
                                class="w-full resize-none rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition-colors"
                                placeholder="Taip penerangan organisasi di sini..."
                            ></textarea>
                            <InputError :message="descriptionForm.errors.description" />
                            <div class="flex items-center gap-3">
                                <button
                                    type="submit"
                                    :disabled="descriptionForm.processing"
                                    class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 transition-colors"
                                >
                                    {{ descriptionForm.processing ? 'Menyimpan...' : 'Simpan' }}
                                </button>
                            </div>
                        </form>

                        <p v-else-if="organization.description" class="mt-3 whitespace-pre-line text-sm leading-relaxed text-gray-700">
                            {{ organization.description }}
                        </p>
                        <p v-else class="mt-3 text-sm text-gray-400">
                            {{ canManage ? 'Belum ada penerangan. Klik Edit untuk tambah maklumat organisasi.' : 'Tiada penerangan buat masa ini.' }}
                        </p>
                    </div>

                    <!-- Social links -->
                    <div v-if="socialLinks.length" class="mt-8">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-gray-400">Hubungi & Media Sosial</h2>
                        <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <a
                                v-for="link in socialLinks"
                                :key="link.key"
                                :href="normalizeSocialUrl(link.href)"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                            >
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white border border-gray-100 text-gray-500">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-2 2a4 4 0 01-5.656-5.656l2-2" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.172 13.828a4 4 0 010-5.656l2-2a4 4 0 015.656 5.656l-2 2" />
                                    </svg>
                                </span>
                                <span class="truncate">{{ link.label }}</span>
                                <span class="ms-auto shrink-0 text-xs text-gray-400">↗</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!--  TAB: CARTA ORGANISASI                                     -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <section v-else class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-black text-gray-900 md:text-2xl">Carta Organisasi {{ organization.name }}</h1>
                        <p class="mt-1 text-sm text-gray-500">Senarai jawatan utama — klik emel untuk hubungi.</p>
                    </div>
                    <button
                        v-if="canManage"
                        type="button"
                        @click="openAddMember"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition-colors"
                    >
                        + Tambah Ahli
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <div
                        v-for="member in chartMembers"
                        :key="member.id"
                        class="group relative overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                    >
                        <!-- Admin actions -->
                        <div v-if="canManage" class="absolute right-2 top-2 z-10 flex gap-1.5">
                            <button
                                type="button"
                                @click="openEditMember(member)"
                                class="grid h-8 w-8 place-items-center rounded-xl bg-white/95 text-gray-500 shadow-sm hover:text-gray-800 transition-colors"
                                title="Edit"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                            </button>
                            <button
                                type="button"
                                @click="requestDeleteMember(member)"
                                class="grid h-8 w-8 place-items-center rounded-xl bg-white/95 text-red-500 shadow-sm hover:text-red-600 transition-colors"
                                title="Padam"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                            </button>
                        </div>

                        <!-- Photo -->
                        <div class="aspect-[3/4] w-full overflow-hidden bg-gray-100">
                            <img
                                v-if="member.image_path"
                                :src="member.image_path"
                                :alt="member.name"
                                class="h-full w-full object-cover object-center transition duration-300 group-hover:scale-[1.03]"
                            >
                            <div v-else class="grid h-full w-full place-items-center bg-emerald-50 text-4xl font-black text-emerald-600">
                                {{ (member.name || '?').charAt(0).toUpperCase() }}
                            </div>
                        </div>

                        <!-- Details -->
                        <div class="p-3.5 text-center">
                            <p class="truncate text-sm font-bold text-gray-900" :title="member.name">{{ member.name }}</p>
                            <p class="mt-0.5 truncate text-xs font-medium text-gray-500" :title="member.position">{{ member.position }}</p>
                            <a
                                v-if="member.email"
                                :href="`mailto:${member.email}`"
                                class="mt-2 inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100 transition-colors"
                                :title="`Emel ${member.name}`"
                            >
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                Hubungi
                            </a>
                        </div>
                    </div>

                    <div
                        v-if="!chartMembers.length"
                        class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-12 text-center text-sm text-gray-500"
                    >
                        <p class="text-lg font-semibold text-gray-700">Tiada entri carta organisasi</p>
                        <p v-if="canManage" class="mt-1">Klik <strong>+ Tambah Ahli</strong> untuk muat naik gambar, nama, jawatan dan emel.</p>
                        <p v-else class="mt-1">Ahli organisasi belum menambah sebarang entri buat masa ini.</p>
                    </div>
                </div>
            </section>

            <!-- ── Add/Edit Member Modal ──────────────────────────────────── -->
            <transition
                enter-active-class="transition ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showMemberModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="showMemberModal = false">
                    <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl" @click.stop>
                        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-5">
                            <h3 class="text-base font-bold text-gray-900">
                                {{ editingMember ? 'Edit Ahli Carta' : 'Tambah Ahli Carta' }}
                            </h3>
                            <button @click="showMemberModal = false" class="p-2 rounded-xl text-gray-400 hover:bg-gray-100 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
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
                                <input v-model="memberForm.name" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition-colors" placeholder="Nama penuh" />
                                <p v-if="memberForm.errors.name" class="mt-1 text-xs text-red-500">{{ memberForm.errors.name }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Jawatan <span class="text-red-500">*</span></label>
                                <input v-model="memberForm.position" type="text" required class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition-colors" placeholder="cth. Yang Dipertua" />
                                <p v-if="memberForm.errors.position" class="mt-1 text-xs text-red-500">{{ memberForm.errors.position }}</p>
                            </div>

                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Emel (Mailto)</label>
                                <input v-model="memberForm.email" type="email" class="w-full rounded-2xl border border-gray-200 px-3 py-2.5 text-sm focus:border-emerald-400 focus:ring-0 outline-none transition-colors" placeholder="nama@contoh.com" />
                                <p v-if="memberForm.errors.email" class="mt-1 text-xs text-red-500">{{ memberForm.errors.email }}</p>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" :disabled="memberForm.processing" class="flex-1 rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-60 transition-colors">
                                    {{ memberForm.processing ? 'Menyimpan...' : (editingMember ? 'Kemas Kini' : 'Tambah') }}
                                </button>
                                <button type="button" @click="showMemberModal = false" class="flex-1 rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
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
                <div v-if="confirmDeleteMember" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="confirmDeleteMember = null">
                    <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" @click.stop>
                        <h3 class="text-base font-bold text-gray-900">Padam entri carta?</h3>
                        <p class="mt-2 text-sm text-gray-600">
                            Anda pasti mahu memadam <strong>"{{ confirmDeleteMember.name }}"</strong> daripada Carta Organisasi? Tindakan ini tidak boleh dibatalkan.
                        </p>
                        <div class="mt-5 flex items-center gap-3">
                            <button @click="confirmDelete" class="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                                Ya, Padam
                            </button>
                            <button @click="confirmDeleteMember = null" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </transition>
        </div>
    </AppLayout>
</template>

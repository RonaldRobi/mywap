<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    event: Object,
    forms: Array,
    buildFormUrl: String,
    registrationsUrl: String,
    attendanceUrl: String,
    editUrl: String,
    qrUrl: { type: String, default: null },
});

const isPublished = () => props.event?.status === 'published';
const hasForms = computed(() => (props.forms?.length ?? 0) > 0);
const primaryForm = computed(() => (props.forms?.length === 1 ? props.forms[0] : null));

const statusColor = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-red-50 text-red-600 border-red-200',
};

function windowDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('ms-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

// ─── Salin pautan pendaftaran (bila hanya ada satu borang) ────────────────
const registrationCopied = ref(false);

function copyRegistrationLink() {
    const url = primaryForm.value?.public_url;
    if (!url) return;

    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            registrationCopied.value = true;
            setTimeout(() => { registrationCopied.value = false; }, 1500);
        });
    } else {
        window.prompt('Salin pautan ini:', url);
    }
}

const registrationQrUrl = computed(() => route('events.share-qr.download', props.event.id));

// ─── Kongsi borang (QR + salin pautan) ─────────────────────────────────────
const shareForm = ref(null);
const copied = ref(false);

function openShare(form) {
    shareForm.value = form;
    copied.value = false;
}

function copyShareLink() {
    const url = shareForm.value?.public_url || '';
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(url).then(() => {
            copied.value = true;
            setTimeout(() => { copied.value = false; }, 1500);
        });
    } else {
        window.prompt('Salin pautan ini:', url);
    }
}

function deleteEvent() {
    if (!confirm(`Padam program "${props.event.title}"?\n\nSemua borang, pendaftaran dan kehadiran program ini juga akan dipadam. Tindakan ini tidak boleh dibatalkan.`)) return;
    router.delete(route('events.destroy', props.event.id));
}

// ─── Kongsi Program (pautan umum) ─────────────────────────────────────────
const eventShareOpen = ref(false);

function shareProgram() {
    eventShareOpen.value = true;
}

function closeShareProgram() {
    eventShareOpen.value = false;
}

function eventShareUrl() {
    return route('share.event', props.event?.id, true);
}
</script>

<template>
    <Head :title="event.title" />

    <AppLayout>
        <div class="max-w-5xl mx-auto px-4 py-8 space-y-6">
            <!-- ─── Header (maklumat program) ─────────────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-hidden">
                <div class="grid grid-cols-1 lg:grid-cols-[300px_1fr]">
                    <div class="aspect-[4/5] bg-gray-100 overflow-hidden">
                        <img :src="event.featured_image_url" :alt="event.title" class="w-full h-full object-cover" />
                    </div>
                    <div class="p-6">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="statusColor[event.status] ?? statusColor.draft">
                                {{ event.status_label }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-bold text-gray-600">
                                {{ event.category_label }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-bold text-gray-600">
                                {{ event.type === 'physical' ? 'Fizikal' : 'Dalam Talian' }}
                            </span>
                        </div>
                        <h1 class="text-2xl font-black text-gray-900">{{ event.title }}</h1>
                        <p class="text-sm text-gray-500 mt-1">{{ event.start_formatted }} · {{ event.location_or_link || '—' }}</p>
                        <p class="text-sm text-gray-400 mt-0.5">{{ event.organization_name }}</p>
                        <p v-if="event.organizations.length > 1" class="text-xs text-gray-400 mt-0.5">Terlibat: {{ event.organizations.join(', ') }}</p>

                        <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-bold">
                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-700">
                                Peserta unik: {{ event.unique_participants_count }}
                            </span>
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-emerald-700">
                                Hadir: {{ event.attended_count }}
                            </span>
                            <span
                                v-if="event.registrations_count > event.unique_participants_count"
                                class="rounded-full bg-amber-50 px-2.5 py-1 text-amber-700"
                                title="Rekod berulang daripada import/borang yang sama"
                            >
                                {{ event.registrations_count - event.unique_participants_count }} rekod pendua
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Amaran belum diterbitkan ─────────────────────────────── -->
            <div v-if="!isPublished()" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 text-sm text-amber-800">
                    <p class="font-bold">Program ini masih berstatus {{ event.status_label }}</p>
                    <p class="text-amber-700 mt-0.5 text-xs">
                        Pautan &amp; QR pendaftaran <strong>tidak akan berfungsi</strong> untuk pengguna (paparan 404) sehingga program diterbitkan.
                    </p>
                </div>
                <a :href="editUrl" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 transition">
                    Terbitkan Sekarang
                </a>
            </div>

            <!-- ─── Tetingkap pendaftaran & kehadiran ────────────────────── -->
            <div class="rounded-2xl border border-sky-100 bg-sky-50 px-4 py-3.5 text-sm text-sky-800">
                <p class="font-bold">Pendaftaran dibuka sebaik program diterbitkan, ditutup 24 jam selepas program tamat</p>
                <p class="mt-0.5 text-xs text-sky-700">
                    {{ event.registration_open ? 'Pendaftaran sedang dibuka' : 'Pendaftaran telah ditutup' }}
                    <template v-if="event.registration_closes_at"> &middot; sehingga {{ windowDate(event.registration_closes_at) }}</template>
                </p>
                <p class="mt-1 text-xs text-sky-700">
                    QR kehadiran: {{ windowDate(event.attendance_opens_at) }} hingga {{ windowDate(event.attendance_closes_at) }} (24 jam sebelum hingga 24 jam selepas program).
                </p>
            </div>

            <!-- ─── Langkah seterusnya (checklist) ───────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-4">Langkah Seterusnya</h2>

                <div class="space-y-4">
                    <!-- 1. Terbitkan -->
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="isPublished() ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                            <svg v-if="isPublished()" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span v-else>1</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-800">Terbitkan program</p>
                            <p class="text-xs text-gray-500">Program mesti diterbitkan sebelum pautan/QR boleh dibuka orang awam.</p>
                        </div>
                        <a v-if="!isPublished()" :href="editUrl" class="shrink-0 rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700">Terbitkan</a>
                        <span v-else class="shrink-0 text-xs font-bold text-emerald-600">Selesai</span>
                    </div>

                    <!-- 2. Cipta borang -->
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="hasForms ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                            <svg v-if="hasForms" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span v-else>2</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-800">Cipta borang pendaftaran</p>
                            <p class="text-xs text-gray-500">Borang ini yang akan diisi peserta (percuma atau berbayar).</p>
                        </div>
                        <a v-if="!hasForms" :href="buildFormUrl" class="shrink-0 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-indigo-700">Cipta Borang</a>
                        <span v-else class="shrink-0 text-xs font-bold text-emerald-600">Selesai</span>
                    </div>

                    <!-- 3. Kongsi pautan -->
                    <div class="flex items-start gap-3">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" :class="hasForms ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                            <span>3</span>
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-800">Kongsi pautan pendaftaran</p>
                            <p class="text-xs text-gray-500">Hantar pautan/QR kepada peserta melalui WhatsApp, Facebook atau Telegram.</p>
                        </div>
                        <button
                            v-if="hasForms"
                            type="button"
                            @click="primaryForm ? copyRegistrationLink() : null"
                            :disabled="!primaryForm"
                            class="shrink-0 rounded-xl bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            :title="primaryForm ? 'Salin pautan pendaftaran' : 'Lihat senarai borang di bawah'"
                        >
                            {{ primaryForm ? (registrationCopied ? 'Disalin!' : 'Salin Link') : 'Pilih Borang' }}
                        </button>
                        <span v-else class="shrink-0 text-xs font-semibold text-gray-400">Belum ada borang</span>
                    </div>
                </div>
            </div>

            <!-- ─── QR (dua jenis berbeza) ───────────────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-black text-gray-900">QR</h2>
                <p class="text-sm text-gray-500 mb-4">Ada dua QR berbeza — pilih mengikut tujuan.</p>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- QR 1: untuk dikongsi (pendaftaran) -->
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/40 p-4">
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">QR Pendaftaran</p>
                        <p class="mt-1 text-sm font-bold text-gray-800">Untuk admin kongsikan</p>
                        <p class="mt-0.5 text-xs text-gray-500">Peserta imbas/klik untuk <strong>mendaftar</strong>. Kongsi di WhatsApp/Facebook atau cetak pada poster.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template v-if="hasForms && event.registration_open">
                                <a :href="registrationQrUrl" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Muat Turun QR Pendaftaran</a>
                                <button
                                    type="button"
                                    @click="primaryForm ? openShare(primaryForm) : null"
                                    :disabled="!primaryForm"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Kongsi Borang
                                </button>
                            </template>
                            <span v-else-if="hasForms" class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-amber-700">
                                Pendaftaran telah ditutup. QR pendaftaran tidak lagi tersedia.
                            </span>
                            <span v-else class="text-xs text-gray-400">Cipta borang dahulu untuk jana QR ini.</span>
                        </div>
                    </div>

                    <!-- QR 2: untuk semak masuk (kehadiran) -->
                    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
                        <p class="text-xs font-bold uppercase tracking-widest text-indigo-600">QR Semak Masuk</p>
                        <p class="mt-1 text-sm font-bold text-gray-800">Untuk peserta imbas semasa hadir</p>
                        <p class="mt-0.5 text-xs text-gray-500">Panjangkan di kaunter/projektor. Peserta imbas untuk <strong>merekod kehadiran</strong> pada hari acara.</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <template v-if="qrUrl">
                                <a :href="qrUrl" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Buka QR Semak Masuk</a>
                                <a :href="route('events.qr.download', event.id)" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Muat Turun</a>
                                <a :href="route('events.qr.print', event.id)" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Cetak</a>
                            </template>
                            <p v-else class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-amber-700">
                                QR kehadiran telah tamat tempoh (24 jam selepas program tamat).
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Sebelum acara: Daftar & Kongsi ───────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Sebelum Acara: Daftar &amp; Kongsi</h2>
                        <p class="text-sm text-gray-500">Pautan &amp; QR untuk peserta mendaftar.</p>
                    </div>
                    <a :href="buildFormUrl" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Cipta Borang
                    </a>
                </div>

                <div class="flex flex-wrap gap-2 mb-3">
                    <button
                        type="button"
                        @click="copyRegistrationLink"
                        :disabled="!primaryForm"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{ registrationCopied ? 'Link Disalin!' : 'Salin Pautan Pendaftaran' }}
                    </button>
                    <button
                        type="button"
                        @click="shareProgram"
                        title="Pautan umum program (bukan borang pendaftaran)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        Kongsi Program
                    </button>
                </div>

                <p v-if="forms.length > 1" class="text-xs text-amber-600">
                    Ada {{ forms.length }} borang. Pilih borang berkaitan di bawah dan tekan <strong>Kongsi Borang</strong>.
                </p>
                <p v-else-if="forms.length === 0" class="text-xs text-amber-600">
                    Belum ada borang. Klik <strong>Cipta Borang</strong> untuk dapatkan pautan/QR pendaftaran.
                </p>

                <!-- Senarai borang -->
                <div v-if="forms.length === 0" class="mt-4 rounded-2xl bg-gray-50 border border-gray-100 p-8 text-center">
                    <p class="text-sm text-gray-500">Belum ada borang pendaftaran untuk program ini.</p>
                    <p class="text-xs text-gray-400 mt-1">Klik "Cipta Borang" untuk tambah.</p>
                </div>

                <div v-else class="mt-4 space-y-3">
                    <div v-for="f in forms" :key="f.id" class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 flex flex-col sm:flex-row sm:items-center gap-3 sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-gray-800">{{ f.title }}</p>
                                <span v-if="!f.is_active" class="inline-flex items-center rounded-full border border-gray-200 bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-500">Tidak Aktif</span>
                            </div>
                            <p v-if="f.description" class="text-xs text-gray-500 mt-0.5 line-clamp-1">{{ f.description }}</p>
                            <div class="flex flex-wrap gap-2 mt-1.5 text-[11px] text-gray-500">
                                <span class="rounded-lg bg-white border border-gray-100 px-2 py-0.5">Soalan: {{ f.questions_count }}</span>
                                <span class="rounded-lg bg-white border border-gray-100 px-2 py-0.5">Respons: {{ f.responses_count }}</span>
                                <template v-if="f.payment_required">
                                    <span v-if="f.price_tiers?.length" class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-0.5 font-bold text-emerald-700">
                                        {{ f.price_tiers.map(t => `${t.label} RM${Number(t.price).toFixed(2)}`).join(' · ') }}
                                    </span>
                                    <span v-else-if="f.price" class="rounded-lg bg-emerald-50 border border-emerald-100 px-2 py-0.5 font-bold text-emerald-600">RM {{ Number(f.price).toFixed(2) }}</span>
                                </template>
                                <span v-else class="rounded-lg bg-white border border-gray-100 px-2 py-0.5">Percuma</span>
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button @click="openShare(f)" title="Pautan & QR pendaftaran untuk dikongsi" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Kongsi Borang</button>
                            <a :href="f.edit_url" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Edit Borang</a>
                            <a :href="f.public_url" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-800">Lihat Borang</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── Hari acara: Peserta & Kehadiran ──────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-black text-gray-900">Hari Acara: Peserta &amp; Kehadiran</h2>
                <p class="text-sm text-gray-500 mb-4">Panjangkan QR ini di kaunter/projektor untuk peserta imbas semasa mendaftar masuk.</p>

                <div class="flex flex-wrap gap-2">
                    <a :href="registrationsUrl" title="Senarai pendaftaran & import CSV" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Senarai Peserta ({{ event.unique_participants_count }})
                    </a>
                    <a :href="attendanceUrl" title="Buka dashboard kehadiran (program ini)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Kehadiran ({{ event.attended_count }})
                    </a>
                </div>
                <p class="mt-2 text-[11px] text-gray-400">
                    Untuk merekod kehadiran, buka <strong>QR Semak Masuk</strong> di bahagian QR di atas.
                </p>
            </div>

            <!-- ─── Urusan ──────────────────────────────────────────────── -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500 mb-3">Urusan</h2>
                <div class="flex flex-wrap gap-2">
                    <a :href="editUrl" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit Program</a>
                    <a :href="route('events.show', event.slug)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Lihat (Awam)</a>
                    <button
                        @click="deleteEvent"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-red-100 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Padam
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── Modal Kongsi Program (pautan umum) ──────────────────────── -->
        <Teleport to="body">
            <div v-if="eventShareOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm" @click.self="closeShareProgram">
                <div class="w-full max-w-md rounded-3xl border border-white/50 bg-white/95 shadow-2xl p-6">
                    <div class="flex items-start justify-between gap-3 mb-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-black text-gray-800">Kongsi Program</h3>
                            <p class="text-sm text-gray-500 truncate mt-0.5">{{ event.title }}</p>
                        </div>
                        <button @click="closeShareProgram" class="rounded-full p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-if="!isPublished()" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-left mb-4">
                        <p class="text-xs font-bold text-amber-800">Program belum diterbitkan</p>
                        <p class="text-[11px] text-amber-700 mt-0.5">Pengguna akan melihat 404 apabila membuka pautan ini. Sila terbitkan program dahulu.</p>
                    </div>

                    <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 mb-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Pautan Umum Program</p>
                        <p class="text-xs text-gray-600 break-all">{{ eventShareUrl() }}</p>
                    </div>

                    <SocialShareButtons :title="event.title" :url="eventShareUrl()" />
                    <p class="mt-3 text-[11px] text-gray-400">
                        Ini pautan <strong>program</strong>. Untuk jemput orang mendaftar, gunakan <strong>Kongsi Borang</strong>.
                    </p>
                </div>
            </div>
        </Teleport>

        <!-- ─── Modal Kongsi Borang (QR + Salin Pautan) ─────────────────── -->
        <div v-if="shareForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="shareForm = null">
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl text-center space-y-4">
                <p class="text-sm font-bold text-gray-800">Kongsi Borang — {{ shareForm.title }}</p>
                <div v-if="!isPublished()" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2.5 text-left">
                    <p class="text-xs font-bold text-amber-800">Program belum diterbitkan</p>
                    <p class="text-[11px] text-amber-700 mt-0.5">Pengguna akan melihat 404 apabila membuka pautan/QR ini. Sila terbitkan program dahulu.</p>
                </div>
                <div class="mx-auto flex justify-center rounded-2xl border border-gray-100 bg-white p-4">
                    <div class="w-48 h-48 [&_svg]:w-full [&_svg]:h-full" v-html="shareForm.qr_svg"></div>
                </div>
                <p class="text-xs text-gray-500 break-all">{{ shareForm.public_url }}</p>
                <div class="flex gap-2">
                    <button @click="copyShareLink" class="flex-1 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                        {{ copied ? 'Disalin!' : 'Salin Pautan' }}
                    </button>
                    <button @click="shareForm = null" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Tutup</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

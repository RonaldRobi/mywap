<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    event: Object,
    forms: Array,
    buildFormUrl: String,
    registrationsUrl: String,
    attendanceUrl: String,
    editUrl: String,
    qrUrl: String,
});

const isPublished = () => props.event?.status === 'published';

const statusColor = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-red-50 text-red-600 border-red-200',
};

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

// ─── Kongsi Program (popup platform) ────────────────────────────────────────

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
        <div class="max-w-5xl mx-auto px-4 py-8">
            <!-- Header -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-hidden mb-6">
                <div class="grid grid-cols-1 lg:grid-cols-[340px_1fr]">
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

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            @click="shareProgram"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 6l-4-4-4 4M12 2v12"/></svg>
                            Kongsi
                        </button>
                        <a :href="editUrl" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</a>
                        <a :href="route('events.show', event.slug)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Lihat Awam</a>
                        <a :href="qrUrl" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">QR Kehadiran</a>
                        <a :href="registrationsUrl" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Peserta ({{ event.registrations_count }})</a>
                        <a :href="attendanceUrl" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Dashboard Kehadiran</a>
                        <button
                            @click="deleteEvent"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-red-100 bg-red-50 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-100 transition"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Padam
                        </button>
                    </div>
                </div>
                </div>
            </div>

            <!-- Amaran: event belum diterbitkan -->
            <div v-if="!isPublished()" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3.5 mb-6 flex flex-col sm:flex-row sm:items-center gap-3">
                <div class="flex-1 text-sm text-amber-800">
                    <p class="font-bold flex items-center gap-2">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                        Program ini masih berstatus {{ event.status_label }}
                    </p>
                    <p class="text-amber-700 mt-0.5">
                        QR &amp; pautan kongsi borang pendaftaran di bawah <strong>tidak akan berfungsi</strong> untuk pengguna (paparan 404) sehingga program diterbitkan.
                    </p>
                </div>
                <a :href="editUrl" class="shrink-0 inline-flex items-center gap-1.5 rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white hover:bg-amber-700 transition">
                    Terbitkan Sekarang
                </a>
            </div>

            <!-- Borang Pendaftaran -->
            <div class="rounded-3xl bg-white border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-lg font-black text-gray-900">Borang Pendaftaran</h2>
                        <p class="text-sm text-gray-500">Setiap borang = satu pendaftaran (cth: Borang ABIM, Borang PKPIM).</p>
                    </div>
                    <a :href="buildFormUrl" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Cipta Borang Pendaftaran
                    </a>
                </div>

                <div v-if="forms.length === 0" class="rounded-2xl bg-gray-50 border border-gray-100 p-8 text-center">
                    <p class="text-sm text-gray-500">Belum ada borang pendaftaran untuk program ini.</p>
                    <p class="text-xs text-gray-400 mt-1">Klik "Cipta Borang Pendaftaran" untuk tambah (cth: Borang ABIM, Borang PKPIM, Borang WADAH).</p>
                </div>

                <div v-else class="space-y-3">
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
                            <button @click="openShare(f)" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Kongsi</button>
                            <a :href="f.edit_url" class="rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">Edit Borang</a>
                            <a :href="f.public_url" target="_blank" rel="noopener noreferrer" class="rounded-xl bg-gray-900 px-3 py-2 text-xs font-bold text-white hover:bg-gray-800">Pratonton</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Kongsi Program (popup platform) -->
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
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1">Pautan Kongsi</p>
                        <p class="text-xs text-gray-600 break-all">{{ eventShareUrl() }}</p>
                    </div>

                    <SocialShareButtons :title="event.title" :url="eventShareUrl()" />
                </div>
            </div>
        </Teleport>

        <!-- Modal Kongsi (QR + Salin Pautan) -->
        <div v-if="shareForm" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" @click.self="shareForm = null">
            <div class="w-full max-w-sm rounded-3xl bg-white p-6 shadow-2xl text-center space-y-4">
                <p class="text-sm font-bold text-gray-800">Kongsi — {{ shareForm.title }}</p>
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

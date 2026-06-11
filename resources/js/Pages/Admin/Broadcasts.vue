<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    recentMessages: { type: Array, default: () => [] },
    usrahGroups: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    defaultOrganizationId: Number,
    isSuperadmin: Boolean,
});

const activeTab = ref('broadcast'); // 'broadcast' or 'announcement'

// ─── Broadcast Notifications (Push/Email) ────────────────────────────────────
const broadcastForm = useForm({
    title: '',
    content: '',
    target_criteria: 'all',
    usrah_group_id: '',
});

function submitBroadcast() {
    broadcastForm.post(route('admin.broadcasts.store'), {
        preserveScroll: true,
        onSuccess: () => broadcastForm.reset('title', 'content', 'target_criteria', 'usrah_group_id'),
    });
}

// ─── Announcements (Pengumuman System) ───────────────────────────────────────
const announcementForm = useForm({
    organization_id: props.defaultOrganizationId,
    title: '',
    content: '',
    is_pinned: false,
    published_at: '',
});

const editingAnnouncementId = ref(null);
const announcementEditForm = useForm({
    title: '',
    content: '',
    is_pinned: false,
    published_at: '',
});

function submitAnnouncement() {
    announcementForm.post(route('admin.hub.announcements.store'), {
        preserveScroll: true,
        onSuccess: () => announcementForm.reset('title', 'content', 'is_pinned', 'published_at'),
    });
}

function toDateTimeLocal(value) {
    if (!value) return '';
    return value.replace(' ', 'T').slice(0, 16);
}

function startEditAnnouncement(item) {
    editingAnnouncementId.value = item.id;
    announcementEditForm.title = item.title ?? '';
    announcementEditForm.content = item.content ?? '';
    announcementEditForm.is_pinned = !!item.is_pinned;
    announcementEditForm.published_at = toDateTimeLocal(item.published_at);
}

function cancelEditAnnouncement() {
    editingAnnouncementId.value = null;
    announcementEditForm.reset();
}

function submitEditAnnouncement(item) {
    announcementEditForm.put(route('admin.hub.announcements.update', item.id), {
        preserveScroll: true,
        onSuccess: () => cancelEditAnnouncement(),
    });
}

function removeAnnouncement(id) {
    if (!confirm('Pastikan anda mahu memadam pengumuman ini?')) return;
    router.delete(route('admin.hub.announcements.destroy', id), { preserveScroll: true });
}

function togglePinned(id) {
    router.patch(route('admin.hub.announcements.pin', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Broadcast & Pengumuman" />

    <AppLayout>
        <template #header>Broadcast & Pengumuman</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition-all duration-300">
                <span class="flex items-center gap-2">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                    {{ $page.props.flash.success }}
                </span>
            </div>

            <!-- Page Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div>
                    <h2 class="text-3xl font-black tracking-tight text-gray-900">Pusat Hebahan Maklumat</h2>
                    <p class="text-sm font-medium text-gray-500 mt-1">Urus hebahan notifikasi push dan pengumuman terpapar di Dashboard ahli.</p>
                </div>
                
                <!-- Tab Controls -->
                <div class="flex items-center rounded-2xl bg-gray-100/80 p-1 border border-gray-200">
                    <button 
                        @click="activeTab = 'broadcast'"
                        :class="[
                            'px-4 py-2 text-sm font-bold rounded-xl transition-all',
                            activeTab === 'broadcast' ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-900/5' : 'text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        Notifikasi Terus (Push)
                    </button>
                    <button 
                        @click="activeTab = 'announcement'"
                        :class="[
                            'px-4 py-2 text-sm font-bold rounded-xl transition-all',
                            activeTab === 'announcement' ? 'bg-white text-gray-900 shadow-sm ring-1 ring-gray-900/5' : 'text-gray-500 hover:text-gray-700'
                        ]"
                    >
                        Papan Pengumuman
                    </button>
                </div>
            </div>

            <div v-show="activeTab === 'broadcast'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                <!-- Push Broadcast Form  -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-black text-gray-800">Cipta Hebahan Baharu</h2>
                        <p class="text-xs text-gray-500">Hebahan akan dihantar terus kepada pengguna berdasarkan kumpulan sasar.</p>
                    </div>

                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitBroadcast">
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tajuk Notifikasi</label>
                            <input v-model="broadcastForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold" required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kandungan / Mesej</label>
                            <textarea v-model="broadcastForm.content" rows="4" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kumpulan Sasar</label>
                            <select v-model="broadcastForm.target_criteria" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option value="all">Kesemua Ahli Berdaftar</option>
                                <option value="unpaid_fees">Ahli Tunggakan Yuran Saja</option>
                                <option value="specific_usrah">Kumpulan Usrah Spesifik</option>
                            </select>
                        </div>

                        <div v-if="broadcastForm.target_criteria === 'specific_usrah'">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pilih Kumpulan Usrah</label>
                            <select v-model="broadcastForm.usrah_group_id" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option value="">Sila Pilih...</option>
                                <option v-for="group in usrahGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                            </select>
                            <p v-if="broadcastForm.errors.usrah_group_id" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.usrah_group_id }}</p>
                        </div>

                        <div class="md:col-span-2 flex justify-end mt-2">
                            <button type="submit" :disabled="broadcastForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5 disabled:opacity-60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                {{ broadcastForm.processing ? 'Menghantar Proses...' : 'Hantar Hebahan (Broadcast)' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-gray-800 mb-4">Sejarah Hebahan Keluar</h2>
                    <div class="space-y-3">
                        <article v-for="item in recentMessages" :key="item.id" class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-gray-100 bg-gray-50/50 p-4 hover:border-gray-200 transition-colors">
                            <div class="flex items-start gap-4">
                                <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800">{{ item.title }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 font-medium tracking-wide">
                                        <span class="text-gray-700">{{ item.organization_name }}</span> • 
                                        Kumpulan: <span class="capitalize">{{ item.target_criteria.replace('_', ' ') }}</span>
                                        <span v-if="item.usrah_group_name">({{ item.usrah_group_name }})</span>
                                    </p>
                                </div>
                            </div>
                            <div class="shrink-0 flex items-center justify-end">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold"
                                      :class="item.sent_at ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'">
                                    <span v-if="item.sent_at" class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    {{ item.sent_at ? 'Selesai Dihantar' : 'Menunggu Beratur (Queue)' }}
                                </span>
                            </div>
                        </article>

                        <div v-if="!recentMessages.length" class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-12 text-center">
                            <p class="text-sm font-bold text-gray-400">Tiada rekod hebahan ditemui.</p>
                        </div>
                    </div>
                </section>
            </div>

            <div v-show="activeTab === 'announcement'" class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                <!-- Announcements Form -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-black text-gray-800">Muat Naik Pengumuman</h2>
                        <p class="text-xs text-gray-500">Artikel pengumuman ini akan dipaparkan di Dashboard ahli.</p>
                    </div>

                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitAnnouncement">
                        <div v-if="isSuperadmin" class="md:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Mewakili Organisasi</label>
                            <select v-model="announcementForm.organization_id" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>

                        <div :class="isSuperadmin ? 'md:col-span-1' : 'md:col-span-2'">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tajuk Pengumuman</label>
                            <input v-model="announcementForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold" required>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kandungan</label>
                            <textarea v-model="announcementForm.content" rows="4" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required></textarea>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tarikh Terbit (Pilihan)</label>
                            <input v-model="announcementForm.published_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all">
                        </div>

                        <div class="flex items-center gap-3 pt-5 pl-2">
                            <input id="is_pinned" v-model="announcementForm.is_pinned" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                            <label for="is_pinned" class="text-sm font-bold text-gray-700 cursor-pointer">Pin (Utamakan) pengumuman ini</label>
                        </div>

                        <div class="md:col-span-2 flex justify-end mt-2">
                            <button type="submit" :disabled="announcementForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5 disabled:opacity-60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                {{ announcementForm.processing ? 'Menyimpan...' : 'Terbitkan Pengumuman' }}
                            </button>
                        </div>
                    </form>
                </section>

                <!-- Announcements List -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-gray-800 mb-4">Senarai Papan Pengumuman</h2>
                    
                    <div class="space-y-4">
                        <article
                            v-for="item in announcements"
                            :key="item.id"
                            class="rounded-3xl border p-5 transition-all shadow-sm"
                            :class="item.is_pinned ? 'border-amber-200 bg-amber-50/30 ring-1 ring-amber-100' : 'border-gray-100 bg-white hover:border-gray-200'"
                        >
                            <template v-if="editingAnnouncementId === item.id">
                                <form class="space-y-4" @submit.prevent="submitEditAnnouncement(item)">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tajuk</label>
                                        <input v-model="announcementEditForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900" required>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kandungan</label>
                                        <textarea v-model="announcementEditForm.content" rows="4" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900" required></textarea>
                                    </div>
                                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tarikh Terbit</label>
                                            <input v-model="announcementEditForm.published_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900">
                                        </div>
                                        <div class="flex items-center gap-3 pt-6">
                                            <input id="edit_is_pinned" v-model="announcementEditForm.is_pinned" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                                            <label for="edit_is_pinned" class="text-sm font-bold text-gray-700 cursor-pointer">Pin Pengumuman</label>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 justify-end pt-2 border-t border-gray-100 mt-4">
                                        <button type="button" @click="cancelEditAnnouncement" class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-50 transition-colors">Batal</button>
                                        <button type="submit" :disabled="announcementEditForm.processing" class="rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-colors disabled:opacity-60">
                                            {{ announcementEditForm.processing ? 'Menyimpan...' : 'Kemaskini' }}
                                        </button>
                                    </div>
                                </form>
                            </template>
                            
                            <template v-else>
                                <div class="flex flex-col gap-4 sm:flex-row sm:justify-between">
                                    <div class="flex items-start gap-4">
                                        <div v-if="item.is_pinned" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 ring-2 ring-white">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 18V4z"/></svg>
                                        </div>
                                        <div v-else class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400 ring-2 ring-white">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" /></svg>
                                        </div>
                                        <div>
                                            <h3 class="text-[15px] font-black text-gray-900 leading-tight">{{ item.title }}</h3>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">{{ item.organization_name }}</span>
                                                <span class="text-gray-300">•</span>
                                                <span>{{ item.published_human || 'Draf / Tiada Tarikh' }}</span>
                                            </div>
                                            <p class="mt-3 text-[14px] text-gray-600 whitespace-pre-line leading-relaxed">{{ item.content }}</p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2 shrink-0 self-start sm:border-l sm:border-gray-100 sm:pl-4">
                                        <button @click="startEditAnnouncement(item)" class="p-2 rounded-xl border border-gray-200 bg-white text-gray-600 hover:text-gray-900 hover:bg-gray-50 focus:ring-2 focus:ring-gray-200 transition-colors" title="Edit">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                        </button>
                                        <button @click="togglePinned(item.id)" class="p-2 rounded-xl border focus:ring-2 transition-colors" :class="item.is_pinned ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 focus:ring-amber-200' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50 focus:ring-gray-200'" :title="item.is_pinned ? 'Unpin' : 'Pin'">
                                            <svg v-if="item.is_pinned" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 4v14l5-2.5L15 18V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm10 0V2a4 4 0 00-4-4H9a4 4 0 00-4 4v2H3v14c0 1.105.895 2 2 2h10a2 2 0 002-2V4h-2z" clip-rule="evenodd" /></svg>
                                            <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" /></svg>
                                        </button>
                                        <button @click="removeAnnouncement(item.id)" class="p-2 rounded-xl border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 focus:ring-2 focus:ring-red-200 transition-colors" title="Delete">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </article>
                        
                        <div v-if="!announcements.length" class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-12 text-center">
                            <p class="text-sm font-bold text-gray-400">Tiada papan pengumuman ditemui.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>

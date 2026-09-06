<script setup>
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue';
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    recentMessages: { type: Array, default: () => [] },
    usrahGroups: { type: Array, default: () => [] },
    announcements: { type: Array, default: () => [] },
    organizations: { type: Array, default: () => [] },
    defaultOrganizationId: Number,
    isSuperadmin: Boolean,
});

const page = usePage();
const activeTab = ref('broadcast');

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('tab') === 'announcement') {
        activeTab.value = 'announcement';
    }
});

// ─── Push Notification Form ──────────────────────────────────────────────────
const broadcastForm = useForm({
    title: '',
    content: '',
    target_criteria: 'all',
    target_organization_id: props.defaultOrganizationId,
    recipient_ids: [],
    notification_channels: ['in_app'],
    email_use_template: false,
});

const selectedOrgId = ref(props.defaultOrganizationId);

function onTargetCriteriaChange() {
    if (broadcastForm.target_criteria === 'organization') {
        broadcastForm.target_organization_id = selectedOrgId.value;
    } else {
        broadcastForm.target_organization_id = null;
    }
    if (broadcastForm.target_criteria === 'specific_members') {
        broadcastForm.recipient_ids = selectedMembers.value.map(m => m.id);
    } else {
        broadcastForm.recipient_ids = [];
        selectedMembers.value = [];
        memberSearchQuery.value = '';
    }
}

function toggleChannel(channel) {
    const idx = broadcastForm.notification_channels.indexOf(channel);
    if (idx > -1) {
        if (broadcastForm.notification_channels.length > 1) {
            broadcastForm.notification_channels.splice(idx, 1);
        }
    } else {
        broadcastForm.notification_channels.push(channel);
    }
}

function submitBroadcast() {
    broadcastForm.post(route('admin.broadcasts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            broadcastForm.reset('title', 'content', 'target_criteria', 'recipient_ids', 'notification_channels', 'email_use_template');
            selectedMembers.value = [];
            memberSearchQuery.value = '';
            memberResults.value = [];
            broadcastForm.target_organization_id = props.defaultOrganizationId;
            selectedOrgId.value = props.defaultOrganizationId;
            broadcastForm.target_criteria = 'all';
        },
    });
}

// ─── Multi-Member Search ─────────────────────────────────────────────────────
const memberSearchQuery = ref('');
const memberResults = ref([]);
const selectedMembers = ref([]);
const memberSearchLoading = ref(false);
const memberSearchError = ref('');
const showMemberDropdown = ref(false);
let memberDebounceTimer = null;

function orgNameById(orgId) {
    return props.organizations.find((o) => Number(o.id) === Number(orgId))?.name;
}

function onMemberSearchInput(val) {
    memberSearchQuery.value = val;
    memberSearchError.value = '';
    clearTimeout(memberDebounceTimer);
    if (val.length < 2) {
        memberResults.value = [];
        showMemberDropdown.value = false;
        return;
    }
    memberDebounceTimer = setTimeout(() => {
        memberSearchLoading.value = true;
        const params = { q: val };
        if (!props.isSuperadmin) {
            params.organization_id = props.defaultOrganizationId;
        }
        axios.get('/api/members/search', { params })
            .then((res) => {
                const alreadySelected = selectedMembers.value.map(m => m.id);
                memberResults.value = (res.data || []).filter(m => !alreadySelected.includes(m.id));
                showMemberDropdown.value = true;
            })
            .catch((err) => {
                memberResults.value = [];
                memberSearchError.value = err?.response?.status === 429
                    ? 'Terlalu banyak carian. Sila tunggu sebentar dan cuba lagi.'
                    : 'Ralat carian. Sila cuba lagi.';
                showMemberDropdown.value = true;
            })
            .finally(() => { memberSearchLoading.value = false; });
    }, 300);
}

function addMember(member) {
    if (!selectedMembers.value.find(m => m.id === member.id)) {
        selectedMembers.value.push(member);
        broadcastForm.recipient_ids = selectedMembers.value.map(m => m.id);
    }
    memberSearchQuery.value = '';
    memberResults.value = [];
    memberSearchError.value = '';
    showMemberDropdown.value = false;
}

function removeMember(memberId) {
    selectedMembers.value = selectedMembers.value.filter(m => m.id !== memberId);
    broadcastForm.recipient_ids = selectedMembers.value.map(m => m.id);
}

function onMemberBlur() {
    setTimeout(() => { showMemberDropdown.value = false; }, 200);
}

function onMemberFocus() {
    if (memberResults.value.length > 0 || memberSearchError.value) {
        showMemberDropdown.value = true;
    }
}

// ─── Announcements (Pengumuman System) ───────────────────────────────────────
const announcementForm = useForm({
    organization_id: props.defaultOrganizationId,
    title: '',
    content: '',
    is_pinned: false,
    published_at: '',
    target_criteria: 'all',
    usrah_group_id: '',
});

const announcementEditForm = useForm({
    title: '',
    content: '',
    is_pinned: false,
    published_at: '',
    target_criteria: 'all',
    usrah_group_id: '',
});

function submitAnnouncement() {
    const data = new FormData();
    data.append('title', announcementForm.title);
    data.append('content', announcementForm.content);
    data.append('is_pinned', announcementForm.is_pinned ? '1' : '0');
    data.append('published_at', announcementForm.published_at);
    data.append('target_criteria', announcementForm.target_criteria);
    data.append('usrah_group_id', announcementForm.usrah_group_id);
    if (announcementForm.organization_id) data.append('organization_id', announcementForm.organization_id);

    const coverInput = document.querySelector('input[name="cover_image"]');
    if (coverInput?.files?.[0]) data.append('cover_image', coverInput.files[0]);

    const galleryInput = document.querySelector('input[name="gallery_images"]');
    if (galleryInput?.files) {
        Array.from(galleryInput.files).forEach(file => data.append('gallery_images[]', file));
    }

    router.post(route('admin.hub.announcements.store'), data, {
        preserveScroll: true,
        onSuccess: () => {
            announcementForm.reset();
            coverImagePreview.value = null;
            galleryPreviews.value = [];
            document.querySelectorAll('input[type="file"]').forEach(el => { el.value = ''; });
        },
    });
}

function toDateTimeLocal(value) {
    if (!value) return '';
    return value.replace(' ', 'T').slice(0, 16);
}

function startEditAnnouncement(item) {
    editingAnnouncementId.value = item.id;
    editRemoveImageIds.value = [];
    announcementEditForm.title = item.title ?? '';
    announcementEditForm.content = item.content ?? '';
    announcementEditForm.is_pinned = !!item.is_pinned;
    announcementEditForm.published_at = toDateTimeLocal(item.published_at);
    announcementEditForm.target_criteria = item.target_criteria ?? 'all';
    announcementEditForm.usrah_group_id = item.usrah_group_id ?? '';
}

function cancelEditAnnouncement() {
    editingAnnouncementId.value = null;
    announcementEditForm.reset();
}

function submitEditAnnouncement(item) {
    const data = new FormData();
    data.append('_method', 'PUT');
    data.append('title', announcementEditForm.title);
    data.append('content', announcementEditForm.content);
    data.append('is_pinned', announcementEditForm.is_pinned ? '1' : '0');
    data.append('published_at', announcementEditForm.published_at);
    data.append('target_criteria', announcementEditForm.target_criteria);
    data.append('usrah_group_id', announcementEditForm.usrah_group_id);

    editRemoveImageIds.value.forEach(id => data.append('remove_image_ids[]', id));

    const editForm = document.getElementById(`edit-form-${item.id}`);
    const coverInput = editForm?.querySelector('input[name="cover_image"]');
    if (coverInput?.files?.[0]) data.append('cover_image', coverInput.files[0]);

    const galleryInput = editForm?.querySelector('input[name="gallery_images"]');
    if (galleryInput?.files) {
        Array.from(galleryInput.files).forEach(file => data.append('gallery_images[]', file));
    }

    router.post(route('admin.hub.announcements.update', item.id), data, {
        preserveScroll: true,
        onSuccess: () => {
            cancelEditAnnouncement();
            editRemoveImageIds.value = [];
        },
    });
}

function removeAnnouncement(id) {
    if (!confirm('Pastikan anda mahu memadam pengumuman ini?')) return;
    router.delete(route('admin.hub.announcements.destroy', id), { preserveScroll: true });
}

function togglePinned(id) {
    router.patch(route('admin.hub.announcements.pin', id), {}, { preserveScroll: true });
}

const coverImagePreview = ref(null);
function onCoverImageChange(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => { coverImagePreview.value = e.target.result; };
        reader.readAsDataURL(file);
    } else {
        coverImagePreview.value = null;
    }
}

const galleryPreviews = ref([]);
function onGalleryChange(event) {
    const files = Array.from(event.target.files || []);
    galleryPreviews.value = [];
    files.forEach(file => {
        const reader = new FileReader();
        reader.onload = (e) => {
            galleryPreviews.value.push({ name: file.name, url: e.target.result });
        };
        reader.readAsDataURL(file);
    });
}

const editRemoveImageIds = ref([]);
function markImageForRemoval(imageId) {
    editRemoveImageIds.value.push(imageId);
}
function undoRemoveImage(imageId) {
    editRemoveImageIds.value = editRemoveImageIds.value.filter(id => id !== imageId);
}

// ─── Sejarah: status, log & auto-refresh ─────────────────────────────────────
const logsOpen = ref(new Set());
const logsCache = ref({});
const logsLoading = ref({});

async function toggleLogs(id) {
    if (logsOpen.value.has(id)) {
        const next = new Set(logsOpen.value);
        next.delete(id);
        logsOpen.value = next;
        return;
    }
    const next = new Set(logsOpen.value);
    next.add(id);
    logsOpen.value = next;

    if (logsCache.value[id] === undefined) {
        logsLoading.value[id] = true;
        try {
            const res = await axios.get(route('admin.broadcasts.logs', id));
            logsCache.value[id] = res.data?.logs || [];
        } catch {
            logsCache.value[id] = [];
        } finally {
            logsLoading.value[id] = false;
        }
    }
}

function logsFor(id) {
    return logsCache.value[id];
}

function statusBadgeClass(status) {
    return {
        completed: 'bg-emerald-50 text-emerald-700',
        partial: 'bg-orange-50 text-orange-700',
        failed: 'bg-red-50 text-red-700',
        processing: 'bg-blue-50 text-blue-700',
        queued: 'bg-amber-50 text-amber-700',
    }[status] || 'bg-amber-50 text-amber-700';
}

function statusDotClass(status) {
    return {
        completed: 'bg-emerald-500',
        partial: 'bg-orange-500',
        failed: 'bg-red-500',
        processing: 'bg-blue-500 animate-pulse',
        queued: 'bg-amber-500',
    }[status] || 'bg-amber-500';
}

function channelLabel(ch) {
    return ch === 'in_app' ? 'In-App' : 'Email';
}

function logEventLabel(event) {
    return {
        queued: 'Dalam giliran',
        processing: 'Mula diproses',
        completed: 'Selesai',
        partial: 'Sebahagian gagal',
        failed: 'Gagal',
        delivery_failed: 'Hantaran gagal',
        fcm_sent: 'Push peranti (FCM) dihantar',
        fcm_skipped: 'Push peranti (FCM) dilangkau',
    }[event] || event;
}

function hasActiveBroadcast() {
    return (props.recentMessages || []).some(m => m.status === 'queued' || m.status === 'processing');
}

let pollTimer = null;

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

watch(() => props.recentMessages, () => {
    if (hasActiveBroadcast()) {
        if (!pollTimer) {
            pollTimer = setInterval(() => {
                router.reload({ only: ['recentMessages'], preserveScroll: true });
            }, 8000);
        }
    } else {
        stopPolling();
    }
}, { immediate: true });

onBeforeUnmount(stopPolling);
</script>

<template>
    <Head title="Push Notification & Pengumuman" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Push Notification & Pengumuman</template>

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
                    <p class="text-sm font-medium text-gray-500 mt-1">Urus push notification dan pengumuman terpapar di Dashboard ahli.</p>
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
                        Push Notification
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
                <!-- Push Notification Form -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="mb-4">
                        <h2 class="text-lg font-black text-gray-800">Cipta Push Notification Baharu</h2>
                        <p class="text-xs text-gray-500">Push notification akan dihantar terus kepada pengguna berdasarkan kumpulan sasar.</p>
                    </div>

                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitBroadcast">
                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tajuk Notifikasi</label>
                            <input v-model="broadcastForm.title" type="text" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold" required>
                            <p v-if="broadcastForm.errors.title" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.title }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kandungan / Mesej</label>
                            <textarea v-model="broadcastForm.content" rows="4" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all" required></textarea>
                            <p v-if="broadcastForm.errors.content" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.content }}</p>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kumpulan Sasar</label>
                            <select v-model="broadcastForm.target_criteria" @change="onTargetCriteriaChange" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option value="all">Semua Ahli</option>
                                <option value="organization">Ahli Organisasi (Wadah, Abim & PKpim) Sahaja</option>
                                <option value="specific_members">Individu Tertentu (Cari Ahli)</option>
                            </select>
                            <p v-if="broadcastForm.errors.target_criteria" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.target_criteria }}</p>
                        </div>

                        <div v-if="broadcastForm.target_criteria === 'organization' && isSuperadmin">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pilih Organisasi</label>
                            <select v-model="selectedOrgId" @change="broadcastForm.target_organization_id = selectedOrgId" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>

                        <div v-if="broadcastForm.target_criteria === 'organization' && !isSuperadmin" class="md:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Organisasi</label>
                            <input type="text" :value="organizations[0]?.name ?? ''" disabled class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-500 font-semibold cursor-not-allowed">
                        </div>

                        <!-- Multi-Member Search for specific_members -->
                        <div v-if="broadcastForm.target_criteria === 'specific_members'" class="md:col-span-2">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Cari & Pilih Ahli</label>

                            <div class="relative">
                                <input
                                    v-model="memberSearchQuery"
                                    @input="onMemberSearchInput($event.target.value)"
                                    @blur="onMemberBlur"
                                    @focus="onMemberFocus"
                                    type="text"
                                    placeholder="Cari nama atau no ahli..."
                                    class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold"
                                >
                                <div v-if="memberSearchLoading" class="absolute right-3 top-1/2 -translate-y-1/2">
                                    <svg class="h-4 w-4 animate-spin text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                    </svg>
                                </div>

                                <ul v-if="showMemberDropdown" class="absolute z-50 mt-1 w-full rounded-xl border border-gray-200 bg-white py-1 shadow-lg max-h-48 overflow-y-auto">
                                    <li
                                        v-for="member in memberResults"
                                        :key="member.id"
                                        @mousedown.prevent="addMember(member)"
                                        class="flex cursor-pointer items-center justify-between px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                    >
                                        <span class="min-w-0">
                                            <span class="block font-medium truncate">{{ member.name }}</span>
                                            <span class="block text-[11px] text-gray-400">
                                                <span class="font-mono">{{ member.member_no }}</span>
                                                <template v-if="isSuperadmin && member.current_organization_id && orgNameById(member.current_organization_id)">
                                                    <span class="mx-0.5">&middot;</span>{{ orgNameById(member.current_organization_id) }}
                                                </template>
                                            </span>
                                        </span>
                                    </li>
                                    <li v-if="memberSearchError" class="px-4 py-3 text-sm text-red-600 text-center">{{ memberSearchError }}</li>
                                    <li v-else-if="!memberSearchLoading && memberResults.length === 0" class="px-4 py-3 text-sm text-gray-400 text-center">Tiada hasil</li>
                                </ul>
                            </div>

                            <!-- Selected Members Tags -->
                            <div v-if="selectedMembers.length" class="mt-3 flex flex-wrap gap-2">
                                <span
                                    v-for="member in selectedMembers"
                                    :key="member.id"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200 px-3 py-1 text-xs font-semibold text-blue-800"
                                >
                                    {{ member.name }}
                                    <span class="text-[10px] text-blue-400 font-mono">{{ member.member_no }}</span>
                                    <button @click="removeMember(member.id)" type="button" class="ml-0.5 text-blue-400 hover:text-red-600 transition-colors">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </span>
                            </div>

                            <p v-if="broadcastForm.errors.recipient_ids" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.recipient_ids }}</p>
                        </div>

                        <!-- Notification Channels -->
                        <div class="md:col-span-2 mt-3">
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Saluran Penghantaran</label>
                            <div class="flex flex-wrap items-center gap-6">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="broadcastForm.notification_channels.includes('in_app')"
                                        @change="toggleChannel('in_app')"
                                        class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                    >
                                    <span class="text-sm font-semibold text-gray-700">In-App</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :checked="broadcastForm.notification_channels.includes('email')"
                                        @change="toggleChannel('email')"
                                        class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900"
                                    >
                                    <span class="text-sm font-semibold text-gray-700">Email</span>
                                </label>
                            </div>
                            <p v-if="broadcastForm.errors.notification_channels" class="mt-1 text-xs text-red-600">{{ broadcastForm.errors.notification_channels }}</p>
                        </div>

                        <!-- Email Template Toggle (only when Email channel is selected) -->
                        <div v-if="broadcastForm.notification_channels.includes('email')" class="md:col-span-2">
                            <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-500">Format Emel</label>
                            <div class="flex items-center gap-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        :value="false"
                                        v-model="broadcastForm.email_use_template"
                                        class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                    >
                                    <span class="text-sm font-semibold text-gray-700">Kandungan Broadcast</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="radio"
                                        :value="true"
                                        v-model="broadcastForm.email_use_template"
                                        class="h-4 w-4 border-gray-300 text-gray-900 focus:ring-gray-900"
                                    >
                                    <span class="text-sm font-semibold text-gray-700">Template Khas (Header/Footer)</span>
                                </label>
                            </div>
                        </div>

                        <div class="md:col-span-2 flex justify-end mt-2">
                            <button type="submit" :disabled="broadcastForm.processing" class="inline-flex items-center gap-2 rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-gray-800 transition-all hover:-translate-y-0.5 disabled:opacity-60">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" /></svg>
                                {{ broadcastForm.processing ? 'Menghantar...' : 'Hantar Push Notification' }}
                            </button>
                        </div>
                    </form>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-gray-800 mb-4">Sejarah Push Notification Keluar</h2>
                    <div class="space-y-3">
                        <article v-for="item in recentMessages" :key="item.id" class="rounded-2xl border border-gray-100 bg-gray-50/50 p-4 transition-colors hover:border-gray-200" :class="item.status === 'failed' ? 'border-red-200 bg-red-50/30' : (item.status === 'partial' ? 'border-orange-200 bg-orange-50/20' : '')">
                            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                                <div class="flex items-start gap-4 min-w-0">
                                    <div class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-blue-100 text-blue-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" /></svg>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-gray-800">{{ item.title }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 font-medium tracking-wide">
                                            <span class="text-gray-700">{{ item.organization_name }}</span> &bull;
                                            <span class="capitalize">{{ item.target_criteria_label }}</span>
                                            <span v-if="item.target_criteria === 'organization' && item.target_organization_name">({{ item.target_organization_name }})</span>
                                            <span v-if="item.target_criteria === 'specific_members' && item.recipient_count">({{ item.recipient_count }} ahli)</span>
                                        </p>
                                        <p v-if="item.notification_channels?.length" class="mt-0.5 text-[11px] text-gray-400">
                                            Saluran:
                                            <span v-for="(ch, idx) in item.notification_channels" :key="ch" class="inline-flex items-center">
                                                <span class="font-medium">{{ channelLabel(ch) }}</span>
                                                <span v-if="idx < item.notification_channels.length - 1" class="mx-0.5">+</span>
                                            </span>
                                        </p>
                                        <p v-if="item.total_recipients !== null && item.total_recipients !== undefined" class="mt-1 text-[11px] font-semibold text-gray-400">
                                            {{ item.total_recipients }} penerima
                                            <span v-if="(item.failed_count ?? 0) > 0" class="text-red-500">· {{ item.success_count ?? 0 }} berjaya · {{ item.failed_count }} gagal</span>
                                            <span v-else class="text-emerald-600">· {{ item.success_count ?? 0 }} berjaya</span>
                                        </p>
                                        <p v-if="item.error_message" class="mt-1 text-[11px] font-semibold text-red-500">{{ item.error_message }}</p>
                                    </div>
                                </div>
                                <div class="shrink-0 flex flex-col items-start sm:items-end gap-1.5">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadgeClass(item.status)">
                                        <span :class="['h-1.5 w-1.5 rounded-full', statusDotClass(item.status)]"></span>
                                        {{ item.status_label }}
                                    </span>
                                    <span v-if="item.finished_at" class="text-[10px] text-gray-400 font-medium">Siap: {{ item.finished_at }}</span>
                                    <button
                                        type="button"
                                        @click="toggleLogs(item.id)"
                                        class="text-[11px] font-bold text-gray-500 hover:text-gray-900 transition-colors underline underline-offset-2"
                                    >
                                        {{ logsOpen.has(item.id) ? 'Tutup log' : 'Lihat log' }}
                                    </button>
                                </div>
                            </div>

                            <div v-if="logsOpen.has(item.id)" class="mt-3 border-t border-gray-100 pt-3 space-y-1.5">
                                <div v-if="logsLoading[item.id]" class="text-[11px] text-gray-400 font-medium">Memuat log...</div>
                                <template v-else>
                                    <div v-for="log in logsFor(item.id) || []" :key="log.id" class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px]">
                                        <span class="font-mono text-gray-300">{{ log.created_at }}</span>
                                        <span class="font-bold text-gray-600 rounded bg-gray-100 px-1.5 py-0.5">{{ logEventLabel(log.event) }}</span>
                                        <span v-if="log.channel" class="text-gray-400 font-mono">{{ channelLabel(log.channel) }}</span>
                                        <span v-if="log.message" class="text-gray-500">{{ log.message }}</span>
                                    </div>
                                    <div v-if="!(logsFor(item.id) || []).length" class="text-[11px] text-gray-400 font-medium">Tiada rekod log.</div>
                                </template>
                            </div>
                        </article>

                        <div v-if="!recentMessages.length" class="rounded-2xl border border-dashed border-gray-200 bg-white px-4 py-12 text-center">
                            <p class="text-sm font-bold text-gray-400">Tiada rekod push notification ditemui.</p>
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

                    <form class="grid grid-cols-1 gap-4 md:grid-cols-2" @submit.prevent="submitAnnouncement" enctype="multipart/form-data">
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
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Gambar Cover (Pilihan)</label>
                            <input type="file" name="cover_image" accept="image/*" @change="onCoverImageChange" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 transition-all">
                            <div v-if="coverImagePreview" class="mt-2 rounded-xl overflow-hidden h-32 w-full bg-gray-100">
                                <img :src="coverImagePreview" class="w-full h-full object-cover" />
                            </div>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Galeri Gambar (Pilihan)</label>
                            <input type="file" name="gallery_images" multiple accept="image/*" @change="onGalleryChange" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 transition-all">
                            <div v-if="galleryPreviews.length" class="mt-2 grid grid-cols-3 gap-2">
                                <div v-for="(preview, idx) in galleryPreviews" :key="idx" class="rounded-xl overflow-hidden h-20 bg-gray-100">
                                    <img :src="preview.url" class="w-full h-full object-cover" />
                                </div>
                            </div>
                        </div>

                        <div class="md:col-span-1">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kumpulan Sasaran</label>
                            <select v-model="announcementForm.target_criteria" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option value="all">Kesemua Ahli</option>
                                <option value="unpaid_fees">Ahli Tunggakan Yuran</option>
                                <option value="specific_usrah">Kumpulan Usrah Spesifik</option>
                            </select>
                        </div>

                        <div v-if="announcementForm.target_criteria === 'specific_usrah'">
                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pilih Kumpulan Usrah</label>
                            <select v-model="announcementForm.usrah_group_id" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900 transition-all font-semibold">
                                <option value="">Sila Pilih...</option>
                                <option v-for="group in usrahGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                            </select>
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
                                <form :id="`edit-form-${item.id}`" class="space-y-4" @submit.prevent="submitEditAnnouncement(item)">
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
                                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Kumpulan Sasaran</label>
                                            <select v-model="announcementEditForm.target_criteria" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900">
                                                <option value="all">Kesemua Ahli</option>
                                                <option value="unpaid_fees">Ahli Tunggakan Yuran</option>
                                                <option value="specific_usrah">Kumpulan Usrah Spesifik</option>
                                            </select>
                                        </div>
                                        <div v-if="announcementEditForm.target_criteria === 'specific_usrah'">
                                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pilih Kumpulan Usrah</label>
                                            <select v-model="announcementEditForm.usrah_group_id" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900">
                                                <option value="">Sila Pilih...</option>
                                                <option v-for="group in usrahGroups" :key="group.id" :value="group.id">{{ group.name }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tarikh Terbit</label>
                                            <input v-model="announcementEditForm.published_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-gray-900 focus:ring-gray-900">
                                        </div>
                                        <div class="flex items-center gap-3 pt-6">
                                            <input id="edit_is_pinned" v-model="announcementEditForm.is_pinned" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-amber-500 focus:ring-amber-500">
                                            <label for="edit_is_pinned" class="text-sm font-bold text-gray-700 cursor-pointer">Pin Pengumuman</label>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Galeri Gambar Sedia Ada</label>
                                        <div v-if="item.images?.length" class="grid grid-cols-4 gap-2 mb-3">
                                            <div v-for="img in item.images" :key="img.id" class="relative rounded-xl overflow-hidden h-20 bg-gray-100 group">
                                                <img :src="img.url" class="w-full h-full object-cover" />
                                                <button v-if="!editRemoveImageIds.includes(img.id)" @click.prevent="markImageForRemoval(img.id)" type="button" class="absolute top-1 right-1 p-1 rounded-full bg-red-500 text-white opacity-0 group-hover:opacity-100 transition-opacity shadow">
                                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                                </button>
                                                <div v-if="editRemoveImageIds.includes(img.id)" class="absolute inset-0 bg-red-500/60 flex items-center justify-center">
                                                    <button @click.prevent="undoRemoveImage(img.id)" type="button" class="text-white text-[10px] font-bold underline">Batal</button>
                                                </div>
                                            </div>
                                        </div>
                                        <input type="file" name="gallery_images" multiple accept="image/*" class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm file:mr-3 file:rounded-xl file:border-0 file:bg-gray-900 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-white hover:file:bg-gray-800 transition-all">
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
                                    <div class="flex items-start gap-4 min-w-0">
                                        <div v-if="item.is_pinned" class="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-600 ring-2 ring-white">
                                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path d="M5 4a2 2 0 012-2h6a2 2 0 012 2v14l-5-2.5L5 21V4z"/></svg>
                                        </div>
                                        <div class="min-w-0">
                                            <!-- Cover thumb -->
                                            <div v-if="item.cover_image_url" class="mb-2 rounded-xl overflow-hidden h-28 w-full bg-gray-100">
                                                <img :src="item.cover_image_url" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-if="item.images?.length" class="mb-2 grid grid-cols-4 gap-1.5">
                                                <div v-for="img in item.images" :key="img.id" class="rounded-lg overflow-hidden h-16 bg-gray-100">
                                                    <img :src="img.url" class="w-full h-full object-cover" />
                                                </div>
                                            </div>
                                            <h3 class="text-[15px] font-black text-gray-900 leading-tight truncate">{{ item.title }}</h3>
                                            <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-600">{{ item.organization_name }}</span>
                                                <span class="text-gray-300">&bull;</span>
                                                <span>{{ item.published_human || 'Draf / Tiada Tarikh' }}</span>
                                                <span v-if="item.author_name" class="text-gray-300">&bull;</span>
                                                <span v-if="item.author_name">{{ item.author_name }}</span>
                                            </div>
                                            <p class="mt-2 text-[14px] text-gray-600 whitespace-pre-line leading-relaxed line-clamp-2">{{ item.content }}</p>
                                            <!-- Stats row -->
                                            <div class="mt-2 flex items-center gap-3 text-[11px] text-gray-400">
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" /></svg>
                                                    {{ item.likes_count ?? 0 }}
                                                </span>
                                                <span class="inline-flex items-center gap-1">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    {{ item.reads_count ?? 0 }}
                                                </span>
                                                <span v-if="item.target_criteria && item.target_criteria !== 'all'" class="rounded bg-gray-100 px-1.5 py-0.5 text-gray-500">
                                                    {{ item.target_criteria === 'unpaid_fees' ? 'Tunggakan' : 'Usrah' }}
                                                </span>
                                            </div>
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

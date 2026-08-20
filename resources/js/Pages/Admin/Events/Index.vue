<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    events: Object, // paginator
    filters: Object,
    organizations: Array,
    statuses: Array,
    categories: Array,
});

const statusColor = {
    draft: 'bg-gray-100 text-gray-600 border-gray-200',
    published: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    closed: 'bg-red-50 text-red-600 border-red-200',
};

function applyFilters() {
    router.get(route('admin.events.index'), {
        search: document.getElementById('f-search')?.value || '',
        status: document.getElementById('f-status')?.value || '',
        category: document.getElementById('f-category')?.value || '',
        org: document.getElementById('f-org')?.value || '',
    }, { preserveState: true, replace: true });
}

function deleteEvent(event) {
    if (!confirm(`Padam program "${event.title}"?\n\nSemua borang, pendaftaran dan kehadiran program ini juga akan dipadam. Tindakan ini tidak boleh dibatalkan.`)) return;
    router.delete(route('events.destroy', event.id));
}
</script>

<template>
    <Head title="Pengurusan Event" />

    <AppLayout>
        <div class="max-w-7xl mx-auto px-4 py-8">
            <div class="flex items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Pengurusan Event</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Cipta &amp; urus semua event, borang pendaftaran, peserta dan kehadiran.</p>
                </div>
                <Link :href="route('admin.events.create')" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Cipta Event
                </Link>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl bg-white border border-gray-100 p-4 shadow-sm mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input id="f-search" :value="filters.search || ''" placeholder="Cari tajuk / lokasi..." class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0" />
                <select id="f-status" :value="filters.status || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Status</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
                <select id="f-category" :value="filters.category || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Kategori</option>
                    <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
                <select id="f-org" :value="filters.org || ''" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                    <option value="">Semua Organisasi</option>
                    <option v-for="o in organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <button @click="applyFilters" class="rounded-xl bg-gray-900 text-white px-4 py-2 text-sm font-semibold">Tapis</button>
            </div>

            <!-- Empty -->
            <div v-if="events.data.length === 0" class="rounded-3xl bg-white border border-gray-100 p-12 text-center">
                <p class="text-gray-400 text-sm">Tiada event. Klik "Cipta Event" untuk mulakan.</p>
            </div>

            <!-- Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="e in events.data" :key="e.id" class="rounded-3xl bg-white border border-gray-100 shadow-sm overflow-hidden flex flex-col">
                    <div class="aspect-[16/9] bg-gray-100 overflow-hidden">
                        <img :src="e.featured_image_url" :alt="e.title" class="w-full h-full object-cover" />
                    </div>
                    <div class="p-4 flex-1 flex flex-col gap-3">
                        <div class="flex flex-wrap gap-1.5">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-bold" :class="statusColor[e.status] ?? statusColor.draft">
                                {{ e.status_label }}
                            </span>
                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-[11px] font-bold text-gray-600">
                                {{ e.category_label }}
                            </span>
                        </div>

                        <div>
                            <h3 class="font-bold text-gray-900 leading-snug line-clamp-2">{{ e.title }}</h3>
                            <p class="text-xs text-gray-500 mt-1">{{ e.start_formatted }}</p>
                        </div>

                        <div class="text-xs text-gray-500 space-y-1">
                            <p class="font-semibold">{{ e.organization_name }}</p>
                            <p v-if="e.organizations.length > 1" class="text-gray-400">
                                Terlibat: {{ e.organizations.map(o => o.name).join(', ') }}
                            </p>
                            <p class="text-gray-400">Pendaftaran: {{ e.registrations_count }}</p>
                        </div>

                        <div class="mt-auto flex gap-2 pt-2">
                            <a
                                v-if="e.form_url"
                                :href="e.form_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="flex-1 rounded-xl bg-emerald-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-emerald-700"
                            >
                                Borang{{ e.forms_count > 1 ? ` (${e.forms_count})` : '' }}
                            </a>
                            <span v-else class="flex-1 rounded-xl bg-gray-100 px-3 py-2 text-center text-xs font-semibold text-gray-400 cursor-not-allowed" title="Tiada borang aktif">
                                Borang
                            </span>
                            <Link :href="route('admin.events.show', e.id)" class="flex-1 rounded-xl bg-indigo-600 px-3 py-2 text-center text-xs font-bold text-white hover:bg-indigo-700">Urus</Link>
                            <Link :href="route('admin.events.edit', e.id)" class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">Edit</Link>
                            <Link :href="route('events.show', e.slug)" class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">Lihat</Link>
                            <button
                                @click="deleteEvent(e)"
                                title="Padam program"
                                class="rounded-xl border border-red-100 bg-red-50 px-2.5 py-2 text-red-500 hover:bg-red-100 transition"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

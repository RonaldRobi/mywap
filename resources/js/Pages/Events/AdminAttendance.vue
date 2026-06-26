<script setup>
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    events: { type: Object, required: true },
    stats: { type: Object, default: () => ({ total_events: 0, total_attended: 0 }) },
    filters: { type: Object, default: () => ({ search: '', start: '', end: '', status: '' }) },
});

const page = usePage();
const search = ref(props.filters.search ?? '');
const start = ref(props.filters.start ?? '');
const end = ref(props.filters.end ?? '');
const status = ref(props.filters.status ?? '');

function applyFilters() {
    router.get(route('admin.attendance'), {
        search: search.value,
        start: start.value,
        end: end.value,
        status: status.value,
    }, { preserveState: true, replace: true });
}

function resetFilters() {
    search.value = '';
    start.value = '';
    end.value = '';
    status.value = '';
    router.get(route('admin.attendance'), {}, { preserveState: true, replace: true });
}


</script>

<template>
    <Head title="Kehadiran Program" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Kehadiran Program</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <!-- Flash message -->
            <div v-if="page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <!-- Summary cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Jumlah Program</p>
                    <p class="mt-1 text-3xl font-black text-gray-800">{{ stats.total_events }}</p>
                </div>
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-widest text-gray-400">Jumlah Kehadiran</p>
                    <p class="mt-1 text-3xl font-black text-gray-800">{{ stats.total_attended }}</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px] flex-1">
                        <label class="block text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Carian</label>
                        <input v-model="search" type="text" placeholder="Cari program..." class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" @keyup.enter="applyFilters" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Tarikh Mula</label>
                        <input v-model="start" type="date" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Tarikh Tamat</label>
                        <input v-model="end" type="date" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">Status</label>
                        <select v-model="status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0">
                            <option value="">Semua</option>
                            <option value="hadir">Hadir</option>
                            <option value="tidak_hadir">Tidak Hadir</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button @click="applyFilters" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Tapis</button>
                        <button @click="resetFilters" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">Set Semula</button>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="rounded-3xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div v-if="!events.data?.length" class="p-10 text-center text-sm text-gray-500">Tiada program dijumpai untuk tapisan ini.</div>

                <table v-else class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3">#</th>
                            <th class="px-5 py-3">Program</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Tarikh</th>
                            <th class="px-5 py-3">Lokasi</th>
                            <th class="px-5 py-3">Hadir</th>
                            <th class="px-5 py-3">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="(event, i) in events.data" :key="event.id" class="hover:bg-gray-50">
                            <td class="px-5 py-4 text-xs text-gray-400">{{ events.from + i }}</td>
                            <td class="px-5 py-4 font-semibold text-gray-900">{{ event.title }}</td>
                            <td class="px-5 py-4">
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', event.type === 'physical' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700']">
                                    {{ event.type === 'physical' ? 'Fizikal' : 'Online' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-600 whitespace-nowrap">{{ event.start_formatted }}</td>
                            <td class="px-5 py-4 text-xs text-gray-500 max-w-[160px] truncate">{{ event.location_or_link || '—' }}</td>
                            <td class="px-5 py-4">
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', event.attendance_count > 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500']">
                                    {{ event.attendance_count }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1">
                                    <a :href="route('events.qr', { event: event.id })" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-emerald-600 hover:bg-emerald-50">QR</a>
                                    <a :href="route('events.print', { event: event.id })" target="_blank" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-blue-600 hover:bg-blue-50">Senarai</a>
                                    <a :href="route('events.show', { slug: event.slug })" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">Lihat</a>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div v-if="events.last_page > 1" class="flex items-center justify-center gap-2">
                <a v-for="link in events.links" :key="link.label" :href="link.url"
                    :class="['rounded-lg px-3 py-1.5 text-sm', link.active ? 'bg-gray-900 text-white' : link.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 cursor-default']"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>
</template>

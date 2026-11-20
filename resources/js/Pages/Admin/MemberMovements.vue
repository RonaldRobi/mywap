<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    movements: { type: Object, default: () => ({ data: [] }) },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', direction: 'incoming', branch_id: null }) },
});

const search = ref(props.filters.search ?? '');
const branchId = ref(props.filters.branch_id ?? '');
const loading = ref(false);
const filterError = ref('');

const directions = [
    { value: 'incoming', label: 'Masuk' },
    { value: 'outgoing', label: 'Keluar' },
    { value: 'all', label: 'Semua' },
];

const directionLabels = {
    incoming: 'Masuk',
    outgoing: 'Keluar',
    internal: 'Dalam jagaan',
};

function requestMovements(direction = props.filters.direction ?? 'incoming') {
    filterError.value = '';
    router.get(route('admin.member-movements.index'), {
        search: search.value.trim(),
        direction,
        branch_id: branchId.value || '',
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { loading.value = true; },
        onError: () => { filterError.value = 'Rekod tidak dapat dimuatkan. Cuba sekali lagi.'; },
        onFinish: () => { loading.value = false; },
    });
}

function resetFilters() {
    search.value = '';
    branchId.value = '';
    requestMovements('incoming');
}
</script>

<template>
    <Head title="Pergerakan Ahli" />

    <AppLayout>
        <div class="mx-auto w-full max-w-6xl px-4 py-5 md:px-6 md:py-7">
            <header class="mb-5">
                <h1 class="text-2xl font-black tracking-tight text-gray-900">Pergerakan Ahli</h1>
                <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">
                    Rekod ahli yang masuk atau keluar daripada cawangan jagaan anda. Kelulusan pertukaran kekal di bawah admin organisasi.
                </p>
            </header>

            <div class="mb-4 flex gap-1 rounded-xl border border-gray-200 bg-white p-1" aria-label="Tapis arah pergerakan">
                <button
                    v-for="item in directions"
                    :key="item.value"
                    type="button"
                    class="min-h-11 min-w-0 flex-1 cursor-pointer rounded-lg px-3 text-sm font-bold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2F6B32] focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60"
                    :class="filters.direction === item.value ? 'bg-[#123D2A] text-white' : 'text-gray-600 hover:bg-gray-100 active:bg-gray-200'"
                    :aria-pressed="filters.direction === item.value"
                    :disabled="loading"
                    @click="requestMovements(item.value)"
                >
                    {{ item.label }}
                </button>
            </div>

            <form class="mb-5 rounded-2xl border border-gray-200 bg-white p-4" @submit.prevent="requestMovements()">
                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_14rem_auto] md:items-end">
                    <div>
                        <label for="movement-search" class="mb-1.5 block text-sm font-semibold text-gray-700">Cari ahli</label>
                        <input
                            id="movement-search"
                            v-model="search"
                            type="search"
                            placeholder="Nama, nombor ahli atau emel"
                            class="min-h-11 w-full rounded-xl border-gray-300 text-sm focus:border-[#2F6B32] focus:ring-[#2F6B32]"
                        >
                    </div>

                    <div v-if="branches.length > 1">
                        <label for="movement-branch" class="mb-1.5 block text-sm font-semibold text-gray-700">Cawangan</label>
                        <select
                            id="movement-branch"
                            v-model="branchId"
                            class="min-h-11 w-full cursor-pointer rounded-xl border-gray-300 text-sm focus:border-[#2F6B32] focus:ring-[#2F6B32]"
                        >
                            <option value="">Semua cawangan jagaan</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="loading"
                            class="min-h-11 flex-1 cursor-pointer rounded-xl bg-[#123D2A] px-5 text-sm font-bold text-white hover:bg-[#0d2f20] active:bg-[#071f15] disabled:cursor-not-allowed disabled:opacity-60 md:flex-none"
                        >
                            {{ loading ? 'Memuatkan...' : 'Tapis' }}
                        </button>
                        <button
                            type="button"
                            :disabled="loading"
                            class="min-h-11 flex-1 cursor-pointer rounded-xl border border-gray-300 px-4 text-sm font-bold text-gray-700 hover:bg-gray-50 active:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-60 md:flex-none"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </form>

            <p v-if="filterError" role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800">
                {{ filterError }}
            </p>

            <div v-if="loading" role="status" class="rounded-2xl border border-gray-200 bg-white px-5 py-10 text-center text-sm font-semibold text-gray-600">
                Memuatkan rekod pergerakan ahli...
            </div>

            <div v-else-if="movements.data.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white px-5 py-12 text-center">
                <p class="font-bold text-gray-900">Tiada rekod ditemui</p>
                <p class="mt-1 text-sm text-gray-600">Ubah carian atau penapis untuk melihat rekod lain.</p>
            </div>

            <template v-else>
                <div class="space-y-3 lg:hidden">
                    <article v-for="movement in movements.data" :key="movement.id" class="rounded-2xl border border-gray-200 bg-white p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-bold text-gray-900">{{ movement.user.name }}</p>
                                <p class="mt-0.5 text-xs text-gray-600">{{ movement.user.member_no || 'Tiada nombor ahli' }}</p>
                            </div>
                            <span
                                class="shrink-0 rounded-lg px-2.5 py-1 text-xs font-bold"
                                :class="{
                                    'bg-emerald-100 text-emerald-800': movement.direction === 'incoming',
                                    'bg-amber-100 text-amber-900': movement.direction === 'outgoing',
                                    'bg-blue-100 text-blue-800': movement.direction === 'internal',
                                }"
                            >
                                {{ directionLabels[movement.direction] }}
                            </span>
                        </div>

                        <div class="mt-4 grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-2 text-sm">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold text-gray-500">Dari</p>
                                <p class="mt-0.5 truncate font-semibold text-gray-800">{{ movement.from_branch || 'Ahli baharu' }}</p>
                            </div>
                            <svg aria-hidden="true" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-4-4 4 4-4 4" />
                            </svg>
                            <div class="min-w-0 text-right">
                                <p class="text-xs font-semibold text-gray-500">Ke</p>
                                <p class="mt-0.5 truncate font-semibold text-gray-800">{{ movement.to_branch }}</p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-end justify-between gap-3 border-t border-gray-100 pt-3">
                            <div class="text-xs text-gray-600">
                                <p class="font-semibold text-gray-700">{{ movement.source }}</p>
                                <p class="mt-0.5">{{ movement.changed_at_relative }}</p>
                            </div>
                            <Link v-if="movement.member_url" :href="movement.member_url" class="inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-bold text-[#2F6B32] hover:bg-[#EDF5EE] active:bg-[#DCECDF] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2F6B32]">
                                Lihat ahli
                            </Link>
                            <span v-else class="inline-flex min-h-11 items-center text-xs font-semibold text-gray-500">Sudah keluar</span>
                        </div>
                    </article>
                </div>

                <div class="hidden overflow-hidden rounded-2xl border border-gray-200 bg-white lg:block">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs font-bold text-gray-600">
                            <tr>
                                <th class="px-5 py-3">Ahli</th>
                                <th class="px-5 py-3">Pergerakan</th>
                                <th class="px-5 py-3">Rekod</th>
                                <th class="px-5 py-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="movement in movements.data" :key="movement.id" class="hover:bg-gray-50">
                                <td class="px-5 py-4">
                                    <p class="font-bold text-gray-900">{{ movement.user.name }}</p>
                                    <p class="mt-0.5 text-xs text-gray-600">{{ movement.user.member_no || 'Tiada nombor ahli' }}</p>
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-2">
                                        <span class="max-w-36 truncate text-gray-700">{{ movement.from_branch || 'Ahli baharu' }}</span>
                                        <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-4-4 4 4-4 4" /></svg>
                                        <span class="max-w-36 truncate font-semibold text-gray-900">{{ movement.to_branch }}</span>
                                    </div>
                                    <span class="mt-2 inline-block rounded-lg bg-gray-100 px-2 py-1 text-xs font-bold text-gray-700">{{ directionLabels[movement.direction] }}</span>
                                </td>
                                <td class="px-5 py-4 text-gray-700">
                                    <p class="font-semibold">{{ movement.source }}</p>
                                    <p class="mt-0.5 text-xs text-gray-600">{{ movement.changed_at }}</p>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <Link v-if="movement.member_url" :href="movement.member_url" class="inline-flex min-h-11 items-center rounded-xl px-3 font-bold text-[#2F6B32] hover:bg-[#EDF5EE] active:bg-[#DCECDF] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2F6B32]">
                                        Lihat ahli
                                    </Link>
                                    <span v-else class="inline-flex min-h-11 items-center text-xs font-semibold text-gray-500">Sudah keluar</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav v-if="movements.last_page > 1" class="mt-5 flex items-center justify-between gap-3" aria-label="Navigasi halaman">
                    <Link
                        v-if="movements.prev_page_url"
                        :href="movements.prev_page_url"
                        preserve-scroll
                        class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-4 text-sm font-bold text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                    >
                        Sebelumnya
                    </Link>
                    <span v-else class="min-h-11" aria-hidden="true"></span>
                    <p class="text-xs font-semibold text-gray-600">Halaman {{ movements.current_page }} daripada {{ movements.last_page }}</p>
                    <Link
                        v-if="movements.next_page_url"
                        :href="movements.next_page_url"
                        preserve-scroll
                        class="inline-flex min-h-11 items-center rounded-xl border border-gray-300 bg-white px-4 text-sm font-bold text-gray-700 hover:bg-gray-50 active:bg-gray-100"
                    >
                        Seterusnya
                    </Link>
                    <span v-else class="min-h-11" aria-hidden="true"></span>
                </nav>
            </template>
        </div>
    </AppLayout>
</template>

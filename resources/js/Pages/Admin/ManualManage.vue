<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    manuals: { type: Object, default: () => ({ data: [] }) },
    audiences: { type: Array, default: () => ['user', 'admin', 'all'] },
    filters: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.filters?.search ?? '');
const audienceFilter = ref(props.filters?.audience ?? '');

watch([searchQuery, audienceFilter], (val) => {
    const timer = setTimeout(() => {
        router.get(route('admin.manuals.index'), {
            search: val[0] || '',
            audience: val[1] || '',
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
}, { deep: true });

const showModal = ref(false);
const editingManual = ref(null);

const form = useForm({
    title: '',
    description: '',
    audience: 'user',
    sort_order: 0,
    is_active: true,
    document: null,
});

const audienceLabels = {
    user: 'Ahli / Pengguna',
    admin: 'Admin Organisasi',
    all: 'Semua (Ahli + Admin)',
};

function audienceLabel(value) {
    return audienceLabels[value] ?? value;
}

function openAdd() {
    editingManual.value = null;
    form.reset();
    form.audience = 'user';
    form.sort_order = 0;
    form.is_active = true;
    showModal.value = true;
}

function openEdit(manual) {
    editingManual.value = manual;
    form.title = manual.title ?? '';
    form.description = manual.description ?? '';
    form.audience = manual.audience ?? 'user';
    form.sort_order = manual.sort_order ?? 0;
    form.is_active = manual.is_active;
    form.document = null;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingManual.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingManual.value) {
        form
            .transform((data) => ({ ...data, _method: 'post' }))
            .post(route('admin.manuals.update', editingManual.value.id), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => closeModal(),
            });
    } else {
        form.post(route('admin.manuals.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => closeModal(),
        });
    }
}

function removeManual(manual) {
    if (!confirm(`Padam manual "${manual.title}"?`)) return;
    useForm({}).delete(route('admin.manuals.destroy', manual.id), {
        preserveScroll: true,
    });
}

const visiblePages = computed(() => {
    if (!props.manuals) return [];
    const last = props.manuals.last_page ?? 1;
    const cur = props.manuals.current_page ?? 1;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [1];
    if (cur > 3) pages.push('...');
    for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
    if (cur < last - 2) pages.push('...');
    pages.push(last);
    return pages;
});
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <Head title="Manual Pengguna" />

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Manual Pengguna</h1>
                    <p class="mt-1 text-sm text-gray-500">
                        Muat naik PDF panduan dan pilih siapa yang boleh melihatnya. Ahli hanya nampak manual Ahli, Admin hanya nampak manual Admin.
                    </p>
                </div>
                <button @click="openAdd" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                    + Muat Naik Manual
                </button>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="🔍 Cari tajuk manual..."
                    class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm"
                >
                <select v-model="audienceFilter" class="rounded-xl border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Semua Sasaran</option>
                    <option v-for="a in audiences" :key="a" :value="a">{{ audienceLabel(a) }}</option>
                </select>
            </div>

            <!-- Table -->
            <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="border-b border-gray-100 bg-gray-50/70 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-bold">Status</th>
                                <th scope="col" class="px-4 py-3 font-bold">Tajuk</th>
                                <th scope="col" class="px-4 py-3 font-bold">Sasaran</th>
                                <th scope="col" class="px-4 py-3 font-bold">Susunan</th>
                                <th scope="col" class="px-4 py-3 font-bold">Fail</th>
                                <th scope="col" class="px-4 py-3 text-right font-bold">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="!manuals.data?.length">
                                <td colspan="6" class="px-4 py-12 text-center text-gray-400">Tiada manual dimuat naik lagi.</td>
                            </tr>
                            <tr v-for="manual in manuals.data" :key="manual.id" class="transition-colors hover:bg-gray-50/50">
                                <td class="px-4 py-3">
                                    <span :class="manual.is_active ? 'text-emerald-600' : 'text-gray-400'">
                                        {{ manual.is_active ? '🟢 Aktif' : '⚪ Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900">{{ manual.title }}</div>
                                    <div v-if="manual.description" class="max-w-[320px] truncate text-xs text-gray-400">{{ manual.description }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="{
                                            'bg-blue-100 text-blue-700': manual.audience === 'user',
                                            'bg-amber-100 text-amber-700': manual.audience === 'admin',
                                            'bg-emerald-100 text-emerald-700': manual.audience === 'all',
                                        }"
                                    >
                                        {{ audienceLabel(manual.audience) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ manual.sort_order ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <a :href="manual.file_path" target="_blank" class="text-blue-600 hover:underline">📎 Buka PDF</a>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="openEdit(manual)" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100">✏️</button>
                                    <button @click="removeManual(manual)" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-500 hover:bg-red-50">🗑️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="manuals.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                    <div class="text-xs text-gray-500">
                        Menunjukkan
                        <span class="font-semibold">{{ manuals.from ?? 0 }}</span> -
                        <span class="font-semibold">{{ manuals.to ?? 0 }}</span> dari
                        <span class="font-semibold">{{ manuals.total ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link v-if="manuals.prev_page_url" :href="manuals.prev_page_url" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs hover:bg-gray-50">&lsaquo;</Link>
                        <template v-for="(page, i) in visiblePages" :key="i">
                            <span v-if="page === '...'" class="px-1 text-xs text-gray-400">...</span>
                            <Link v-else :href="manuals.links?.find(l => l.label == page)?.url ?? '#'"
                                :class="['rounded-lg px-2.5 py-1 text-xs', page == manuals.current_page ? 'bg-gray-900 text-white' : 'border border-gray-200 hover:bg-gray-50']">
                                {{ page }}
                            </Link>
                        </template>
                        <Link v-if="manuals.next_page_url" :href="manuals.next_page_url" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs hover:bg-gray-50">&rsaquo;</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm">
                <div class="w-full max-w-lg rounded-2xl border border-gray-100 bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-lg font-black text-gray-900">
                            {{ editingManual ? 'Edit Manual' : 'Muat Naik Manual' }}
                        </h2>
                        <button @click="closeModal" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submit" class="space-y-3">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Tajuk Manual *</span>
                            <input v-model="form.title" type="text" placeholder="Contoh: Manual Pengguna (Ahli)" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <p v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Penerangan (pilihan)</span>
                            <textarea v-model="form.description" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="Ringkasan kandungan manual"></textarea>
                        </label>

                        <div class="grid grid-cols-2 gap-3">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Sasaran *</span>
                                <select v-model="form.audience" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    <option v-for="a in audiences" :key="a" :value="a">{{ audienceLabel(a) }}</option>
                                </select>
                                <p v-if="form.errors.audience" class="mt-1 text-xs text-red-500">{{ form.errors.audience }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Susunan</span>
                                <input v-model.number="form.sort_order" type="number" min="0" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">
                                Fail PDF {{ editingManual ? '(biarkan kosong untuk kekalkan fail sedia ada)' : '*' }}
                            </span>
                            <input
                                type="file"
                                accept="application/pdf,.pdf"
                                @change="form.document = $event.target.files[0]"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <p v-if="form.errors.document" class="mt-1 text-xs text-red-500">{{ form.errors.document }}</p>
                            <p v-if="editingManual" class="mt-1 text-xs text-blue-600">
                                📎 Fail sedia ada: <a :href="editingManual.file_path" target="_blank" class="underline">Buka</a>
                            </p>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300">
                            Aktif (papar dalam app)
                        </label>

                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" @click="closeModal" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                                Batal
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </teleport>
    </AppLayout>
</template>

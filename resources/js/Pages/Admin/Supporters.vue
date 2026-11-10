<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    supporters: { type: Object, default: () => ({ data: [], links: [] }) },
    isSuperadmin: Boolean,
    organizations: { type: Array, default: () => [] },
    defaultOrganizationId: Number,
    filters: { type: Object, default: () => ({ search: '' }) },
});

const search = ref(props.filters?.search ?? '');
const form = useForm({
    name: '',
    email: '',
    phone: '',
    notes: '',
    organization_id: props.isSuperadmin ? '' : (props.defaultOrganizationId ?? ''),
});

const importForm = useForm({
    file: null,
    organization_id: props.isSuperadmin ? '' : (props.defaultOrganizationId ?? ''),
});

function submitImport() {
    importForm.post(route('admin.supporters.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => importForm.reset('file'),
    });
}

function submit() {
    form.post(route('admin.supporters.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('name', 'email', 'phone', 'notes'),
    });
}

function runSearch() {
    router.get(route('admin.supporters.index'), { search: search.value }, { preserveState: true, preserveScroll: true });
}

function removeSupporter(item) {
    if (!confirm(`Buang ${item.email} daripada senarai Pendukung?`)) return;
    router.delete(route('admin.supporters.destroy', item.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Pendukung" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Pendukung</template>

        <div class="mx-auto max-w-5xl px-4 py-8 md:px-6 md:py-10">
            <div class="mb-6 rounded-3xl border border-emerald-100 bg-emerald-50/70 p-5">
                <h1 class="text-lg font-black text-emerald-900">Pendukung (Emel Bukan Ahli)</h1>
                <p class="mt-1 text-sm text-emerald-800">
                    Simpan emel orang awam di sini untuk dijadikan sasaran hebahan. Mereka tidak mempunyai akaun ahli.
                </p>
            </div>

            <div v-if="$page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <form @submit.prevent="submit" class="mb-6 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama (pilihan)</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0" placeholder="Nama penuh">
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Emel</label>
                        <input v-model="form.email" type="email" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0" placeholder="nama@emel.com">
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">No. Telefon (pilihan)</label>
                        <input v-model="form.phone" type="tel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0" placeholder="0123456789">
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-500">{{ form.errors.phone }}</p>
                    </div>
                    <div v-if="isSuperadmin">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                        <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                            <option value="">Platform (semua)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                        <p v-if="form.errors.organization_id" class="mt-1 text-xs text-red-500">{{ form.errors.organization_id }}</p>
                    </div>
                </div>
                <div class="mt-3">
                    <label class="mb-1 block text-xs font-semibold text-gray-500">Nota (pilihan)</label>
                    <input v-model="form.notes" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0" placeholder="Contoh: peserta program, kenalan, dll.">
                </div>
                <button type="submit" :disabled="form.processing" class="mt-4 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-60">
                    {{ form.processing ? 'Menyimpan...' : 'Tambah Pendukung' }}
                </button>
            </form>

            <form @submit.prevent="submitImport" class="mb-6 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-bold text-gray-800">Import CSV</h2>
                <p class="mt-1 text-xs text-gray-500">
                    Muat naik fail CSV. Lajur diterima: email (wajib), name/nama, phone/telefon. Emel ahli sedia ada akan dilangkau secara automatik.
                </p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <input
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            @change="importForm.file = $event.target.files[0]"
                        >
                        <p v-if="importForm.errors.file" class="mt-1 text-xs text-red-500">{{ importForm.errors.file }}</p>
                    </div>
                    <div v-if="isSuperadmin" class="sm:w-56">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                        <select v-model="importForm.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                            <option value="">Platform (semua)</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>
                    <button type="submit" :disabled="importForm.processing || !importForm.file" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800 disabled:opacity-60">
                        {{ importForm.processing ? 'Mengimport...' : 'Import CSV' }}
                    </button>
                </div>
            </form>

            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm font-semibold text-gray-500">{{ supporters.total ?? supporters.data.length }} pendukung</p>
                <div class="flex items-center gap-2">
                    <input v-model="search" type="text" placeholder="Cari nama / emel / telefon" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0" @keyup.enter="runSearch">
                    <button type="button" class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="runSearch">Cari</button>
                </div>
            </div>

            <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] uppercase tracking-wider text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Nama</th>
                            <th class="px-4 py-3">Emel</th>
                            <th class="px-4 py-3">Telefon</th>
                            <th v-if="isSuperadmin" class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in supporters.data" :key="item.id" class="border-t border-gray-100">
                            <td class="px-4 py-3 font-semibold text-gray-800">{{ item.name || '-' }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ item.email }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ item.phone || '-' }}</td>
                            <td v-if="isSuperadmin" class="px-4 py-3 text-gray-600">{{ item.organization_name || 'Platform' }}</td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50" @click="removeSupporter(item)">Buang</button>
                            </td>
                        </tr>
                        <tr v-if="!supporters.data.length">
                            <td :colspan="isSuperadmin ? 5 : 4" class="px-4 py-8 text-center text-sm text-gray-400">
                                Belum ada Pendukung. Tambah emel di atas.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="supporters.links?.length > 3" class="mt-4 flex flex-wrap gap-1.5">
                <template v-for="link in supporters.links" :key="link.label">
                    <component
                        :is="link.url ? 'a' : 'span'"
                        :href="link.url || undefined"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                        :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : link.url ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50' : 'border-gray-100 bg-gray-50 text-gray-300'"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

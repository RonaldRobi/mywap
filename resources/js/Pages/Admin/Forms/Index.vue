<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    forms:         Object,
    organizations: Array,
    filters:       Object,
});

const filters = reactive({ search: props.filters?.search ?? '' });

function applyFilters() {
    router.get(route('admin.forms.index'), { ...filters }, { preserveState: true, replace: true });
}

const confirmDelete = ref(null);
function deleteForm(form) {
    if (confirm(`Padam borang "${form.title}"?`)) {
        router.delete(route('admin.forms.destroy', form.id));
    }
}

const sendForm = useForm({ recipient_name: '' });

function sendToEmails(form) {
    if (!form.recipient_count) {
        alert('Tiada emel penerima diisi untuk borang ini. Sila edit borang dan tambah emel penerima dahulu.');
        return;
    }
    if (confirm(`Hantar borang "${form.title}" ke ${form.recipient_count} emel penerima?`)) {
        sendForm.post(route('admin.forms.send', form.id), { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <Head title="Borang" />

        <div class="max-w-6xl mx-auto px-4 py-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Borang</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Bina & urus borang tersuai. Ganti Google Forms.</p>
                </div>
                <Link
                    :href="route('admin.forms.create')"
                    class="inline-flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition"
                >
                    + Borang Baru
                </Link>
            </div>

            <!-- Flash -->
            <div v-if="$page.props.flash?.success" class="rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- Search -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl p-4 shadow-sm">
                <input
                    v-model="filters.search"
                    @keyup.enter="applyFilters"
                    placeholder="Cari borang..."
                    class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:ring-0 focus:border-gray-300 placeholder:text-gray-300"
                />
            </div>

            <!-- Table -->
            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Borang</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Pertubuhan</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Respons</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Awam</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="forms.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada borang. <Link :href="route('admin.forms.create')" class="text-indigo-600 underline">Cipta borang pertama</Link>.</td>
                            </tr>
                            <tr v-for="f in forms.data" :key="f.id" class="hover:bg-gray-50/60 transition">
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800">{{ f.title }}</p>
                                    <p class="text-xs text-gray-400 truncate max-w-[250px]">{{ f.description ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">{{ f.organization_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="f.responses_count > 0 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500'">
                                        {{ f.responses_count }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="f.is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                                        {{ f.is_active ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs">
                                    <span v-if="f.allow_public" class="text-emerald-600">Ya</span>
                                    <span v-else class="text-gray-400">Tidak</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Link
                                            :href="route('admin.forms.responses', f.id)"
                                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 border border-indigo-200 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            Jawapan ({{ f.responses_count }})
                                        </Link>
                                        <button
                                            @click="sendToEmails(f)"
                                            :disabled="sendForm.processing"
                                            class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-50"
                                        >
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                            Hantar
                                        </button>
                                        <Link :href="f.public_url" target="_blank" class="text-xs text-indigo-600 hover:underline">Buka</Link>
                                        <Link :href="route('admin.forms.edit', f.id)" class="text-xs text-gray-600 hover:underline">Edit</Link>
                                        <button @click="deleteForm(f)" class="text-xs text-red-500 hover:underline">Padam</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="forms.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Menunjukkan {{ forms.from }}–{{ forms.to }} daripada {{ forms.total }}</p>
                    <div class="flex gap-2">
                        <a v-if="forms.prev_page_url" :href="forms.prev_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">← Sebelum</a>
                        <a v-if="forms.next_page_url" :href="forms.next_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Seterusnya →</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

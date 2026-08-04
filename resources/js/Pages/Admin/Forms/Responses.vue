<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    form:      Object,
    responses: Object,
});
</script>

<template>
    <AppLayout>
        <Head :title="`Respons — ${form.title}`" />

        <div class="max-w-7xl mx-auto px-4 py-8 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <Link :href="route('admin.forms.index')" class="text-sm text-gray-500 hover:text-gray-700">← Kembali ke senarai borang</Link>
                    <h1 class="text-2xl font-black text-gray-900 mt-1">{{ form.title }}</h1>
                    <p class="text-sm text-gray-500">Jumlah respons: {{ responses.total }}</p>
                </div>
                <a
                    :href="route('admin.forms.export', form.id)"
                    class="inline-flex items-center gap-1.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition"
                >
                    Eksport CSV
                </a>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Emel</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tarikh</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Jawapan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="responses.data.length === 0">
                                <td colspan="5" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada respons diterima.</td>
                            </tr>
                            <tr v-for="r in responses.data" :key="r.id" class="hover:bg-gray-50/60 transition align-top">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ r.id }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ r.respondent_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ r.respondent_email ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ r.submitted_at ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="space-y-1">
                                        <div v-for="a in r.answers" :key="a.question_label" class="text-xs">
                                            <span class="font-semibold text-gray-600">{{ a.question_label }}:</span>
                                            <span class="text-gray-800 ml-1">{{ a.value || '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div v-if="responses.last_page > 1" class="flex items-center justify-between px-4 py-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Menunjukkan {{ responses.from }}–{{ responses.to }} daripada {{ responses.total }}</p>
                    <div class="flex gap-2">
                        <a v-if="responses.prev_page_url" :href="responses.prev_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">← Sebelum</a>
                        <a v-if="responses.next_page_url" :href="responses.next_page_url" class="rounded-lg border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-50">Seterusnya →</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

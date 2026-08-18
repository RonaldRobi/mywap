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
                <div class="flex items-center gap-3">
                    <a
                        :href="route('admin.forms.qr', form.id)"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-100 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        QR
                    </a>
                    <a
                        :href="route('admin.forms.export', form.id)"
                        class="inline-flex items-center gap-1.5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 transition"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Eksport CSV
                    </a>
                </div>
            </div>

            <!-- Summary stats -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-indigo-600">{{ responses.total }}</p>
                    <p class="text-xs text-gray-500 font-semibold">Jumlah Respons</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-emerald-600">{{ form.questions?.length ?? 0 }}</p>
                    <p class="text-xs text-gray-500 font-semibold">Soalan</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-amber-600">{{ responses.data.filter(r => r.respondent_name).length }}</p>
                    <p class="text-xs text-gray-500 font-semibold">Dengan Nama</p>
                </div>
                <div class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm">
                    <p class="text-2xl font-black text-sky-600">{{ responses.data.filter(r => r.respondent_email).length }}</p>
                    <p class="text-xs text-gray-500 font-semibold">Dengan Emel</p>
                </div>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white/80 backdrop-blur-xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nama</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Emel</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Telefon</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tarikh</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Jawapan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-if="responses.data.length === 0">
                                <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">Tiada respons diterima lagi.</td>
                            </tr>
                            <tr v-for="r in responses.data" :key="r.id" class="hover:bg-gray-50/60 transition align-top">
                                <td class="px-4 py-3 text-gray-400 text-xs">{{ r.id }}</td>
                                <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ r.respondent_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ r.respondent_email ?? '—' }}</td>
                                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ r.respondent_phone ?? '—' }}</td>
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

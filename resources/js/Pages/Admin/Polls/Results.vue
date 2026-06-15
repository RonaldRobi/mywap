<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    poll: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    total_responses: { type: Number, default: 0 },
    total_members: { type: Number, default: 0 },
    response_rate: { type: Number, default: 0 },
    respondents: { type: Array, default: () => [] },
});

const showRespondents = ref(false);
</script>

<template>
    <Head :title="'Keputusan - ' + poll.title" />

    <AppLayout>
        <template #header>Keputusan: {{ poll.title }}</template>

        <div class="mx-auto max-w-5xl px-4 py-6 md:px-6 space-y-6">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-gray-400">Jumlah Respon</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ total_responses }}</p>
                </div>
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-gray-400">Jumlah Ahli</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ total_members }}</p>
                </div>
                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <p class="text-xs font-semibold uppercase text-gray-400">Kadar Respon</p>
                    <p class="mt-1 text-2xl font-black text-gray-900">{{ response_rate }}%</p>
                </div>
            </div>

            <div v-for="(q, qi) in questions" :key="q.id" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-base font-bold text-gray-900 mb-1">
                    <span class="text-gray-400">Soalan {{ qi + 1 }}:</span> {{ q.question_text }}
                </h3>
                <p class="text-xs text-gray-400 mb-4">{{ q.total_answers }} jumlah jawapan</p>

                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs font-semibold uppercase text-gray-400">
                            <th class="pb-2">Pilihan</th>
                            <th class="pb-2 text-right">Jumlah</th>
                            <th class="pb-2 text-right">Peratus</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="opt in q.options" :key="opt.id" class="border-b border-gray-50">
                            <td class="py-2 text-gray-700">{{ opt.option_text }}</td>
                            <td class="py-2 text-right font-semibold text-gray-900">{{ opt.count }}</td>
                            <td class="py-2 text-right text-gray-500">{{ opt.percentage }}%</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Visual bar -->
                <div class="mt-3 space-y-1.5">
                    <div v-for="opt in q.options" :key="opt.id" class="space-y-0.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-600 truncate">{{ opt.option_text }}</span>
                        </div>
                        <div class="h-3 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div
                                class="h-full rounded-full bg-gray-900 transition-all duration-500"
                                :style="{ width: opt.percentage + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Respondents List -->
            <section class="rounded-3xl border border-gray-100 bg-white shadow-sm">
                <button @click="showRespondents = !showRespondents" class="flex w-full items-center justify-between px-6 py-4 text-left">
                    <h3 class="text-sm font-bold text-gray-800">Senarai Responden ({{ respondents.length }})</h3>
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 transition-transform" :class="showRespondents ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-if="showRespondents" class="border-t border-gray-100 px-6 py-4">
                    <table v-if="respondents.length" class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold uppercase text-gray-400">
                                <th class="pb-2">Nama</th>
                                <th class="pb-2">Email</th>
                                <th class="pb-2">Dihantar Pada</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in respondents" :key="r.id" class="border-b border-gray-50">
                                <td class="py-2 font-medium text-gray-900">{{ r.name }}</td>
                                <td class="py-2 text-gray-500">{{ r.email }}</td>
                                <td class="py-2 text-gray-500">{{ r.submitted_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-else class="text-sm text-gray-400">Tiada responden.</p>
                </div>
            </section>

            <div class="flex items-center gap-4">
                <a :href="route('admin.polls.index')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">Kembali</a>
                <a :href="route('admin.polls.export', poll.id)" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Export CSV</a>
            </div>
        </div>
    </AppLayout>
</template>

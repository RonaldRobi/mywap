<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    poll: { type: Object, required: true },
    questions: { type: Array, default: () => [] },
    total_responses: { type: Number, default: 0 },
    my_answers: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="'Keputusan - ' + poll.title" />

    <AppLayout>
        <template #header>Keputusan: {{ poll.title }}</template>

        <div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-6">
            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <p class="text-sm text-gray-500">Jumlah respon: <span class="font-bold text-gray-900">{{ total_responses }}</span></p>
                <p v-if="poll.is_expired" class="mt-1 text-xs font-semibold text-red-600">Undian ini telah tamat.</p>
            </div>

            <div v-for="(q, qi) in questions" :key="q.id" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-base font-bold text-gray-900 mb-1">
                    <span class="text-gray-400">Soalan {{ qi + 1 }}:</span> {{ q.question_text }}
                </h3>
                <p class="text-xs text-gray-400 mb-4">{{ q.total_answers }} jumlah jawapan</p>

                <div class="space-y-3">
                    <div v-for="opt in q.options" :key="opt.id" class="space-y-1">
                        <div class="flex items-center justify-between text-sm">
                            <span :class="my_answers.includes(opt.id) ? 'font-bold text-gray-900' : 'text-gray-700'">
                                {{ opt.option_text }}
                                <span v-if="my_answers.includes(opt.id)" class="ml-1 text-xs text-indigo-600">(jawapan anda)</span>
                            </span>
                            <span class="text-xs text-gray-500">{{ opt.count }} ({{ opt.percentage }}%)</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-gray-100 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all duration-500"
                                :class="my_answers.includes(opt.id) ? 'bg-indigo-500' : 'bg-gray-400'"
                                :style="{ width: opt.width_pct + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <a :href="route('member.polls.index')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">Kembali ke Senarai</a>
            </div>
        </div>
    </AppLayout>
</template>

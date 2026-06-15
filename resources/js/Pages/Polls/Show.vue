<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const props = defineProps({
    poll: { type: Object, required: true },
});

const confirmRef = ref(null);

const form = useForm({
    answers: props.poll.questions.map(q => ({
        question_id: q.id,
        option_ids: [],
    })),
});

function toggleOption(questionIndex, optionId) {
    const answer = form.answers[questionIndex];
    const q = props.poll.questions[questionIndex];

    if (q.type === 'single_choice') {
        answer.option_ids = [optionId];
    } else {
        const idx = answer.option_ids.indexOf(optionId);
        if (idx === -1) {
            answer.option_ids.push(optionId);
        } else {
            answer.option_ids.splice(idx, 1);
        }
    }
}

function isSelected(questionIndex, optionId) {
    return form.answers[questionIndex].option_ids.includes(optionId);
}

function allAnswered() {
    return form.answers.every(a => a.option_ids.length > 0);
}

function confirmSubmit() {
    if (!allAnswered()) return;
    confirmRef.value.show();
}

function submitForm() {
    form.post(route('member.polls.respond', props.poll.id), {
        preserveScroll: true,
        onSuccess: () => { /* redirect handled by server */ },
    });
}
</script>

<template>
    <Head :title="poll.title" />

    <AppLayout>
        <template #header>{{ poll.title }}</template>

        <div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-6">
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-center gap-2 mb-1">
                    <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', poll.type === 'poll' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700']">
                        {{ poll.type === 'poll' ? 'Poll' : 'Survey' }}
                    </span>
                    <span v-if="poll.is_expired" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Tamat</span>
                </div>

                <p v-if="poll.description" class="mt-3 text-sm text-gray-600">{{ poll.description }}</p>
                <p v-if="poll.ends_at" class="mt-2 text-xs text-gray-400">Tamat: {{ poll.ends_at_formatted }}</p>
            </div>

            <form @submit.prevent="confirmSubmit">
                <div v-for="(q, qi) in poll.questions" :key="q.id" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-bold text-gray-900 mb-3">
                        <span class="text-gray-400">Soalan {{ qi + 1 }}:</span> {{ q.question_text }}
                    </h3>

                    <div class="space-y-2">
                        <label
                            v-for="option in q.options"
                            :key="option.id"
                            :class="['flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition', isSelected(qi, option.id) ? 'border-gray-900 bg-gray-50' : 'border-gray-200 hover:border-gray-300']"
                        >
                            <input
                                :type="q.type === 'single_choice' ? 'radio' : 'checkbox'"
                                :name="'q_' + q.id"
                                :checked="isSelected(qi, option.id)"
                                :value="option.id"
                                class="h-4 w-4 accent-gray-900"
                                @change="toggleOption(qi, option.id)"
                            />
                            <span class="text-sm text-gray-700">{{ option.option_text }}</span>
                        </label>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-4">
                    <a :href="route('member.polls.index')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">Kembali</a>
                    <button
                        type="submit"
                        :disabled="form.processing || !allAnswered()"
                        class="rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                    >{{ form.processing ? 'Menghantar...' : 'Hantar' }}</button>
                </div>
            </form>

            <ConfirmDialog
                ref="confirmRef"
                title="Hantar Undian?"
                message="Adakah anda pasti dengan jawapan anda? Tindakan ini tidak boleh dibatalkan."
                confirm-text="Hantar"
                cancel-text="Batal"
                @confirm="submitForm"
            />
        </div>
    </AppLayout>
</template>

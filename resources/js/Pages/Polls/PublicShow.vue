<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    poll: { type: Object, required: true },
});

const page = usePage();
const submitted = computed(() => !!page.props.flash?.success);

const form = useForm({
    answers: props.poll.questions.map((q) => ({
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
    return form.answers.every((a) => a.option_ids.length > 0);
}

function submitForm() {
    form.post(route('polls.public.respond', props.poll.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="poll.title" />

    <div class="min-h-screen bg-gradient-to-br from-indigo-50 via-white to-emerald-50 px-4 py-8 md:py-14">
        <div class="mx-auto w-full max-w-xl">
            <div class="mb-6 flex items-center justify-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span class="text-sm font-bold text-gray-700">myWAP {{ t('Maklum Balas') }}</span>
            </div>

            <!-- Thank you state -->
            <div v-if="submitted" class="rounded-3xl border border-emerald-200 bg-white p-10 text-center shadow-lg">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h1 class="mt-5 text-xl font-black text-gray-900">{{ t('Terima kasih!') }}</h1>
                <p class="mt-2 text-sm text-gray-600">{{ t('Maklum balas anda berjaya dihantar.') }}</p>
            </div>

            <template v-else>
                <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-lg">
                    <div class="flex items-center gap-2">
                        <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', poll.type === 'poll' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700']">
                            {{ poll.type === 'poll' ? 'Poll' : 'Survey' }}
                        </span>
                        <span v-if="poll.is_expired" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">{{ t('Tamat') }}</span>
                    </div>
                    <h1 class="mt-3 text-xl font-black text-gray-900">{{ poll.title }}</h1>
                    <p v-if="poll.description" class="mt-2 text-sm text-gray-600">{{ poll.description }}</p>
                    <p v-if="poll.ends_at" class="mt-2 text-xs text-gray-400">{{ t('Tamat') }}: {{ poll.ends_at_formatted }}</p>
                </div>

                <form @submit.prevent="submitForm" class="mt-4 space-y-4">
                    <div v-for="(q, qi) in poll.questions" :key="q.id" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-lg">
                        <h3 class="text-base font-bold text-gray-900">
                            <span class="text-gray-400">{{ t('Soalan') }} {{ qi + 1 }}:</span> {{ q.question_text }}
                        </h3>
                        <div class="mt-3 space-y-2">
                            <label
                                v-for="option in q.options"
                                :key="option.id"
                                :class="['flex items-center gap-3 rounded-xl border p-3 cursor-pointer transition', isSelected(qi, option.id) ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300']"
                            >
                                <input
                                    :type="q.type === 'single_choice' ? 'radio' : 'checkbox'"
                                    :name="'q_' + q.id"
                                    :checked="isSelected(qi, option.id)"
                                    :value="option.id"
                                    class="h-4 w-4 accent-indigo-600"
                                    @change="toggleOption(qi, option.id)"
                                />
                                <span class="text-sm text-gray-700">{{ option.option_text }}</span>
                            </label>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !allAnswered()"
                        class="w-full rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-lg hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {{ form.processing ? t('Menghantar...') : t('Hantar Maklum Balas') }}
                    </button>
                </form>
            </template>
        </div>
    </div>
</template>

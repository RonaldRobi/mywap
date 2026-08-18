<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({ form: Object });

const responseForm = useForm({
    respondent_name:  '',
    respondent_email: '',
    respondent_phone: '',
    answers: {},
});

function initAnswers() {
    for (const q of props.form.questions) {
        if (!(q.id in responseForm.answers)) {
            responseForm.answers[q.id] = q.type === 'checkbox' ? [] : '';
        }
    }
}
initAnswers();

function submit() {
    responseForm.post(route('forms.public.submit', { token: props.form.share_token ?? window.location.pathname.split('/').pop() }));
}
</script>

<template>
    <Head :title="form.title" />

    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100">
        <div class="max-w-2xl mx-auto px-4 py-12">
            <!-- Success -->
            <div v-if="$page.props.flash?.success" class="rounded-3xl bg-emerald-50 border border-emerald-200 p-8 text-center space-y-4">
                <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                </div>
                <p class="text-lg font-bold text-emerald-700">{{ $page.props.flash.success }}</p>
                <p class="text-sm text-emerald-600">Respons anda telah direkodkan. Terima kasih!</p>
            </div>

            <!-- Form -->
            <div v-else class="space-y-0">
                <!-- Header Image -->
                <div v-if="form.header_image_url" class="rounded-t-3xl overflow-hidden">
                    <img :src="form.header_image_url" :alt="form.title" class="w-full aspect-[16/9] object-cover" />
                </div>

                <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-8 shadow-sm space-y-6" :class="form.header_image_url ? 'rounded-t-none border-t-0' : ''">
                    <div>
                        <p v-if="form.organization_name" class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">{{ form.organization_name }}</p>
                        <h1 class="text-2xl font-black text-gray-900">{{ form.title }}</h1>
                        <p v-if="form.description" class="text-sm text-gray-500 mt-2 whitespace-pre-line">{{ form.description }}</p>
                    </div>

                <div class="space-y-4">
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wide">Maklumat Responden</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="responseForm.respondent_name" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Nama (opsional)" />
                        <input v-model="responseForm.respondent_email" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Emel (opsional)" />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <input v-model="responseForm.respondent_phone" type="tel" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Telefon (opsional)" />
                    </div>
                </div>

                <hr class="border-gray-100" />

                <div v-for="q in form.questions" :key="q.id" class="space-y-1.5">
                    <label class="block text-sm font-semibold text-gray-700">
                        {{ q.label }}
                        <span v-if="q.required" class="text-red-500">*</span>
                    </label>
                    <p v-if="q.help_text" class="text-xs text-gray-400">{{ q.help_text }}</p>

                    <!-- Text / Email / Phone / Number / Date -->
                    <input
                        v-if="['text', 'email', 'phone', 'number', 'date'].includes(q.type)"
                        v-model="responseForm.answers[q.id]"
                        :type="q.type === 'phone' ? 'tel' : q.type"
                        :placeholder="q.placeholder || ''"
                        :required="q.required"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300"
                    />

                    <!-- Textarea -->
                    <textarea
                        v-else-if="q.type === 'textarea'"
                        v-model="responseForm.answers[q.id]"
                        :placeholder="q.placeholder || ''"
                        :required="q.required"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300"
                    ></textarea>

                    <!-- Select -->
                    <select
                        v-else-if="q.type === 'select'"
                        v-model="responseForm.answers[q.id]"
                        :required="q.required"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                    >
                        <option value="">{{ q.placeholder || 'Pilih...' }}</option>
                        <option v-for="opt in q.options" :key="opt" :value="opt">{{ opt }}</option>
                    </select>

                    <!-- Radio -->
                    <div v-else-if="q.type === 'radio'" class="space-y-1.5">
                        <label v-for="opt in q.options" :key="opt" class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="radio" v-model="responseForm.answers[q.id]" :value="opt" class="text-indigo-600 border-gray-300" />
                            {{ opt }}
                        </label>
                    </div>

                    <!-- Checkbox -->
                    <div v-else-if="q.type === 'checkbox'" class="space-y-1.5">
                        <label v-for="opt in q.options" :key="opt" class="flex items-center gap-2 text-sm cursor-pointer">
                            <input type="checkbox" :value="opt" v-model="responseForm.answers[q.id]" class="rounded border-gray-300 text-indigo-600" />
                            {{ opt }}
                        </label>
                    </div>

                    <p v-if="responseForm.errors[`answers.${q.id}`]" class="text-xs text-red-500">{{ responseForm.errors[`answers.${q.id}`] }}</p>
                </div>

                <button
                    @click="submit"
                    :disabled="responseForm.processing"
                    class="w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-indigo-700 transition disabled:opacity-50"
                >
                    {{ responseForm.processing ? 'Menghantar...' : 'Hantar' }}
                </button>

                <p class="text-center text-xs text-gray-400">
                    Dikuasakan oleh myWAP
                </p>
                </div>
            </div>
        </div>
    </div>
</template>

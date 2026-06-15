<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    templates: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const activeKey = ref(props.templates[0]?.key ?? null);

const activeTemplate = props.templates.find(t => t.key === activeKey.value) ?? props.templates[0];

const form = useForm({
    subject: activeTemplate?.subject ?? '',
    body: activeTemplate?.body ?? '',
});

function switchTemplate(key) {
    activeKey.value = key;
    const t = props.templates.find(t => t.key === key);
    if (t) {
        form.subject = t.subject;
        form.body = t.body;
        form.clearErrors();
    }
}

function save() {
    const t = props.templates.find(t => t.key === activeKey.value);
    if (!t) return;

    form.put(route('admin.email-templates.update', { emailTemplate: t.id }), {
        preserveScroll: true,
    });
}

const templateLabels = {
    otp_login: 'OTP Pengesahan Emel',
};

const templateDescriptions = {
    otp_login: 'Dihantar kepada ahli semasa pendaftaran kali pertama atau menukar emel.',
};

const placeholderInfo = [
    { key: '{{name}}', desc: 'Nama ahli' },
    { key: '{{code}}', desc: 'Kod OTP 6-digit' },
    { key: '{{purpose}}', desc: 'Tujuan (Log Masuk / Pengesahan Emel)' },
];
</script>

<template>
    <AppLayout>
        <Head title="Template Emel" />

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Template Emel</h1>
                <p class="mt-1 text-sm text-gray-500">Uruskan template emel untuk pemberitahuan sistem.</p>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ page.props.flash.success }}
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-6 flex flex-wrap gap-2">
                    <button
                        v-for="t in templates"
                        :key="t.key"
                        type="button"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                        :class="activeKey === t.key ? 'bg-gray-900 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        @click="switchTemplate(t.key)"
                    >
                        {{ templateLabels[t.key] || t.key }}
                    </button>
                </div>

                <form @submit.prevent="save" class="space-y-4">
                    <p class="text-xs text-gray-500">{{ templateDescriptions[activeKey] }}</p>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Subjek</span>
                        <input v-model="form.subject" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" maxlength="255">
                        <p v-if="form.errors.subject" class="mt-1 text-xs text-red-500">{{ form.errors.subject }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Body</span>
                        <textarea v-model="form.body" rows="8" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono"></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-500">{{ form.errors.body }}</p>
                    </label>

                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800">
                        <p class="mb-2 font-semibold">Placeholders yang boleh digunakan:</p>
                        <ul class="space-y-1">
                            <li v-for="p in placeholderInfo" :key="p.key" class="flex gap-2">
                                <code class="shrink-0 rounded bg-blue-100 px-1.5 py-0.5 font-bold">{{ p.key }}</code>
                                <span>{{ p.desc }}</span>
                            </li>
                        </ul>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Template' }}
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

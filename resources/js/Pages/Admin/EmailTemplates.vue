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
    header_image: null,
    remove_header_image: false,
});

function switchTemplate(key) {
    activeKey.value = key;
    const t = props.templates.find(t => t.key === key);
    if (t) {
        form.subject = t.subject;
        form.body = t.body;
        form.header_image = null;
        form.remove_header_image = false;
        form.clearErrors();
    }
}

function save() {
    const t = props.templates.find(t => t.key === activeKey.value);
    if (!t) return;

    form.put(route('admin.email-templates.update', { emailTemplate: t.id }), {
        preserveScroll: true,
        forceFormData: true,
    });
}

function onImageSelected(event) {
    const file = event.target.files?.[0];
    if (file) {
        form.header_image = file;
        form.remove_header_image = false;
    }
}

function removeImage() {
    form.header_image = null;
    form.remove_header_image = true;
}

const templateLabels = {
    otp_login: 'OTP Log Masuk',
    otp_email_verify: 'OTP Pengesahan Emel',
    registration_received: 'Pendaftaran Diterima',
    registration_activated: 'Akaun Diaktifkan',
    new_member_alert: 'Pemberitahuan Admin (Ahli Baru)',
    registration_confirmation: 'Pengesahan Pendaftaran Program',
    form_invitation: 'Jemputan Borang',
    password_reset: 'Tetapkan Semula Kata Laluan',
};

const templateDescriptions = {
    otp_login: 'Dihantar kepada ahli semasa log masuk kali pertama (kod OTP).',
    otp_email_verify: 'Dihantar untuk mengesahkan alamat emel ahli (kod OTP).',
    registration_received: 'Dihantar kepada pendaftar baharu selepas pendaftaran diterima, sebelum bayaran yuran.',
    registration_activated: 'Dihantar selepas yuran disahkan dan akaun ahli diaktifkan.',
    new_member_alert: 'Dihantar kepada pentadbir apabila seorang ahli baharu mendaftar.',
    registration_confirmation: 'Dihantar kepada peserta selepas pendaftaran program/event disahkan.',
    form_invitation: 'Dihantar untuk menjemput seseorang mengisi borang pendaftaran.',
    password_reset: 'Dihantar kepada ahli yang meminta tetapan semula kata laluan.',
};

const placeholders = {
    otp_login: [
        { key: '{{name}}', desc: 'Nama ahli' },
        { key: '{{code}}', desc: 'Kod OTP 6-digit' },
        { key: '{{purpose}}', desc: 'Tujuan (Log Masuk)' },
    ],
    otp_email_verify: [
        { key: '{{name}}', desc: 'Nama ahli' },
        { key: '{{code}}', desc: 'Kod OTP 6-digit' },
        { key: '{{purpose}}', desc: 'Tujuan (Pengesahan Emel)' },
    ],
    registration_received: [
        { key: '{{name}}', desc: 'Nama ahli' },
        { key: '{{member_no}}', desc: 'No Ahli' },
        { key: '{{organization}}', desc: 'Nama organisasi' },
        { key: '{{branch}}', desc: 'Cawangan' },
        { key: '{{fee}}', desc: 'Yuran (RM)' },
    ],
    registration_activated: [
        { key: '{{name}}', desc: 'Nama ahli' },
        { key: '{{member_no}}', desc: 'No Ahli' },
        { key: '{{organization}}', desc: 'Nama organisasi' },
        { key: '{{login_link}}', desc: 'Pautan log masuk' },
    ],
    new_member_alert: [
        { key: '{{name}}', desc: 'Nama ahli baharu' },
        { key: '{{member_no}}', desc: 'No Ahli' },
        { key: '{{ic_number}}', desc: 'No IC' },
        { key: '{{organization}}', desc: 'Nama organisasi' },
        { key: '{{branch}}', desc: 'Cawangan' },
        { key: '{{fee}}', desc: 'Yuran (RM)' },
    ],
    registration_confirmation: [
        { key: '{{name}}', desc: 'Nama peserta' },
        { key: '{{registration_no}}', desc: 'No Pendaftaran' },
        { key: '{{event_title}}', desc: 'Tajuk program/event' },
        { key: '{{event_date}}', desc: 'Tarikh & masa program' },
        { key: '{{location}}', desc: 'Lokasi / pautan' },
        { key: '{{payment_status}}', desc: 'Status bayaran' },
    ],
    form_invitation: [
        { key: '{{name}}', desc: 'Nama penerima' },
        { key: '{{form_title}}', desc: 'Tajuk borang' },
        { key: '{{form_link}}', desc: 'Pautan borang' },
        { key: '{{organization}}', desc: 'Nama organisasi' },
    ],
    password_reset: [
        { key: '{{name}}', desc: 'Nama ahli' },
        { key: '{{url}}', desc: 'Pautan tetapan semula kata laluan' },
    ],
};
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
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

                    <div class="rounded-2xl border border-gray-200 p-4">
                        <p class="mb-2 text-xs font-semibold text-gray-500">Imej Header (Logo)</p>
                        <p class="mb-3 text-xs text-gray-400">Logo/imej ini akan muncul di bahagian atas emel. Kosongkan untuk guna logo sistem.</p>

                        <div v-if="form.header_image || activeTemplate?.header_image_path" class="mb-3">
                            <img
                                :src="form.header_image ? URL.createObjectURL(form.header_image) : activeTemplate.header_image_path"
                                alt="Preview"
                                class="h-12 w-auto rounded-lg border border-gray-200 bg-white object-contain"
                            >
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <label class="cursor-pointer rounded-xl border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                Pilih Imej
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onImageSelected">
                            </label>
                            <button
                                v-if="form.header_image || activeTemplate?.header_image_path"
                                type="button"
                                @click="removeImage"
                                class="rounded-xl border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50"
                            >
                                Buang Imej
                            </button>
                            <span v-if="form.header_image" class="text-xs text-emerald-600">Imej baharu dipilih — tekan Simpan.</span>
                        </div>
                        <p v-if="form.errors.header_image" class="mt-1 text-xs text-red-500">{{ form.errors.header_image }}</p>
                    </div>

                    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-xs text-blue-800">
                        <p class="mb-2 font-semibold">Placeholders yang boleh digunakan:</p>
                        <ul class="space-y-1">
                            <li v-for="p in (placeholders[activeKey] || [])" :key="p.key" class="flex gap-2">
                                <code class="shrink-0 rounded bg-blue-100 px-1.5 py-0.5 font-bold">{{ p.key }}</code>
                                <span>{{ p.desc }}</span>
                            </li>
                            <li v-if="!(placeholders[activeKey] || []).length" class="text-blue-500">Tiada placeholder khusus.</li>
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

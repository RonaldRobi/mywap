<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({ statement: { type: Object, required: true }, organizations: { type: Array, default: () => [] } });
const form = useForm({ organization_id: props.statement.organization_id ?? '', title: props.statement.title, content: props.statement.content, featured_image: null, pdf: null, attachments: [], is_published: !!props.statement.is_published, is_featured: !!props.statement.is_featured });
function submit() { form.transform(data => ({ ...data, _method: 'put' })).post(route('admin.media-statements.update', props.statement.id), { forceFormData: true }); }
</script>

<template>
    <Head :title="'Edit: ' + statement.title" /><AppLayout :back-route="route('admin.media-statements.index')" back-label="Kembali ke Senarai"><template #header>Edit Kenyataan Media</template>
        <div class="mx-auto max-w-4xl px-4 py-6 md:px-6"><form class="space-y-5" @submit.prevent="submit"><section class="space-y-4 rounded-3xl border bg-white p-6 shadow-sm"><label class="block text-sm font-semibold">Organisasi<select v-model="form.organization_id" class="mt-1 w-full rounded-xl border px-3 py-2"><option value="">Umum (Semua)</option><option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option></select></label><label class="block text-sm font-semibold">Tajuk<input v-model="form.title" required class="mt-1 w-full rounded-xl border px-3 py-2" /></label><label class="block text-sm font-semibold">Kandungan<div class="mt-1 overflow-hidden rounded-xl border"><QuillEditor v-model:content="form.content" contentType="html" toolbar="full" class="min-h-[360px]" /></div></label></section><section class="space-y-4 rounded-3xl border bg-white p-6 shadow-sm"><p v-if="statement.pdf_path" class="text-sm">PDF semasa: <a :href="statement.pdf_path" target="_blank" class="text-emerald-700 underline">Buka PDF</a></p><label class="block text-sm font-semibold">Gambar utama<input type="file" accept="image/*" class="mt-1 block w-full" @change="form.featured_image = $event.target.files?.[0]" /></label><label class="block text-sm font-semibold">PDF<input type="file" accept="application/pdf" class="mt-1 block w-full" @change="form.pdf = $event.target.files?.[0]" /></label><label class="block text-sm font-semibold">Lampiran gambar<input type="file" accept="image/*" multiple class="mt-1 block w-full" @change="form.attachments = Array.from($event.target.files || [])" /></label><div class="flex gap-5 text-sm"><label><input v-model="form.is_published" type="checkbox" /> Terbitkan</label><label><input v-model="form.is_featured" type="checkbox" /> Featured</label></div><button :disabled="form.processing" class="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white">{{ form.processing ? 'Menyimpan...' : 'Kemas Kini' }}</button></section></form></div>
    </AppLayout>
</template>

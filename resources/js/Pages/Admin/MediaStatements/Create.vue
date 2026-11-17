<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

defineProps({ organizations: { type: Array, default: () => [] } });
const form = useForm({ organization_id: '', title: '', content: '', featured_image: null, pdf: null, attachments: [], is_published: true, is_featured: false });
function submit() { form.post(route('admin.media-statements.store'), { forceFormData: true }); }
</script>

<template>
    <Head title="Kenyataan Media Baru" />
    <AppLayout :back-route="route('admin.media-statements.index')" back-label="Kembali ke Senarai"><template #header>Kenyataan Media Baru</template>
        <div class="mx-auto max-w-4xl px-4 py-6 md:px-6"><form class="space-y-5" @submit.prevent="submit">
            <section class="space-y-4 rounded-3xl border bg-white p-6 shadow-sm">
                <label class="block text-sm font-semibold">Organisasi<select v-model="form.organization_id" class="mt-1 w-full rounded-xl border px-3 py-2"><option value="">Umum (Semua)</option><option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option></select></label>
                <label class="block text-sm font-semibold">Tajuk<input v-model="form.title" required class="mt-1 w-full rounded-xl border px-3 py-2" /></label>
                <label class="block text-sm font-semibold">Kandungan<div class="mt-1 overflow-hidden rounded-xl border"><QuillEditor v-model:content="form.content" contentType="html" toolbar="full" class="min-h-[360px]" /></div></label>
            </section>
            <section class="space-y-4 rounded-3xl border bg-white p-6 shadow-sm"><label class="block text-sm font-semibold">Gambar utama<input type="file" accept="image/*" class="mt-1 block w-full" @change="form.featured_image = $event.target.files?.[0]" /></label><label class="block text-sm font-semibold">PDF<input type="file" accept="application/pdf" class="mt-1 block w-full" @change="form.pdf = $event.target.files?.[0]" /></label><label class="block text-sm font-semibold">Lampiran gambar<input type="file" accept="image/*" multiple class="mt-1 block w-full" @change="form.attachments = Array.from($event.target.files || [])" /></label><div class="flex gap-5 text-sm"><label><input v-model="form.is_published" type="checkbox" /> Terbitkan</label><label><input v-model="form.is_featured" type="checkbox" /> Featured</label></div><button :disabled="form.processing" class="rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white">{{ form.processing ? 'Menyimpan...' : 'Simpan' }}</button></section>
        </form></div>
    </AppLayout>
</template>

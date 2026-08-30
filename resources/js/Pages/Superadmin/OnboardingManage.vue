<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({ slides: { type: Array, default: () => [] }, loginBranding: { type: Object, required: true } });
const forms = ref(Object.fromEntries(props.slides.map((slide) => [slide.id, useForm({
    title: slide.title ?? '', body: slide.body ?? '', button_label: slide.button_label ?? '', button_url: slide.button_url ?? '',
    background_start: slide.background_start, background_end: slide.background_end, text_color: slide.text_color, is_active: !!slide.is_active, media: null,
})])));
const previews = ref({});
const loginForm = useForm({
    mobile_login_title: props.loginBranding.title ?? '', mobile_login_subtitle: props.loginBranding.subtitle ?? '',
    mobile_login_background_start: props.loginBranding.background_start, mobile_login_background_end: props.loginBranding.background_end,
    mobile_login_accent: props.loginBranding.accent,
});

function selectMedia(slide, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    forms.value[slide.id].media = file;
    previews.value[slide.id] = { url: URL.createObjectURL(file), video: file.type === 'video/mp4', name: file.name, size: (file.size / 1024 / 1024).toFixed(1) + ' MB' };
}

function save(slide) {
    forms.value[slide.id].transform((data) => ({ ...data, _method: 'put' })).post(route('superadmin.onboarding.update', slide.id), { forceFormData: true, preserveScroll: true, onSuccess: () => { forms.value[slide.id].media = null; delete previews.value[slide.id]; } });
}

function deleteMedia(slide) {
    if (confirm(`Padam media Slide ${slide.slide_order}?`)) useForm({}).delete(route('superadmin.onboarding.media.destroy', slide.id), { preserveScroll: true });
}

function saveLoginBranding() {
    loginForm.put(route('superadmin.onboarding.login-branding.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Onboarding Aplikasi" />
    <AppLayout>
        <template #header>Onboarding Aplikasi</template>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ $page.props.flash.success }}</div>
            <section class="rounded-3xl border border-indigo-100 bg-indigo-50 p-5 text-sm text-indigo-950">
                <h1 class="font-black">3 Slide Onboarding Mobile</h1>
                <p class="mt-1 text-indigo-800">Konfigurasi ini digunakan oleh aplikasi iOS dan Android sebelum pengguna log masuk. Setiap slide boleh mempunyai gradient, teks, butang dan media sendiri.</p>
                <p class="mt-3 font-semibold">Media disyorkan: 1080×1920px (nisbah 9:16). Format: JPG, PNG, WebP, GIF atau MP4. Maksimum 10MB. MP4 disyorkan H.264 tanpa audio atau audio ringkas untuk prestasi terbaik.</p>
            </section>
            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Flutter sahaja</p><h2 class="text-lg font-black text-gray-900">Branding Log Masuk Mobile</h2><p class="mt-1 text-sm text-gray-500">Tetapan ini digunakan pada aplikasi iOS dan Android sahaja, bukan halaman login web.</p></div>
                <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveLoginBranding">
                    <div><label class="field-label">Tajuk</label><input v-model="loginForm.mobile_login_title" class="field-input" maxlength="120"></div>
                    <div><label class="field-label">Penerangan</label><input v-model="loginForm.mobile_login_subtitle" class="field-input" maxlength="255"></div>
                    <div><label class="field-label">Background Mula</label><input v-model="loginForm.mobile_login_background_start" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                    <div><label class="field-label">Background Tamat</label><input v-model="loginForm.mobile_login_background_end" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                    <div><label class="field-label">Warna Utama / Butang</label><input v-model="loginForm.mobile_login_accent" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                    <div class="flex items-end"><button :disabled="loginForm.processing" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60">{{ loginForm.processing ? 'Menyimpan...' : 'Simpan Branding Login' }}</button></div>
                </form>
            </section>
            <section v-for="slide in slides" :key="slide.id" class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <div><p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Slide {{ slide.slide_order }} daripada 3</p><h2 class="font-black text-gray-900">{{ slide.title || 'Belum dinamakan' }}</h2></div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600"><input v-model="forms[slide.id].is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600"> Aktif</label>
                </div>
                <form class="grid gap-5 p-5 md:grid-cols-[minmax(0,1fr)_280px]" @submit.prevent="save(slide)">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2"><label class="field-label">Tajuk</label><input v-model="forms[slide.id].title" class="field-input" maxlength="120"><p v-if="forms[slide.id].errors.title" class="field-error">{{ forms[slide.id].errors.title }}</p></div>
                        <div class="md:col-span-2"><label class="field-label">Penerangan</label><textarea v-model="forms[slide.id].body" class="field-input" rows="3" maxlength="1000" /></div>
                        <div><label class="field-label">Teks Butang</label><input v-model="forms[slide.id].button_label" class="field-input" maxlength="40" placeholder="Contoh: Seterusnya"></div>
                        <div><label class="field-label">URL Butang (pilihan)</label><input v-model="forms[slide.id].button_url" class="field-input" type="url" placeholder="https://..."></div>
                        <div><label class="field-label">Gradient Mula</label><input v-model="forms[slide.id].background_start" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Gradient Tamat</label><input v-model="forms[slide.id].background_end" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Warna Teks</label><input v-model="forms[slide.id].text_color" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Media</label><input accept="image/jpeg,image/png,image/webp,image/gif,video/mp4" class="block w-full text-xs" type="file" @change="selectMedia(slide, $event)"><p v-if="forms[slide.id].errors.media" class="field-error">{{ forms[slide.id].errors.media }}</p></div>
                        <div class="md:col-span-2"><button :disabled="forms[slide.id].processing" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60">{{ forms[slide.id].processing ? 'Menyimpan...' : 'Simpan Slide' }}</button></div>
                    </div>
                    <aside class="rounded-2xl border border-gray-200 bg-gray-50 p-3">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Thumbnail Media</p>
                        <video v-if="previews[slide.id]?.video" :src="previews[slide.id].url" class="aspect-[9/16] w-full rounded-xl bg-gray-900 object-cover" controls muted />
                        <img v-else-if="previews[slide.id]" :src="previews[slide.id].url" class="aspect-[9/16] w-full rounded-xl object-cover">
                        <video v-else-if="slide.media_path && slide.media_type === 'video'" :src="slide.media_path" class="aspect-[9/16] w-full rounded-xl bg-gray-900 object-cover" controls muted />
                        <img v-else-if="slide.media_path" :src="slide.media_path" class="aspect-[9/16] w-full rounded-xl object-cover">
                        <div v-else class="flex aspect-[9/16] items-center justify-center rounded-xl border-2 border-dashed border-gray-200 text-center text-xs text-gray-400">Tiada media</div>
                        <p v-if="previews[slide.id]" class="mt-2 truncate text-xs text-gray-500">{{ previews[slide.id].name }} · {{ previews[slide.id].size }}</p>
                        <button v-if="slide.media_path" type="button" class="mt-3 text-xs font-semibold text-red-600" @click="deleteMedia(slide)">Padam media sedia ada</button>
                    </aside>
                </form>
            </section>
            <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
        </main>
    </AppLayout>
</template>

<style scoped>
.field-label { @apply mb-1 block text-xs font-semibold text-gray-500; }
.field-input { @apply w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0; }
.field-error { @apply mt-1 text-xs font-semibold text-red-600; }
</style>

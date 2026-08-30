<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    slides: { type: Array, default: () => [] },
    loginBranding: { type: Object, required: true },
});

const forms = ref(Object.fromEntries(props.slides.map((slide) => [slide.id, useForm({
    title: slide.title ?? '',
    body: slide.body ?? '',
    button_label: slide.button_label ?? '',
    button_url: slide.button_url ?? '',
    background_start: slide.background_start,
    background_end: slide.background_end,
    text_color: slide.text_color,
    overlay_start_color: slide.overlay_start_color ?? '#071525',
    overlay_end_color: slide.overlay_end_color ?? '#071525',
    overlay_start_opacity: slide.overlay_start_opacity ?? 0,
    overlay_end_opacity: slide.overlay_end_opacity ?? 90,
    overlay_start_position: slide.overlay_start_position ?? 0,
    overlay_end_position: slide.overlay_end_position ?? 100,
    is_active: !!slide.is_active,
    media: null,
})])));

const previews = ref({});

const loginForm = useForm({
    mobile_login_title: props.loginBranding.title ?? '',
    mobile_login_subtitle: props.loginBranding.subtitle ?? '',
    mobile_login_background_start: props.loginBranding.background_start,
    mobile_login_background_end: props.loginBranding.background_end,
    mobile_login_accent: props.loginBranding.accent,
});

// Preset biasa supaya admin tak perlu tune manual dari kosong.
const presets = [
    { label: 'Gelap di bawah', start: 0, end: 100, startOpacity: 0, endOpacity: 90 },
    { label: 'Gelap separuh bawah', start: 45, end: 100, startOpacity: 0, endOpacity: 92 },
    { label: 'Gelap penuh', start: 0, end: 100, startOpacity: 35, endOpacity: 95 },
    { label: 'Tiada overlay', start: 0, end: 100, startOpacity: 0, endOpacity: 0 },
];

function applyPreset(slide, preset) {
    const form = forms.value[slide.id];
    form.overlay_start_position = preset.start;
    form.overlay_end_position = preset.end;
    form.overlay_start_opacity = preset.startOpacity;
    form.overlay_end_opacity = preset.endOpacity;
}

function rgba(hex, opacityPercent) {
    const value = hex.replace('#', '');
    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    return `rgba(${r}, ${g}, ${b}, ${opacityPercent / 100})`;
}

/// Gradient CSS yang sama formulanya dengan Flutter: warna + opacity + posisi %.
function overlayCss(slideId) {
    const form = forms.value[slideId];
    const start = Math.min(form.overlay_start_position, form.overlay_end_position);
    const end = Math.max(form.overlay_start_position, form.overlay_end_position);
    return `linear-gradient(to bottom, ${rgba(form.overlay_start_color, form.overlay_start_opacity)} ${start}%, ${rgba(form.overlay_end_color, form.overlay_end_opacity)} ${end}%)`;
}

function backgroundCss(slideId) {
    const form = forms.value[slideId];
    return `linear-gradient(135deg, ${form.background_start}, ${form.background_end})`;
}

function mediaFor(slide) {
    const local = previews.value[slide.id];
    if (local) return { url: local.url, video: local.video };
    if (slide.media_path) return { url: slide.media_path, video: slide.media_type === 'video' };
    return null;
}

function selectMedia(slide, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    forms.value[slide.id].media = file;
    previews.value[slide.id] = {
        url: URL.createObjectURL(file),
        video: file.type === 'video/mp4',
        name: file.name,
        size: (file.size / 1024 / 1024).toFixed(1) + ' MB',
    };
}

function save(slide) {
    forms.value[slide.id]
        .transform((data) => ({ ...data, _method: 'put' }))
        .post(route('superadmin.onboarding.update', slide.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                forms.value[slide.id].media = null;
                delete previews.value[slide.id];
            },
        });
}

function deleteMedia(slide) {
    if (confirm(`Padam media Slide ${slide.slide_order}?`)) {
        useForm({}).delete(route('superadmin.onboarding.media.destroy', slide.id), { preserveScroll: true });
    }
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
                <p class="mt-1 text-indigo-800">Konfigurasi ini digunakan oleh aplikasi iOS dan Android sahaja, sebelum pengguna log masuk. Halaman login web tidak terjejas.</p>
                <p class="mt-3 font-semibold">Media disyorkan: 1080×1920px (nisbah 9:16). Format: JPG, PNG, WebP, GIF atau MP4. Maksimum 10MB.</p>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Flutter sahaja</p>
                    <h2 class="text-lg font-black text-gray-900">Branding Log Masuk Mobile</h2>
                </div>
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
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Slide {{ slide.slide_order }} daripada 3</p>
                        <h2 class="font-black text-gray-900">{{ forms[slide.id].title || 'Belum dinamakan' }}</h2>
                    </div>
                    <label class="flex items-center gap-2 text-sm font-semibold text-gray-600">
                        <input v-model="forms[slide.id].is_active" type="checkbox" class="rounded border-gray-300 text-emerald-600"> Aktif
                    </label>
                </div>

                <form class="grid gap-6 p-5 md:grid-cols-[minmax(0,1fr)_300px]" @submit.prevent="save(slide)">
                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="md:col-span-2"><label class="field-label">Tajuk</label><input v-model="forms[slide.id].title" class="field-input" maxlength="120"><p v-if="forms[slide.id].errors.title" class="field-error">{{ forms[slide.id].errors.title }}</p></div>
                        <div class="md:col-span-2"><label class="field-label">Penerangan</label><textarea v-model="forms[slide.id].body" class="field-input" rows="3" maxlength="1000" /></div>
                        <div><label class="field-label">Teks Butang</label><input v-model="forms[slide.id].button_label" class="field-input" maxlength="40" placeholder="Contoh: Seterusnya"></div>
                        <div><label class="field-label">URL Butang (pilihan)</label><input v-model="forms[slide.id].button_url" class="field-input" type="url" placeholder="https://..."></div>
                        <div><label class="field-label">Background Mula</label><input v-model="forms[slide.id].background_start" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Background Tamat</label><input v-model="forms[slide.id].background_end" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Warna Teks</label><input v-model="forms[slide.id].text_color" class="h-10 w-full rounded-xl border border-gray-200" type="color"></div>
                        <div><label class="field-label">Media</label><input accept="image/jpeg,image/png,image/webp,image/gif,video/mp4" class="block w-full text-xs" type="file" @change="selectMedia(slide, $event)"><p v-if="forms[slide.id].errors.media" class="field-error">{{ forms[slide.id].errors.media }}</p></div>

                        <!-- ── Editor overlay gradient ── -->
                        <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-slate-50 p-4">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <p class="text-xs font-bold uppercase tracking-wide text-gray-600">Overlay Gradient</p>
                                <div class="flex flex-wrap gap-1.5">
                                    <button v-for="preset in presets" :key="preset.label" type="button" class="rounded-full border border-gray-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-gray-600 hover:border-gray-900 hover:text-gray-900" @click="applyPreset(slide, preset)">{{ preset.label }}</button>
                                </div>
                            </div>

                            <div class="flex gap-4">
                                <!-- Bar menegak: wakil skrin telefon dari atas ke bawah -->
                                <div class="relative w-14 shrink-0 overflow-hidden rounded-xl border border-gray-300 bg-[repeating-conic-gradient(#e5e7eb_0_25%,#ffffff_0_50%)] bg-[length:12px_12px]">
                                    <div class="absolute inset-0" :style="{ background: overlayCss(slide.id) }"></div>
                                    <span class="absolute left-1 top-1 text-[9px] font-bold text-gray-500">Atas</span>
                                    <span class="absolute bottom-1 left-1 text-[9px] font-bold text-gray-500">Bawah</span>
                                </div>

                                <div class="grid flex-1 gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Titik Atas</p>
                                        <input v-model="forms[slide.id].overlay_start_color" class="h-9 w-full rounded-lg border border-gray-200" type="color">
                                        <label class="flex items-center justify-between text-xs font-semibold text-gray-500">Kepekatan <span>{{ forms[slide.id].overlay_start_opacity }}%</span></label>
                                        <input v-model.number="forms[slide.id].overlay_start_opacity" class="w-full accent-slate-900" type="range" min="0" max="100">
                                        <label class="flex items-center justify-between text-xs font-semibold text-gray-500">Posisi dari atas <span>{{ forms[slide.id].overlay_start_position }}%</span></label>
                                        <input v-model.number="forms[slide.id].overlay_start_position" class="w-full accent-emerald-600" type="range" min="0" max="100">
                                    </div>

                                    <div class="space-y-2">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-500">Titik Bawah</p>
                                        <input v-model="forms[slide.id].overlay_end_color" class="h-9 w-full rounded-lg border border-gray-200" type="color">
                                        <label class="flex items-center justify-between text-xs font-semibold text-gray-500">Kepekatan <span>{{ forms[slide.id].overlay_end_opacity }}%</span></label>
                                        <input v-model.number="forms[slide.id].overlay_end_opacity" class="w-full accent-slate-900" type="range" min="0" max="100">
                                        <label class="flex items-center justify-between text-xs font-semibold text-gray-500">Posisi dari atas <span>{{ forms[slide.id].overlay_end_position }}%</span></label>
                                        <input v-model.number="forms[slide.id].overlay_end_position" class="w-full accent-emerald-600" type="range" min="0" max="100">
                                    </div>
                                </div>
                            </div>
                            <p v-if="forms[slide.id].errors.overlay_end_position" class="field-error">{{ forms[slide.id].errors.overlay_end_position }}</p>
                            <p class="mt-3 text-[11px] text-gray-500">Overlay bermula pada <strong>{{ forms[slide.id].overlay_start_position }}%</strong> dan tamat pada <strong>{{ forms[slide.id].overlay_end_position }}%</strong> dari atas skrin.</p>
                        </div>

                        <div class="md:col-span-2 flex items-center gap-3">
                            <button :disabled="forms[slide.id].processing" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-60">{{ forms[slide.id].processing ? 'Menyimpan...' : 'Simpan Slide' }}</button>
                            <button v-if="slide.media_path" type="button" class="text-xs font-semibold text-red-600" @click="deleteMedia(slide)">Padam media</button>
                        </div>
                    </div>

                    <!-- ── Preview telefon sebenar ── -->
                    <aside>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Preview Skrin Sebenar</p>
                        <div class="relative mx-auto aspect-[9/16] w-full max-w-[260px] overflow-hidden rounded-[28px] border-4 border-gray-900 bg-gray-900 shadow-lg">
                            <!-- Lapisan 1: background gradient -->
                            <div class="absolute inset-0" :style="{ background: backgroundCss(slide.id) }"></div>

                            <!-- Lapisan 2: media fullscreen -->
                            <template v-if="mediaFor(slide)">
                                <video v-if="mediaFor(slide).video" :src="mediaFor(slide).url" class="absolute inset-0 h-full w-full object-cover" autoplay loop muted playsinline />
                                <img v-else :src="mediaFor(slide).url" class="absolute inset-0 h-full w-full object-cover">
                            </template>

                            <!-- Lapisan 3: overlay gradient -->
                            <div class="absolute inset-0" :style="{ background: overlayCss(slide.id) }"></div>

                            <!-- Lapisan 4: kandungan bawah -->
                            <div class="absolute inset-x-0 bottom-0 p-4">
                                <p class="text-[15px] font-extrabold leading-tight" :style="{ color: forms[slide.id].text_color }">{{ forms[slide.id].title || 'Tajuk slide' }}</p>
                                <p class="mt-1.5 text-[10px] leading-relaxed opacity-90" :style="{ color: forms[slide.id].text_color }">{{ forms[slide.id].body || 'Penerangan ringkas slide onboarding.' }}</p>
                                <div class="mt-3 flex gap-1">
                                    <span class="h-1 w-5 rounded-full" :style="{ background: forms[slide.id].text_color }"></span>
                                    <span class="h-1 w-1.5 rounded-full bg-white/40"></span>
                                    <span class="h-1 w-1.5 rounded-full bg-white/40"></span>
                                </div>
                                <div class="mt-3 rounded-xl bg-white py-2 text-center text-[11px] font-bold text-gray-900">{{ forms[slide.id].button_label || 'Seterusnya' }}</div>
                            </div>
                        </div>
                        <p v-if="previews[slide.id]" class="mt-2 truncate text-center text-[11px] text-gray-500">{{ previews[slide.id].name }} · {{ previews[slide.id].size }}</p>
                        <p v-else-if="!slide.media_path" class="mt-2 text-center text-[11px] text-gray-400">Tiada media dimuat naik</p>
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

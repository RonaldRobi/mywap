<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { computed, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    loadingScreen: { type: Object, required: true },
});

const form = useForm({
    loading_screen_gif: null,
    loading_screen_background_start: props.loadingScreen.background_start,
    loading_screen_background_end: props.loadingScreen.background_end,
    loading_screen_duration_ms: props.loadingScreen.duration_ms,
    loading_screen_enabled: props.loadingScreen.enabled,
});

const previewUrl = ref(null);

const gifPath = computed(() =>
    previewUrl.value || props.loadingScreen.gif_path || null,
);

function gradientCss() {
    return `linear-gradient(135deg, ${form.loading_screen_background_start}, ${form.loading_screen_background_end})`;
}

function selectGif(event) {
    const file = event.target.files?.[0] ?? null;
    form.loading_screen_gif = file;

    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }

    if (file) {
        previewUrl.value = URL.createObjectURL(file);
    }
}

function save() {
    form.post(route('superadmin.app-settings.loading-screen.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.loading_screen_gif = null;
            if (previewUrl.value) {
                URL.revokeObjectURL(previewUrl.value);
                previewUrl.value = null;
            }
        },
    });
}

function removeGif() {
    if (! confirm('Buang GIF loading screen?')) {
        return;
    }
    form.delete(route('superadmin.app-settings.loading-screen.remove'), {
        preserveScroll: true,
    });
}

onBeforeUnmount(() => {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
    }
});
</script>

<template>
    <Head title="Tetapan Aplikasi" />
    <AppLayout>
        <template #header>Tetapan Aplikasi</template>
        <main class="mx-auto max-w-6xl space-y-6 px-4 py-6 md:px-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ $page.props.flash.success }}</div>
            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $page.props.flash.error }}</div>

            <section class="rounded-3xl border border-indigo-100 bg-indigo-50 p-5 text-sm text-indigo-950">
                <h1 class="font-black">Tetapan Aplikasi Mudah Alih</h1>
                <p class="mt-1 text-indigo-800">Konfigurasi ini digunakan oleh aplikasi <strong>iOS dan Android (Flutter) sahaja</strong>. Aplikasi web tidak terjejas.</p>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-600">Flutter sahaja</p>
                    <h2 class="text-lg font-black text-gray-900">Loading Screen (GIF)</h2>
                    <p class="mt-1 text-xs text-gray-500">Dipaparkan setiap kali pengguna membuka aplikasi. GIF disyorkan <strong>1080 × 1080px</strong>, latar transparen, format GIF. Maksimum 10MB.</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <form class="space-y-4" @submit.prevent="save">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">GIF Loading Screen</span>
                            <input
                                type="file"
                                accept="image/gif"
                                @change="selectGif"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <p v-if="form.errors.loading_screen_gif" class="mt-1 text-xs text-red-500">{{ form.errors.loading_screen_gif }}</p>
                            <p class="mt-1 text-[11px] text-gray-400">GIF akan dipaparkan di tengah-tengah skrin. Latar belakang diisi dengan gradient di bawah.</p>
                        </label>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Gradient Mula</span>
                                <input v-model="form.loading_screen_background_start" type="color" class="h-10 w-full rounded-xl border border-gray-200 p-1">
                                <p v-if="form.errors.loading_screen_background_start" class="mt-1 text-xs text-red-500">{{ form.errors.loading_screen_background_start }}</p>
                            </label>

                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Gradient Tamat</span>
                                <input v-model="form.loading_screen_background_end" type="color" class="h-10 w-full rounded-xl border border-gray-200 p-1">
                                <p v-if="form.errors.loading_screen_background_end" class="mt-1 text-xs text-red-500">{{ form.errors.loading_screen_background_end }}</p>
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 flex items-center justify-between text-xs font-semibold text-gray-500">
                                Tempoh Minimum <span>{{ form.loading_screen_duration_ms }} ms</span>
                            </span>
                            <input v-model.number="form.loading_screen_duration_ms" type="range" min="500" max="8000" step="100" class="w-full accent-emerald-600">
                            <p class="mt-1 text-[11px] text-gray-400">Masa minimum GIF dipaparkan sebelum skrin seterusnya dibuka.</p>
                            <p v-if="form.errors.loading_screen_duration_ms" class="mt-1 text-xs text-red-500">{{ form.errors.loading_screen_duration_ms }}</p>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.loading_screen_enabled" type="checkbox" class="rounded border-gray-300 text-emerald-600">
                            Aktifkan loading screen
                        </label>

                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-gray-800 disabled:opacity-60"
                            >
                                {{ form.processing ? 'Menyimpan...' : 'Simpan Tetapan' }}
                            </button>
                            <button
                                v-if="props.loadingScreen.gif_path"
                                type="button"
                                :disabled="form.processing"
                                @click="removeGif"
                                class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-60"
                            >
                                Buang GIF
                            </button>
                        </div>
                    </form>

                    <aside>
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Preview Skrin Sebenar</p>
                        <div
                            class="relative mx-auto aspect-[9/16] w-full max-w-[240px] overflow-hidden rounded-[28px] border-4 border-gray-900 bg-gray-900 shadow-lg"
                            :style="{ background: gradientCss() }"
                        >
                            <template v-if="gifPath">
                                <img :src="gifPath" alt="GIF loading screen" class="absolute inset-0 m-auto h-2/3 w-2/3 object-contain">
                            </template>
                            <span v-else class="absolute inset-0 flex items-center justify-center px-6 text-center text-xs font-semibold text-white/70">Tiada GIF dimuat naik</span>
                        </div>
                        <p v-if="!form.loading_screen_enabled" class="mt-2 text-center text-[11px] text-gray-400">Loading screen dimatikan — skrin lalai akan dipaparkan.</p>
                    </aside>
                </div>
            </section>

            <Link :href="route('admin.dashboard')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Dashboard</Link>
        </main>
    </AppLayout>
</template>

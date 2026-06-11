<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    systemLogoPath: {
        type: String,
        default: null,
    },
    canManageSystemLogo: {
        type: Boolean,
        default: false,
    },
    splashImagePath: {
        type: String,
        default: null,
    },
    splashBackgroundColor: {
        type: String,
        default: '#0f172a',
    },
    splashTitle: {
        type: String,
        default: 'myWAP',
    },
    splashDurationMs: {
        type: Number,
        default: 1800,
    },
    splashEnabled: {
        type: Boolean,
        default: true,
    },
});

const form = useForm({
    system_logo: null,
});

const splashForm = useForm({
    splash_image: null,
    splash_background_color: props.splashBackgroundColor || '#0f172a',
    splash_title: props.splashTitle || 'myWAP',
    splash_duration_ms: props.splashDurationMs || 1800,
    splash_enabled: props.splashEnabled,
});
const splashPreviewUrl = ref(null);

function uploadSystemLogo() {
    form.post(route('superadmin.settings.system-logo.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('system_logo'),
    });
}

function saveSplashSettings() {
    splashForm.post(route('superadmin.settings.splash.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => splashForm.reset('splash_image'),
    });
}

function onSplashImageSelected(event) {
    const file = event.target.files?.[0] ?? null;
    splashForm.splash_image = file;

    if (splashPreviewUrl.value) {
        URL.revokeObjectURL(splashPreviewUrl.value);
        splashPreviewUrl.value = null;
    }

    if (file) {
        splashPreviewUrl.value = URL.createObjectURL(file);
    }
}

onBeforeUnmount(() => {
    if (splashPreviewUrl.value) {
        URL.revokeObjectURL(splashPreviewUrl.value);
    }
});
</script>

<template>
    <AppLayout>
        <Head title="myWAP Settings" />

        <div class="mx-auto max-w-4xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">myWAP Settings</h1>
                <p class="mt-1 text-sm text-gray-500">Tetapan peringkat sistem seperti logo rasmi myWAP.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="!canManageSystemLogo" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Tetapan ini memerlukan migration baru. Jalankan <strong>php artisan migrate</strong> dahulu.
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Logo Sistem</h2>
                <p class="mt-1 text-xs text-gray-500">Cadangan saiz: <strong>512 × 512px</strong>, format PNG/SVG dengan latar transparen.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="systemLogoPath" :src="systemLogoPath" alt="System Logo" class="h-20 w-20 object-contain">
                        <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadSystemLogo">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="form.system_logo = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="form.errors.system_logo" class="text-xs text-red-500">{{ form.errors.system_logo }}</p>
                        <button
                            type="submit"
                            :disabled="form.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Memuat naik...' : 'Simpan Logo myWAP' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Splash Screen Web</h2>
                <p class="mt-1 text-xs text-gray-500">Dipaparkan sekali setiap sesi browser semasa aplikasi mula dibuka. Format imej: JPG, PNG, WEBP, SVG, GIF.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Preview</p>
                        <div
                            class="mt-3 flex h-48 items-center justify-center rounded-2xl"
                            :style="{ backgroundColor: splashForm.splash_background_color || '#0f172a' }"
                        >
                            <div class="text-center">
                                <img
                                    v-if="splashForm.splash_image || splashImagePath || systemLogoPath"
                                    :src="splashPreviewUrl || splashImagePath || systemLogoPath"
                                    class="mx-auto h-16 w-16 object-contain"
                                    alt="Splash preview"
                                >
                                <p class="mt-3 text-sm font-bold text-white">{{ splashForm.splash_title || 'myWAP' }}</p>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="saveSplashSettings">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Imej Splash (optional)</span>
                            <input
                                type="file"
                                accept="image/*"
                                :disabled="!canManageSystemLogo"
                                @change="onSplashImageSelected"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <p v-if="splashForm.errors.splash_image" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_image }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Warna Latar</span>
                            <input v-model="splashForm.splash_background_color" type="color" class="h-10 w-full rounded-xl border border-gray-200 p-1">
                            <p v-if="splashForm.errors.splash_background_color" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_background_color }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</span>
                            <input v-model="splashForm.splash_title" type="text" maxlength="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <p v-if="splashForm.errors.splash_title" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_title }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Tempoh (ms)</span>
                            <input v-model.number="splashForm.splash_duration_ms" type="number" min="300" max="6000" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <p v-if="splashForm.errors.splash_duration_ms" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_duration_ms }}</p>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="splashForm.splash_enabled" type="checkbox" class="rounded border-gray-300">
                            Aktifkan splash screen
                        </label>

                        <button
                            type="submit"
                            :disabled="splashForm.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ splashForm.processing ? 'Menyimpan...' : 'Simpan Tetapan Splash' }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const visible = ref(false);

const brand = computed(() => page.props.brand ?? {});
const enabled = computed(() => Boolean(brand.value.splash_enabled ?? true));
const backgroundColor = computed(() => brand.value.splash_background_color || '#0f172a');
const splashTitle = computed(() => brand.value.splash_title || 'myWAP');
const splashImage = computed(() => brand.value.splash_image_path || brand.value.system_logo_path || null);
const durationMs = computed(() => {
    const parsed = Number(brand.value.splash_duration_ms ?? 1800);
    return Math.min(6000, Math.max(300, Number.isNaN(parsed) ? 1800 : parsed));
});

onMounted(() => {
    if (!enabled.value) {
        return;
    }

    const hasSeen = sessionStorage.getItem('mywap_splash_seen') === '1';
    if (hasSeen) {
        return;
    }

    visible.value = true;
    sessionStorage.setItem('mywap_splash_seen', '1');

    window.setTimeout(() => {
        visible.value = false;
    }, durationMs.value);
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-400 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-0 z-[9999] flex items-center justify-center px-6"
            :style="{ backgroundColor }"
        >
            <div class="flex flex-col items-center text-center">
                <div class="flex h-28 w-28 items-center justify-center rounded-3xl bg-white/10 p-4 shadow-2xl ring-1 ring-white/20 backdrop-blur-sm">
                    <img
                        v-if="splashImage"
                        :src="splashImage"
                        :alt="splashTitle"
                        class="h-full w-full object-contain"
                    >
                    <span v-else class="text-2xl font-black text-white">{{ splashTitle }}</span>
                </div>
                <p class="mt-4 text-lg font-bold tracking-wide text-white">{{ splashTitle }}</p>
                <div class="mt-4 h-1.5 w-28 overflow-hidden rounded-full bg-white/20">
                    <div class="h-full w-1/2 animate-pulse rounded-full bg-white"></div>
                </div>
            </div>
        </div>
    </Transition>
</template>

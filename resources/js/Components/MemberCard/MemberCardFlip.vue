<script setup>
import { onMounted, ref } from 'vue';
import MemberCardFront from './MemberCardFront.vue';

const props = defineProps({
    card: { type: Object, required: true },
    qrSvg: { type: String, default: null },
});

const emit = defineEmits(['qr-click']);

const flipped = ref(false);
const fontsVersion = ref(0);

onMounted(() => {
    // Ukur semula teks bila Figtree 700/800 siap dimuat.
    document.fonts?.ready?.then(() => { fontsVersion.value += 1; });
});

function toggle() {
    flipped.value = !flipped.value;
}

defineExpose({ flipped, toggle });
</script>

<template>
    <div class="mx-auto w-full" style="perspective: 1600px;">
        <button
            type="button"
            class="relative block w-full cursor-pointer focus:outline-none"
            style="aspect-ratio: 638 / 1004;"
            :aria-label="flipped ? 'Lihat depan kad' : 'Lihat belakang kad'"
            @click="toggle"
        >
            <div class="flip-inner absolute inset-0" :class="{ 'is-flipped': flipped }">
                <!-- Depan -->
                <div id="membership-card-front" class="flip-face absolute inset-0 overflow-hidden rounded-[4.6%/2.9%] shadow-[0_18px_40px_-12px_rgba(0,60,40,0.45)]">
                    <MemberCardFront :card="card" :qr-svg="qrSvg" :fonts-version="fontsVersion" @qr-click="emit('qr-click')" />
                </div>

                <!-- Belakang (static) -->
                <div id="membership-card-back" class="flip-face flip-back absolute inset-0 overflow-hidden rounded-[4.6%/2.9%] bg-gradient-to-b from-[#e6f2ea] to-[#0f5a3e] shadow-[0_18px_40px_-12px_rgba(0,60,40,0.45)]">
                    <img
                        v-if="card.card_back_path"
                        :src="card.card_back_path"
                        alt="Belakang kad keahlian"
                        class="h-full w-full object-cover"
                        draggable="false"
                    >
                    <div v-else class="flex h-full items-center justify-center p-6 text-center text-sm font-semibold text-white/80">
                        Design belakang kad belum dimuat naik.
                    </div>
                </div>
            </div>
        </button>
    </div>
</template>

<style scoped>
.flip-inner {
    transform-style: preserve-3d;
    transition: transform 0.7s cubic-bezier(0.4, 0.2, 0.2, 1);
}
.flip-inner.is-flipped {
    transform: rotateY(180deg);
}
.flip-face {
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}
.flip-back {
    transform: rotateY(180deg);
}
@media (prefers-reduced-motion: reduce) {
    .flip-inner { transition: none; }
}
</style>

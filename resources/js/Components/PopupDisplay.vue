<script setup>
import { ref } from 'vue';
import Modal from '@/Components/Modal.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    popupData: {
        type: Object,
        default: null,
    },
});

const show = ref(false);

if (props.popupData) {
    let isDismissed = false;
    try {
        isDismissed = !!localStorage.getItem('popup_dismissed_' + props.popupData.id);
    } catch {}
    if (!isDismissed) {
        setTimeout(() => {
            show.value = true;
        }, 800);
    }
}

function dismiss() {
    if (props.popupData) {
        try {
            localStorage.setItem('popup_dismissed_' + props.popupData.id, '1');
        } catch {}
    }
    show.value = false;
}

function buttonClick(url) {
    dismiss();
    if (url) {
        window.location.href = url;
    }
}
</script>

<template>
    <Modal :show="show" :max-width="popupData?.popup_size === 'sm' ? 'md' : popupData?.popup_size === 'lg' ? '2xl' : 'lg'" :closeable="true" @close="dismiss">
        <div v-if="popupData" class="relative">
            <button @click="dismiss" class="absolute top-3 right-3 z-10 w-8 h-8 rounded-full bg-black/20 backdrop-blur-md flex items-center justify-center text-white hover:bg-black/40 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

            <img v-if="popupData.image_path" :src="popupData.image_path" :alt="popupData.title" class="w-full aspect-[2/1] object-cover rounded-t-lg">

            <div :class="['px-6 pb-6', popupData.image_path ? 'pt-4' : 'pt-6']">
                <h3 class="text-lg font-bold text-gray-900">{{ popupData.title }}</h3>
                <p v-if="popupData.content" class="mt-2 text-sm text-gray-600 leading-relaxed" v-html="popupData.content.replace(/\n/g, '<br>')"></p>

                <div v-if="popupData.button_text || popupData.button_text_2" class="mt-5 flex flex-wrap gap-3">
                    <a v-if="popupData.button_text" :href="popupData.button_url || '#'" @click.prevent="buttonClick(popupData.button_url)" class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 transition-colors">
                        {{ popupData.button_text }}
                    </a>
                    <a v-if="popupData.button_text_2" :href="popupData.button_url_2 || '#'" @click.prevent="buttonClick(popupData.button_url_2)" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                        {{ popupData.button_text_2 }}
                    </a>
                </div>
            </div>
        </div>
    </Modal>
</template>

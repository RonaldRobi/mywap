<template>
    <Teleport to="body">
        <div v-if="visible" class="fixed inset-0 z-[90] flex items-center justify-center bg-black/40" @click.self="onCancel">
            <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
                <h3 v-if="title" class="text-lg font-bold text-gray-900">{{ title }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
                <div class="mt-5 flex justify-end gap-3">
                    <button @click="onCancel" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">{{ cancelText }}</button>
                    <button @click="onConfirm" :class="confirmClass" class="rounded-xl px-4 py-2.5 text-sm font-semibold text-white">{{ confirmText }}</button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    message: { type: String, required: true },
    confirmText: { type: String, default: 'Sahkan' },
    cancelText: { type: String, default: 'Batal' },
    variant: { type: String, default: 'danger' },
});

const emit = defineEmits(['confirm', 'cancel']);

const visible = ref(false);

function show() { visible.value = true; }
function hide() { visible.value = false; }

function onConfirm() {
    emit('confirm');
    hide();
}

function onCancel() {
    emit('cancel');
    hide();
}

const confirmClass = {
    danger: 'bg-red-600 hover:bg-red-700',
    primary: 'bg-indigo-700 hover:bg-indigo-600',
    default: 'bg-gray-900 hover:bg-gray-800',
}[props.variant] || 'bg-red-600 hover:bg-red-700';

defineExpose({ show, hide });
</script>

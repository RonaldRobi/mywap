<template>
    <Teleport to="body">
        <div class="fixed top-4 right-4 z-[100] flex flex-col gap-2 pointer-events-none">
            <transition-group name="toast">
                <div v-for="t in toasts" :key="t.id"
                    class="pointer-events-auto rounded-xl px-4 py-3 text-sm font-semibold shadow-lg flex items-center gap-2 min-w-[280px] max-w-sm"
                    :class="{
                        'bg-green-50 text-green-800 border border-green-200': t.type === 'success',
                        'bg-red-50 text-red-800 border border-red-200': t.type === 'error',
                        'bg-indigo-50 text-indigo-800 border border-indigo-200': t.type === 'info',
                    }">
                    <span v-if="t.type === 'success'">&#10003;</span>
                    <span v-else-if="t.type === 'error'">&#10007;</span>
                    <span v-else>&#9432;</span>
                    <span class="flex-1">{{ t.message }}</span>
                </div>
            </transition-group>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, onUnmounted } from 'vue';

const toasts = ref([]);
let nextId = 0;

function show(message, type = 'info', duration = 3000) {
    const id = ++nextId;
    toasts.value.push({ id, message, type });
    if (duration > 0) {
        setTimeout(() => dismiss(id), duration);
    }
    return id;
}

function success(message) { return show(message, 'success', 3000); }
function error(message) { return show(message, 'error', 6000); }
function info(message) { return show(message, 'info', 3000); }

function dismiss(id) {
    toasts.value = toasts.value.filter(t => t.id !== id);
}

defineExpose({ show, success, error, info, dismiss });

onUnmounted(() => { toasts.value = []; });
</script>

<style scoped>
.toast-enter-active { transition: all 0.25s ease-out; }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from { opacity: 0; transform: translateX(30px); }
.toast-leave-to { opacity: 0; transform: translateX(30px); }
</style>

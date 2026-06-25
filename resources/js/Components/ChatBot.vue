<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';

const page = usePage();
const chatbotLogo = computed(() => page.props.brand?.chatbot_logo_path ?? null);
const adminEmail = computed(() => page.props.brand?.admin_contact_email ?? '');
const adminPhone = computed(() => page.props.brand?.admin_contact_phone ?? '');

const open = ref(false);
const messages = ref([]);
const input = ref('');
const loading = ref(false);
const hasInteracted = ref(false);

const STORAGE_KEY = 'mywap_chat_history';

const suggestions = [
    'Apa itu myWAP?',
    'Macam mana nak jadi ahli?',
    'Yuran ahli berapa?',
    'Cara daftar program?',
];

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
        try {
            const parsed = JSON.parse(saved);
            if (Array.isArray(parsed) && parsed.length) {
                messages.value = parsed;
                return;
            }
        } catch {}
    }
    messages.value.push({
        role: 'bot',
        text: '👋 Hai! Ada apa-apa yang saya boleh bantu?',
    });
});

watch(messages, (val) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(val));
}, { deep: true });

watch(open, (val) => {
    if (val) {
        hasInteracted.value = true;
        nextTick(() => scrollToBottom());
    }
});

function scrollToBottom() {
    nextTick(() => {
        const container = document.getElementById('chat-messages');
        if (container) container.scrollTop = container.scrollHeight;
    });
}

async function send() {
    const text = input.value.trim();
    if (!text || loading.value) return;

    messages.value.push({ role: 'user', text });
    input.value = '';
    loading.value = true;
    scrollToBottom();

    try {
        const { data } = await axios.post('/api/chat', { message: text });
        messages.value.push({ role: 'bot', text: data.reply });
    } catch {
        messages.value.push({
            role: 'bot',
            text: 'Maaf, saya tidak dapat memproses soalan anda sekarang. Sila cuba sebentar lagi.',
        });
    } finally {
        loading.value = false;
        scrollToBottom();
    }
}

function pickSuggestion(text) {
    input.value = text;
    send();
}

function toggle() {
    open.value = !open.value;
}

function clearChat() {
    messages.value = [];
    localStorage.removeItem(STORAGE_KEY);
    messages.value.push({
        role: 'bot',
        text: '👋 Hai! Ada apa-apa yang saya boleh bantu?',
    });
}
</script>

<template>
    <div class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-3">
        <!-- Chat Panel -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-4"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-4"
        >
            <div
                v-if="open"
                class="flex w-[360px] max-w-[calc(100vw-48px)] flex-col rounded-2xl border border-gray-100 bg-white shadow-2xl"
                style="height: 520px; max-height: calc(100vh - 120px);"
            >
                <!-- Header -->
                <div class="flex items-center justify-between rounded-t-2xl bg-gradient-to-r from-indigo-500 to-purple-600 px-4 py-3 text-white">
                    <div class="flex items-center gap-2.5">
                        <img
                            v-if="chatbotLogo"
                            :src="chatbotLogo"
                            class="h-7 w-7 rounded-full object-contain"
                            alt="AI"
                        >
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 00-2.455 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                        </svg>
                        <span class="text-sm font-bold">MyWAP AI</span>
                    </div>
                    <button @click="toggle" class="rounded-lg p-1 hover:bg-white/10" title="Tutup">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Messages -->
                <div id="chat-messages" class="flex-1 overflow-y-auto px-4 py-3 space-y-3 scroll-smooth">
                    <div v-for="(msg, i) in messages" :key="i" :class="msg.role === 'user' ? 'flex justify-end' : 'flex justify-start'">
                        <div
                            :class="msg.role === 'user'
                                ? 'max-w-[80%] rounded-2xl bg-gray-900 px-4 py-2.5 text-sm text-white'
                                : 'max-w-[85%] rounded-2xl bg-gray-100 px-4 py-2.5 text-sm text-gray-800'"
                        >
                            <div class="whitespace-pre-wrap leading-relaxed">{{ msg.text }}</div>
                        </div>
                    </div>

                    <!-- Loading -->
                    <div v-if="loading" class="flex justify-start">
                        <div class="rounded-2xl bg-gray-100 px-4 py-3 text-sm text-gray-500">
                            <span class="inline-flex gap-1">
                                <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                                <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                                <span class="h-2 w-2 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                            </span>
                        </div>
                    </div>

                    <!-- Suggestions (only when no user messages yet) -->
                    <div v-if="!messages.some(m => m.role === 'user')" class="pt-2 space-y-2">
                        <p class="text-xs font-semibold text-gray-500">📌 Cadangan Soalan:</p>
                        <button
                            v-for="(s, i) in suggestions"
                            :key="i"
                            @click="pickSuggestion(s)"
                            class="block w-full rounded-xl border border-gray-200 px-3 py-2 text-left text-xs text-gray-600 transition-colors hover:border-gray-300 hover:bg-gray-50"
                        >
                            {{ s }}
                        </button>
                    </div>
                </div>

                <!-- Footer with input -->
                <div class="border-t border-gray-100 px-3 py-3">
                    <div class="flex items-center gap-2">
                        <input
                            v-model="input"
                            @keydown.enter="send"
                            type="text"
                            placeholder="Taip soalan..."
                            :disabled="loading"
                            class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm outline-none transition-colors focus:border-gray-400 focus:ring-1 focus:ring-gray-300 disabled:opacity-50"
                        >
                        <button
                            @click="send"
                            :disabled="loading || !input.trim()"
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-gray-900 text-white transition-colors hover:bg-gray-800 disabled:opacity-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                            </svg>
                        </button>
                        <button
                            @click="clearChat"
                            title="Padam sejarah chat"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </transition>

        <!-- Floating Button -->
        <button
            @click="toggle"
            class="flex h-14 w-14 items-center justify-center rounded-full shadow-lg transition-all duration-200 hover:scale-105 hover:shadow-xl active:scale-95"
            :class="open
                ? 'bg-gradient-to-br from-indigo-600 to-purple-700'
                : 'bg-gradient-to-br from-indigo-500 to-purple-600'"
            :title="open ? 'Tutup chatbot' : 'Buka chatbot MyWAP AI'"
        >
            <img
                v-if="chatbotLogo && !open"
                :src="chatbotLogo"
                class="h-9 w-9 rounded-full object-contain ring-2 ring-white/20"
                alt="MyWAP AI"
            >
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path v-if="open" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
        </button>
    </div>
</template>

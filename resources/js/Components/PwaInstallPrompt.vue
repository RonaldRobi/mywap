<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const deferredPrompt = ref(null);
const showBanner = ref(false);
const isIos = ref(false);
const showIosTooltip = ref(false);

function handleBeforeInstallPrompt(e) {
    e.preventDefault();
    deferredPrompt.value = e;
    
    // Don't show if already dismissed recently
    const dismissed = localStorage.getItem('pwa-install-dismissed');
    if (dismissed && Date.now() - parseInt(dismissed) < 7 * 24 * 60 * 60 * 1000) return; // 7 days
    
    showBanner.value = true;
}

async function installApp() {
    if (!deferredPrompt.value) return;
    deferredPrompt.value.prompt();
    const { outcome } = await deferredPrompt.value.userChoice;
    if (outcome === 'accepted') {
        showBanner.value = false;
    }
    deferredPrompt.value = null;
}

function dismiss() {
    showBanner.value = false;
    showIosTooltip.value = false;
    localStorage.setItem('pwa-install-dismissed', Date.now().toString());
}

onMounted(() => {
    // Check if already installed as PWA
    if (window.matchMedia('(display-mode: standalone)').matches) return;
    if (window.navigator.standalone === true) return;

    // Detect iOS Safari
    const ua = window.navigator.userAgent;
    const isiOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    
    if (isiOS) {
        isIos.value = true;
        const dismissed = localStorage.getItem('pwa-install-dismissed');
        if (!dismissed || Date.now() - parseInt(dismissed) >= 7 * 24 * 60 * 60 * 1000) {
            showBanner.value = true;
        }
    }

    // For Android / Chrome
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});

onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
});
</script>

<template>
    <!-- Subtle bottom floating banner -->
    <transition
        enter-active-class="transition duration-500 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-300 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div 
            v-if="showBanner"
            class="fixed bottom-4 left-4 right-4 z-[9999] mx-auto max-w-sm"
        >
            <div class="relative flex items-center gap-3 rounded-2xl bg-gray-900/95 backdrop-blur-xl px-4 py-3 shadow-2xl shadow-black/30 ring-1 ring-white/10">
                <!-- App Icon -->
                <div class="shrink-0">
                    <img 
                        src="/pwa-icon-192.png" 
                        alt="MyMarhalah" 
                        class="h-10 w-10 rounded-xl shadow-md ring-1 ring-white/10"
                    >
                </div>
                
                <!-- Text -->
                <div class="flex-1 min-w-0">
                    <p class="text-[13px] font-bold text-white leading-tight">Pasang MyMarhalah</p>
                    <p class="text-[11px] text-gray-400 leading-snug mt-0.5">Akses pantas dari Skrin Utama</p>
                </div>
                
                <!-- Action buttons -->
                <div class="flex items-center gap-2 shrink-0">
                    <!-- iOS: Show instructions tooltip -->
                    <button 
                        v-if="isIos"
                        @click="showIosTooltip = !showIosTooltip"
                        class="rounded-xl bg-white px-3.5 py-2 text-xs font-bold text-gray-900 shadow-sm hover:bg-gray-100 transition-all active:scale-95"
                    >
                        Pasang
                    </button>
                    
                    <!-- Android/Chrome: Direct install -->
                    <button 
                        v-else
                        @click="installApp"
                        class="rounded-xl bg-white px-3.5 py-2 text-xs font-bold text-gray-900 shadow-sm hover:bg-gray-100 transition-all active:scale-95"
                    >
                        Pasang
                    </button>
                    
                    <!-- Dismiss X -->
                    <button 
                        @click="dismiss" 
                        class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-500 hover:text-white hover:bg-white/10 transition-colors"
                        aria-label="Tutup"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- iOS Instructions Tooltip -->
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0 translate-y-2 scale-95"
                enter-to-class="opacity-100 translate-y-0 scale-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100 translate-y-0 scale-100"
                leave-to-class="opacity-0 translate-y-2 scale-95"
            >
                <div 
                    v-if="showIosTooltip" 
                    class="absolute bottom-full left-0 right-0 mb-3 rounded-2xl bg-white p-4 shadow-2xl ring-1 ring-gray-900/5"
                >
                    <p class="text-xs font-bold text-gray-900 mb-2">Cara pasang di iPhone / iPad:</p>
                    <ol class="space-y-1.5 text-xs text-gray-600 pl-1">
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gray-900 text-[10px] font-bold text-white">1</span>
                            <span>Tekan ikon <strong>Kongsi</strong> (Share) 
                                <svg class="inline h-3.5 w-3.5 -mt-0.5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path d="M15 8a1 1 0 01-1 1h-1.586l-2-2H14a1 1 0 011 1zm-8.707.293l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 6.414V14a1 1 0 11-2 0V6.414L5.707 9.707a1 1 0 01-1.414-1.414z"/></svg>
                                di bawah
                            </span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gray-900 text-[10px] font-bold text-white">2</span>
                            <span>Pilih <strong>"Add to Home Screen"</strong></span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-gray-900 text-[10px] font-bold text-white">3</span>
                            <span>Tekan <strong>"Add"</strong> — Siap!</span>
                        </li>
                    </ol>
                    <button @click="showIosTooltip = false" class="mt-3 w-full rounded-xl bg-gray-900 py-2 text-xs font-bold text-white hover:bg-gray-800 transition-colors">Faham, Terima Kasih</button>
                </div>
            </transition>
        </div>
    </transition>
</template>

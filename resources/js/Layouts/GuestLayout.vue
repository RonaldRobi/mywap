<script setup>
import AppSplashScreen from '@/Components/AppSplashScreen.vue';
import ChatBot from '@/Components/ChatBot.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-[#F4F6F1] px-4 py-8 sm:justify-center sm:py-12"
    >
        <div class="mb-6 text-center">
            <Link href="/" class="inline-block text-xl font-black tracking-tight text-[#123D2A]">
                myWAP
            </Link>
            <p class="mt-1 text-xs font-medium text-[#52645A]">Platform digital ahli</p>
        </div>

        <div
            class="w-full max-w-md overflow-hidden rounded-2xl border border-[#D5E3D8] bg-white px-5 py-6 shadow-[0_16px_40px_rgba(7,21,37,0.08)] sm:px-8 sm:py-8"
        >
            <slot />
        </div>
        <AppSplashScreen />
        <ChatBot />
    </div>
</template>

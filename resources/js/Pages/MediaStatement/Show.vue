<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ statement: { type: Object, required: true } });
</script>
<template><Head :title="statement.title" /><AppLayout><template #header>{{ statement.title }}</template><main class="mx-auto max-w-3xl px-4 py-8"><img v-if="statement.featured_image" :src="statement.featured_image" alt="" class="mb-6 max-h-[420px] w-full rounded-2xl object-cover" /><p class="text-sm text-gray-500">{{ statement.published_date }}</p><h1 class="mt-2 text-3xl font-black">{{ statement.title }}</h1><div class="prose mt-6 max-w-none" v-html="statement.content"></div><a v-if="statement.pdf_url" :href="statement.pdf_url" target="_blank" class="mt-8 inline-block rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white">Buka PDF</a></main></AppLayout></template>

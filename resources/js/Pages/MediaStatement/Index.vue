<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ statements: { type: Array, default: () => [] } });
</script>
<template><Head title="Kenyataan Media" /><AppLayout><template #header>Kenyataan Media</template><main class="mx-auto max-w-5xl px-4 py-8"><div class="grid gap-4 md:grid-cols-2"><Link v-for="statement in statements" :key="statement.id" :href="route('media-statements.show', statement.id)" class="rounded-2xl border bg-white p-5 shadow-sm"><img v-if="statement.featured_image" :src="statement.featured_image" alt="" class="mb-4 h-44 w-full rounded-xl object-cover" /><p class="text-xs text-gray-500">{{ statement.published_date }}</p><h2 class="mt-1 text-xl font-bold">{{ statement.title }}</h2></Link></div><p v-if="!statements.length" class="py-12 text-center text-sm text-gray-500">Belum ada kenyataan media.</p></main></AppLayout></template>

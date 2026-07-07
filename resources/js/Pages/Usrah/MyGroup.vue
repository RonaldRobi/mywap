<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <Head title="My Usrah" />

    <AppLayout>
        <template #header>Kumpulan Usrah Saya</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <div class="flex flex-col items-center justify-center py-20 text-center">
                <div class="text-6xl">🚧</div>
                <h2 class="mt-4 text-2xl font-black text-gray-800">Coming Soon</h2>
                <p class="mt-2 text-gray-500">Halaman ini sedang dalam pembangunan.</p>
            </div>
        </div>
    </AppLayout>
</template>

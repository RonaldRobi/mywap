<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnnouncementsBoard from '@/Components/AnnouncementsBoard.vue';

defineProps({
    announcements: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Pengumuman" />

    <AppLayout>
        <template #header>Pengumuman</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <section class="rounded-3xl border border-gray-100 bg-white/90 backdrop-blur-sm p-5 shadow-sm">
                <AnnouncementsBoard :announcements="announcements" />
            </section>
        </div>
    </AppLayout>
</template>

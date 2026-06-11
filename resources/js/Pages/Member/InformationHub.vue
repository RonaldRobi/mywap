<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AnnouncementsBoard from '@/Components/AnnouncementsBoard.vue';
import LibraryGrid from '@/Components/LibraryGrid.vue';

defineProps({
    announcements: {
        type: Array,
        default: () => [],
    },
    libraryItems: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Information Hub" />

    <AppLayout>
        <template #header>Information Hub</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <section class="rounded-3xl border border-gray-100 bg-white/90 backdrop-blur-sm p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-black text-gray-800">Pengumuman</h2>
                </div>
                <AnnouncementsBoard :announcements="announcements" />
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 backdrop-blur-sm p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-black text-gray-800">Pustaka PDF Digital</h2>
                </div>
                <LibraryGrid :items="libraryItems" />
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    videos: { type: Object, default: () => ({}) },
});

const openVideo = ref(null);

function play(video) {
    openVideo.value = video;
}

function closePlayer() {
    openVideo.value = null;
}
</script>

<template>
    <Head title="Video" />

    <AppLayout>
        <template #header>Video</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="videos.data?.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <button
                    v-for="item in videos.data"
                    :key="item.id"
                    @click="play(item)"
                    class="group rounded-2xl border border-gray-100 bg-white p-2 shadow-sm text-left hover:shadow-md transition-all hover:-translate-y-0.5"
                >
                    <div class="relative aspect-video w-full overflow-hidden rounded-xl bg-gray-100">
                        <img :src="item.thumbnail_url" :alt="item.title" class="h-full w-full object-cover">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 opacity-0 group-hover:opacity-100 transition-opacity">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-white/90 shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="ml-0.5 h-5 w-5 text-gray-900" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <p class="mt-2 px-1 text-sm font-semibold text-gray-800 line-clamp-2">{{ item.title }}</p>
                </button>
            </div>

            <div v-else class="rounded-2xl border-2 border-dashed border-gray-200 px-4 py-16 text-center">
                <div class="mx-auto w-14 h-14 rounded-2xl bg-gray-100 flex items-center justify-center mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-7 h-7 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-700">Tiada video buat masa ini.</p>
            </div>

            <!-- Pagination -->
            <div v-if="videos.links?.length > 3" class="flex items-center justify-center gap-2">
                <Link
                    v-for="(link, i) in videos.links"
                    :key="i"
                    :href="link.url || '#'"
                    :class="[
                        'rounded-xl px-3 py-1.5 text-xs font-semibold transition',
                        link.active
                            ? 'bg-gray-900 text-white'
                            : link.url
                                ? 'border border-gray-200 text-gray-600 hover:bg-gray-50'
                                : 'text-gray-300 cursor-default',
                    ]"
                    v-html="link.label"
                />
            </div>
        </div>
    </AppLayout>

    <!-- Video Player Modal -->
    <Teleport to="body">
        <transition
            enter-active-class="transition-opacity duration-200"
            leave-active-class="transition-opacity duration-150"
        >
            <div v-if="openVideo" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4" @click.self="closePlayer">
                <div class="w-full max-w-4xl">
                    <div class="relative aspect-video w-full overflow-hidden rounded-2xl bg-black shadow-2xl">
                        <iframe
                            :src="openVideo.embed_url + '?autoplay=1'"
                            class="absolute inset-0 h-full w-full"
                            frameborder="0"
                            allow="autoplay; encrypted-media"
                            allowfullscreen
                        />
                    </div>
                    <p class="mt-3 text-lg font-bold text-white">{{ openVideo.title }}</p>
                    <button @click="closePlayer" class="mt-3 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-white hover:bg-white/20 transition">
                        Tutup
                    </button>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

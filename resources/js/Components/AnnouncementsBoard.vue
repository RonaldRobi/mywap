<script setup>
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    announcements: {
        type: Array,
        default: () => [],
    },
});

const readTriggered = ref({});
const lightboxOpen = ref(false);
const lightboxImages = ref([]);
const lightboxIndex = ref(0);

function openLightbox(images, index) {
    lightboxImages.value = images;
    lightboxIndex.value = index;
    lightboxOpen.value = true;
}

function closeLightbox() {
    lightboxOpen.value = false;
}

function prevImage() {
    if (lightboxIndex.value > 0) lightboxIndex.value--;
}

function nextImage() {
    if (lightboxIndex.value < lightboxImages.value.length - 1) lightboxIndex.value++;
}

function toggleLike(item) {
    router.post(route('member.announcements.react', item.id), {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function markAsRead(item) {
    if (!item.is_read && !readTriggered.value[item.id]) {
        readTriggered.value[item.id] = true;
        router.post(route('member.announcements.read', item.id), {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

onMounted(() => {
    props.announcements.forEach(item => {
        if (!item.is_read) {
            setTimeout(() => markAsRead(item), 2000);
        }
    });
});
</script>

<template>
    <div class="space-y-4">
        <article
            v-for="item in announcements"
            :key="item.id"
            class="rounded-2xl bg-white shadow-sm border border-gray-100 overflow-hidden transition-all"
            :class="[item.is_pinned ? 'border-l-4 border-amber-400 bg-amber-50/40' : '', item.is_read ? '' : 'ring-1 ring-blue-200']"
        >
            <!-- Cover Image -->
            <div v-if="item.cover_image_url" class="w-full h-48 overflow-hidden bg-gray-100">
                <img :src="item.cover_image_url" :alt="item.title" class="w-full h-full object-cover cursor-pointer" @click="openLightbox([{url: item.cover_image_url}], 0)" />
            </div>

            <!-- Gallery Grid -->
            <div v-if="item.images?.length" class="px-4 pt-4">
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-for="(img, idx) in item.images.slice(0, 4)"
                        :key="img.id"
                        class="relative rounded-xl overflow-hidden bg-gray-100 cursor-pointer group"
                        :class="idx === 3 && item.images.length > 4 ? 'h-24' : 'h-24'"
                        @click="openLightbox(item.images, idx)"
                    >
                        <img :src="img.url" :alt="img.caption || ''" class="w-full h-full object-cover" />
                        <div v-if="idx === 3 && item.images.length > 4" class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <span class="text-white text-lg font-black">+{{ item.images.length - 4 }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-sm font-bold text-gray-900">{{ item.title }}</h3>
                    <span v-if="item.is_pinned" class="shrink-0 inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M10 2a1 1 0 01.894.553l1.382 2.764 3.051.443a1 1 0 01.554 1.705l-2.208 2.152.521 3.038a1 1 0 01-1.451 1.054L10 12.347l-2.729 1.435a1 1 0 01-1.451-1.054l.521-3.038-2.208-2.152a1 1 0 01.554-1.705l3.051-.443L9.106 2.553A1 1 0 0110 2z" />
                        </svg>
                        Pinned
                    </span>
                </div>

                <!-- Meta: Author + Date -->
                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-gray-400">
                    <span v-if="item.author_name" class="font-medium text-gray-500">{{ item.author_name }}</span>
                    <span v-if="item.author_name && item.published_human" class="text-gray-300">•</span>
                    <span>{{ item.published_human ?? item.published_at ?? 'Draft' }}</span>
                </div>

                <!-- Content -->
                <div class="mt-3 text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ item.content }}</div>

                <!-- Actions Footer -->
                <div class="mt-4 flex items-center gap-4 border-t border-gray-100 pt-3">
                    <!-- Like Button -->
                    <button
                        @click="toggleLike(item)"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold transition-colors"
                        :class="item.user_reaction ? 'text-red-500' : 'text-gray-400 hover:text-red-400'"
                    >
                        <svg class="w-4 h-4" :class="item.user_reaction ? 'fill-current' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                        </svg>
                        <span>{{ item.likes_count ?? 0 }}</span>
                    </button>

                    <!-- Read Count -->
                    <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        {{ item.reads_count ?? 0 }}
                    </span>

                    <!-- Unread indicator -->
                    <span v-if="!item.is_read" class="ml-auto inline-flex items-center gap-1 rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-semibold text-blue-600">
                        Baru
                    </span>
                </div>
            </div>
        </article>

        <div v-if="!announcements.length" class="rounded-2xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            Tiada pengumuman buat masa ini.
        </div>
    </div>

    <!-- Lightbox -->
    <teleport to="body">
        <div
            v-if="lightboxOpen"
            class="fixed inset-0 z-[9999] bg-black/90 flex items-center justify-center"
            @click="closeLightbox"
        >
            <button @click.stop="closeLightbox" class="absolute top-4 right-4 p-2 text-white hover:text-gray-300 z-10">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>

            <button v-if="lightboxIndex > 0" @click.stop="prevImage" class="absolute left-4 p-2 text-white hover:text-gray-300 z-10">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
            </button>

            <img
                :src="lightboxImages[lightboxIndex]?.url"
                class="max-h-[90vh] max-w-[90vw] object-contain rounded-xl"
                @click.stop
            />

            <button v-if="lightboxIndex < lightboxImages.length - 1" @click.stop="nextImage" class="absolute right-4 p-2 text-white hover:text-gray-300 z-10">
                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
            </button>

            <div class="absolute bottom-4 text-white text-sm font-medium">
                {{ lightboxIndex + 1 }} / {{ lightboxImages.length }}
            </div>
        </div>
    </teleport>
</template>

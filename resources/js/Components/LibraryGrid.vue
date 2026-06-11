<script setup>
defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
        <article v-for="item in items" :key="item.id" class="space-y-2">
            <div class="aspect-[3/4] bg-gray-100 rounded-xl overflow-hidden group relative border border-gray-200">
                <img v-if="item.cover_image_path" :src="item.cover_image_path" :alt="item.title" class="absolute inset-0 h-full w-full object-cover">
                <template v-else>
                    <div class="absolute inset-0 bg-gradient-to-b from-gray-100 to-gray-200"></div>
                    <div class="absolute inset-x-0 top-0 p-3">
                        <span class="inline-flex rounded-full bg-white/80 px-2 py-0.5 text-[10px] font-semibold text-gray-600">{{ item.category || 'Umum' }}</span>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-3">
                        <p class="text-xs font-bold text-gray-700 line-clamp-2">{{ item.title }}</p>
                    </div>
                </template>

                <div class="backdrop-blur-sm bg-black/50 opacity-0 group-hover:opacity-100 transition-all flex items-center justify-center absolute inset-0">
                    <a :href="item.file_path" target="_blank" class="rounded-xl bg-white px-3 py-2 text-xs font-semibold text-gray-800 shadow">
                        Download / Read PDF
                    </a>
                </div>
            </div>
            <p class="text-[11px] text-gray-500 line-clamp-2">{{ item.description }}</p>
        </article>

        <div v-if="!items.length" class="col-span-full rounded-2xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            Tiada dokumen dalam pustaka digital.
        </div>
    </div>
</template>

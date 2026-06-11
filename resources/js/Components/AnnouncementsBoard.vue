<script setup>
defineProps({
    announcements: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="space-y-3">
        <article
            v-for="item in announcements"
            :key="item.id"
            class="rounded-2xl bg-white p-4 shadow-sm border border-gray-100"
            :class="item.is_pinned ? 'border-l-4 border-amber-400 bg-amber-50/40' : ''"
        >
            <div class="flex items-start justify-between gap-3">
                <h3 class="text-sm font-bold text-gray-800">{{ item.title }}</h3>
                <span v-if="item.is_pinned" class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a1 1 0 01.894.553l1.382 2.764 3.051.443a1 1 0 01.554 1.705l-2.208 2.152.521 3.038a1 1 0 01-1.451 1.054L10 12.347l-2.729 1.435a1 1 0 01-1.451-1.054l.521-3.038-2.208-2.152a1 1 0 01.554-1.705l3.051-.443L9.106 2.553A1 1 0 0110 2z" />
                    </svg>
                    Pinned
                </span>
            </div>

            <p class="mt-2 text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ item.content }}</p>
            <p class="mt-3 text-[11px] text-gray-400">{{ item.published_human ?? item.published_at ?? 'Draft' }}</p>
        </article>

        <div v-if="!announcements.length" class="rounded-2xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500">
            Tiada pengumuman buat masa ini.
        </div>
    </div>
</template>

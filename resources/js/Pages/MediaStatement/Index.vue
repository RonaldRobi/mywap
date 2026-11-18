<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ statements: { type: Array, default: () => [] } });

const typeLabels = { text: 'Teks', pdf: 'PDF', both: 'Teks + PDF' };

function typeLabel(type) {
    return typeLabels[type] ?? 'Teks';
}
</script>

<template>
    <Head title="Kenyataan Media" />

    <AppLayout>
        <template #header>Kenyataan Media</template>

        <main class="mx-auto max-w-5xl px-4 py-8 md:px-6">
            <div class="grid gap-4 md:grid-cols-2">
                <Link
                    v-for="statement in statements"
                    :key="statement.id"
                    :href="route('media-statements.show', statement.id)"
                    class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:border-emerald-200"
                >
                    <div class="aspect-[16/9] w-full bg-gray-100">
                        <img
                            v-if="statement.featured_image"
                            :src="statement.featured_image"
                            :alt="statement.title"
                            width="800"
                            height="450"
                            loading="lazy"
                            class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]"
                        />
                        <div v-else class="flex h-full items-center justify-center px-4 text-center text-xs text-gray-400">
                            Kenyataan media
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 font-semibold text-emerald-800">
                                {{ typeLabel(statement.content_type) }}
                            </span>
                            <span class="text-gray-500">{{ statement.published_date }}</span>
                        </div>

                        <h2 class="mt-3 text-lg font-bold leading-snug text-gray-900 group-hover:text-emerald-800">
                            {{ statement.title }}
                        </h2>
                        <p class="mt-2 text-xs text-gray-500">{{ statement.organization_name || 'Umum' }}</p>
                    </div>
                </Link>
            </div>

            <div v-if="!statements.length" class="rounded-2xl border border-dashed border-gray-200 bg-white py-16 text-center">
                <p class="text-sm font-semibold text-gray-700">Belum ada kenyataan media.</p>
                <p class="mt-1 text-sm text-gray-500">Sila semak semula kemudian.</p>
            </div>
        </main>
    </AppLayout>
</template>

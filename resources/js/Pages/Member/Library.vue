<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import LibraryGrid from '@/Components/LibraryGrid.vue';

const props = defineProps({
    libraryItems: {
        type: Array,
        default: () => [],
    },
});

const searchText = ref('');
const selectedCategory = ref('all');

const categories = computed(() => {
    const unique = Array.from(new Set((props.libraryItems || []).map(item => item.category || 'Umum')));
    return ['all', ...unique];
});

const filteredItems = computed(() => {
    const keyword = searchText.value.trim().toLowerCase();

    return (props.libraryItems || []).filter(item => {
        const itemCategory = item.category || 'Umum';
        const categoryMatch = selectedCategory.value === 'all' || itemCategory === selectedCategory.value;

        if (!categoryMatch) {
            return false;
        }

        if (!keyword) {
            return true;
        }

        const haystack = `${item.title || ''} ${item.description || ''} ${itemCategory}`.toLowerCase();
        return haystack.includes(keyword);
    });
});
</script>

<template>
    <Head title="Pustaka PDF Digital" />

    <AppLayout>
        <template #header>Pustaka PDF Digital</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <section class="rounded-3xl border border-gray-100 bg-white/90 backdrop-blur-sm p-5 shadow-sm">
                <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Cari Buku / Kata Kunci</label>
                        <input
                            v-model="searchText"
                            type="text"
                            placeholder="Contoh: usrah, tarbiah, fiqh"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"
                        >
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                        <select
                            v-model="selectedCategory"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"
                        >
                            <option value="all">Semua Kategori</option>
                            <option v-for="category in categories.filter(c => c !== 'all')" :key="category" :value="category">{{ category }}</option>
                        </select>
                    </div>
                </div>

                <LibraryGrid :items="filteredItems" />
            </section>
        </div>
    </AppLayout>
</template>

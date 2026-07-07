<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    categories: {
        type: Object,
        required: true,
    },
});

const page = usePage();
</script>

<template>
    <Head title="Kategori" />

    <AppLayout>
        <template #header>Kategori</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6">
            <div v-if="page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-gray-900">Senarai Kategori</p>
                    <p class="mt-1 text-sm text-gray-500">Susun kategori produk.</p>
                </div>
                <Link :href="route('categories.create')" class="inline-flex items-center justify-center rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                    Tambah Kategori
                </Link>
            </div>

            <div class="mt-4 overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">Nama</th>
                                <th class="px-4 py-3 text-left">Deskripsi</th>
                                <th class="px-4 py-3 text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="c in categories.data" :key="c.id" class="hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-semibold text-gray-900">{{ c.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ c.description || '—' }}</td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="route('categories.edit', c.id)" class="rounded-xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        Edit
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="!categories.data?.length">
                                <td colspan="3" class="px-4 py-10 text-center text-sm text-gray-500">
                                    Tiada kategori lagi.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="categories.links?.length > 3" class="mt-8 flex items-center justify-center">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Link
                        v-for="link in categories.links"
                        :key="link.label"
                        :href="link.url || ''"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                        :class="[
                            link.active ? 'bg-gray-900 text-white shadow-md' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:text-gray-900',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>


<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    articles: { type: Object, required: true },
    categories: { type: Array, default: () => [] },
});

function removePost(article) {
    if (!confirm('Padam artikel ini?')) return;
    useForm({}).delete(route('admin.articles.destroy', article.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Semua Artikel" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Semua Artikel</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ articles.total }} artikel</p>
                <Link :href="route('admin.articles.create')" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 transition">
                    + Tulis Artikel
                </Link>
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="space-y-4">
                    <article v-for="article in articles.data" :key="article.id" class="rounded-2xl border border-gray-100 bg-gray-50 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-emerald-700">{{ article.organization?.name || 'Umum' }}</span>
                                    <span :class="article.is_published ? 'text-emerald-700' : 'text-amber-700'">{{ article.is_published ? 'Published' : 'Draft' }}</span>
                                    <span v-if="article.is_featured" class="rounded-full bg-amber-100 px-2 py-0.5 text-amber-700">Featured</span>
                                    <span v-for="cat in article.categories" :key="cat.id" class="rounded-full bg-blue-100 px-2 py-0.5 text-blue-700">{{ cat.name }}</span>
                                </div>
                                <h3 class="mt-2 text-lg font-black text-gray-900 truncate">{{ article.title }}</h3>
                                <p class="mt-1 text-sm text-gray-600 line-clamp-2">{{ article.excerpt || 'Tiada ringkasan' }}</p>
                                <p class="mt-1 text-xs text-gray-400">Penulis: {{ article.author?.name }} • {{ article.published_at ? new Date(article.published_at).toLocaleDateString() : '-' }}</p>
                            </div>
                            <img v-if="article.cover_image_path" :src="article.cover_image_path" class="h-20 w-20 rounded-xl object-cover border border-gray-200 shrink-0" />
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <Link :href="route('admin.articles.edit', article.id)" class="rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-100 transition">
                                Edit
                            </Link>
                            <button @click="removePost(article)" class="rounded-lg border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition">
                                Delete
                            </button>
                        </div>
                    </article>

                    <div v-if="!articles.data.length" class="text-center py-12">
                        <p class="text-sm text-gray-500">Belum ada artikel.</p>
                        <Link :href="route('admin.articles.create')" class="mt-2 inline-block text-sm font-semibold text-emerald-600 hover:text-emerald-700">
                            Tulis artikel pertama
                        </Link>
                    </div>
                </div>

                <div v-if="articles.last_page > 1" class="mt-6 flex items-center justify-center gap-2">
                    <Link v-if="articles.prev_page_url" :href="articles.prev_page_url" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Sebelumnya
                    </Link>
                    <span class="text-xs text-gray-500">Halaman {{ articles.current_page }} / {{ articles.last_page }}</span>
                    <Link v-if="articles.next_page_url" :href="articles.next_page_url" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                        Seterusnya
                    </Link>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

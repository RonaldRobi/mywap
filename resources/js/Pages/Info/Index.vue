<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    posts: { type: Object, default: () => ({ data: [], links: [] }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ category_id: null }) },
});

function selectCategory(categoryId = null) {
    router.get(
        route('news.index'),
        { category_id: categoryId || undefined },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function readableDate(value) {
    if (!value) return '-';

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
}

function linkIsUsable(link) {
    return link?.url && !link.url.includes('null');
}
</script>

<template>
    <Head title="Info Terkini" />

    <AppLayout>
        <template #header>Info Terkini</template>

        <div class="mx-auto max-w-7xl px-4 py-5 md:px-6 md:py-6">
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-black text-gray-900 md:text-2xl">Berita & Kemas Kini Organisasi</h1>
                        <p class="mt-1 text-sm text-gray-500">Lihat info terkini, ikut kategori pilihan anda.</p>
                    </div>
                    <Link
                        v-if="$page.props.auth?.user?.roles?.includes('Admin') || $page.props.auth?.user?.roles?.includes('Superadmin')"
                        :href="route('admin.news.manage')"
                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100"
                    >
                        Urus Info
                    </Link>
                </div>

                <div class="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <button
                        type="button"
                        @click="selectCategory(null)"
                        :class="[
                            'shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                            !filters?.category_id ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                        ]"
                    >
                        Semua
                    </button>

                    <button
                        v-for="category in categories"
                        :key="category.id"
                        type="button"
                        @click="selectCategory(category.id)"
                        :class="[
                            'shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition',
                            Number(filters?.category_id) === Number(category.id)
                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                                : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                        ]"
                    >
                        {{ category.icon ? `${category.icon} ` : '' }}{{ category.name }}
                    </button>
                </div>
            </div>

            <section class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="post in posts.data"
                    :key="post.id"
                    class="group overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <Link :href="route('news.show', post.id)" class="block">
                        <div class="aspect-[16/10] bg-gray-100">
                            <img
                                v-if="post.cover_image_path"
                                :src="post.cover_image_path"
                                :alt="post.title"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-[1.02]"
                            >
                            <div v-else class="grid h-full place-items-center text-sm text-gray-400">Tiada gambar</div>
                        </div>

                        <div class="p-4">
                            <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ post.category_name }}</span>
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ post.organization_name }}</span>
                            </div>

                            <h2 class="mt-2 line-clamp-2 text-base font-black text-gray-900">{{ post.title }}</h2>
                            <p class="mt-1 line-clamp-2 text-sm text-gray-600">{{ post.excerpt || 'Klik untuk baca lanjut info ini.' }}</p>

                            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                                <span>{{ readableDate(post.published_at) }}</span>
                                <div class="flex items-center gap-2">
                                    <span>👍 {{ post.likes_count }}</span>
                                    <span>👎 {{ post.dislikes_count }}</span>
                                    <span>💬 {{ post.comments_count }}</span>
                                </div>
                            </div>
                        </div>
                    </Link>
                </article>

                <div v-if="!posts.data.length" class="sm:col-span-2 xl:col-span-3 rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
                    Tiada info terkini untuk kategori ini.
                </div>
            </section>

            <nav v-if="posts.links?.length > 3" class="mt-6 flex flex-wrap items-center gap-2">
                <component
                    :is="linkIsUsable(link) ? Link : 'span'"
                    v-for="(link, index) in posts.links"
                    :key="index"
                    :href="linkIsUsable(link) ? link.url : undefined"
                    class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                    :class="[
                        link.active
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-700'
                            : linkIsUsable(link)
                                ? 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'
                                : 'border-gray-100 bg-gray-50 text-gray-400'
                    ]"
                    v-html="link.label"
                />
            </nav>
        </div>
    </AppLayout>
</template>

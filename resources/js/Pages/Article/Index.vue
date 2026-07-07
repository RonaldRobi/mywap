<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    articles: { type: Array, required: true },
    featured: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Semua Artikel" />

    <div class="min-h-screen bg-slate-50 font-sans pb-16 md:pb-8">
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Kembali ke Laman Utama
                </Link>
                <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-8 w-auto" />
            </div>
        </nav>

        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="mb-10 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-2">Semua Artikel</h1>
                <p class="text-slate-500 font-semibold text-sm md:text-base">Terokai penulisan dan perkongsian terkini dari penganjur kami.</p>
            </div>

            <div v-if="featured.length" class="mb-10">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-4">Unggulan</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <Link
                        v-for="article in featured"
                        :key="article.id"
                        :href="article.public_url"
                        class="group flex flex-col bg-gradient-to-br from-emerald-50 to-white rounded-3xl border border-emerald-100 overflow-hidden hover:shadow-xl hover:shadow-emerald-900/10 transition-all duration-300"
                    >
                        <div class="aspect-[16/10] bg-slate-100 relative overflow-hidden">
                            <img v-if="article.cover_image" :src="article.cover_image" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" alt="Cover" />
                            <div v-else class="w-full h-full flex items-center justify-center text-slate-300 bg-slate-100">
                                <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                        </div>
                        <div class="p-6 flex flex-col flex-1">
                            <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-3">
                                <span class="text-emerald-600 bg-emerald-100 px-2 py-1 rounded-md">{{ article.author_name }}</span>
                                <span>{{ article.published_date }}</span>
                            </div>
                            <h3 class="text-lg font-black text-slate-900 leading-tight mb-2 group-hover:text-emerald-600 transition">{{ article.title }}</h3>
                            <p class="text-sm text-slate-600 line-clamp-2 mt-auto">{{ article.excerpt }}</p>
                        </div>
                    </Link>
                </div>
            </div>

            <div v-if="articles.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link
                    v-for="article in articles"
                    :key="article.id"
                    :href="article.public_url"
                    class="group flex flex-col bg-white rounded-3xl border border-slate-100 overflow-hidden hover:shadow-xl hover:shadow-emerald-900/5 transition-all duration-300"
                >
                    <div class="aspect-[16/10] bg-slate-100 relative overflow-hidden">
                        <img v-if="article.cover_image" :src="article.cover_image" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" alt="Cover" />
                        <div v-else class="w-full h-full flex items-center justify-center text-slate-300 bg-slate-100">
                            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                    </div>
                    <div class="p-6 flex flex-col flex-1">
                        <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-3">
                            <span class="text-emerald-600 bg-emerald-50 px-2 py-1 rounded-md">{{ article.author_name }}</span>
                            <span>{{ article.published_date }}</span>
                        </div>
                        <h3 class="text-lg font-black text-slate-900 leading-tight mb-2 group-hover:text-emerald-600 transition">{{ article.title }}</h3>
                        <p class="text-sm text-slate-600 line-clamp-2 mt-auto">{{ article.excerpt }}</p>
                    </div>
                </Link>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5a2.5 2.5 0 00-2.5-2.5H15M9 11l3 3m0 0l3-3m-3 3V8"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">Tiada Artikel</h3>
                <p class="text-sm text-slate-500">Belum ada artikel yang diterbitkan buat masa ini.</p>
            </div>
        </div>
    </div>
</template>

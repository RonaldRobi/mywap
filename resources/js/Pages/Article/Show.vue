<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    article: { type: Object, required: true },
    comments: { type: Array, default: () => [] },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const reactionForm = useForm({ reaction: '' });
const commentForm = useForm({ content: '', anonymous_name: '' });
const activeGallery = ref(0);

function react(type) {
    reactionForm.reaction = type;
    reactionForm.post(route('articles.react', props.article.slug), { preserveScroll: true });
}

function submitComment() {
    commentForm.post(route('articles.comments.store', props.article.slug), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset('content'),
    });
}

const gallery = computed(() => props.article.gallery || []);
const hasGallery = computed(() => gallery.value.length > 0);
</script>

<template>
    <Head :title="article.title" />

    <div class="min-h-screen bg-slate-50 font-sans text-slate-900 pb-16 md:pb-0">
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center">
                    <img src="/storage/logos/organizations/logomywaphorizontal.png" alt="myWAP Logo" class="h-8 w-auto" />
                </Link>
                <Link href="/" class="text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    Kembali ke Halaman Utama
                </Link>
            </div>
        </nav>

        <div class="mx-auto max-w-4xl px-4 py-8 md:px-6 space-y-6">
            <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div v-if="article.cover_image_path" class="aspect-[16/8] bg-gray-100 w-full">
                    <img :src="article.cover_image_path" :alt="article.title" class="h-full w-full object-cover">
                </div>

                <div class="p-6 md:p-10">
                    <div class="mb-4 flex flex-wrap items-center gap-3 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">{{ article.organization_name }}</span>
                        <span>{{ article.published_at || '-' }}</span>
                        <span>Oleh: {{ article.author_name }}</span>
                    </div>

                    <div v-if="article.categories?.length" class="mb-4 flex flex-wrap gap-2">
                        <span v-for="cat in article.categories" :key="cat.id" class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">
                            {{ cat.name }}
                        </span>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-black text-slate-900 leading-tight">{{ article.title }}</h1>
                    <p v-if="article.excerpt" class="mt-4 text-lg text-slate-600 leading-relaxed">{{ article.excerpt }}</p>

                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <p class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Kongsikan Artikel Ini</p>
                        <SocialShareButtons
                            :title="article.title"
                            :text="article.excerpt || 'Baca artikel menarik ini dari myWAP.'"
                            :url="route('share.article', article.slug, true)"
                        />
                    </div>

                    <div class="prose prose-emerald mt-10 max-w-none text-slate-700 leading-relaxed text-lg" v-html="article.content"></div>

                    <div v-if="hasGallery" class="mt-10 pt-6 border-t border-slate-100">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-slate-500 mb-4">Galeri</h3>
                        <div class="relative rounded-2xl overflow-hidden bg-slate-100">
                            <img :src="gallery[activeGallery].path" class="w-full aspect-[16/9] object-cover transition-opacity duration-300" />
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/50 to-transparent p-4">
                                <p v-if="gallery[activeGallery].caption" class="text-white text-sm">{{ gallery[activeGallery].caption }}</p>
                                <p class="text-white/70 text-xs mt-1">{{ activeGallery + 1 }} / {{ gallery.length }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2 overflow-x-auto pb-2">
                            <button
                                v-for="(img, i) in gallery"
                                :key="img.id"
                                @click="activeGallery = i"
                                :class="[
                                    'h-16 w-16 shrink-0 rounded-lg border-2 overflow-hidden transition',
                                    activeGallery === i ? 'border-emerald-500' : 'border-transparent opacity-60 hover:opacity-100'
                                ]"
                            >
                                <img :src="img.path" class="h-full w-full object-cover" />
                            </button>
                        </div>
                    </div>

                    <div v-if="article.tags?.length" class="mt-6 flex flex-wrap gap-2">
                        <span v-for="tag in article.tags" :key="tag.id" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                            #{{ tag.name }}
                        </span>
                    </div>

                    <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap items-center gap-3">
                        <button
                            @click="react('like')"
                            :class="[
                                'rounded-xl border px-4 py-2.5 text-sm font-bold transition-all',
                                article.my_reaction === 'like' ? 'border-emerald-300 bg-emerald-50 text-emerald-700 shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            Suka ({{ article.likes_count }})
                        </button>
                        <button
                            @click="react('dislike')"
                            :class="[
                                'rounded-xl border px-4 py-2.5 text-sm font-bold transition-all',
                                article.my_reaction === 'dislike' ? 'border-red-300 bg-red-50 text-red-700 shadow-sm' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                            ]"
                        >
                            Tidak Suka ({{ article.dislikes_count }})
                        </button>
                    </div>
                </div>
            </article>

            <section class="rounded-3xl border border-gray-100 bg-white p-6 md:p-10 shadow-sm">
                <h2 class="text-2xl font-black text-slate-900 mb-6">Komen</h2>

                <form class="space-y-4 mb-10 bg-slate-50 p-5 rounded-2xl border border-slate-100" @submit.prevent="submitComment">
                    <p class="text-sm text-slate-600 mb-2 font-medium">Tinggalkan komen anda ({{ user ? 'sebagai ' + user.name : 'boleh sebagai anonim' }}):</p>
                    <div v-if="!user" class="mb-3">
                        <label for="anonymous_name" class="sr-only">Nama (Pilihan)</label>
                        <input id="anonymous_name" v-model="commentForm.anonymous_name" type="text" class="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-sm focus:border-emerald-500 focus:ring-emerald-500 transition" placeholder="Nama anda (Pilihan)" />
                    </div>
                    <div>
                        <label for="content" class="sr-only">Komen</label>
                        <textarea id="content" v-model="commentForm.content" rows="3" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-emerald-500 focus:ring-emerald-500 transition" placeholder="Tulis komen anda di sini..." required></textarea>
                        <p v-if="commentForm.errors.content" class="text-xs text-red-600 mt-1">{{ commentForm.errors.content }}</p>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="commentForm.processing" class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white hover:bg-slate-800 transition shadow-sm disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ commentForm.processing ? 'Menghantar...' : 'Hantar Komen' }}
                        </button>
                    </div>
                </form>

                <div class="space-y-4">
                    <article v-for="comment in comments" :key="comment.id" class="rounded-2xl border border-slate-100 bg-white p-5 shadow-sm">
                        <div class="flex justify-between items-start mb-2">
                            <p class="text-sm font-bold text-slate-800">{{ comment.user_name }}</p>
                            <p class="text-xs text-slate-500 font-medium">{{ comment.created_at }}</p>
                        </div>
                        <p class="text-slate-700 leading-relaxed">{{ comment.content }}</p>
                    </article>
                    <p v-if="!comments.length" class="text-center text-slate-500 py-6 border-2 border-dashed border-slate-100 rounded-2xl">Belum ada komen. Jadilah yang pertama!</p>
                </div>
            </section>
        </div>
    </div>
</template>

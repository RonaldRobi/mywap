<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({
    post: { type: Object, required: true },
    comments: { type: Array, default: () => [] },
});

const reactionForm = useForm({
    reaction: '',
});

const commentForm = useForm({
    content: '',
});

function react(type) {
    reactionForm.reaction = type;
    reactionForm.post(route('news.react', props.post.id), {
        preserveScroll: true,
    });
}

function submitComment() {
    commentForm.post(route('news.comments.store', props.post.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}
</script>

<template>
    <Head :title="post.title" />

    <AppLayout>
        <template #header>Info Terkini</template>

        <div class="mx-auto max-w-5xl px-4 py-6 md:px-6 space-y-5">
            <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div class="aspect-[16/8] bg-gray-100">
                    <img v-if="post.cover_image_path" :src="post.cover_image_path" :alt="post.title" class="h-full w-full object-cover">
                    <div v-else class="grid h-full place-items-center text-sm text-gray-400">Tiada gambar</div>
                </div>

                <div class="p-5 md:p-7">
                    <div class="mb-3 flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-indigo-700">{{ post.category?.name || 'Umum' }}</span>
                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-emerald-700">{{ post.organization_name }}</span>
                        <span>{{ post.published_at || '-' }}</span>
                    </div>

                    <h1 class="text-2xl font-black text-gray-900">{{ post.title }}</h1>
                    <p v-if="post.excerpt" class="mt-2 text-gray-600">{{ post.excerpt }}</p>

                    <div class="mt-4">
                        <p class="mb-2 text-xs font-bold uppercase tracking-wide text-gray-500">Sebarkan Info Ini</p>
                        <SocialShareButtons
                            :title="post.title"
                            :text="post.excerpt || 'Baca info terkini ini.'"
                            :url="route('share.info', post.id, true)"
                        />
                    </div>

                    <div class="prose mt-5 max-w-none text-gray-700 whitespace-pre-line">{{ post.content }}</div>

                    <div class="mt-6 flex flex-wrap items-center gap-2">
                        <button
                            @click="react('like')"
                            :class="[
                                'rounded-xl border px-3 py-2 text-xs font-semibold transition-colors',
                                post.my_reaction === 'like' ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                            ]"
                        >
                            👍 Suka ({{ post.likes_count }})
                        </button>
                        <button
                            @click="react('dislike')"
                            :class="[
                                'rounded-xl border px-3 py-2 text-xs font-semibold transition-colors',
                                post.my_reaction === 'dislike' ? 'border-red-300 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'
                            ]"
                        >
                            👎 Tidak Suka ({{ post.dislikes_count }})
                        </button>
                    </div>
                </div>
            </article>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm md:p-6">
                <h2 class="text-lg font-black text-gray-900">Komen Ahli</h2>

                <form class="mt-4 space-y-2" @submit.prevent="submitComment">
                    <textarea
                        v-model="commentForm.content"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"
                        placeholder="Tulis komen anda..."
                        required
                    ></textarea>
                    <p v-if="commentForm.errors.content" class="text-xs text-red-600">{{ commentForm.errors.content }}</p>
                    <button type="submit" :disabled="commentForm.processing" class="rounded-xl bg-gray-900 px-4 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                        {{ commentForm.processing ? 'Menghantar...' : 'Hantar Komen' }}
                    </button>
                </form>

                <div class="mt-6 space-y-3">
                    <article v-for="comment in comments" :key="comment.id" class="rounded-2xl border border-gray-100 bg-gray-50 p-3">
                        <p class="text-sm font-semibold text-gray-800">{{ comment.user_name }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ comment.content }}</p>
                        <p class="mt-1 text-xs text-gray-400">{{ comment.created_at }}</p>
                    </article>

                    <p v-if="!comments.length" class="text-sm text-gray-500">Belum ada komen. Jadilah yang pertama.</p>
                </div>
            </section>

            <div>
                <Link :href="route('news.index')" class="text-sm font-semibold text-gray-500 hover:text-gray-700">← Kembali ke Info Terkini</Link>
            </div>
        </div>
    </AppLayout>
</template>

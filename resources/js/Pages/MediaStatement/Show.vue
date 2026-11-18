<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialShareButtons from '@/Components/SocialShareButtons.vue';

const props = defineProps({ statement: { type: Object, required: true } });

function plainText(html) {
    if (!html) {
        return '';
    }

    const container = document.createElement('div');
    container.innerHTML = html;

    return (container.textContent || '').replace(/\s+/g, ' ').trim();
}

const hasText = computed(() => !!props.statement.content && plainText(props.statement.content) !== '');
const description = computed(() => {
    const content = plainText(props.statement.content);

    if (content) {
        return content.slice(0, 160);
    }

    return props.statement.pdf_url
        ? 'Kenyataan media rasmi dalam format PDF.'
        : 'Kenyataan media rasmi.';
});

const shareText = computed(() => description.value);
</script>

<template>
    <Head :title="statement.title">
        <meta name="description" :content="description" />
        <meta property="og:type" content="article" />
        <meta property="og:title" :content="statement.title" />
        <meta property="og:description" :content="description" />
        <meta property="og:url" :content="statement.public_url" />
        <meta v-if="statement.featured_image" property="og:image" :content="statement.featured_image" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" :content="statement.title" />
        <meta name="twitter:description" :content="description" />
        <meta v-if="statement.featured_image" name="twitter:image" :content="statement.featured_image" />
    </Head>

    <AppLayout>
        <template #header>Kenyataan Media</template>

        <main class="mx-auto max-w-4xl px-4 py-8 md:px-6">
            <article class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                <div v-if="statement.featured_image" class="aspect-[16/9] w-full bg-gray-100">
                    <img
                        :src="statement.featured_image"
                        :alt="statement.title"
                        width="1200"
                        height="675"
                        class="h-full w-full object-cover"
                        fetchpriority="high"
                    />
                </div>

                <div class="p-6 md:p-10">
                    <div class="flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <span v-if="statement.organization" class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800">
                            {{ statement.organization.name }}
                        </span>
                        <span v-else class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-800">Umum</span>
                        <span>{{ statement.published_date }}</span>
                    </div>

                    <h1 class="mt-4 text-3xl font-black leading-tight text-gray-900 md:text-4xl">{{ statement.title }}</h1>

                    <div v-if="statement.pdf_url" class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4 sm:flex sm:items-center sm:justify-between sm:gap-4">
                        <div>
                            <p class="text-sm font-bold text-gray-900">Kenyataan penuh dalam PDF</p>
                            <p class="mt-1 text-xs text-gray-600">Buka atau muat turun dokumen rasmi untuk simpanan anda.</p>
                        </div>
                        <a
                            :href="statement.pdf_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 inline-flex shrink-0 items-center justify-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 sm:mt-0"
                        >
                            Buka PDF
                        </a>
                    </div>

                    <div v-if="hasText" class="statement-body mt-8" v-html="statement.content"></div>

                    <div v-if="!statement.organization" class="mt-10 border-t border-gray-100 pt-6">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Kongsi kenyataan ini</p>
                        <div class="mt-3">
                            <SocialShareButtons
                                :title="statement.title"
                                :text="shareText"
                                :url="statement.share_url"
                            />
                        </div>
                    </div>

                    <div v-if="statement.attachments?.length" class="mt-10 border-t border-gray-100 pt-6">
                        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-500">Imej sokongan</h2>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <a
                                v-for="attachment in statement.attachments"
                                :key="attachment.id"
                                :href="attachment.url"
                                target="_blank"
                                rel="noopener"
                                class="block overflow-hidden rounded-xl border border-gray-200"
                            >
                                <img
                                    :src="attachment.url"
                                    alt="Imej sokongan kenyataan media"
                                    width="480"
                                    height="320"
                                    class="aspect-[3/2] w-full object-cover"
                                />
                            </a>
                        </div>
                    </div>
                </div>
            </article>
        </main>
    </AppLayout>
</template>

<style scoped>
.statement-body {
    color: #1f2937;
    font-size: 1rem;
    line-height: 1.8;
}

.statement-body :deep(p) {
    margin-bottom: 1rem;
}

.statement-body :deep(h2),
.statement-body :deep(h3) {
    margin-top: 1.75rem;
    margin-bottom: 0.75rem;
    font-weight: 800;
    line-height: 1.3;
    color: #111827;
}

.statement-body :deep(ul),
.statement-body :deep(ol) {
    margin: 0.75rem 0 1rem 1.25rem;
    padding-left: 0.5rem;
}

.statement-body :deep(li) {
    margin-bottom: 0.35rem;
}

.statement-body :deep(a) {
    color: #047857;
    font-weight: 600;
    text-decoration: underline;
}

.statement-body :deep(blockquote) {
    margin: 1rem 0;
    border-left: 3px solid #a7f3d0;
    padding-left: 1rem;
    color: #374151;
    font-style: italic;
}

.statement-body :deep(img) {
    max-width: 100%;
    height: auto;
    margin: 1rem 0;
    border-radius: 16px;
}
</style>

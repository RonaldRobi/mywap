<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ statements: { type: Object, required: true } });

const typeLabels = { text: 'Teks', pdf: 'PDF', both: 'Teks + PDF' };

function typeLabel(type) {
    return typeLabels[type] ?? 'Teks';
}

function formatDate(value) {
    if (!value) {
        return 'Belum diterbitkan';
    }

    return new Intl.DateTimeFormat('ms-MY', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
}

function removeStatement(statement) {
    if (confirm('Padam kenyataan media ini?')) {
        useForm({}).delete(route('admin.media-statements.destroy', statement.id));
    }
}
</script>

<template>
    <Head title="Kenyataan Media" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Kenyataan Media</template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-500">{{ statements.total }} kenyataan media</p>
                    <p class="mt-1 text-xs text-gray-400">Teks, PDF, atau kedua-duanya dalam satu kenyataan.</p>
                </div>
                <Link
                    :href="route('admin.media-statements.create')"
                    class="cursor-pointer rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                    Kenyataan Media Baru
                </Link>
            </div>

            <section class="space-y-3 rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-5">
                <article
                    v-for="statement in statements.data"
                    :key="statement.id"
                    class="flex flex-col gap-4 rounded-2xl border border-gray-100 bg-gray-50 p-4 sm:flex-row sm:items-center"
                >
                    <div class="h-28 w-full shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-white sm:h-20 sm:w-32">
                        <img
                            v-if="statement.featured_image_path"
                            :src="statement.featured_image_path"
                            :alt="'Imej utama: ' + statement.title"
                            width="320"
                            height="180"
                            class="h-full w-full object-cover"
                        />
                        <div v-else class="flex h-full items-center justify-center px-3 text-center text-[11px] text-gray-400">
                            Tiada imej utama
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span
                                class="rounded-full px-2.5 py-1 font-semibold"
                                :class="statement.is_published ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ statement.is_published ? 'Diterbitkan' : 'Draf' }}
                            </span>
                            <span class="rounded-full bg-white px-2.5 py-1 font-semibold text-gray-600 ring-1 ring-gray-200">
                                {{ typeLabel(statement.content_type) }}
                            </span>
                            <span class="text-gray-500">{{ statement.organization?.name || 'Umum' }}</span>
                        </div>

                        <h3 class="mt-2 truncate text-base font-black text-gray-900 sm:text-lg">{{ statement.title }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ formatDate(statement.published_at) }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 sm:justify-end">
                        <Link
                            v-if="statement.is_published"
                            :href="route('media-statements.show', statement.id)"
                            class="cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Lihat
                        </Link>
                        <Link
                            :href="route('admin.media-statements.edit', statement.id)"
                            class="cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            Edit
                        </Link>
                        <button
                            type="button"
                            class="cursor-pointer rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-100"
                            @click="removeStatement(statement)"
                        >
                            Padam
                        </button>
                    </div>
                </article>

                <div v-if="!statements.data.length" class="px-4 py-16 text-center">
                    <p class="text-sm font-semibold text-gray-700">Belum ada kenyataan media.</p>
                    <p class="mt-1 text-sm text-gray-500">Mulakan dengan satu kenyataan teks, PDF, atau kedua-duanya.</p>
                    <Link
                        :href="route('admin.media-statements.create')"
                        class="mt-4 inline-block cursor-pointer rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700"
                    >
                        Kenyataan Media Baru
                    </Link>
                </div>
            </section>

            <nav v-if="statements.links?.length > 3" class="flex flex-wrap gap-1" aria-label="Navigasi halaman">
                <template v-for="link in statements.links" :key="link.label">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold"
                        :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50'"
                        v-html="link.label"
                    />
                    <span v-else class="rounded-lg border border-gray-100 px-3 py-1.5 text-xs text-gray-400" v-html="link.label" />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>

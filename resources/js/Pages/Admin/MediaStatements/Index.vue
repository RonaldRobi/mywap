<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ statements: { type: Object, required: true } });

function removeStatement(statement) {
    if (confirm('Padam kenyataan media ini?')) useForm({}).delete(route('admin.media-statements.destroy', statement.id));
}
</script>

<template>
    <Head title="Kenyataan Media" />
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Kenyataan Media</template>
        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ statements.total }} kenyataan media</p>
                <Link :href="route('admin.media-statements.create')" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">+ Kenyataan Media</Link>
            </div>
            <section class="space-y-3 rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <article v-for="statement in statements.data" :key="statement.id" class="flex items-start justify-between gap-4 rounded-2xl border border-gray-100 bg-gray-50 p-4">
                    <div class="min-w-0">
                        <p class="text-xs text-gray-500">{{ statement.organization?.name || 'Umum' }} · {{ statement.is_published ? 'Diterbitkan' : 'Draf' }}</p>
                        <h3 class="mt-1 truncate text-lg font-black text-gray-900">{{ statement.title }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ statement.published_at || 'Belum diterbitkan' }}</p>
                        <div class="mt-3 flex gap-2"><Link :href="route('admin.media-statements.edit', statement.id)" class="rounded-lg border bg-white px-3 py-1 text-xs font-semibold">Edit</Link><button class="rounded-lg border border-red-200 bg-red-50 px-3 py-1 text-xs font-semibold text-red-700" @click="removeStatement(statement)">Padam</button></div>
                    </div>
                    <img v-if="statement.featured_image_path" :src="statement.featured_image_path" alt="" class="h-20 w-20 shrink-0 rounded-xl object-cover" />
                </article>
                <p v-if="!statements.data.length" class="py-12 text-center text-sm text-gray-500">Belum ada kenyataan media.</p>
            </section>
        </div>
    </AppLayout>
</template>

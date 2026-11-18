<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MediaStatementForm from '@/Components/MediaStatementForm.vue';

defineProps({ organizations: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Kenyataan Media Baru" />

    <AppLayout :back-route="route('admin.media-statements.index')" back-label="Kembali ke Senarai">
        <template #header>Kenyataan Media Baru</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <MediaStatementForm mode="create" :organizations="organizations" />
        </div>
    </AppLayout>
</template>

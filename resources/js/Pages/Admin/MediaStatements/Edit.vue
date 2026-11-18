<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import MediaStatementForm from '@/Components/MediaStatementForm.vue';

defineProps({
    statement: { type: Object, required: true },
    organizations: { type: Array, default: () => [] },
});
</script>

<template>
    <Head :title="'Edit: ' + statement.title" />

    <AppLayout :back-route="route('admin.media-statements.index')" back-label="Kembali ke Senarai">
        <template #header>Edit Kenyataan Media</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6">
            <MediaStatementForm mode="edit" :statement="statement" :organizations="organizations" />
        </div>
    </AppLayout>
</template>

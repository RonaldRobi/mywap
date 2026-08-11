<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductForm from './Partials/ProductForm.vue';

defineProps({
    categories: {
        type: Array,
        required: true,
    },
    organizations: {
        type: Array,
        default: () => [],
    },
    isSuperadmin: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <Head title="Tambah Produk" />

    <AppLayout :back-route="route('products.index')" back-label="Kembali ke Produk">
        <template #header>Tambah Produk</template>

        <ProductForm
            :categories="categories"
            :organizations="organizations"
            :is-superadmin="isSuperadmin"
            :submit-url="route('products.store')"
            :cancel-url="route('products.index')"
            submit-label="Simpan Produk"
        />
    </AppLayout>
</template>

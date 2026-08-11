<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductForm from './Partials/ProductForm.vue';

defineProps({
    categories: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <Head title="Tambah Produk" />

    <AppLayout :back-route="route('products.index')" back-label="Kembali ke Produk">
        <template #header>Tambah Produk</template>

        <ProductForm
            :categories="categories"
            :submit-url="route('products.store')"
            :cancel-url="route('products.index')"
            submit-label="Simpan Produk"
        />
    </AppLayout>
</template>

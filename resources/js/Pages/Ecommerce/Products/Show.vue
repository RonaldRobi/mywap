<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
});

const page = usePage();

const buyForm = useForm({
    products: [
        { id: props.product.id, quantity: 1 },
    ],
});

function buyNow() {
    buyForm.post(route('orders.store'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="product.name" />

    <AppLayout>
        <template #header>Produk</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6">
            <div v-if="page.props.errors?.error" class="mb-4 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ page.props.errors.error }}
            </div>
            <div v-if="page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                    <div class="aspect-[4/3] bg-gray-50">
                        <img
                            v-if="product.image"
                            :src="`/storage/${product.image}`"
                            alt="Gambar Produk"
                            class="h-full w-full object-cover"
                        >
                    </div>
                    <div v-if="!product.image" class="flex aspect-[4/3] items-center justify-center text-sm text-gray-400">
                        Tiada gambar
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h1 class="truncate text-xl font-black text-gray-900 sm:text-2xl">{{ product.name }}</h1>
                            <p class="mt-1 text-sm text-gray-500">Kategori: {{ product.category?.name ?? '—' }}</p>
                        </div>
                        <span
                            class="inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                            :class="product.status ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600'"
                        >
                            {{ product.status ? 'Aktif' : 'Tidak Aktif' }}
                        </span>
                    </div>

                    <p class="mt-4 text-2xl font-black text-gray-900">RM{{ Number(product.price ?? 0).toFixed(2) }}</p>
                    <p class="mt-1 text-sm text-gray-600">Stok: <span class="font-semibold text-gray-900">{{ product.stock }}</span></p>

                    <p v-if="product.description" class="mt-4 whitespace-pre-line text-sm text-gray-700">{{ product.description }}</p>
                    <p v-else class="mt-4 text-sm text-gray-400">Tiada deskripsi.</p>

                    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-semibold text-gray-500">Kuantiti</label>
                            <input
                                v-model.number="buyForm.products[0].quantity"
                                type="number"
                                min="1"
                                :max="product.stock"
                                class="w-24 rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"
                            >
                        </div>
                        <button
                            type="button"
                            @click="buyNow"
                            :disabled="buyForm.processing || product.stock < 1"
                            class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                            {{ buyForm.processing ? 'Memproses...' : 'Beli Sekarang' }}
                        </button>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                        <Link :href="route('products.index')" class="text-sm font-semibold text-gray-700 hover:underline">
                            ← Kembali
                        </Link>
                        <Link :href="route('products.edit', product.id)" class="rounded-2xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Edit Produk
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>


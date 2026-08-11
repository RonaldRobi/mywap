<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductForm from './Partials/ProductForm.vue';

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
});

const confirmingDelete = ref(false);

function destroy() {
    router.delete(route('products.destroy', props.product.id));
}
</script>

<template>
    <Head :title="`Edit ${product.name}`" />

    <AppLayout :back-route="route('products.index')" back-label="Kembali ke Produk">
        <template #header>Edit Produk</template>

        <ProductForm
            :product="product"
            :categories="categories"
            :submit-url="route('products.update', product.id)"
            :cancel-url="route('products.index')"
            submit-label="Kemaskini Produk"
        />

        <!-- ═══ DANGER ZONE ═══ -->
        <div class="mx-auto max-w-6xl px-4 pb-10 md:px-6">
            <div class="rounded-2xl border border-red-100 bg-red-50/50 p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-red-800">Padam produk</h3>
                        <p class="mt-0.5 text-xs text-red-600">
                            Tindakan ini kekal. Pesanan lampau yang mengandungi produk ini tidak terjejas.
                        </p>
                    </div>

                    <div class="shrink-0">
                        <button
                            v-if="!confirmingDelete"
                            type="button"
                            class="rounded-xl border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50"
                            @click="confirmingDelete = true"
                        >
                            Padam
                        </button>

                        <div v-else class="flex items-center gap-2">
                            <span class="text-xs font-semibold text-red-700">Anda pasti?</span>
                            <button
                                type="button"
                                class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-red-700"
                                @click="destroy"
                            >
                                Ya, padam
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"
                                @click="confirmingDelete = false"
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <p class="mt-4 text-center text-xs text-gray-400">
                Lihat di kedai:
                <Link :href="route('mall.show', product.id)" class="font-semibold text-amber-600 hover:underline">
                    {{ product.name }}
                </Link>
            </p>
        </div>
    </AppLayout>
</template>

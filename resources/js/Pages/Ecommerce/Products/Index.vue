<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    products: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();

const isAdmin = computed(() => {
    return page.props.auth.user?.roles?.some(role => ['Admin', 'Superadmin'].includes(role));
});

const search = ref(props.filters?.search || '');
const category_id = ref(props.filters?.category_id || '');
const sort = ref(props.filters?.sort || 'latest');

let debounceTimeout;
watch([search, category_id, sort], ([newSearch, newCategoryId, newSort]) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        router.get(
            route('products.index'),
            { search: newSearch, category_id: newCategoryId, sort: newSort },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }, 300);
});
</script>

<template>
    <Head title="Produk" />

    <AppLayout>
        <template #header>Produk</template>

        <div class="mx-auto max-w-7xl px-4 py-8 md:px-6 md:py-10">
            <div v-if="page.props.flash?.success" class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-8">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Senarai Produk</h2>
                    <p class="mt-1 text-sm text-gray-500">Terokai produk yang ditawarkan dalam modul e-commerce.</p>
                </div>
                <Link v-if="isAdmin" :href="route('products.create')" class="inline-flex items-center justify-center rounded-2xl bg-gray-900 px-5 py-2.5 text-sm font-medium text-white shadow-sm hover:bg-gray-800 transition-colors focus:ring-2 focus:ring-gray-900 focus:ring-offset-2 relative z-10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah Produk
                </Link>
            </div>

            <!-- Filter & Search Bar -->
            <div class="mb-8 flex flex-col sm:flex-row gap-4">
                <div class="relative flex-1">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                        <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                           <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input 
                        v-model="search"
                        type="search" 
                        class="block w-full rounded-2xl border-0 py-3 pl-11 pr-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-gray-900 sm:text-sm sm:leading-6 bg-white transition-all" 
                        placeholder="Cari produk berdasarkan nama..." 
                    />
                </div>
                
                <div class="sm:w-64">
                    <select 
                        v-model="category_id"
                        class="block w-full rounded-2xl border-0 py-3 pl-4 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-inset focus:ring-gray-900 sm:text-sm sm:leading-6 bg-white transition-all cursor-pointer"
                    >
                        <option value="">Semua Kategori</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <div class="sm:w-56">
                    <select 
                        v-model="sort"
                        class="block w-full rounded-2xl border-0 py-3 pl-4 pr-10 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-inset focus:ring-gray-900 sm:text-sm sm:leading-6 bg-white transition-all cursor-pointer"
                    >
                        <option value="latest">Terbaru</option>
                        <option value="oldest">Paling Lama</option>
                        <option value="price_low">Harga: Rendah ke Tinggi</option>
                        <option value="price_high">Harga: Tinggi ke Rendah</option>
                    </select>
                </div>
            </div>

            <!-- Grid Layout -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <div v-for="product in products.data" :key="product.id" class="group relative flex flex-col overflow-hidden rounded-3xl bg-white shadow-sm ring-1 ring-gray-200 transition-all hover:shadow-md hover:ring-gray-300">
                    <!-- Image Container -->
                    <div class="aspect-h-1 aspect-w-1 sm:aspect-none w-full bg-gray-100 h-64 sm:h-56 relative overflow-hidden group-hover:opacity-95 transition-opacity">
                        <img 
                            v-if="product.image" 
                            :src="'/storage/' + product.image" 
                            :alt="product.name" 
                            class="h-full w-full object-cover object-center sm:h-full sm:w-full"
                        />
                        <!-- Dummy Image Data for missing image -->
                        <img 
                            v-else 
                            :src="'https://picsum.photos/seed/' + product.id + '/400/400'" 
                            :alt="product.name" 
                            class="h-full w-full object-cover object-center sm:h-full sm:w-full"
                        />
                        
                        <!-- Stock Badges -->
                        <div v-if="product.stock <= 0" class="absolute top-3 right-3 bg-red-50 text-red-700 px-2.5 py-1 text-xs font-bold rounded-full shadow-sm ring-1 ring-inset ring-red-600/20 z-10">
                            Kehabisan Stok
                        </div>
                        <div v-else-if="product.stock > 0 && product.stock <= 5" class="absolute top-3 right-3 bg-amber-50 text-amber-700 px-2.5 py-1 text-xs font-bold rounded-full shadow-sm ring-1 ring-inset ring-amber-600/20 z-10">
                            Stok Terhad
                        </div>
                    </div>

                    <!-- Content Details -->
                    <div class="flex flex-1 flex-col space-y-2.5 p-5">
                        <div class="flex items-center justify-between text-xs text-gray-500">
                            <span class="font-semibold text-indigo-700 bg-indigo-50 px-2 py-1 rounded-lg ring-1 ring-inset ring-indigo-700/10">{{ product.category?.name ?? 'Umum' }}</span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900 leading-tight line-clamp-2 min-h-[2.5rem] mt-1">
                            <Link :href="route('products.show', product.id)">
                                <span aria-hidden="true" class="absolute inset-0 z-0"></span>
                                {{ product.name }}
                            </Link>
                        </h3>
                        <div class="flex flex-1 flex-col justify-end mt-4 pt-2">
                            <p class="text-xl font-black text-gray-900">RM {{ Number(product.price ?? 0).toFixed(2) }}</p>
                        </div>
                        
                        <div v-if="isAdmin" class="pt-4 mt-auto border-t border-gray-100 flex items-center justify-end relative z-10">
                            <Link :href="route('products.edit', product.id)" class="inline-flex items-center text-sm font-semibold text-indigo-700 hover:text-indigo-800 bg-indigo-50 hover:bg-indigo-100 px-3.5 py-1.5 rounded-xl transition-colors">
                                Edit Produk
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="!products.data?.length" class="mt-8 text-center rounded-3xl border border-dashed border-gray-300 py-16 px-6 bg-white">
                 <svg class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m14 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m14 0l-4 4m-4-4l-4 4"></path>
                </svg>
                <h3 class="mt-4 text-sm font-bold text-gray-900">Tiada produk</h3>
                <p class="mt-1 text-sm text-gray-500">Belum ada sebarang produk ditambah untuk masa ini.</p>
            </div>

            <!-- Pagination -->
            <div v-if="products.links?.length > 3" class="mt-12 mb-4 flex items-center justify-center">
                <div class="flex flex-wrap items-center justify-center gap-2">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url || ''"
                        class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 focus:ring-gray-900 focus:ring-offset-2"
                        :class="[
                            link.active ? 'bg-gray-900 text-white shadow-md' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50 hover:text-gray-900',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>


<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCart } from '@/composables/useCart';

const cart = useCart();

function addToCart(product, event) {
    event.preventDefault();
    event.stopPropagation();
    cart.add(product, 1);
}

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
    isMall: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();

const isAdmin = computed(() => {
    return page.props.auth.user?.roles?.some(role => ['Admin', 'Superadmin'].includes(role));
});

const search = ref(props.filters?.search || '');
const category_id = ref(props.filters?.category_id || '');
const sort = ref(props.filters?.sort || 'latest');

function listRoute() {
    return props.isMall ? 'mall.index' : 'products.index';
}

let debounceTimeout;
watch([search, category_id, sort], ([newSearch, newCategoryId, newSort]) => {
    clearTimeout(debounceTimeout);
    debounceTimeout = setTimeout(() => {
        router.get(
            route(listRoute()),
            { search: newSearch, category_id: newCategoryId, sort: newSort },
            { preserveState: true, replace: true, preserveScroll: true }
        );
    }, 300);
});

function selectCategory(id) {
    category_id.value = id === category_id.value ? '' : id;
}

function selectSort(value) {
    sort.value = value;
}

// Resolve an image path to a usable URL; falls back to a stable placeholder.
function productImageUrl(path, seed = 'mywap') {
    if (!path) {
        return `https://picsum.photos/seed/${encodeURIComponent(String(seed))}/600/600`;
    }
    if (/^https?:\/\//.test(path)) return path;
    if (path.startsWith('/storage/')) return path;
    return '/storage/' + path.replace(/^\/+/, '');
}

const showResults = computed(() => props.products.data.length > 0);

const productHref = (id) => route(props.isMall ? 'mall.show' : 'products.show', id);
</script>

<template>
    <Head :title="isMall ? 'MyWAP Mall' : 'Senarai Produk'" />

    <AppLayout>
        <template #header>{{ isMall ? 'MyWAP Mall' : 'Produk' }}</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 md:py-8">
            <div v-if="page.props.flash?.success" class="mb-6 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <!-- ═══ HERO BANNER ═══ -->
            <div
                class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-orange-500 to-amber-600 px-6 py-8 md:px-10 md:py-10 shadow-lg shadow-amber-500/10"
            >
                <!-- decorative circles -->
                <div class="pointer-events-none absolute -right-10 -top-10 h-48 w-48 rounded-full bg-white/10"></div>
                <div class="pointer-events-none absolute -bottom-14 right-24 h-40 w-40 rounded-full bg-white/10"></div>

                <div class="relative flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-widest text-amber-100">myWAP Mall</p>
                        <h1 class="mt-1.5 text-2xl font-black tracking-tight text-white md:text-3xl">
                            {{ isMall ? 'Beli-belah dengan harga istimewa ahli' : 'Urus Katalog Produk' }}
                        </h1>
                        <p class="mt-2 max-w-xl text-sm text-amber-50/90">
                            {{ isMall
                                ? 'Teroka produk organisasi, tambah ke bakul, dan nikmati harga ahli.'
                                : 'Tambah, edit dan uruskan produk yang dipaparkan di MyWAP Mall.' }}
                        </p>
                    </div>
                    <div v-if="isAdmin" class="shrink-0">
                        <Link
                            :href="route('products.create')"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-3 text-sm font-bold text-amber-700 shadow-sm transition hover:bg-amber-50 hover:-translate-y-0.5"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah Produk
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ═══ SEARCH & FILTERS ═══ -->
            <div class="mb-6 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <!-- Search -->
                    <div class="relative flex-1">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4">
                            <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <input
                            v-model="search"
                            type="search"
                            class="block w-full rounded-xl border-0 bg-gray-50 py-2.5 pl-11 pr-4 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:bg-white focus:ring-2 focus:ring-inset focus:ring-amber-500"
                            placeholder="Cari produk berdasarkan nama..."
                        />
                    </div>

                    <!-- Sort -->
                    <div class="relative lg:w-60">
                        <select
                            v-model="sort"
                            @change="selectSort(sort)"
                            class="block w-full cursor-pointer appearance-none rounded-xl border-0 bg-gray-50 py-2.5 pl-4 pr-10 text-sm text-gray-700 ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-inset focus:ring-amber-500"
                        >
                            <option value="latest">Terbaru</option>
                            <option value="oldest">Paling Lama</option>
                            <option value="price_low">Harga: Rendah → Tinggi</option>
                            <option value="price_high">Harga: Tinggi → Rendah</option>
                        </select>
                    </div>
                </div>

                <!-- Category chips -->
                <div class="mt-3 flex items-center gap-2 overflow-x-auto pb-1">
                    <button
                        @click="selectCategory('')"
                        class="shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                        :class="category_id === '' ? 'bg-gray-900 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >
                        Semua
                    </button>
                    <button
                        v-for="category in categories"
                        :key="category.id"
                        @click="selectCategory(category.id)"
                        class="shrink-0 rounded-full px-4 py-1.5 text-xs font-semibold transition"
                        :class="String(category_id) === String(category.id) ? 'bg-amber-500 text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >
                        {{ category.name }}
                    </button>
                </div>
            </div>

            <!-- ═══ RESULTS COUNT ═══ -->
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    <span class="font-bold text-gray-800">{{ products.total ?? products.data.length }}</span> produk ditemui
                </p>
            </div>

            <!-- ═══ PRODUCT GRID ═══ -->
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                <article
                    v-for="product in products.data"
                    :key="product.id"
                    class="group relative flex flex-col overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-gray-200/50"
                >
                    <!-- Image -->
                    <Link :href="productHref(product.id)" class="relative block aspect-square overflow-hidden bg-gray-100">
                        <img
                            :src="productImageUrl(product.image, product.id)"
                            :alt="product.name"
                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        />
                        <!-- Member price badge -->
                        <span
                            v-if="product.member_price != null && Number(product.member_price) < Number(product.price)"
                            class="absolute left-2 top-2 rounded-lg bg-amber-500 px-2 py-0.5 text-[10px] font-bold text-white shadow-sm"
                        >JIMAT {{ (((Number(product.price) - Number(product.member_price)) / Number(product.price)) * 100).toFixed(0) }}%</span>
                        <!-- Out of stock overlay -->
                        <div v-if="product.stock <= 0" class="absolute inset-0 flex items-center justify-center bg-white/70 backdrop-blur-[1px]">
                            <span class="rounded-full bg-gray-900 px-3 py-1 text-xs font-bold text-white">Kehabisan Stok</span>
                        </div>
                    </Link>

                    <!-- Body -->
                    <div class="flex flex-1 flex-col p-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ product.category?.name ?? 'Umum' }}</p>
                        <Link :href="productHref(product.id)" class="mt-1 line-clamp-2 text-sm font-semibold leading-snug text-gray-900 hover:text-amber-600">
                            {{ product.name }}
                        </Link>

                        <div class="mt-auto pt-2.5">
                            <div class="flex items-baseline gap-1.5">
                                <span class="text-base font-black text-gray-900">RM {{ Number(product.price ?? 0).toFixed(2) }}</span>
                                <span v-if="product.member_price != null" class="text-[10px] font-semibold text-amber-600">Ahli RM {{ Number(product.member_price).toFixed(2) }}</span>
                            </div>

                            <div class="mt-2.5 flex items-center gap-2">
                                <button
                                    v-if="!isAdmin"
                                    @click="addToCart(product, $event)"
                                    :disabled="product.stock <= 0"
                                    class="relative z-10 flex-1 rounded-xl bg-gray-900 px-2 py-2 text-xs font-semibold text-white transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:bg-gray-200 disabled:text-gray-400"
                                >
                                    + Bakul
                                </button>
                                <Link
                                    v-if="isAdmin"
                                    :href="route('products.edit', product.id)"
                                    class="relative z-10 flex-1 rounded-xl bg-indigo-50 px-2 py-2 text-center text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100"
                                >
                                    Edit
                                </Link>
                                <Link
                                    :href="productHref(product.id)"
                                    class="relative z-10 rounded-xl border border-gray-200 px-2.5 py-2 text-xs font-semibold text-gray-600 transition hover:border-amber-300 hover:text-amber-600"
                                    title="Lihat butiran"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                                    </svg>
                                </Link>
                            </div>
                        </div>
                    </div>
                </article>
            </div>

            <!-- ═══ EMPTY STATE ═══ -->
            <div v-if="!showResults" class="mt-8 rounded-3xl border-2 border-dashed border-gray-200 bg-white px-6 py-16 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50">
                    <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m14 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m14 0l-4 4m-4-4l-4 4" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-gray-900">Tiada produk ditemui</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-gray-500">
                    {{ search || category_id ? 'Cuba tukar carian atau kategori anda.' : 'Belum ada sebarang produk ditambah untuk masa ini.' }}
                </p>
            </div>

            <!-- ═══ PAGINATION ═══ -->
            <div v-if="products.links?.length > 3" class="mt-10 flex items-center justify-center">
                <div class="flex flex-wrap items-center justify-center gap-1.5">
                    <Link
                        v-for="link in products.links"
                        :key="link.label"
                        :href="link.url || ''"
                        class="flex h-9 min-w-[36px] items-center justify-center rounded-xl px-3 text-sm font-semibold transition"
                        :class="[
                            link.active ? 'bg-amber-500 text-white shadow-md' : 'bg-white border border-gray-200 text-gray-700 hover:border-amber-300 hover:text-amber-600',
                            !link.url ? 'pointer-events-none opacity-40' : '',
                        ]"
                        v-html="link.label"
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

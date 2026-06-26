<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    product: { type: Object, required: true },
    relatedProducts: { type: Array, default: () => [] },
});

const page = usePage();

const isAdmin = computed(() => {
    return page.props.auth.user?.roles?.some(role => ['Admin', 'Superadmin'].includes(role));
});

const showShipping = ref(false);
const quantity = ref(1);

// ─── Image Gallery ──────────────────────────────────────────────────────────

const allImages = computed(() => {
    const images = [];
    if (props.product.image) images.push(props.product.image);
    if (props.product.images?.length) images.push(...props.product.images);
    return images;
});

const selectedImageIndex = ref(0);
const selectedImage = computed(() => {
    if (!allImages.value.length) return null;
    return allImages.value[selectedImageIndex.value] || allImages.value[0];
});

function selectImage(index) {
    selectedImageIndex.value = index;
}

// ─── Variations ─────────────────────────────────────────────────────────────

const selectedOptions = ref({});

props.product.variations?.forEach(v => {
    selectedOptions.value[v.id] = null;
});

const selectedVariationDetails = computed(() => {
    const details = {};
    for (const [varId, optId] of Object.entries(selectedOptions.value)) {
        if (optId) {
            const variation = props.product.variations?.find(v => v.id === parseInt(varId));
            const option = variation?.options?.find(o => o.id === parseInt(optId));
            if (option) details[varId] = { variation, option };
        }
    }
    return details;
});

const basePrice = computed(() => parseFloat(props.product.price) || 0);

const adjustmentsTotal = computed(() => {
    let total = 0;
    for (const details of Object.values(selectedVariationDetails.value)) {
        total += parseFloat(details.option.price_adjustment) || 0;
    }
    return total;
});

const displayPrice = computed(() => basePrice.value + adjustmentsTotal.value);
const postageCost = computed(() => parseFloat(props.product.postage_cost) || 0);
const totalPayable = computed(() => (displayPrice.value * quantity.value) + postageCost.value);

const availableStock = computed(() => {
    for (const details of Object.values(selectedVariationDetails.value)) {
        if (details.option.stock !== null && details.option.stock !== undefined) {
            return parseInt(details.option.stock);
        }
    }
    return parseInt(props.product.stock) || 0;
});

const allRequiredSelected = computed(() => {
    if (!props.product.variations?.length) return true;
    return props.product.variations.every(v => {
        if (!v.required) return true;
        return selectedOptions.value[v.id] != null;
    });
});

const canBuy = computed(() => {
    if (!allRequiredSelected.value) return false;
    if (availableStock.value < 1) return false;
    return true;
});

function selectOption(variationId, optionId) {
    selectedOptions.value[variationId] = String(optionId);
    if (quantity.value > availableStock.value) {
        quantity.value = Math.max(1, availableStock.value);
    }
}

// ─── Shipping ───────────────────────────────────────────────────────────────

const user = computed(() => page.props.auth.user);

const shippingForm = useForm({
    shipping_name: user.value?.name ?? '',
    shipping_address: user.value?.address_1 ?? '',
    shipping_postcode: user.value?.postcode ?? '',
    shipping_phone: user.value?.phone ?? '',
});

// ─── Order Form ─────────────────────────────────────────────────────────────

const buyForm = useForm({
    products: [
        {
            id: props.product.id,
            quantity: 1,
            variation_option_id: null,
        },
    ],
});

function buyNow() {
    const snapshot = {};
    const optionIds = [];
    for (const details of Object.values(selectedVariationDetails.value)) {
        snapshot[details.variation.name] = details.option.name;
        optionIds.push(details.option.id);
    }

    buyForm.products[0].quantity = quantity.value;
    buyForm.products[0].variation_option_id = optionIds[0] ?? null;
    buyForm.products[0].variation_snapshot = Object.keys(snapshot).length ? JSON.stringify(snapshot) : null;

    buyForm
        .transform((data) => ({
            ...data,
            shipping_name: shippingForm.shipping_name,
            shipping_address: shippingForm.shipping_address,
            shipping_postcode: shippingForm.shipping_postcode,
            shipping_phone: shippingForm.shipping_phone,
        }))
        .post(route('orders.store'), {
            preserveScroll: true,
        });
}

// Clamp quantity on stock change
watch(availableStock, (stock) => {
    if (quantity.value > stock) {
        quantity.value = Math.max(1, stock);
    }
});
</script>

<template>
    <Head :title="product.name" />

    <AppLayout>
        <template #header>{{ product.name }}</template>

        <div class="mx-auto max-w-6xl px-4 py-4 md:px-6 md:py-6">
            <!-- Flash messages -->
            <div v-if="page.props.errors?.error" class="mb-4 rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ page.props.errors.error }}
            </div>
            <div v-if="page.props.flash?.success" class="mb-4 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <!-- Left: Image Gallery -->
                <div class="overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-sm">
                    <!-- Main Image -->
                    <div class="aspect-[4/3] bg-gray-50 relative overflow-hidden group">
                        <img
                            v-if="selectedImage"
                            :src="'/storage/' + selectedImage"
                            :alt="product.name"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        >
                        <div v-else class="flex h-full items-center justify-center text-sm text-gray-400">
                            Tiada gambar
                        </div>
                    </div>
                    <!-- Thumbnails -->
                    <div v-if="allImages.length > 1" class="flex gap-2 border-t border-gray-100 p-3 overflow-x-auto">
                        <button
                            v-for="(img, i) in allImages"
                            :key="i"
                            @click="selectImage(i)"
                            class="h-16 w-16 flex-shrink-0 overflow-hidden rounded-xl border-2 transition-all"
                            :class="selectedImageIndex === i ? 'border-gray-900 ring-1 ring-gray-900' : 'border-gray-200 hover:border-gray-400'"
                        >
                            <img :src="'/storage/' + img" alt="" class="h-full w-full object-cover">
                        </button>
                    </div>
                </div>

                <!-- Right: Product Details -->
                <div class="flex flex-col">
                    <!-- Category -->
                    <div class="mb-2">
                        <span class="inline-block rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600">
                            {{ product.category?.name }}
                        </span>
                        <span
                            v-if="product.status"
                            class="ml-2 inline-block rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700"
                        >Aktif</span>
                        <span v-else class="ml-2 inline-block rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">Tidak Aktif</span>
                    </div>

                    <!-- Name -->
                    <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ product.name }}</h1>

                    <!-- Price -->
                    <div class="mt-3 flex items-baseline gap-3">
                        <span class="text-3xl font-bold text-gray-900">RM {{ displayPrice.toFixed(2) }}</span>
                        <span v-if="adjustmentsTotal > 0" class="text-sm text-gray-500 line-through">RM {{ basePrice.toFixed(2) }}</span>
                    </div>

                    <!-- Postage -->
                    <div v-if="postageCost > 0" class="mt-1 flex items-center gap-1.5 text-sm text-gray-500">
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                        </svg>
                        Kos Pos: RM {{ postageCost.toFixed(2) }}
                    </div>
                    <div v-else class="mt-1 text-sm text-emerald-600 font-semibold">Percuma Pos</div>

                    <!-- Variations -->
                    <div v-for="variation in product.variations" :key="variation.id" class="mt-5">
                        <div class="mb-2 flex items-center gap-1">
                            <span class="text-sm font-semibold text-gray-700">{{ variation.name }}</span>
                            <span v-if="variation.required" class="text-xs text-red-500">*</span>
                        </div>

                        <!-- Text pills -->
                        <div v-if="variation.type === 'select'" class="flex flex-wrap gap-2">
                            <button
                                v-for="option in variation.options"
                                :key="option.id"
                                @click="selectOption(variation.id, option.id)"
                                class="rounded-xl border-2 px-4 py-2 text-sm font-semibold transition-all"
                                :class="selectedOptions[variation.id] === String(option.id)
                                    ? 'border-gray-900 bg-gray-900 text-white shadow-sm'
                                    : 'border-gray-200 bg-white text-gray-700 hover:border-gray-400'"
                            >
                                {{ option.name }}
                                <span v-if="option.price_adjustment > 0" class="ml-1 text-[10px] opacity-75">
                                    +RM{{ parseFloat(option.price_adjustment).toFixed(2) }}
                                </span>
                                <span v-if="option.price_adjustment < 0" class="ml-1 text-[10px] opacity-75">
                                    -RM{{ Math.abs(parseFloat(option.price_adjustment)).toFixed(2) }}
                                </span>
                            </button>
                        </div>

                        <!-- Color swatches -->
                        <div v-if="variation.type === 'color'" class="flex flex-wrap gap-3">
                            <button
                                v-for="option in variation.options"
                                :key="option.id"
                                @click="selectOption(variation.id, option.id)"
                                class="group relative flex flex-col items-center gap-1"
                            >
                                <span
                                    class="block h-10 w-10 rounded-full border-2 transition-all"
                                    :class="selectedOptions[variation.id] === String(option.id)
                                        ? 'border-gray-900 ring-2 ring-gray-900 ring-offset-2'
                                        : 'border-gray-300 hover:border-gray-500'"
                                    :style="{ backgroundColor: option.hex_color || '#ccc' }"
                                ></span>
                                <span class="text-[10px] font-medium text-gray-500 group-hover:text-gray-800"
                                    :class="{ 'text-gray-900 font-semibold': selectedOptions[variation.id] === String(option.id) }"
                                >{{ option.name }}</span>
                            </button>
                        </div>

                        <!-- Stock indicator per variation option -->
                        <div v-if="selectedOptions[variation.id]" class="mt-1">
                            <template v-for="(details, key) in selectedVariationDetails" :key="key">
                                <div v-if="parseInt(key) === variation.id">
                                    <span v-if="details.option.stock !== null && details.option.stock !== undefined">
                                        <span v-if="details.option.stock > 10" class="text-xs text-emerald-600">Stok: {{ details.option.stock }}</span>
                                        <span v-else-if="details.option.stock > 0" class="text-xs text-amber-600">Stok tinggal {{ details.option.stock }} lagi!</span>
                                        <span v-else class="text-xs text-red-600">Kehabisan stok</span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Quantity & Buy -->
                    <div class="mt-6 flex flex-col gap-4">
                        <!-- Quantity -->
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-semibold text-gray-700">Kuantiti</span>
                            <div class="flex items-center rounded-xl border border-gray-200">
                                <button
                                    @click="quantity = Math.max(1, quantity - 1)"
                                    :disabled="quantity <= 1"
                                    class="flex h-9 w-9 items-center justify-center text-gray-600 hover:bg-gray-50 disabled:opacity-30 rounded-l-xl"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
                                </button>
                                <input
                                    v-model="quantity"
                                    type="number"
                                    min="1"
                                    :max="availableStock"
                                    class="h-9 w-14 border-x border-gray-200 text-center text-sm font-semibold focus:ring-0"
                                >
                                <button
                                    @click="quantity = Math.min(availableStock, quantity + 1)"
                                    :disabled="quantity >= availableStock"
                                    class="flex h-9 w-9 items-center justify-center text-gray-600 hover:bg-gray-50 disabled:opacity-30 rounded-r-xl"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                </button>
                            </div>

                            <!-- Stock display -->
                            <span v-if="availableStock > 0 && availableStock <= 10" class="text-xs text-amber-600 font-semibold">
                                Tinggal {{ availableStock }} unit!
                            </span>
                        </div>

                        <!-- Buy Button -->
                        <button
                            type="button"
                            @click="buyNow"
                            :disabled="!canBuy || buyForm.processing"
                            class="inline-flex items-center justify-center rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            <svg v-if="!buyForm.processing" class="mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                            </svg>
                            <svg v-else class="mr-2 h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>

                            <span v-if="buyForm.processing">Memproses...</span>
                            <span v-else>
                                Beli Sekarang
                                <span class="ml-1 text-emerald-200">RM {{ totalPayable.toFixed(2) }}</span>
                            </span>
                        </button>

                        <p v-if="!allRequiredSelected" class="text-xs text-amber-600">Sila pilih semua variasi yang wajib.</p>
                        <p v-else-if="availableStock < 1" class="text-xs text-red-600">Stok tidak mencukupi.</p>
                    </div>

                    <!-- Shipping Address (collapsible) -->
                    <div class="mt-5 border-t border-gray-100 pt-4">
                        <button
                            @click="showShipping = !showShipping"
                            class="flex items-center gap-2 text-sm font-semibold text-gray-700 hover:text-gray-900"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            Alamat Penghantaran
                            <svg
                                class="h-3.5 w-3.5 transition-transform"
                                :class="{ 'rotate-180': showShipping }"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div v-if="showShipping" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-400">Nama Penerima</label>
                                <input v-model="shippingForm.shipping_name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-xs font-semibold text-gray-400">Alamat</label>
                                <textarea v-model="shippingForm.shipping_address" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-400">Poskod</label>
                                <input v-model="shippingForm.shipping_postcode" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-400">No. Telefon</label>
                                <input v-model="shippingForm.shipping_phone" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div v-if="product.description" class="mt-6 rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="mb-3 text-sm font-bold text-gray-900">Penerangan Produk</h3>
                <p class="text-sm leading-relaxed text-gray-600 whitespace-pre-line">{{ product.description }}</p>
            </div>

            <!-- Related Products -->
            <div v-if="relatedProducts.length" class="mt-10">
                <h3 class="mb-4 text-lg font-bold text-gray-900">Produk Lain dalam Kategori Ini</h3>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    <Link
                        v-for="rp in relatedProducts"
                        :key="rp.id"
                        :href="route('products.show', rp.id)"
                        class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition-all hover:shadow-md"
                    >
                        <div class="aspect-[4/3] bg-gray-100 overflow-hidden">
                            <img
                                v-if="rp.image"
                                :src="'/storage/' + rp.image"
                                :alt="rp.name"
                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                            >
                            <div v-else class="flex h-full items-center justify-center text-xs text-gray-400">Tiada gambar</div>
                        </div>
                        <div class="p-3">
                            <h4 class="text-sm font-semibold text-gray-900 truncate">{{ rp.name }}</h4>
                            <p class="mt-1 text-sm font-bold text-gray-900">RM {{ parseFloat(rp.price).toFixed(2) }}</p>
                        </div>
                    </Link>
                </div>
            </div>

            <!-- Admin Edit -->
            <div class="mt-6 flex flex-wrap items-center justify-between gap-2">
                <Link :href="route('products.index')" class="text-sm font-semibold text-gray-700 hover:underline">
                    ← Kembali
                </Link>
                <Link
                    v-if="isAdmin"
                    :href="route('products.edit', product.id)"
                    class="inline-flex items-center gap-1.5 rounded-2xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Produk
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

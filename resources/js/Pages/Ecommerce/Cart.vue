<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCart } from '@/composables/useCart';
import { productImageUrl, onImageError, formatPrice } from '@/composables/useProductImage';
import { computed, ref } from 'vue';

const cart = useCart();
const page = usePage();

const user = computed(() => page.props.auth?.user ?? null);

// Must mirror OrderController::checkout(), which grants the member price only
// to users holding the Member role. Anything looser shows a price we then
// refuse to honour at checkout.
const isMember = computed(() => user.value?.roles?.includes('Member') ?? false);

const shippingName = ref(user.value?.name ?? '');
const shippingPhone = ref(user.value?.phone ?? '');
const shippingAddress = ref(user.value?.address_1 ?? '');
const shippingPostcode = ref(user.value?.postcode ?? '');

// Guards against double-submitting an order on a slow connection.
const processing = ref(false);
const clientErrors = ref({});

function itemPrice(item) {
    return isMember.value && item.member_price != null ? Number(item.member_price) : Number(item.price);
}

const subtotal = computed(() =>
    cart.items.value.reduce((sum, i) => sum + itemPrice(i) * i.quantity, 0)
);

const memberSavings = computed(() =>
    cart.items.value.reduce((sum, i) => {
        if (i.member_price == null) return sum;
        const saving = (Number(i.price) - Number(i.member_price)) * i.quantity;
        return sum + Math.max(0, saving);
    }, 0)
);

const grandTotal = computed(() => subtotal.value + cart.combinedPostage.value);

function validate() {
    const errors = {};
    if (!shippingName.value.trim()) errors.shipping_name = 'Nama penerima diperlukan.';
    if (!shippingPhone.value.trim()) errors.shipping_phone = 'No telefon diperlukan.';
    if (!shippingAddress.value.trim()) errors.shipping_address = 'Alamat penghantaran diperlukan.';
    clientErrors.value = errors;
    return Object.keys(errors).length === 0;
}

function checkout() {
    if (processing.value) return;
    if (!validate()) {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }

    const products = cart.items.value.map((i) => ({
        id: i.id,
        quantity: i.quantity,
        variation_option_id: i.variation_option_id || null,
        variation_snapshot: i.variation_snapshot || null,
    }));

    processing.value = true;

    router.post(
        route('mall.checkout'),
        {
            products,
            shipping_name: shippingName.value || null,
            shipping_phone: shippingPhone.value || null,
            shipping_address: shippingAddress.value || null,
            shipping_postcode: shippingPostcode.value || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => cart.clear(),
            onFinish: () => (processing.value = false),
        }
    );
}

const inputClass =
    'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-amber-500';
</script>

<template>
    <Head title="Bakul Beli-belah" />

    <AppLayout :back-route="route('mall.index')" back-label="MyWAP Mall">
        <template #header>Bakul Beli-belah</template>

        <div class="mx-auto max-w-5xl px-4 py-6 md:px-6">
            <!-- Server error -->
            <div v-if="page.props.errors?.error" class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ page.props.errors.error }}
            </div>

            <!-- ═══ EMPTY ═══ -->
            <div v-if="cart.count.value === 0" class="rounded-3xl border-2 border-dashed border-gray-200 bg-white px-6 py-20 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-50">
                    <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z" />
                    </svg>
                </div>
                <p class="text-base font-bold text-gray-900">Bakul anda kosong</p>
                <p class="mt-1 text-sm text-gray-500">Teroka produk dan tambah ke bakul untuk mula membeli.</p>
                <Link :href="route('mall.index')" class="mt-5 inline-block rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-bold text-white transition hover:bg-amber-600">
                    Mula beli-belah
                </Link>
            </div>

            <!-- ═══ CART ═══ -->
            <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Items + shipping -->
                <div class="space-y-5 lg:col-span-2">
                    <section class="overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm">
                        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                            <h2 class="text-sm font-bold text-gray-900">{{ cart.count.value }} item dalam bakul</h2>
                            <button class="text-xs font-semibold text-gray-400 transition hover:text-red-600" @click="cart.clear()">
                                Kosongkan
                            </button>
                        </div>

                        <div
                            v-for="item in cart.items.value"
                            :key="item.key"
                            class="flex items-center gap-4 border-b border-gray-50 p-4 last:border-b-0"
                        >
                            <Link :href="route('mall.show', item.id)" class="h-20 w-20 shrink-0 overflow-hidden rounded-xl bg-gray-50">
                                <img :src="productImageUrl(item.image)" :alt="item.name" class="h-full w-full object-cover" @error="onImageError" />
                            </Link>

                            <div class="min-w-0 flex-1">
                                <Link :href="route('mall.show', item.id)" class="line-clamp-2 text-sm font-bold text-gray-900 hover:text-amber-600">
                                    {{ item.name }}
                                </Link>
                                <p v-if="item.variation" class="mt-0.5 text-xs text-gray-400">{{ item.variation }}</p>
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ formatPrice(itemPrice(item)) }}
                                    <span v-if="isMember && item.member_price != null" class="ml-1 text-xs font-semibold text-amber-600">harga ahli</span>
                                </p>

                                <!-- Quantity -->
                                <div class="mt-2 flex items-center gap-2">
                                    <div class="flex items-center rounded-lg ring-1 ring-inset ring-gray-200">
                                        <button
                                            class="flex h-7 w-7 items-center justify-center rounded-l-lg text-gray-500 transition hover:bg-gray-50 disabled:opacity-30"
                                            :disabled="item.quantity <= 1"
                                            aria-label="Kurangkan kuantiti"
                                            @click="cart.updateQuantity(item.key, item.quantity - 1)"
                                        >−</button>
                                        <span class="w-9 text-center text-sm font-semibold">{{ item.quantity }}</span>
                                        <button
                                            class="flex h-7 w-7 items-center justify-center rounded-r-lg text-gray-500 transition hover:bg-gray-50 disabled:opacity-30"
                                            :disabled="item.quantity >= (item.stock || 999)"
                                            aria-label="Tambah kuantiti"
                                            @click="cart.updateQuantity(item.key, item.quantity + 1)"
                                        >+</button>
                                    </div>
                                    <span v-if="item.stock && item.quantity >= item.stock" class="text-[11px] font-semibold text-amber-600">
                                        Stok maksimum
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-col items-end gap-2">
                                <span class="text-sm font-black text-gray-900">
                                    {{ formatPrice(itemPrice(item) * item.quantity) }}
                                </span>
                                <button
                                    class="rounded-lg p-1.5 text-gray-300 transition hover:bg-red-50 hover:text-red-600"
                                    aria-label="Buang item"
                                    @click="cart.remove(item.key)"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </section>

                    <!-- Shipping -->
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="mb-1 text-sm font-bold text-gray-900">Maklumat Penghantaran</h2>
                        <p class="mb-4 text-xs text-gray-500">Kami perlukan ini untuk menghantar pesanan anda.</p>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-600">Nama penerima <span class="text-red-500">*</span></label>
                                <input v-model="shippingName" type="text" :class="inputClass" placeholder="Nama penuh" />
                                <p v-if="clientErrors.shipping_name || page.props.errors?.shipping_name" class="mt-1 text-xs font-medium text-red-600">
                                    {{ clientErrors.shipping_name || page.props.errors.shipping_name }}
                                </p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-600">No telefon <span class="text-red-500">*</span></label>
                                <input v-model="shippingPhone" type="tel" :class="inputClass" placeholder="01X-XXXXXXX" />
                                <p v-if="clientErrors.shipping_phone || page.props.errors?.shipping_phone" class="mt-1 text-xs font-medium text-red-600">
                                    {{ clientErrors.shipping_phone || page.props.errors.shipping_phone }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="mb-1.5 block text-xs font-semibold text-gray-600">Alamat penghantaran <span class="text-red-500">*</span></label>
                                <textarea v-model="shippingAddress" rows="3" :class="inputClass" placeholder="No rumah, jalan, taman, bandar, negeri"></textarea>
                                <p v-if="clientErrors.shipping_address || page.props.errors?.shipping_address" class="mt-1 text-xs font-medium text-red-600">
                                    {{ clientErrors.shipping_address || page.props.errors.shipping_address }}
                                </p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-xs font-semibold text-gray-600">Poskod</label>
                                <input v-model="shippingPostcode" type="text" maxlength="10" :class="inputClass" placeholder="43000" />
                            </div>
                        </div>
                    </section>
                </div>

                <!-- ═══ SUMMARY ═══ -->
                <div>
                    <div class="lg:sticky lg:top-6 space-y-4">
                        <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                            <h2 class="mb-4 text-sm font-bold text-gray-900">Ringkasan Pesanan</h2>

                            <dl class="space-y-2.5 text-sm">
                                <div class="flex justify-between">
                                    <dt class="text-gray-500">Subtotal ({{ cart.count.value }} item)</dt>
                                    <dd class="font-semibold text-gray-900">{{ formatPrice(subtotal) }}</dd>
                                </div>

                                <div v-if="isMember && memberSavings > 0" class="flex justify-between text-emerald-600">
                                    <dt class="font-medium">Penjimatan ahli</dt>
                                    <dd class="font-semibold">− {{ formatPrice(memberSavings) }}</dd>
                                </div>

                                <div class="flex justify-between border-t border-gray-100 pt-2.5">
                                    <dt class="text-gray-500">Penghantaran</dt>
                                    <dd class="font-semibold" :class="cart.combinedPostage.value > 0 ? 'text-gray-900' : 'text-emerald-600'">
                                        {{ cart.combinedPostage.value > 0 ? formatPrice(cart.combinedPostage.value) : 'Percuma' }}
                                    </dd>
                                </div>

                                <div class="flex items-baseline justify-between border-t border-gray-200 pt-3">
                                    <dt class="text-sm font-bold text-gray-900">Jumlah</dt>
                                    <dd class="text-xl font-black text-gray-900">{{ formatPrice(grandTotal) }}</dd>
                                </div>
                            </dl>

                            <button
                                :disabled="processing"
                                class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-amber-500 px-6 py-3.5 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                                @click="checkout"
                            >
                                <svg v-if="processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                {{ processing ? 'Memproses pesanan...' : 'Teruskan ke Pembayaran' }}
                            </button>

                            <Link :href="route('mall.index')" class="mt-2 block text-center text-xs font-semibold text-gray-500 transition hover:text-gray-800">
                                Terus beli-belah
                            </Link>
                        </section>

                        <!-- Guest nudge -->
                        <div v-if="!user" class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-xs font-bold text-amber-900">Anda membeli sebagai tetamu</p>
                            <p class="mt-1 text-xs text-amber-700">
                                Log masuk untuk harga ahli yang lebih murah dan untuk menjejaki pesanan anda.
                            </p>
                            <Link :href="route('login')" class="mt-2 inline-block text-xs font-bold text-amber-800 underline">
                                Log masuk
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

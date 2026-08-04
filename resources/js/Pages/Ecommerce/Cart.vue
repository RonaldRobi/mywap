<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCart } from '@/composables/useCart';
import { computed, ref } from 'vue';

const cart = useCart();
const page = usePage();

const isMember = computed(() => page.props.auth.user?.roles?.includes('Member') ?? false);

const shippingName = ref(page.props.auth.user?.name ?? '');
const shippingPhone = ref(page.props.auth.user?.phone ?? '');
const shippingAddress = ref('');
const shippingPostcode = ref('');

function itemPrice(item) {
    return isMember.value && item.member_price != null ? item.member_price : item.price;
}

const subtotal = computed(() => cart.items.value.reduce((sum, i) => sum + itemPrice(i) * i.quantity, 0));
const grandTotal = computed(() => subtotal.value + cart.combinedPostage.value);

function checkout() {
    const products = cart.items.value.map(i => ({
        id: i.id,
        quantity: i.quantity,
        variation_option_id: i.variation_option_id || null,
        variation_snapshot: i.variation_snapshot || null,
    }));

    router.post(route('mall.checkout'), {
        products,
        shipping_name: shippingName.value || null,
        shipping_phone: shippingPhone.value || null,
        shipping_address: shippingAddress.value || null,
        shipping_postcode: shippingPostcode.value || null,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            cart.clear();
        },
    });
}
</script>

<template>
    <Head title="Bakul Beli-belah" />

    <AppLayout :back-route="route('mall.index')" back-label="MyWAP Mall">
        <template #header>Bakul Beli-belah</template>

        <div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-5">
            <div v-if="page.props.errors?.error" class="rounded-2xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ page.props.errors.error }}
            </div>

            <div v-if="cart.count.value === 0" class="rounded-3xl border border-dashed border-gray-200 bg-white p-16 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-50"><svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg></div>
                <p class="font-semibold text-gray-600">Bakul anda kosong</p>
                <a :href="route('mall.index')" class="mt-4 inline-block rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white">Lihat Produk</a>
            </div>

            <template v-else>
                <div v-for="item in cart.items.value" :key="item.key" class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm flex items-center gap-4">
                    <div class="w-16 h-16 shrink-0 rounded-xl bg-gray-50 overflow-hidden"><img v-if="item.image" :src="'/storage/' + item.image" :alt="item.name" class="w-full h-full object-cover"><span v-else class="flex items-center justify-center h-full text-xs text-gray-400">No img</span></div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-bold text-gray-900 truncate">{{ item.name }}</p>
                        <p v-if="item.variation" class="text-[11px] text-gray-400">{{ item.variation }}</p>
                        <p class="mt-1 text-sm font-bold text-gray-900">RM {{ (itemPrice(item) * item.quantity).toFixed(2) }}</p>
                        <p v-if="item.member_price != null" class="text-[11px] text-amber-600">Harga Ahli: RM {{ (item.member_price * item.quantity).toFixed(2) }}</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button @click="cart.updateQuantity(item.key, item.quantity - 1)" class="w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-bold">−</button>
                        <span class="w-8 text-center text-sm font-semibold">{{ item.quantity }}</span>
                        <button @click="cart.updateQuantity(item.key, item.quantity + 1)" class="w-7 h-7 rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-50 text-sm font-bold">+</button>
                        <button @click="cart.remove(item.key)" class="ml-2 w-7 h-7 rounded-lg border border-red-100 text-red-400 hover:bg-red-50 text-xs">✕</button>
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
                    <p class="text-sm font-bold text-gray-900">Maklumat Penghantaran</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Penerima <span class="text-red-500">*</span></label>
                            <input v-model="shippingName" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" />
                            <p v-if="page.props.errors?.shipping_name" class="mt-1 text-xs text-red-500">{{ page.props.errors.shipping_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">No Telefon <span class="text-red-500">*</span></label>
                            <input v-model="shippingPhone" type="tel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" />
                            <p v-if="page.props.errors?.shipping_phone" class="mt-1 text-xs text-red-500">{{ page.props.errors.shipping_phone }}</p>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Alamat Penghantaran</label>
                        <textarea v-model="shippingAddress" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0"></textarea>
                    </div>
                    <div class="max-w-[200px]">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Poskod</label>
                        <input v-model="shippingPostcode" type="text" maxlength="10" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-400 focus:ring-0" />
                    </div>
                </div>

                <div class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Subtotal ({{ cart.count.value }} item)</span>
                        <span class="font-bold text-gray-900">RM {{ subtotal.toFixed(2) }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-3">
                        <span class="text-gray-500">Kos Penghantaran (Digabung)</span>
                        <span class="font-bold text-gray-900">{{ cart.combinedPostage.value > 0 ? `RM ${cart.combinedPostage.value.toFixed(2)}` : 'Percuma' }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm border-t border-gray-100 pt-3">
                        <span class="text-gray-900 font-bold">Jumlah Keseluruhan</span>
                        <span class="text-xl font-black text-gray-900">RM {{ grandTotal.toFixed(2) }}</span>
                    </div>
                    <button @click="checkout" class="mt-4 w-full rounded-xl bg-amber-500 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-amber-600 transition">Buat Pesanan</button>
                </div>
            </template>
        </div>
    </AppLayout>
</template>

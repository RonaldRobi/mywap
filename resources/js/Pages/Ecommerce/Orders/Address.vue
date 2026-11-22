<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps({
    order: {
        type: Object,
        required: true,
    },
});

const page = usePage();
const form = useForm({
    shipping_address: props.order.shipping_address ?? '',
    shipping_postcode: props.order.shipping_postcode ?? '',
});

function submit() {
    form.post(route('mall.order.address.update', props.order.id), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="`Alamat Pesanan #${order.id}`" />

    <GuestLayout>
        <div class="space-y-6">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#52645A]">Pesanan #{{ order.id }}</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight text-[#071525]">Lengkapkan alamat penghantaran</h1>
                <p class="mt-2 text-sm leading-6 text-[#52645A]">
                    Hai {{ order.shipping_name || 'pelanggan' }}, sila masukkan alamat supaya pesanan anda boleh dihantar.
                </p>
            </div>

            <div v-if="page.props.flash?.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                {{ page.props.flash.success }}
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="shipping_address" class="mb-1.5 block text-sm font-semibold text-[#25352B]">Alamat penghantaran <span class="text-red-600">*</span></label>
                    <textarea
                        id="shipping_address"
                        v-model="form.shipping_address"
                        rows="4"
                        autofocus
                        required
                        class="w-full rounded-xl border-0 bg-white px-3.5 py-3 text-sm text-gray-900 ring-1 ring-inset ring-[#B9CDBE] placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-[#2F6B32]"
                        placeholder="No rumah, jalan, taman, bandar, negeri"
                    />
                    <p v-if="form.errors.shipping_address" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.shipping_address }}</p>
                </div>

                <div>
                    <label for="shipping_postcode" class="mb-1.5 block text-sm font-semibold text-[#25352B]">Poskod</label>
                    <input
                        id="shipping_postcode"
                        v-model="form.shipping_postcode"
                        type="text"
                        maxlength="10"
                        class="w-full rounded-xl border-0 bg-white px-3.5 py-3 text-sm text-gray-900 ring-1 ring-inset ring-[#B9CDBE] placeholder:text-gray-400 focus:ring-2 focus:ring-inset focus:ring-[#2F6B32]"
                        placeholder="43000"
                    />
                    <p v-if="form.errors.shipping_postcode" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.shipping_postcode }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-[#123D2A] px-4 py-3 text-sm font-bold text-white transition hover:bg-[#0B2B1D] disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ form.processing ? 'Menyimpan...' : 'Simpan Alamat' }}
                </button>
            </form>
        </div>
    </GuestLayout>
</template>

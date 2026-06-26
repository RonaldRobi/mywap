<script setup>
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const form = useForm({
    name: props.product.name ?? '',
    description: props.product.description ?? '',
    price: props.product.price ?? '',
    postage_cost: props.product.postage_cost ?? '',
    stock: props.product.stock ?? 0,
    category_id: props.product.category_id ?? null,
    status: !!props.product.status,
    image: null,
    images: [],
    variations: props.product.variations?.length ? props.product.variations.map(v => ({
        id: v.id,
        name: v.name,
        type: v.type,
        required: v.required,
        options: v.options?.map(o => ({
            id: o.id,
            name: o.name,
            price_adjustment: o.price_adjustment ?? '',
            stock: o.stock ?? '',
            hex_color: o.hex_color ?? '',
        })) ?? [],
    })) : [],
});

const existingImages = ref(props.product.images ?? []);
const extraImages = ref([]);

function addVariation() {
    form.variations.push({
        id: null,
        name: '',
        type: 'select',
        required: true,
        options: [],
    });
}

function removeVariation(index) {
    form.variations.splice(index, 1);
}

function addOption(variationIndex) {
    form.variations[variationIndex].options.push({
        id: null,
        name: '',
        price_adjustment: '',
        stock: '',
        hex_color: '',
    });
}

function removeOption(variationIndex, optionIndex) {
    form.variations[variationIndex].options.splice(optionIndex, 1);
}

function handleExtraImages(e) {
    const files = Array.from(e.target.files || []);
    extraImages.value = files;
    form.images = files;
}

function removeExistingImage(index) {
    existingImages.value.splice(index, 1);
}

function submit() {
    form
        .transform((data) => ({
            ...data,
            variations: JSON.stringify(form.variations),
        }))
        .post(route('products.update', props.product.id), {
            forceFormData: true,
            _method: 'put',
        });
}
</script>

<template>
    <Head title="Edit Produk" />

    <AppLayout :back-route="route('products.index')" back-label="Kembali ke Produk">
        <template #header>Edit Produk</template>

        <div class="mx-auto max-w-4xl px-4 py-4 md:px-6 md:py-6">
            <div class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm sm:p-6">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama</label>
                        <input v-model="form.name" type="text" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Kategori</label>
                        <select v-model="form.category_id" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs text-red-600">{{ form.errors.category_id }}</p>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Harga (RM)</label>
                            <input v-model="form.price" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Kos Pos (RM)</label>
                            <input v-model="form.postage_cost" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <p v-if="form.errors.postage_cost" class="mt-1 text-xs text-red-600">{{ form.errors.postage_cost }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Stok</label>
                            <input v-model="form.stock" type="number" min="0" step="1" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="form.errors.stock" class="mt-1 text-xs text-red-600">{{ form.errors.stock }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Deskripsi</label>
                        <textarea v-model="form.description" rows="4" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"></textarea>
                        <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tukar Gambar Utama (optional)</label>
                        <input type="file" accept="image/*" class="block w-full text-sm text-gray-700" @change="form.image = $event.target.files?.[0] ?? null">
                        <p v-if="form.errors.image" class="mt-1 text-xs text-red-600">{{ form.errors.image }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Gambar Tambahan (optional)</label>
                        <div v-if="existingImages.length" class="mb-2 flex flex-wrap gap-2">
                            <div v-for="(img, i) in existingImages" :key="i" class="group relative">
                                <img :src="'/storage/' + img" class="h-16 w-16 rounded-xl object-cover border border-gray-200">
                                <button type="button" @click="removeExistingImage(i)" class="absolute -top-1.5 -right-1.5 rounded-full bg-red-500 text-white p-0.5 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                        </div>
                        <input type="file" accept="image/*" multiple class="block w-full text-sm text-gray-700" @change="handleExtraImages">
                        <p v-if="form.errors.images" class="mt-1 text-xs text-red-600">{{ form.errors.images }}</p>
                        <div v-if="extraImages.length" class="mt-2 flex flex-wrap gap-2">
                            <div v-for="(file, i) in extraImages" :key="i" class="rounded-xl border border-gray-200 px-2 py-1 text-xs text-gray-600">
                                {{ file.name }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="status" v-model="form.status" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                        <label for="status" class="text-sm text-gray-700">Aktif</label>
                    </div>

                    <!-- Variations -->
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="text-sm font-bold text-gray-900">Variasi Produk</h3>
                            <button type="button" @click="addVariation" class="inline-flex items-center gap-1 rounded-2xl border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Tambah Variasi
                            </button>
                        </div>

                        <div v-if="!form.variations.length" class="rounded-2xl border-2 border-dashed border-gray-200 p-6 text-center">
                            <p class="text-sm text-gray-400">Tiada variasi. Klik "Tambah Variasi" untuk tambah saiz, warna, dll.</p>
                        </div>

                        <div v-for="(variation, vIndex) in form.variations" :key="vIndex" class="mb-4 rounded-2xl border border-gray-200 p-4">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-400">Nama Variasi</label>
                                        <input v-model="variation.name" type="text" placeholder="cth: Saiz, Warna" class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:border-gray-500 focus:ring-0" required>
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-400">Jenis</label>
                                        <select v-model="variation.type" class="w-full rounded-xl border border-gray-200 px-3 py-1.5 text-sm focus:border-gray-500 focus:ring-0">
                                            <option value="select">Pilihan Teks</option>
                                            <option value="color">Warna</option>
                                        </select>
                                    </div>
                                    <div class="flex items-end pb-1">
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox" v-model="variation.required" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                                            <span class="text-xs font-semibold text-gray-500">Wajib dipilih</span>
                                        </label>
                                    </div>
                                </div>
                                <button type="button" @click="removeVariation(vIndex)" class="ml-2 rounded-xl p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </div>

                            <!-- Options -->
                            <div class="ml-2">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-semibold text-gray-400">Pilihan</span>
                                    <button type="button" @click="addOption(vIndex)" class="text-xs font-semibold text-gray-600 hover:text-gray-900">
                                        + Tambah Pilihan
                                    </button>
                                </div>

                                <div v-for="(option, oIndex) in variation.options" :key="oIndex" class="mb-2 flex flex-wrap items-center gap-2 rounded-xl border border-gray-100 bg-gray-50 p-2">
                                    <input v-model="option.name" type="text" placeholder="cth: S, M, L" class="min-w-[80px] flex-1 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-500 focus:ring-0" required>
                                    <input v-model="option.price_adjustment" type="number" step="0.01" placeholder="Harga +RM" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-500 focus:ring-0">
                                    <input v-model="option.stock" type="number" placeholder="Stok" class="w-20 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:border-gray-500 focus:ring-0">
                                    <div v-if="variation.type === 'color'" class="flex items-center gap-1">
                                        <input v-model="option.hex_color" type="color" class="h-7 w-7 rounded-lg border border-gray-200 cursor-pointer">
                                        <span v-if="option.hex_color" class="text-[10px] text-gray-500 font-mono">{{ option.hex_color }}</span>
                                    </div>
                                    <button type="button" @click="removeOption(vIndex, oIndex)" class="rounded-lg p-1 text-gray-400 hover:bg-red-50 hover:text-red-600">
                                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Link :href="route('products.index')" class="inline-flex items-center justify-center rounded-2xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Batal
                        </Link>
                        <button type="submit" :disabled="form.processing" class="inline-flex items-center justify-center rounded-2xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

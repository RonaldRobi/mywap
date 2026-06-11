<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    categories: {
        type: Array,
        required: true,
    },
});

const form = useForm({
    name: '',
    description: '',
    price: '',
    stock: 0,
    category_id: props.categories?.[0]?.id ?? null,
    status: true,
    image: null,
});

function submit() {
    form.post(route('products.store'), {
        forceFormData: true,
    });
}
</script>

<template>
    <Head title="Tambah Produk" />

    <AppLayout>
        <template #header>Tambah Produk</template>

        <div class="mx-auto max-w-3xl px-4 py-4 md:px-6 md:py-6">
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

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Harga (RM)</label>
                            <input v-model="form.price" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" required>
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-600">{{ form.errors.price }}</p>
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
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Gambar (optional)</label>
                        <input type="file" accept="image/*" class="block w-full text-sm text-gray-700" @change="form.image = $event.target.files?.[0] ?? null">
                        <p v-if="form.errors.image" class="mt-1 text-xs text-red-600">{{ form.errors.image }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input id="status" v-model="form.status" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-gray-900 focus:ring-gray-900">
                        <label for="status" class="text-sm text-gray-700">Aktif</label>
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


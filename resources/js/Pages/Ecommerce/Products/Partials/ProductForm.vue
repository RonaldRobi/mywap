<script setup>
/**
 * Shared admin product form used by both Create and Edit.
 *
 * Layout follows the Shopify/WooCommerce pattern: a wide main column for the
 * substance of the product and a sticky sidebar for meta (status, category,
 * media). Everything is one page so the admin never loses context.
 */
import { computed, ref, watch } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import { productImageUrl, formatPrice, onImageError } from '@/composables/useProductImage';

const props = defineProps({
    product: { type: Object, default: null },
    categories: { type: Array, required: true },
    submitUrl: { type: String, required: true },
    cancelUrl: { type: String, required: true },
    submitLabel: { type: String, default: 'Simpan Produk' },
    // Superadmin only: the list of organisations the product can be sold under.
    organizations: { type: Array, default: () => [] },
    isSuperadmin: { type: Boolean, default: false },
});

const isEdit = computed(() => !!props.product);

const form = useForm({
    name: props.product?.name ?? '',
    description: props.product?.description ?? '',
    price: props.product?.price ?? '',
    member_price: props.product?.member_price ?? '',
    postage_cost: props.product?.postage_cost ?? '',
    stock: props.product?.stock ?? 0,
    category_id: props.product?.category_id ?? props.categories?.[0]?.id ?? null,
    // Only meaningful for Superadmin; org Admins never send this.
    organisasi_id: props.product?.organisasi_id ?? null,
    status: props.product ? !!props.product.status : true,
    image: null,
    images: [],
    remove_image: false,
    variations: props.product?.variations?.length
        ? props.product.variations.map((v) => ({
              id: v.id,
              name: v.name,
              type: v.type,
              required: !!v.required,
              options:
                  v.options?.map((o) => ({
                      id: o.id,
                      name: o.name,
                      price_adjustment: o.price_adjustment ?? '',
                      stock: o.stock ?? '',
                      hex_color: o.hex_color || '#000000',
                  })) ?? [],
          }))
        : [],
});

/* ── Media ─────────────────────────────────────────────────────────── */

const MAX_MB = 5;
const mainPreview = ref(props.product?.image ? productImageUrl(props.product.image) : null);
const galleryPreviews = ref((props.product?.images ?? []).map(productImageUrl));
const mediaError = ref('');

function tooLarge(file) {
    return file.size > MAX_MB * 1024 * 1024;
}

function handleMainImage(event) {
    mediaError.value = '';
    const file = event.target.files?.[0];
    if (!file) return;

    if (tooLarge(file)) {
        mediaError.value = `"${file.name}" melebihi ${MAX_MB}MB. Sila pilih gambar lebih kecil.`;
        event.target.value = '';
        return;
    }

    form.image = file;
    form.remove_image = false;
    mainPreview.value = URL.createObjectURL(file);
}

function clearMainImage() {
    form.image = null;
    form.remove_image = isEdit.value;
    mainPreview.value = null;
}

function handleGalleryImages(event) {
    mediaError.value = '';
    const files = Array.from(event.target.files || []);

    const oversized = files.filter(tooLarge);
    if (oversized.length) {
        mediaError.value = `${oversized.map((f) => f.name).join(', ')} melebihi ${MAX_MB}MB.`;
        event.target.value = '';
        return;
    }

    if (files.length > 6) {
        mediaError.value = 'Maksimum 6 gambar tambahan.';
        event.target.value = '';
        return;
    }

    form.images = files;
    galleryPreviews.value = files.map((f) => URL.createObjectURL(f));
}

/* ── Variations ────────────────────────────────────────────────────── */

function addVariation() {
    form.variations.push({ id: null, name: '', type: 'select', required: true, options: [] });
}

function removeVariation(index) {
    form.variations.splice(index, 1);
}

function addOption(vIndex) {
    form.variations[vIndex].options.push({
        id: null,
        name: '',
        price_adjustment: '',
        stock: '',
        hex_color: '#000000',
    });
}

function removeOption(vIndex, oIndex) {
    form.variations[vIndex].options.splice(oIndex, 1);
}

/* ── Validation surfacing ──────────────────────────────────────────── */

const errorList = computed(() => Object.values(form.errors).filter(Boolean));
const hasErrors = computed(() => errorList.value.length > 0);

watch(hasErrors, (bad) => {
    if (bad) window.scrollTo({ top: 0, behavior: 'smooth' });
});

/* ── Derived pricing preview ───────────────────────────────────────── */

const savingsPercent = computed(() => {
    const p = Number(form.price);
    const m = Number(form.member_price);
    if (!p || !form.member_price || m >= p) return 0;
    return Math.round(((p - m) / p) * 100);
});

const stockState = computed(() => {
    const n = Number(form.stock);
    if (n <= 0) return { label: 'Kehabisan stok', tone: 'bg-red-50 text-red-700 ring-red-200' };
    if (n <= 5) return { label: `Stok rendah (${n})`, tone: 'bg-amber-50 text-amber-700 ring-amber-200' };
    return { label: `${n} dalam stok`, tone: 'bg-emerald-50 text-emerald-700 ring-emerald-200' };
});

/* ── Selling organisation (Superadmin only) ────────────────────────── */

const selectedOrg = computed(() =>
    props.organizations.find((o) => Number(o.id) === Number(form.organisasi_id)) ?? null
);

// A product sold under an org with no live gateway cannot collect payment.
const orgGatewayWarning = computed(() => {
    if (!props.isSuperadmin || !selectedOrg.value) return false;
    return !selectedOrg.value.has_gateway;
});

function submit() {
    mediaError.value = '';
    form.transform((data) => ({
        ...data,
        // Sent as multipart because of the file inputs, so nested arrays must
        // be serialised. The controller decodes this before validating.
        variations: JSON.stringify(data.variations),
        status: data.status ? 1 : 0,
        remove_image: data.remove_image ? 1 : 0,
        ...(isEdit.value ? { _method: 'put' } : {}),
    })).post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
    });
}

const inputClass =
    'w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-sm text-gray-900 ring-1 ring-inset ring-gray-200 placeholder:text-gray-400 transition focus:ring-2 focus:ring-inset focus:ring-amber-500';
</script>

<template>
    <form class="mx-auto max-w-6xl px-4 py-6 md:px-6" @submit.prevent="submit">
        <!-- ═══ ERROR SUMMARY ═══ -->
        <div
            v-if="hasErrors || mediaError"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4"
            role="alert"
        >
            <div class="flex gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
                </svg>
                <div>
                    <p class="text-sm font-bold text-red-800">Produk tidak dapat disimpan</p>
                    <ul class="mt-1.5 list-inside list-disc space-y-0.5 text-sm text-red-700">
                        <li v-if="mediaError">{{ mediaError }}</li>
                        <li v-for="(err, i) in errorList" :key="i">{{ err }}</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- ═══ MAIN COLUMN ═══ -->
            <div class="space-y-6 lg:col-span-2">
                <!-- Basics -->
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold text-gray-900">Maklumat Produk</h2>

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="mb-1.5 block text-xs font-semibold text-gray-600">
                                Nama produk <span class="text-red-500">*</span>
                            </label>
                            <input
                                id="name"
                                v-model="form.name"
                                type="text"
                                :class="inputClass"
                                placeholder="cth: Baju Melayu Cekak Musang"
                                required
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label for="description" class="mb-1.5 block text-xs font-semibold text-gray-600">Deskripsi</label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="6"
                                :class="inputClass"
                                placeholder="Terangkan bahan, saiz, penjagaan dan apa yang pembeli akan terima..."
                            ></textarea>
                            <p class="mt-1 text-xs text-gray-400">
                                Deskripsi yang jelas mengurangkan pertanyaan pembeli.
                            </p>
                            <p v-if="form.errors.description" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </section>

                <!-- Media -->
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-1 flex items-baseline justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Gambar</h2>
                        <span class="text-xs text-gray-400">JPG, PNG, WEBP · maks {{ MAX_MB }}MB</span>
                    </div>
                    <p class="mb-4 text-xs text-gray-500">Gambar pertama akan jadi paparan utama di Mall.</p>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Main image -->
                        <div>
                            <p class="mb-1.5 text-xs font-semibold text-gray-600">Gambar utama</p>
                            <div
                                v-if="mainPreview"
                                class="group relative aspect-square overflow-hidden rounded-xl border border-gray-200 bg-gray-50"
                            >
                                <img :src="mainPreview" alt="Pratonton gambar utama" class="h-full w-full object-cover" @error="onImageError" />
                                <button
                                    type="button"
                                    class="absolute right-2 top-2 rounded-lg bg-white/90 p-1.5 text-gray-600 shadow-sm transition hover:bg-red-50 hover:text-red-600"
                                    title="Buang gambar"
                                    @click="clearMainImage"
                                >
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <label
                                v-else
                                class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 text-center transition hover:border-amber-400 hover:bg-amber-50/40"
                            >
                                <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0L8 8m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" />
                                </svg>
                                <span class="text-xs font-semibold text-gray-600">Muat naik gambar</span>
                                <span class="text-[11px] text-gray-400">Klik untuk pilih fail</span>
                                <input type="file" accept="image/*" class="sr-only" @change="handleMainImage" />
                            </label>
                            <p v-if="form.errors.image" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.image }}</p>
                        </div>

                        <!-- Gallery -->
                        <div>
                            <p class="mb-1.5 text-xs font-semibold text-gray-600">Gambar tambahan (maks 6)</p>
                            <label
                                class="flex aspect-square cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-gray-200 bg-gray-50 p-3 text-center transition hover:border-amber-400 hover:bg-amber-50/40"
                            >
                                <div v-if="galleryPreviews.length" class="grid w-full grid-cols-3 gap-1.5">
                                    <img
                                        v-for="(src, i) in galleryPreviews.slice(0, 6)"
                                        :key="i"
                                        :src="src"
                                        alt=""
                                        class="aspect-square w-full rounded-md object-cover"
                                        @error="onImageError"
                                    />
                                </div>
                                <template v-else>
                                    <svg class="h-8 w-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                    <span class="text-xs font-semibold text-gray-600">Pilih beberapa gambar</span>
                                </template>
                                <span v-if="galleryPreviews.length" class="text-[11px] font-medium text-amber-600">
                                    {{ galleryPreviews.length }} gambar dipilih · klik untuk tukar
                                </span>
                                <input type="file" accept="image/*" multiple class="sr-only" @change="handleGalleryImages" />
                            </label>
                            <p v-if="form.errors.images" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.images }}</p>
                        </div>
                    </div>
                </section>

                <!-- Pricing -->
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-bold text-gray-900">Harga & Stok</h2>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="price" class="mb-1.5 block text-xs font-semibold text-gray-600">
                                Harga jualan (RM) <span class="text-red-500">*</span>
                            </label>
                            <input id="price" v-model="form.price" type="number" min="0" step="0.01" :class="inputClass" placeholder="0.00" required />
                            <p v-if="form.errors.price" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.price }}</p>
                        </div>

                        <div>
                            <label for="member_price" class="mb-1.5 block text-xs font-semibold text-gray-600">Harga ahli (RM)</label>
                            <input id="member_price" v-model="form.member_price" type="number" min="0" step="0.01" :class="inputClass" placeholder="Kosongkan jika sama" />
                            <p v-if="savingsPercent" class="mt-1 text-xs font-semibold text-emerald-600">
                                Ahli jimat {{ savingsPercent }}%
                            </p>
                            <p v-if="form.errors.member_price" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.member_price }}</p>
                        </div>

                        <div>
                            <label for="postage_cost" class="mb-1.5 block text-xs font-semibold text-gray-600">Kos pos (RM)</label>
                            <input id="postage_cost" v-model="form.postage_cost" type="number" min="0" step="0.01" :class="inputClass" placeholder="0.00" />
                            <p v-if="form.errors.postage_cost" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.postage_cost }}</p>
                        </div>

                        <div>
                            <label for="stock" class="mb-1.5 block text-xs font-semibold text-gray-600">
                                Kuantiti stok <span class="text-red-500">*</span>
                            </label>
                            <input id="stock" v-model="form.stock" type="number" min="0" step="1" :class="inputClass" required />
                            <span
                                class="mt-1.5 inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset"
                                :class="stockState.tone"
                            >{{ stockState.label }}</span>
                            <p v-if="form.errors.stock" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.stock }}</p>
                        </div>
                    </div>
                </section>

                <!-- Variations -->
                <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                    <div class="mb-1 flex items-center justify-between">
                        <h2 class="text-sm font-bold text-gray-900">Variasi</h2>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-gray-700"
                            @click="addVariation"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                            </svg>
                            Tambah variasi
                        </button>
                    </div>
                    <p class="mb-4 text-xs text-gray-500">Contoh: Saiz (S, M, L) atau Warna. Biarkan kosong jika produk ini hanya ada satu jenis.</p>

                    <div v-if="!form.variations.length" class="rounded-xl border-2 border-dashed border-gray-200 p-8 text-center">
                        <p class="text-sm text-gray-400">Tiada variasi ditetapkan.</p>
                    </div>

                    <div v-for="(variation, vIndex) in form.variations" :key="vIndex" class="mb-3 rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                        <div class="flex items-start gap-3">
                            <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-gray-500">Nama</label>
                                    <input v-model="variation.name" type="text" placeholder="Saiz" :class="inputClass" required />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[11px] font-semibold text-gray-500">Jenis</label>
                                    <select v-model="variation.type" :class="inputClass">
                                        <option value="select">Pilihan teks</option>
                                        <option value="color">Warna</option>
                                    </select>
                                </div>
                                <div class="flex items-end pb-2.5">
                                    <label class="flex cursor-pointer items-center gap-2">
                                        <input v-model="variation.required" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                                        <span class="text-xs font-semibold text-gray-600">Wajib dipilih</span>
                                    </label>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="mt-6 rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-600"
                                title="Buang variasi"
                                @click="removeVariation(vIndex)"
                            >
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>

                        <div class="mt-3 border-t border-gray-200 pt-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Pilihan</span>
                                <button type="button" class="text-xs font-semibold text-amber-600 hover:text-amber-700" @click="addOption(vIndex)">
                                    + Tambah pilihan
                                </button>
                            </div>

                            <p v-if="!variation.options.length" class="py-2 text-xs text-gray-400">
                                Belum ada pilihan. Tambah sekurang-kurangnya satu.
                            </p>

                            <div
                                v-for="(option, oIndex) in variation.options"
                                :key="oIndex"
                                class="mb-2 flex flex-wrap items-center gap-2 rounded-lg border border-gray-200 bg-white p-2"
                            >
                                <input v-model="option.name" type="text" placeholder="Nama (cth: M)" class="min-w-[90px] flex-1 rounded-lg border-0 px-2 py-1.5 text-xs ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500" required />
                                <input v-model="option.price_adjustment" type="number" step="0.01" placeholder="+RM" class="w-24 rounded-lg border-0 px-2 py-1.5 text-xs ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500" />
                                <input v-model="option.stock" type="number" min="0" placeholder="Stok" class="w-20 rounded-lg border-0 px-2 py-1.5 text-xs ring-1 ring-inset ring-gray-200 focus:ring-2 focus:ring-amber-500" />
                                <input v-if="variation.type === 'color'" v-model="option.hex_color" type="color" class="h-8 w-8 cursor-pointer rounded-lg border border-gray-200" />
                                <button type="button" class="rounded-lg p-1 text-gray-400 transition hover:bg-red-50 hover:text-red-600" @click="removeOption(vIndex, oIndex)">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ═══ SIDEBAR ═══ -->
            <div class="space-y-6">
                <div class="lg:sticky lg:top-6 space-y-6">
                    <!-- Status -->
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-bold text-gray-900">Status</h2>
                        <label class="flex cursor-pointer items-start gap-3">
                            <input v-model="form.status" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-gray-300 text-amber-600 focus:ring-amber-500" />
                            <span>
                                <span class="block text-sm font-semibold text-gray-800">
                                    {{ form.status ? 'Diterbitkan' : 'Draf' }}
                                </span>
                                <span class="block text-xs text-gray-500">
                                    {{ form.status
                                        ? 'Produk ini kelihatan kepada semua pembeli di MyWAP Mall.'
                                        : 'Disimpan tetapi tersembunyi daripada pembeli.' }}
                                </span>
                            </span>
                        </label>
                    </section>

                    <!-- Selling organisation (Superadmin only) -->
                    <section v-if="isSuperadmin" class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="mb-1 text-sm font-bold text-gray-900">
                            Organisasi Penjual <span class="text-red-500">*</span>
                        </h2>
                        <p class="mb-3 text-xs text-gray-500">
                            Bayaran produk ini akan masuk ke gateway pembayaran organisasi yang dipilih.
                        </p>

                        <select v-model="form.organisasi_id" :class="inputClass" required>
                            <option :value="null" disabled>— Pilih organisasi —</option>
                            <option v-for="o in organizations" :key="o.id" :value="o.id">
                                {{ o.name }}{{ o.has_gateway ? '' : ' (tiada gateway)' }}
                            </option>
                        </select>

                        <!-- Gateway readiness feedback -->
                        <div
                            v-if="selectedOrg && !orgGatewayWarning"
                            class="mt-2 flex items-center gap-1.5 text-xs font-semibold text-emerald-600"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            Gateway aktif: {{ selectedOrg.gateway }}
                        </div>
                        <div
                            v-else-if="orgGatewayWarning"
                            class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800"
                        >
                            <span class="font-bold">Amaran:</span>
                            {{ selectedOrg.name }} belum menetapkan gateway pembayaran. Pembeli tidak akan dapat membayar produk ini sehingga gateway dikonfigurasikan.
                        </div>

                        <p v-if="form.errors.organisasi_id" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.organisasi_id }}</p>
                    </section>

                    <!-- Category -->
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-bold text-gray-900">Kategori</h2>
                        <select v-model="form.category_id" :class="inputClass" required>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                        <p v-if="!categories.length" class="mt-2 text-xs text-amber-600">
                            Tiada kategori lagi.
                            <Link :href="route('categories.create')" class="font-semibold underline">Cipta satu dahulu</Link>.
                        </p>
                        <p v-if="form.errors.category_id" class="mt-1 text-xs font-medium text-red-600">{{ form.errors.category_id }}</p>
                    </section>

                    <!-- Live preview -->
                    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">
                        <h2 class="mb-3 text-sm font-bold text-gray-900">Pratonton</h2>
                        <div class="overflow-hidden rounded-xl border border-gray-100">
                            <img
                                :src="mainPreview || '/images/product-placeholder.svg'"
                                alt=""
                                class="aspect-square w-full bg-gray-50 object-cover"
                                @error="onImageError"
                            />
                            <div class="p-3">
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900">
                                    {{ form.name || 'Nama produk' }}
                                </p>
                                <p class="mt-1 text-base font-black text-gray-900">
                                    {{ formatPrice(form.price || 0) }}
                                </p>
                                <p v-if="form.member_price" class="text-xs font-semibold text-amber-600">
                                    Ahli {{ formatPrice(form.member_price) }}
                                </p>
                            </div>
                        </div>
                    </section>

                    <!-- Actions -->
                    <div class="flex flex-col gap-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <svg v-if="form.processing" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            {{ form.processing ? 'Menyimpan...' : submitLabel }}
                        </button>
                        <Link
                            :href="cancelUrl"
                            class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
                        >
                            Batal
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </form>
</template>

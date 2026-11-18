<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { QuillEditor } from '@vueup/vue-quill';
import '@vueup/vue-quill/dist/vue-quill.snow.css';

const props = defineProps({
    mode: { type: String, default: 'create' },
    statement: { type: Object, default: null },
    organizations: { type: Array, default: () => [] },
});

const TITLE_PATTERN = /^[\p{L}\p{N}\p{M}\s.,'’"()/&:;!?%+-]+$/u;

const contentTypes = [
    { value: 'text', label: 'Teks sahaja', hint: 'Tulis kenyataan penuh dalam editor.' },
    { value: 'pdf', label: 'PDF sahaja', hint: 'Muat naik dokumen PDF tanpa teks.' },
    { value: 'both', label: 'Teks + PDF', hint: 'Tulis ringkasan dan lampirkan PDF penuh.' },
];

const form = useForm({
    organization_id: props.statement?.organization_id ?? '',
    title: props.statement?.title ?? '',
    content_type: props.statement?.content_type ?? 'text',
    content: props.statement?.content ?? '',
    featured_image: null,
    remove_featured_image: false,
    pdf: null,
    attachments: [],
    is_published: props.statement ? !!props.statement.is_published : true,
    is_featured: props.statement ? !!props.statement.is_featured : false,
});

const editorRef = ref(null);
const featuredInput = ref(null);
const featuredPreview = ref(props.statement?.featured_image_path ?? null);
const uploadingImage = ref(false);
const uploadError = ref('');

const showsText = computed(() => ['text', 'both'].includes(form.content_type));
const showsPdf = computed(() => ['pdf', 'both'].includes(form.content_type));

const titleInvalid = computed(() => form.title.length > 0 && !TITLE_PATTERN.test(form.title));

const plainContent = computed(() => {
    if (typeof document === 'undefined') {
        return (form.content || '').replace(/<[^>]*>/g, '').trim();
    }

    const container = document.createElement('div');
    container.innerHTML = form.content || '';

    return (container.textContent || '').replace(/\u00a0/g, ' ').trim();
});

const contentMissing = computed(() => showsText.value && plainContent.value === '');
const pdfMissing = computed(() => showsPdf.value && !form.pdf && !props.statement?.pdf_path);
const dropsExistingPdf = computed(() => form.content_type === 'text' && !!props.statement?.pdf_path);
const dropsExistingText = computed(() => form.content_type === 'pdf' && !!props.statement?.content);

const canSubmit = computed(() => !titleInvalid.value && !contentMissing.value && !pdfMissing.value);

const toolbar = {
    container: [
        [{ header: [2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ indent: '-1' }, { indent: '+1' }],
        ['blockquote', 'link', 'image'],
        ['clean'],
    ],
    handlers: {
        image: selectImage,
    },
};

function setFeatured(event) {
    const file = event.target.files?.[0] ?? null;
    form.featured_image = file;
    form.remove_featured_image = false;

    if (featuredPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(featuredPreview.value);
    }

    featuredPreview.value = file
        ? URL.createObjectURL(file)
        : (props.statement?.featured_image_path ?? null);
}

function removeFeatured() {
    if (featuredPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(featuredPreview.value);
    }

    featuredPreview.value = null;
    form.featured_image = null;
    form.remove_featured_image = true;

    if (featuredInput.value) {
        featuredInput.value.value = '';
    }
}

function selectImage() {
    if (uploadingImage.value) {
        return;
    }

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';

    input.addEventListener('change', async () => {
        const file = input.files?.[0];

        if (!file) {
            return;
        }

        const payload = new FormData();
        payload.append('image', file);

        uploadingImage.value = true;
        uploadError.value = '';

        try {
            const { data } = await window.axios.post(route('admin.media-statements.content-image'), payload);
            const quill = editorRef.value?.getQuill();
            const range = quill?.getSelection(true) ?? { index: quill?.getLength() ?? 0 };

            quill?.insertEmbed(range.index, 'image', data.url, 'user');
            quill?.setSelection(range.index + 1);
        } catch (error) {
            uploadError.value = error?.response?.data?.message
                || 'Imej gagal dimuat naik. Cuba lagi.';
        } finally {
            uploadingImage.value = false;
        }
    }, { once: true });

    input.click();
}

function setAttachments(event) {
    form.attachments = Array.from(event.target.files || []);
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    const url = props.mode === 'edit'
        ? route('admin.media-statements.update', props.statement.id)
        : route('admin.media-statements.store');

    form.transform((data) => (props.mode === 'edit' ? { ...data, _method: 'put' } : data))
        .post(url, {
            forceFormData: true,
            preserveScroll: true,
        });
}

onBeforeUnmount(() => {
    if (featuredPreview.value?.startsWith('blob:')) {
        URL.revokeObjectURL(featuredPreview.value);
    }
});
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="space-y-6">
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-gray-800">Butiran Kenyataan</h2>
                    <p class="mt-1 text-sm text-gray-500">Tajuk sahaja yang dipaparkan pada senarai dan kad kongsi.</p>

                    <div class="mt-5 space-y-5">
                        <div>
                            <label for="statement-organization" class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                            <select id="statement-organization" v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                <option value="">Umum (Semua)</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                            <p v-if="form.errors.organization_id" class="mt-1 text-xs text-red-600">{{ form.errors.organization_id }}</p>
                        </div>

                        <div>
                            <label for="statement-title" class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                            <input
                                id="statement-title"
                                v-model="form.title"
                                type="text"
                                maxlength="255"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                                :class="titleInvalid ? 'border-red-300 focus:border-red-400 focus:ring-red-200' : ''"
                                :aria-invalid="titleInvalid"
                                aria-describedby="statement-title-help"
                            />
                            <p id="statement-title-help" class="mt-1 text-xs text-gray-400">Tanpa emoji atau simbol. Guna huruf, nombor dan tanda baca biasa.</p>
                            <p v-if="titleInvalid" class="mt-1 text-xs font-medium text-red-600">Tajuk tidak boleh mengandungi emoji atau simbol.</p>
                            <p v-else-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                        </div>

                        <fieldset>
                            <legend class="mb-2 block text-xs font-semibold text-gray-500">Jenis kandungan</legend>
                            <div class="grid gap-2 sm:grid-cols-3">
                                <label
                                    v-for="option in contentTypes"
                                    :key="option.value"
                                    class="flex cursor-pointer items-start gap-2 rounded-2xl border p-3 transition"
                                    :class="form.content_type === option.value
                                        ? 'border-emerald-300 bg-emerald-50/60'
                                        : 'border-gray-200 bg-white hover:border-gray-300'"
                                >
                                    <input
                                        v-model="form.content_type"
                                        type="radio"
                                        :value="option.value"
                                        class="mt-0.5 rounded-full border-gray-300 text-emerald-600 focus:ring-emerald-500"
                                    />
                                    <span>
                                        <span class="block text-sm font-bold text-gray-900">{{ option.label }}</span>
                                        <span class="mt-1 block text-xs leading-relaxed text-gray-500">{{ option.hint }}</span>
                                    </span>
                                </label>
                            </div>
                            <p v-if="form.errors.content_type" class="mt-1 text-xs text-red-600">{{ form.errors.content_type }}</p>
                            <p v-if="dropsExistingPdf" class="mt-2 text-xs text-amber-700">PDF sedia ada akan dibuang apabila anda simpan.</p>
                            <p v-if="dropsExistingText" class="mt-2 text-xs text-amber-700">Teks sedia ada akan dikosongkan apabila anda simpan.</p>
                        </fieldset>
                    </div>
                </section>

                <section v-if="showsText" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-black text-gray-800">Kandungan Teks</h2>
                            <p class="mt-1 text-sm text-gray-500">Tambah imej, pautan, senarai dan petikan terus dalam editor.</p>
                        </div>
                        <span v-if="uploadingImage" class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Memuat naik imej...</span>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white">
                        <QuillEditor
                            ref="editorRef"
                            v-model:content="form.content"
                            content-type="html"
                            theme="snow"
                            :toolbar="toolbar"
                        />
                    </div>
                    <p v-if="uploadError" class="mt-2 text-xs text-red-600">{{ uploadError }}</p>
                    <p v-if="contentMissing" class="mt-2 text-xs text-red-600">Tulis kandungan untuk jenis ini.</p>
                    <p v-else-if="form.errors.content" class="mt-2 text-xs text-red-600">{{ form.errors.content }}</p>
                </section>

                <section v-else class="rounded-3xl border border-dashed border-gray-200 bg-white p-6 text-sm text-gray-500">
                    Editor teks tidak digunakan untuk kenyataan PDF sahaja. Muat naik PDF di panel kanan.
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-black text-gray-800">Galeri Tambahan</h2>
                    <p class="mt-1 text-sm text-gray-500">Pilihan. Untuk imej sokongan di luar kandungan utama.</p>

                    <div v-if="statement?.attachments?.length" class="mt-4 flex flex-wrap gap-2">
                        <a
                            v-for="attachment in statement.attachments"
                            :key="attachment.id"
                            :href="attachment.path"
                            target="_blank"
                            rel="noopener"
                            class="block h-20 w-20 overflow-hidden rounded-xl border border-gray-200"
                        >
                            <img :src="attachment.path" alt="Lampiran sedia ada" class="h-full w-full object-cover" />
                        </a>
                    </div>

                    <label for="statement-attachments" class="mt-4 block text-xs font-semibold text-gray-500">Tambah imej</label>
                    <input id="statement-attachments" type="file" accept="image/*" multiple class="mt-1 block w-full text-sm" @change="setAttachments" />
                    <p v-if="form.attachments.length" class="mt-1 text-xs text-gray-500">{{ form.attachments.length }} imej dipilih.</p>
                    <p v-if="form.errors.attachments" class="mt-1 text-xs text-red-600">{{ form.errors.attachments }}</p>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black text-gray-800">Imej Utama</h2>
                    <p class="mt-1 text-xs text-gray-500">Digunakan pada kad senarai dan tag OG semasa dikongsi.</p>

                    <div class="mt-3 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                        <img
                            v-if="featuredPreview"
                            :src="featuredPreview"
                            alt="Pratonton imej utama"
                            width="1200"
                            height="675"
                            class="aspect-[16/9] w-full object-cover"
                        />
                        <div v-else class="flex aspect-[16/9] items-center justify-center px-4 text-center text-xs text-gray-400">
                            Tiada imej. Nisbah disyorkan 16:9, contohnya 1200 x 675.
                        </div>
                    </div>

                    <div class="mt-3 space-y-2">
                        <label for="statement-featured" class="block text-xs font-semibold text-gray-500">Muat naik imej</label>
                        <input id="statement-featured" ref="featuredInput" type="file" accept="image/*" class="block w-full text-sm" @change="setFeatured" />
                        <button
                            v-if="featuredPreview"
                            type="button"
                            class="cursor-pointer text-xs font-semibold text-red-600 hover:text-red-700"
                            @click="removeFeatured"
                        >
                            Buang imej
                        </button>
                    </div>
                    <p v-if="form.errors.featured_image" class="mt-1 text-xs text-red-600">{{ form.errors.featured_image }}</p>
                </section>

                <section v-if="showsPdf" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black text-gray-800">Fail PDF</h2>

                    <a
                        v-if="statement?.pdf_path"
                        :href="statement.pdf_path"
                        target="_blank"
                        rel="noopener"
                        class="mt-2 inline-flex text-xs font-semibold text-emerald-700 underline"
                    >
                        Buka PDF semasa
                    </a>

                    <label for="statement-pdf" class="mt-3 block text-xs font-semibold text-gray-500">Muat naik PDF</label>
                    <input id="statement-pdf" type="file" accept="application/pdf" class="mt-1 block w-full text-sm" @change="form.pdf = $event.target.files?.[0] ?? null" />
                    <p class="mt-1 text-xs text-gray-400">Maksimum 5MB.</p>
                    <p v-if="pdfMissing" class="mt-1 text-xs text-red-600">Muat naik fail PDF untuk jenis kandungan ini.</p>
                    <p v-else-if="form.errors.pdf" class="mt-1 text-xs text-red-600">{{ form.errors.pdf }}</p>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                    <h2 class="text-sm font-black text-gray-800">Penerbitan</h2>

                    <div class="mt-3 space-y-3">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_published" type="checkbox" class="rounded border-gray-300" />
                            Terbitkan
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="form.is_featured" type="checkbox" class="rounded border-gray-300" />
                            Tampilkan sebagai pilihan utama
                        </label>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !canSubmit"
                        class="mt-5 w-full cursor-pointer rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ form.processing ? 'Menyimpan...' : (mode === 'edit' ? 'Simpan Perubahan' : 'Simpan Kenyataan') }}
                    </button>
                    <p v-if="!canSubmit" class="mt-2 text-center text-xs text-gray-500">Lengkapkan tajuk dan kandungan yang diperlukan.</p>
                </section>
            </aside>
        </div>
    </form>
</template>

<style scoped>
:deep(.ql-container) {
    min-height: 320px;
    font-size: 15px;
}

:deep(.ql-editor) {
    min-height: 320px;
    line-height: 1.7;
}

:deep(.ql-editor img) {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
}
</style>

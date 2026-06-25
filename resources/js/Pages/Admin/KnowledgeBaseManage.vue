<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ref, watch, computed } from 'vue';

const props = defineProps({
    articles: { type: Object, default: () => ({ data: [] }) },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const searchQuery = ref(props.filters?.search ?? '');
const categoryFilter = ref(props.filters?.category ?? '');

watch([searchQuery, categoryFilter], (val) => {
    const timer = setTimeout(() => {
        router.get(route('admin.knowledge-base.index'), {
            search: val[0] || '',
            category: val[1] || '',
        }, { preserveState: true, preserveScroll: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
}, { deep: true });

const showModal = ref(false);
const editingArticle = ref(null);
const isDocument = ref(false);

const form = useForm({
    question: '',
    answer: '',
    keywords: '',
    category: '',
    document: null,
    is_active: true,
});

function openAddQa() {
    editingArticle.value = null;
    isDocument.value = false;
    form.reset();
    form.is_active = true;
    showModal.value = true;
}

function openAddDocument() {
    editingArticle.value = null;
    isDocument.value = true;
    form.reset();
    form.is_active = true;
    showModal.value = true;
}

function openEdit(article) {
    editingArticle.value = article;
    isDocument.value = !!article.document_path;
    form.question = article.question ?? '';
    form.answer = article.answer ?? '';
    form.keywords = article.keywords ?? '';
    form.category = article.category ?? '';
    form.document = null;
    form.is_active = article.is_active;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editingArticle.value = null;
    form.reset();
    form.clearErrors();
}

function submit() {
    if (editingArticle.value) {
        form
            .transform((data) => ({ ...data, _method: 'post' }))
            .post(route('admin.knowledge-base.update', editingArticle.value.id), {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: () => closeModal(),
            });
    } else {
        form.post(route('admin.knowledge-base.store'), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => closeModal(),
        });
    }
}

function removeArticle(article) {
    if (!confirm('Padam artikel ini?')) return;
    useForm({}).delete(route('admin.knowledge-base.destroy', article.id), {
        preserveScroll: true,
    });
}

const visiblePages = computed(() => {
    if (!props.articles) return [];
    const last = props.articles.last_page ?? 1;
    const cur = props.articles.current_page ?? 1;
    if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1);
    const pages = [1];
    if (cur > 3) pages.push('...');
    for (let i = Math.max(2, cur - 1); i <= Math.min(last - 1, cur + 1); i++) pages.push(i);
    if (cur < last - 2) pages.push('...');
    pages.push(last);
    return pages;
});

function changeCategory() {
    router.get(route('admin.knowledge-base.index'), {
        search: searchQuery.value || '',
        category: categoryFilter.value || '',
    }, { preserveState: true, preserveScroll: true, replace: true });
}
</script>

<template>
    <AppLayout>
        <Head title="Knowledge Base" />

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">Pangkalan Pengetahuan</h1>
                    <p class="mt-1 text-sm text-gray-500">Urus Q&A dan dokumen untuk chatbot MyWAP AI.</p>
                </div>
                <div class="flex gap-2">
                    <button @click="openAddQa" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        ✏️ Tambah Q&A
                    </button>
                    <button @click="openAddDocument" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        📄 Upload Dokumen
                    </button>
                </div>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="🔍 Cari..."
                    class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm"
                >
                <select
                    v-model="categoryFilter"
                    @change="changeCategory"
                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm"
                >
                    <option value="">Semua Kategori</option>
                    <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                </select>
            </div>

            <!-- Table -->
            <div class="rounded-3xl border border-gray-100 bg-white overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50/70 border-b border-gray-100 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th scope="col" class="px-4 py-3 font-bold">Jenis</th>
                                <th scope="col" class="px-4 py-3 font-bold">Soalan / Tajuk</th>
                                <th scope="col" class="px-4 py-3 font-bold">Isi Kandungan</th>
                                <th scope="col" class="px-4 py-3 font-bold">Kategori</th>
                                <th scope="col" class="px-4 py-3 font-bold">Status</th>
                                <th scope="col" class="px-4 py-3 font-bold text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="!articles.data?.length">
                                <td colspan="6" class="px-4 py-12 text-center text-gray-400">Tiada data dijumpai.</td>
                            </tr>
                            <tr v-for="article in articles.data" :key="article.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="px-4 py-3">
                                    <span v-if="article.document_path" class="rounded-lg bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">Dok</span>
                                    <span v-else class="rounded-lg bg-blue-100 px-2 py-0.5 text-xs font-semibold text-blue-700">Q&A</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-gray-900 max-w-[200px] truncate">
                                    {{ article.question || '(Dokumen)' }}
                                </td>
                                <td class="px-4 py-3 max-w-[300px]">
                                    <div class="truncate text-gray-500">
                                        <template v-if="article.document_path">
                                            <a :href="article.document_path" target="_blank" class="text-blue-600 hover:underline">
                                                📎 Buka Dokumen
                                            </a>
                                            <span v-if="article.answer"> — {{ article.answer.substring(0, 100) }}...</span>
                                        </template>
                                        <template v-else>
                                            {{ article.answer?.substring(0, 150) }}{{ article.answer?.length > 150 ? '...' : '' }}
                                        </template>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="article.category" class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs text-gray-600">{{ article.category }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span :class="article.is_active ? 'text-emerald-600' : 'text-gray-400'">{{ article.is_active ? '🟢 Aktif' : '⚪ Tidak Aktif' }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button @click="openEdit(article)" class="rounded-lg px-2 py-1 text-xs font-semibold text-gray-500 hover:bg-gray-100">✏️</button>
                                    <button @click="removeArticle(article)" class="rounded-lg px-2 py-1 text-xs font-semibold text-red-500 hover:bg-red-50">🗑️</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="articles.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
                    <div class="text-xs text-gray-500">
                        Menunjukkan
                        <span class="font-semibold">{{ articles.from ?? 0 }}</span> -
                        <span class="font-semibold">{{ articles.to ?? 0 }}</span> dari
                        <span class="font-semibold">{{ articles.total ?? 0 }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <Link v-if="articles.prev_page_url" :href="articles.prev_page_url" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs hover:bg-gray-50">&lsaquo;</Link>
                        <template v-for="(page, i) in visiblePages" :key="i">
                            <span v-if="page === '...'" class="px-1 text-xs text-gray-400">...</span>
                            <Link v-else :href="articles.links?.find(l => l.label == page)?.url ?? '#'"
                                :class="['rounded-lg px-2.5 py-1 text-xs', page == articles.current_page ? 'bg-gray-900 text-white' : 'border border-gray-200 hover:bg-gray-50']">
                                {{ page }}
                            </Link>
                        </template>
                        <Link v-if="articles.next_page_url" :href="articles.next_page_url" class="rounded-lg border border-gray-200 px-2.5 py-1 text-xs hover:bg-gray-50">&rsaquo;</Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <teleport to="body">
            <transition
                enter-active-class="transition-all duration-200 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-all duration-150 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
                    <transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 scale-95 translate-y-4"
                        enter-to-class="opacity-100 scale-100 translate-y-0"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 scale-100 translate-y-0"
                        leave-to-class="opacity-0 scale-95 translate-y-4"
                    >
                        <div v-if="showModal" class="w-full max-w-lg rounded-2xl border border-gray-100 bg-white p-6 shadow-xl">
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg font-black text-gray-900">
                                    <template v-if="editingArticle">Edit</template>
                                    <template v-else-if="isDocument">📄 Upload Dokumen</template>
                                    <template v-else>✏️ Tambah Q&A</template>
                                </h2>
                                <button @click="closeModal" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <form @submit.prevent="submit" class="space-y-3">
                                <template v-if="!isDocument || editingArticle">
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-gray-500">Soalan (optional untuk dokumen)</span>
                                        <input v-model="form.question" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                    </label>
                                </template>

                                <label class="block">
                                    <span class="mb-1 block text-xs font-semibold text-gray-500">
                                        {{ isDocument ? 'Ringkasan Kandungan' : 'Jawapan' }}
                                    </span>
                                    <textarea v-model="form.answer" rows="4" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" :required="!isDocument"></textarea>
                                    <p v-if="form.errors.answer" class="mt-1 text-xs text-red-500">{{ form.errors.answer }}</p>
                                </label>

                                <label v-if="isDocument" class="block">
                                    <span class="mb-1 block text-xs font-semibold text-gray-500">Fail Dokumen (PDF/TXT)</span>
                                    <input
                                        type="file"
                                        accept=".txt,.pdf"
                                        @change="form.document = $event.target.files[0]"
                                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                                    >
                                    <p v-if="form.errors.document" class="mt-1 text-xs text-red-500">{{ form.errors.document }}</p>
                                    <p v-if="editingArticle && editingArticle.document_path" class="mt-1 text-xs text-blue-600">
                                        📎 Fail sedia ada: <a :href="editingArticle.document_path" target="_blank" class="underline">Buka</a>
                                    </p>
                                </label>

                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-gray-500">Kata Kunci</span>
                                        <input v-model="form.keywords" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="yuran, bayaran">
                                    </label>

                                    <label class="block">
                                        <span class="mb-1 block text-xs font-semibold text-gray-500">Kategori</span>
                                        <select v-model="form.category" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                            <option value="">Tiada</option>
                                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                                        </select>
                                    </label>
                                </div>

                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-gray-300">
                                    Aktif
                                </label>

                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="closeModal" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
                                        Batal
                                    </button>
                                    <button
                                        type="submit"
                                        :disabled="form.processing"
                                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                                    >
                                        {{ form.processing ? 'Menyimpan...' : 'Simpan' }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </transition>
                </div>
            </transition>
        </teleport>
    </AppLayout>
</template>

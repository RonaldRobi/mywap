<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, computed, nextTick, onUnmounted } from 'vue';

const props = defineProps({
    form:           Object,
    organizations:  Array,
    events:         Array,
    questionTypes:  Array,
    preselectedEventId: { type: Number, default: null },
    backTo:         { type: String, default: null },
});

const isEditing = !!props.form;
const showPreview = ref(false);
const deletedQuestionIds = ref([]);
const builderRoot = ref(null);
const highlightedQuestionUid = ref(null);
let highlightTimer = null;

let uidCounter = 0;
const nextUid = () => ++uidCounter;

function createQuestion(q = {}) {
    return {
        _uid: nextUid(),
        id: q.id ?? null,
        label: q.label ?? '',
        type: q.type ?? 'text',
        options: q.options ?? [],
        required: !!q.required,
        placeholder: q.placeholder ?? '',
        help_text: q.help_text ?? '',
    };
}

function createItem(it = {}) {
    return {
        _uid: nextUid(),
        id: it.id ?? null,
        name: it.name || '',
        description: it.description || '',
        price: it.price ?? '',
        stock: it.stock ?? '',
        is_active: it.is_active ?? true,
        is_required: it.is_required ?? false,
        image: null,
        image_url: it.image_url ?? null,
        remove_image: false,
        variants: (it.variants || []).map(v => ({
            _uid: nextUid(),
            id: v.id ?? null,
            name: v.name || '',
            price: v.price ?? '',
            stock: v.stock ?? '',
            is_active: v.is_active ?? true,
        })),
    };
}

function prefersReducedMotion() {
    return window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
}

function scrollToNewest(selector, focusSelector = null) {
    const nodes = builderRoot.value?.querySelectorAll(selector);
    const el = nodes?.length ? nodes[nodes.length - 1] : null;
    if (!el) return;
    el.scrollIntoView({
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        block: 'center',
    });
    if (focusSelector) {
        el.querySelector(focusSelector)?.focus({ preventScroll: true });
    }
}

onUnmounted(() => clearTimeout(highlightTimer));

const typeLabels = {
    text:      'Teks',
    textarea:  'Perenggan',
    number:    'Nombor',
    email:     'Emel',
    phone:     'Telefon',
    date:      'Tarikh',
    select:    'Dropdown',
    radio:     'Radio',
    checkbox:  'Checkbox',
    file:      'Muat Naik Fail',
    branch:    'Negeri & Cawangan',
};

const formData = reactive({
    title:          props.form?.title ?? '',
    description:    props.form?.description ?? '',
    price:          props.form?.price ?? '',
    price_tiers:    props.form?.price_tiers?.length
        ? props.form.price_tiers.map(t => ({
            label: t.label || '',
            price: t.price ?? '',
            is_default: !!t.is_default,
            requires_document: !!t.requires_document,
            description: t.description || '',
        }))
        : [],
    payment_required: props.form?.payment_required ?? false,
    terms:          props.form?.terms ?? '',
    is_active:      props.form?.is_active ?? true,
    allow_public:   props.form?.allow_public ?? true,
    organization_id: props.form?.organization_id ?? '',
    event_id:       props.form?.event_id ?? props.preselectedEventId ?? '',
    header_image:   null,
    recipient_emails: props.form?.recipient_emails?.length
        ? [...props.form.recipient_emails]
        : [''],
    questions: props.form?.questions?.length
        ? props.form.questions.map(createQuestion)
        : [createQuestion()],
    items: props.form?.items?.length
        ? props.form.items.map(createItem)
        : [],
});

// Jenis pendaftaran yang terhasil daripada tetapan semasa — supaya admin
// faham serta-merta sama ada peserta daftar percuma atau perlu bayar.
const registrationType = computed(() => {
  const basePaid = !!formData.payment_required;
  const hasAddons = (formData.items || []).some(it => it.is_active && it.name?.trim());
  if (hasAddons) return basePaid ? 'Berbayar + Barang Tambahan' : 'Percuma + Barang Tambahan';
  return basePaid ? 'Berbayar' : 'Percuma';
});

// Bahagian lanjutan disembunyikan secara lalai supaya admin baharu tidak
// tenggelam dalam pilihan yang jarang digunakan.
const showTiers = ref((props.form?.price_tiers?.length ?? 0) > 0);
const showItems = ref((props.form?.items?.length ?? 0) > 0);
const showRecipients = ref((props.form?.recipient_emails?.length ?? 0) > 0);

function setRegistrationKind(kind) {
  formData.payment_required = kind === 'paid';
}

async function addItem() {
  formData.items.push(createItem());
  await nextTick();
  scrollToNewest('[data-item-card]', '[data-item-name]');
}

function removeItem(index) {
  formData.items.splice(index, 1);
}

async function addVariant(item) {
  item.variants.push({ _uid: nextUid(), id: null, name: '', price: '', stock: '', is_active: true });
  await nextTick();
  const card = builderRoot.value?.querySelector(`[data-item-uid="${item._uid}"]`);
  const rows = card?.querySelectorAll('[data-variant-row]');
  const row = rows?.length ? rows[rows.length - 1] : null;
  row?.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'nearest' });
  row?.querySelector('input')?.focus({ preventScroll: true });
}

function removeVariant(item, index) {
  item.variants.splice(index, 1);
}

async function addRecipientEmail() {
    formData.recipient_emails.push('');
    await nextTick();
    scrollToNewest('[data-recipient-row]', 'input');
}

async function addTier() {
    formData.price_tiers.push({
        label: '',
        price: '',
        is_default: formData.price_tiers.length === 0,
        requires_document: false,
        description: '',
    });
    await nextTick();
    scrollToNewest('[data-tier-card]', '[data-tier-label]');
}

function removeTier(index) {
    formData.price_tiers.splice(index, 1);
}

function setDefaultTier(index) {
    formData.price_tiers.forEach((t, i) => { t.is_default = i === index; });
}

function removeRecipientEmail(index) {
    formData.recipient_emails.splice(index, 1);
    if (!formData.recipient_emails.length) {
        formData.recipient_emails.push('');
    }
}

async function addQuestion() {
    const question = createQuestion();
    formData.questions.push(question);
    await nextTick();

    const card = builderRoot.value?.querySelector(`[data-question-uid="${question._uid}"]`);
    if (!card) return;

    card.scrollIntoView({
        behavior: prefersReducedMotion() ? 'auto' : 'smooth',
        block: 'center',
    });
    card.querySelector('[data-question-label]')?.focus({ preventScroll: true });

    highlightedQuestionUid.value = question._uid;
    clearTimeout(highlightTimer);
    highlightTimer = setTimeout(() => { highlightedQuestionUid.value = null; }, 1600);
}

function removeQuestion(index) {
    if (formData.questions.length <= 1) return;
    const [removed] = formData.questions.splice(index, 1);
    if (removed?.id) deletedQuestionIds.value.push(removed.id);
}

function onTypeChange(q) {
    if (!['select', 'radio', 'checkbox'].includes(q.type)) {
        q.options = [];
    }
    if (!q.options?.length) {
        q.options = [''];
    }
}

async function addOption(q) {
    if (!q.options) q.options = [];
    q.options.push('');
    await nextTick();
    const card = builderRoot.value?.querySelector(`[data-question-uid="${q._uid}"]`);
    const rows = card?.querySelectorAll('[data-option-row]');
    rows?.[rows.length - 1]?.querySelector('input')?.focus({ preventScroll: true });
}

function removeOption(q, idx) {
    q.options.splice(idx, 1);
}

function moveUp(idx) {
    if (idx === 0) return;
    [formData.questions[idx - 1], formData.questions[idx]] = [formData.questions[idx], formData.questions[idx - 1]];
}

function moveDown(idx) {
    if (idx >= formData.questions.length - 1) return;
    [formData.questions[idx + 1], formData.questions[idx]] = [formData.questions[idx], formData.questions[idx + 1]];
}

const busy = reactive({ saving: false });

function save() {
    if (busy.saving) return;
    busy.saving = true;
    const payload = {
        ...formData,
        back_to: props.backTo ?? '',
        deleted_question_ids: deletedQuestionIds.value,
        price: formData.price === '' ? null : formData.price,
        price_tiers: (formData.price_tiers || [])
            .filter(t => t.label?.trim() && t.price !== '' && t.price !== null)
            .map(t => ({
                label: t.label.trim(),
                price: t.price,
                is_default: !!t.is_default,
                requires_document: !!t.requires_document,
                description: t.description || null,
            })),
        items: (formData.items || [])
            .filter(it => it.name?.trim())
            .map(it => ({
                id: it.id || null,
                name: it.name.trim(),
                description: it.description || null,
                price: it.price === '' || it.price === null ? 0 : it.price,
                stock: it.stock === '' || it.stock === null ? null : it.stock,
                is_active: !!it.is_active,
                is_required: !!it.is_required,
                image: it.image || null,
                remove_image: !!it.remove_image,
                variants: (it.variants || [])
                    .filter(v => v.name?.trim())
                    .map(v => ({
                        id: v.id || null,
                        name: v.name.trim(),
                        price: v.price === '' || v.price === null ? null : v.price,
                        stock: v.stock === '' || v.stock === null ? null : v.stock,
                        is_active: !!v.is_active,
                    })),
            })),
        recipient_emails: (formData.recipient_emails || []).filter(e => e && e.trim()),
        questions: formData.questions.map((q, i) => ({
            id: q.id,
            label: q.label,
            type: q.type,
            options: ['select', 'radio', 'checkbox'].includes(q.type) ? (q.options || []).filter(o => o.trim()) : null,
            required: q.required,
            placeholder: q.placeholder,
            help_text: q.help_text,
        })),
    };

    // A nested File (item image) is not detected by Inertia's top-level file
    // check, so force multipart when any upload is present.
    const hasUploads = !!formData.header_image
        || formData.items.some(it => it.image instanceof File);

    if (isEditing) {
        // Multipart PUT tidak di-parse PHP — guna POST + method spoofing.
        router.post(route('admin.forms.update', props.form.id), { ...payload, _method: 'PUT' }, {
            forceFormData: hasUploads,
            onFinish: () => { busy.saving = false; },
        });
    } else {
        router.post(route('admin.forms.store'), payload, {
            forceFormData: hasUploads,
            onFinish: () => { busy.saving = false; },
        });
    }
}

const valid = computed(() => {
    if (!formData.title.trim()) return false;
    if (!formData.questions.length) return false;
    return formData.questions.every(q => q.label.trim());
});
</script>

<template>
    <AppLayout>
        <Head :title="isEditing ? `Edit Borang — ${form.title}` : 'Borang Baru'" />

        <div ref="builderRoot" class="mx-auto w-full max-w-[1500px] px-4 py-8 space-y-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ isEditing ? 'Edit Borang' : 'Borang Baru' }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Bina borang tersuai untuk program atau pendaftaran.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="showPreview = true"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        Pratonton
                    </button>
                    <button
                        @click="save"
                        :disabled="!valid || busy.saving"
                        class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-sm transition"
                        :class="valid ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'"
                    >
                        {{ busy.saving ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </div>
            </div>

            <!-- Flash error -->
            <div v-if="$page.props.flash?.error" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="Object.keys($page.props.errors || {}).length" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                <p class="font-semibold mb-1">Borang tidak disimpan:</p>
                <ul class="list-disc list-inside space-y-0.5">
                    <li v-for="(msg, key) in $page.props.errors" :key="key">{{ msg }}</li>
                </ul>
            </div>

            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[minmax(0,520px)_minmax(0,1fr)]">
            <!-- LEFT: Tetapan Borang -->
            <div class="space-y-6">
            <!-- Form settings -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide">Tetapan Borang</h2>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Tajuk *</label>
                    <input v-model="formData.title" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Contoh: Pendaftaran Program X" />
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Penerangan</label>
                    <textarea v-model="formData.description" rows="2" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Penerangan ringkas borang..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Pertubuhan</label>
                        <select v-model="formData.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                            <option value="">Semua</option>
                            <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Program (Opsional)</label>
                        <select v-model="formData.event_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                            <option value="">Tiada</option>
                            <option v-for="evt in events" :key="evt.id" :value="evt.id">{{ evt.title }}</option>
                        </select>
                    </div>
                </div>

                <!-- Header Image -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Gambar Header (Opsional)</label>
                    <p class="text-xs text-gray-400 mb-1">Gambar ini dipaparkan di bahagian atas borang dan sebagai OG image apabila dikongsi di media sosial.</p>
                    <input
                        type="file"
                        accept="image/jpg,image/jpeg,image/png,image/webp"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"
                        @change="formData.header_image = $event.target.files?.[0] ?? null"
                    />
                    <div v-if="isEditing && form?.header_image_path" class="mt-2">
                        <img :src="'/storage/' + form.header_image_path" class="h-24 rounded-lg object-cover" alt="Header semasa" />
                        <p class="text-xs text-gray-400 mt-1">Gambar semasa. Biarkan kosong untuk kekalkan.</p>
                    </div>
                </div>

                <!-- Jenis Pendaftaran -->
                <div class="pt-2 border-t border-gray-100">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Jenis Pendaftaran</p>
                            <p class="text-xs text-gray-500 mt-0.5">Pilih sama ada peserta daftar percuma atau perlu bayar yuran pendaftaran.</p>
                        </div>
                        <span
                            class="shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-bold"
                            :class="formData.payment_required ? 'border-indigo-200 bg-indigo-50 text-indigo-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                        >
                            {{ registrationType }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="setRegistrationKind('free')"
                            class="rounded-xl border px-4 py-3 text-left transition"
                            :class="!formData.payment_required ? 'border-emerald-400 bg-emerald-50 ring-2 ring-emerald-200' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <span class="block text-sm font-bold text-gray-800">Percuma</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Tiada bayaran asas. Boleh tambah barang berbayar di bawah.</span>
                        </button>
                        <button
                            type="button"
                            @click="setRegistrationKind('paid')"
                            class="rounded-xl border px-4 py-3 text-left transition"
                            :class="formData.payment_required ? 'border-indigo-400 bg-indigo-50 ring-2 ring-indigo-200' : 'border-gray-200 hover:border-gray-300'"
                        >
                            <span class="block text-sm font-bold text-gray-800">Berbayar</span>
                            <span class="mt-0.5 block text-xs text-gray-500">Peserta bayar yuran pendaftaran untuk mendaftar.</span>
                        </button>
                    </div>

                    <div v-if="formData.payment_required" class="mt-4 space-y-2">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1">Harga Asas (RM) *</label>
                            <input v-model="formData.price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="cth. 50.00" />
                            <p class="text-[11px] text-gray-400">Minimum RM 2.00 (had pembayaran DOKU).</p>
                        </div>

                        <button type="button" @click="showTiers = !showTiers" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
                            {{ showTiers ? '− Sembunyi harga mengikut kategori (tier)' : '+ Guna harga mengikut kategori (tier)' }}
                        </button>

                        <!-- Harga Tier (cth. Pelajar / Orang Awam) — lanjutan -->
                        <div v-if="showTiers" class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-800">Harga Mengikut Kategori (Tier)</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Opsional. Contoh: Pelajar RM20, Orang Awam RM50. Pendaftar pilih kategori sendiri.</p>
                                </div>
                                <button @click="addTier" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Tambah Tier</button>
                            </div>

                            <div v-if="!formData.price_tiers.length" class="mt-3 text-xs text-gray-400">
                                Tiada tier — gunakan Harga Asas di atas.
                            </div>

                            <div v-for="(tier, ti) in formData.price_tiers" :key="ti" data-tier-card class="mt-3 rounded-xl border border-indigo-100 bg-white p-3 space-y-2">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="radio"
                                        :checked="tier.is_default"
                                        @change="setDefaultTier(ti)"
                                        class="text-indigo-600 border-gray-300"
                                        title="Jadikan harga lalai"
                                    />
                                    <input
                                        v-model="tier.label"
                                        type="text"
                                        data-tier-label
                                        placeholder="cth. Pelajar"
                                        class="flex-1 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-300"
                                    />
                                    <div class="flex items-center gap-1">
                                        <span class="text-sm text-gray-400">RM</span>
                                        <input
                                            v-model="tier.price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="w-24 rounded-lg border border-gray-200 px-2.5 py-1.5 text-sm focus:ring-0 focus:border-gray-300"
                                        />
                                    </div>
                                    <button @click="removeTier(ti)" class="text-xs text-red-400 hover:text-red-600" title="Buang tier">✕</button>
                                </div>
                                <div class="flex flex-wrap gap-x-4 gap-y-2">
                                    <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                        <input type="checkbox" v-model="tier.requires_document" class="rounded border-gray-300" />
                                        Perlu dokumen (cth. kad pelajar)
                                    </label>
                                    <input
                                        v-model="tier.description"
                                        type="text"
                                        placeholder="Nota / syarat (cth. bawa kad pelajar)"
                                        class="flex-1 min-w-40 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs focus:ring-0 focus:border-gray-300"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Barang Tambahan (Add-ons / Merchandise) — lanjutan -->
                <div class="pt-2 border-t border-gray-100">
                    <button type="button" @click="showItems = !showItems" class="flex w-full items-start justify-between gap-3 text-left">
                        <div>
                            <p class="text-sm font-semibold text-gray-800">Barang Tambahan (Opsional)</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                Jual merchandise seperti jersi, topi atau kit. Peserta boleh pilih (atau tidak) semasa mendaftar.
                            </p>
                        </div>
                        <span class="flex shrink-0 items-center gap-2">
                            <span v-if="formData.items.length" class="rounded-full border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">{{ formData.items.length }} barang</span>
                            <svg class="h-4 w-4 text-gray-400 transition-transform" :class="{ 'rotate-180': showItems }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                        </span>
                    </button>

                    <div v-show="showItems" class="mt-3">
                        <div class="flex justify-end">
                            <button type="button" @click="addItem" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Tambah Barang</button>
                        </div>

                    <p v-if="!formData.items.length" class="mt-2 rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-3 text-xs text-gray-400">
                        Tiada barang tambahan. Pendaftaran hanya menggunakan yuran asas (atau percuma).
                    </p>

                    <div v-for="(item, ii) in formData.items" :key="item._uid" :data-item-uid="item._uid" data-item-card class="mt-3 rounded-2xl border border-gray-100 bg-gray-50/60 p-3 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-gray-400">Barang #{{ ii + 1 }}</span>
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-1.5 text-xs text-gray-600">
                                    <input type="checkbox" v-model="item.is_active" class="rounded border-gray-300" /> Aktif
                                </label>
                                <label class="flex items-center gap-1.5 text-xs text-gray-600" title="Setiap peserta wajib bayar">
                                    <input type="checkbox" v-model="item.is_required" class="rounded border-gray-300" /> Wajib
                                </label>
                                <button type="button" @click="removeItem(ii)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-0.5">Nama Barang *</label>
                                <input v-model="item.name" data-item-name class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-300" placeholder="cth. Jersi" />
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-0.5">Harga (RM)</label>
                                    <input v-model="item.price" type="number" min="0" step="0.01" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="0.00" />
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-0.5">Stok</label>
                                    <input v-model="item.stock" type="number" min="0" step="1" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="Tanpa had" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Penerangan</label>
                            <input v-model="item.description" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-xs focus:ring-0" placeholder="cth. Jersi rasmi edisi terhad" />
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Gambar Barang (Opsional)</label>
                            <input
                                type="file"
                                accept="image/jpg,image/jpeg,image/png,image/webp"
                                class="mt-1 block w-full text-xs text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold"
                                @change="item.image = $event.target.files?.[0] ?? null; item.remove_image = false"
                            />
                            <div v-if="item.image_url && !item.remove_image && !item.image" class="mt-2 flex items-center gap-2">
                                <img :src="item.image_url" class="h-16 w-16 rounded-lg border border-gray-100 object-cover" alt="Gambar barang" />
                                <button type="button" @click="item.remove_image = true; item.image = null" class="text-xs text-red-400 hover:text-red-600">Buang gambar</button>
                            </div>
                            <p v-else-if="item.remove_image" class="mt-1 text-[11px] text-amber-600">Gambar akan dibuang apabila disimpan.</p>
                        </div>

                        <div class="rounded-xl border border-indigo-100 bg-white p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-xs font-semibold text-gray-700">Varians (cth. saiz)</p>
                                    <p class="text-[11px] text-gray-400">Biarkan harga kosong untuk guna harga barang. Stok kosong = tanpa had.</p>
                                </div>
                                <button type="button" @click="addVariant(item)" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Varians</button>
                            </div>

                            <p v-if="!item.variants.length" class="text-[11px] text-gray-400">Tiada varians — peserta beli barang ini secara terus.</p>

                            <div v-for="(variant, vi) in item.variants" :key="variant._uid" data-variant-row class="flex flex-wrap items-center gap-2">
                                <input v-model="variant.name" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:ring-0" placeholder="Saiz" />
                                <div class="flex items-center gap-1">
                                    <span class="text-[11px] text-gray-400">RM</span>
                                    <input v-model="variant.price" type="number" min="0" step="0.01" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:ring-0" placeholder="ikut harga" />
                                </div>
                                <input v-model="variant.stock" type="number" min="0" step="1" class="w-24 rounded-lg border border-gray-200 px-2 py-1 text-xs focus:ring-0" placeholder="stok" />
                                <label class="flex items-center gap-1 text-[11px] text-gray-600">
                                    <input type="checkbox" v-model="variant.is_active" class="rounded border-gray-300" /> Aktif
                                </label>
                                <button type="button" @click="removeVariant(item, vi)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Terma &amp; Syarat</label>
                    <textarea v-model="formData.terms" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="Syarat-syarat pendaftaran..."></textarea>
                </div>

                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="formData.is_active" class="rounded border-gray-300" /> Aktif
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" v-model="formData.allow_public" class="rounded border-gray-300" /> Boleh diakses awam (pautan)
                    </label>
                </div>

                <!-- Recipient emails (lanjutan) -->
                <div class="pt-2 border-t border-gray-100">
                    <button type="button" @click="showRecipients = !showRecipients" class="flex w-full items-center justify-between gap-3 text-left">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Emel Penerima Borang (Lanjutan)</p>
                            <p class="text-xs text-gray-400 mt-0.5">Hantar borang ke emel ini melalui e-mel. (Opsional)</p>
                        </div>
                        <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform" :class="{ 'rotate-180': showRecipients }" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div v-show="showRecipients" class="mt-2">
                        <div class="flex justify-end">
                            <button @click="addRecipientEmail" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Emel</button>
                        </div>
                        <div class="mt-1 space-y-2">
                            <div v-for="(email, ei) in formData.recipient_emails" :key="ei" data-recipient-row class="flex items-center gap-2">
                                <input
                                    v-model="formData.recipient_emails[ei]"
                                    type="email"
                                    placeholder="contoh@email.com"
                                    class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-300 placeholder:text-gray-300"
                                />
                                <button @click="removeRecipientEmail(ei)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">Anda juga boleh hantar ke semua ahli organisasi dari senarai borang.</p>
                    </div>
                </div>
            </div>
            </div><!-- /left: tetapan -->

            <!-- RIGHT: Soalan -->
            <div class="space-y-6">
            <!-- Questions -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide">Soalan</h2>
                    <button
                        type="button"
                        @click="addQuestion"
                        class="inline-flex min-h-11 items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-semibold text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2"
                    >
                        <span aria-hidden="true" class="text-base leading-none">+</span>
                        Tambah Soalan
                    </button>
                </div>

                <div
                    v-for="(q, idx) in formData.questions"
                    :key="q._uid"
                    :data-question-uid="q._uid"
                    class="rounded-2xl border p-4 bg-gray-50/50 space-y-3 transition-shadow duration-300 motion-reduce:transition-none"
                    :class="highlightedQuestionUid === q._uid ? 'border-indigo-200 ring-2 ring-indigo-200' : 'border-gray-100'"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-400">Soalan #{{ idx + 1 }}</span>
                        <div class="flex items-center gap-1">
                            <button @click="moveUp(idx)" class="text-xs text-gray-400 hover:text-gray-600 px-1" :disabled="idx === 0">↑</button>
                            <button @click="moveDown(idx)" class="text-xs text-gray-400 hover:text-gray-600 px-1" :disabled="idx >= formData.questions.length - 1">↓</button>
                            <button @click="removeQuestion(idx)" class="text-xs text-red-400 hover:text-red-600 px-1" :disabled="formData.questions.length <= 1">✕</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Label *</label>
                            <input v-model="q.label" data-question-label class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-2 focus:ring-indigo-300 focus:border-indigo-300" placeholder="Label soalan..." />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Jenis</label>
                            <select v-model="q.type" @change="onTypeChange(q)" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0">
                                <option v-for="t in questionTypes" :key="t" :value="t">{{ typeLabels[t] ?? t }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Placeholder</label>
                            <input v-model="q.placeholder" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="Teks bantuan dalam input..." />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Teks Bantuan / Pernyataan</label>
                            <textarea v-model="q.help_text" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="Penerangan tambahan... (boleh guna baris baru)"></textarea>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" v-model="q.required" class="rounded border-gray-300" /> Wajib diisi
                    </label>

                    <!-- Options for select/radio/checkbox -->
                    <div v-if="['select', 'radio', 'checkbox'].includes(q.type)" class="space-y-1.5 pl-2 border-l-2 border-indigo-200">
                        <p class="text-xs font-semibold text-gray-500">Pilihan</p>
                        <div v-for="(opt, oi) in q.options" :key="oi" data-option-row class="flex items-center gap-2">
                            <input v-model="q.options[oi]" class="flex-1 rounded-lg border border-gray-200 px-3 py-1 text-xs focus:ring-2 focus:ring-indigo-300 focus:border-indigo-300" placeholder="Pilihan..." />
                            <button @click="removeOption(q, oi)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                        </div>
                        <button @click="addOption(q)" class="text-xs text-indigo-500 hover:text-indigo-600">+ Tambah pilihan</button>
                    </div>

                    <p v-if="q.type === 'branch'" class="text-xs text-gray-400 pl-2">
                        Pilihan Negeri &amp; Cawangan dijana automatik daripada cawangan organisasi borang ini.
                    </p>
                </div>
            </div>

            <!-- Save -->
            <div class="flex items-center justify-between">
                <a :href="route('admin.forms.index')" class="text-sm text-gray-500 hover:text-gray-700">← Kembali</a>
                <button @click="save" :disabled="!valid || busy.saving"
                        class="rounded-2xl px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition"
                        :class="valid ? 'bg-indigo-600 hover:bg-indigo-700' : 'bg-gray-300 cursor-not-allowed'">
                    <span v-if="busy.saving">Menyimpan...</span>
                    <span v-else>{{ isEditing ? 'Simpan Perubahan' : 'Cipta Borang' }}</span>
                </button>
            </div>
            </div><!-- /right: soalan -->
            </div><!-- /grid -->
        </div>

        <!-- ─── Pratonton ─────────────────────────────────────────────────── -->
        <div v-if="showPreview" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:p-8" @click.self="showPreview = false">
            <div class="w-full max-w-2xl rounded-3xl bg-white shadow-2xl overflow-hidden">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                    <p class="text-sm font-bold text-gray-800">Pratonton Borang</p>
                    <button @click="showPreview = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <!-- Preview body -->
                <div class="max-h-[75vh] overflow-y-auto bg-gradient-to-b from-gray-50 to-gray-100 p-5 sm:p-8">
                    <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 space-y-5">
                        <div>
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Pendaftaran</p>
                            <h2 class="text-xl font-black text-gray-900">{{ formData.title || '(Tanpa tajuk)' }}</h2>
                            <p v-if="formData.description" class="text-sm text-gray-500 mt-1 whitespace-pre-line">{{ formData.description }}</p>
                            <p v-if="formData.payment_required && formData.price" class="mt-2 inline-flex items-center rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-700">
                                RM {{ Number(formData.price).toFixed(2) }}
                            </p>
                        </div>

                        <hr class="border-gray-100" />

                        <div v-if="formData.items.some(it => it.name?.trim())" class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-2">
                            <p class="text-xs font-bold uppercase tracking-widest text-indigo-500">Barang Tambahan (Opsional)</p>
                            <div
                                v-for="(item, ii) in formData.items.filter(it => it.name?.trim())"
                                :key="ii"
                                class="rounded-xl border border-gray-100 bg-white px-3 py-2"
                            >
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="mb-2 h-24 w-full rounded-lg object-cover" />
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-800">
                                        {{ item.name }}
                                        <span v-if="item.is_required" class="text-[10px] font-bold text-rose-600">(wajib)</span>
                                        <span v-if="!item.is_active" class="text-[10px] text-amber-600">(tidak aktif)</span>
                                    </span>
                                    <span class="text-sm font-bold text-indigo-600">RM {{ Number(item.price || 0).toFixed(2) }}</span>
                                </div>
                                <p v-if="item.description" class="text-[11px] text-gray-400">{{ item.description }}</p>
                                <div v-if="item.variants.length" class="mt-1 flex flex-wrap gap-1">
                                    <span v-for="(v, vi) in item.variants" :key="vi" class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-600">
                                        {{ v.name || 'Varians' }}<template v-if="v.price !== '' && v.price !== null"> · RM {{ Number(v.price).toFixed(2) }}</template>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div v-for="(q, qi) in formData.questions" :key="qi" class="space-y-1.5">
                            <label class="block text-sm font-semibold text-gray-700 whitespace-pre-line">
                                {{ q.label || '(Tanpa label)' }}
                                <span v-if="q.required" class="text-red-500">*</span>
                            </label>
                            <p v-if="q.help_text" class="text-xs text-gray-400 whitespace-pre-line">{{ q.help_text }}</p>

                            <input
                                v-if="['text', 'email', 'phone', 'number', 'date'].includes(q.type)"
                                :type="q.type === 'phone' ? 'tel' : q.type"
                                :placeholder="q.placeholder || ''"
                                disabled
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                            />

                            <textarea
                                v-else-if="q.type === 'textarea'"
                                :placeholder="q.placeholder || ''"
                                disabled
                                rows="3"
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                            ></textarea>

                            <div v-else-if="q.type === 'file'" class="w-full rounded-xl border border-dashed border-gray-200 bg-gray-50 px-3 py-4 text-center text-xs text-gray-400">
                                Muat naik fail di sini
                            </div>

                            <select
                                v-else-if="q.type === 'select'"
                                disabled
                                class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm"
                            >
                                <option>{{ q.placeholder || 'Pilih...' }}</option>
                            </select>

                            <div v-else-if="q.type === 'branch'" class="space-y-2">
                                <select disabled class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                                    <option>Pilih Negeri...</option>
                                </select>
                                <select disabled class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm">
                                    <option>Pilih Cawangan...</option>
                                </select>
                                <p class="text-[11px] text-gray-400">Pilihan dijana dari cawangan organisasi.</p>
                            </div>

                            <div v-else-if="q.type === 'radio'" class="space-y-1.5">
                                <label v-for="opt in q.options.filter(o => o.trim())" :key="opt" class="flex items-center gap-2 text-sm">
                                    <input type="radio" disabled class="border-gray-300" />
                                    {{ opt }}
                                </label>
                            </div>

                            <div v-else-if="q.type === 'checkbox'" class="space-y-1.5">
                                <label v-for="opt in q.options.filter(o => o.trim())" :key="opt" class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" disabled class="rounded border-gray-300" />
                                    {{ opt }}
                                </label>
                            </div>
                        </div>

                        <div v-if="formData.terms" class="rounded-2xl bg-gray-50 p-4">
                            <p class="text-sm font-bold text-gray-700 mb-1">Terma &amp; Syarat</p>
                            <p class="text-xs text-gray-500 whitespace-pre-line">{{ formData.terms }}</p>
                        </div>

                        <button disabled class="w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white opacity-60">
                            {{ formData.payment_required && formData.price ? `Hantar & Bayar RM ${Number(formData.price).toFixed(2)}` : 'Hantar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

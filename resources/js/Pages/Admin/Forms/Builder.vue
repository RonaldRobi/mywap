<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';

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
};

const formData = reactive({
    title:          props.form?.title ?? '',
    description:    props.form?.description ?? '',
    price:          props.form?.price ?? '',
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
        ? props.form.questions.map(q => ({ id: q.id, label: q.label, type: q.type, options: q.options || [], required: q.required, placeholder: q.placeholder || '', help_text: q.help_text || '' }))
        : [{ id: null, label: '', type: 'text', options: [], required: false, placeholder: '', help_text: '' }],
});

function addRecipientEmail() {
    formData.recipient_emails.push('');
}

function removeRecipientEmail(index) {
    formData.recipient_emails.splice(index, 1);
    if (!formData.recipient_emails.length) {
        formData.recipient_emails.push('');
    }
}

function addQuestion() {
    formData.questions.push({ id: null, label: '', type: 'text', options: [], required: false, placeholder: '', help_text: '' });
}

function removeQuestion(index) {
    if (formData.questions.length <= 1) return;
    formData.questions.splice(index, 1);
}

function onTypeChange(q) {
    if (!['select', 'radio', 'checkbox'].includes(q.type)) {
        q.options = [];
    }
    if (!q.options?.length) {
        q.options = [''];
    }
}

function addOption(q) {
    if (!q.options) q.options = [];
    q.options.push('');
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
    busy.saving = true;
    const payload = {
        ...formData,
        back_to: props.backTo ?? '',
        price: formData.price === '' ? null : formData.price,
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

    if (isEditing) {
        router.put(route('admin.forms.update', props.form.id), payload, {
            forceFormData: true,
            onFinish: () => { busy.saving = false; },
        });
    } else {
        router.post(route('admin.forms.store'), payload, {
            forceFormData: true,
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

        <div class="max-w-3xl mx-auto px-4 py-8 space-y-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ isEditing ? 'Edit Borang' : 'Borang Baru' }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Bina borang tersuai untuk program atau pendaftaran.</p>
                </div>
                <button
                    @click="showPreview = true"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    Pratonton
                </button>
            </div>

            <!-- Flash error -->
            <div v-if="$page.props.flash?.error" class="rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

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

                <!-- Bayaran & Terma (untuk borang pendaftaran event) -->
                <div class="pt-2 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Harga (RM)</label>
                        <input v-model="formData.price" type="number" min="0" step="0.01" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300" placeholder="cth. 50.00 (kosong = percuma)" />
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" v-model="formData.payment_required" class="rounded border-gray-300" /> Wajib bayar
                        </label>
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

                <!-- Recipient emails -->
                <div class="pt-2 border-t border-gray-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Emel Penerima Borang</p>
                            <p class="text-xs text-gray-400 mt-0.5">Hantar borang ke emel ini melalui e-mel. (Opsional)</p>
                        </div>
                        <button @click="addRecipientEmail" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Emel</button>
                    </div>
                    <div class="mt-2 space-y-2">
                        <div v-for="(email, ei) in formData.recipient_emails" :key="ei" class="flex items-center gap-2">
                            <input
                                v-model="formData.recipient_emails[ei]"
                                type="email"
                                placeholder="contoh@email.com"
                                class="flex-1 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-300 placeholder:text-gray-300"
                            />
                            <button @click="removeRecipientEmail(ei)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Anda juga boleh hantar ke semua ahli organisasi dari senarai borang.</p>
                </div>
            </div>

            <!-- Questions -->
            <div class="rounded-3xl border border-white/60 bg-white/80 backdrop-blur-xl p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-500 uppercase tracking-wide">Soalan</h2>
                    <button @click="addQuestion" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">+ Tambah Soalan</button>
                </div>

                <div v-for="(q, idx) in formData.questions" :key="idx" class="border border-gray-100 rounded-2xl p-4 bg-gray-50/50 space-y-3">
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
                            <input v-model="q.label" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="Label soalan..." />
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
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-0.5">Teks Bantuan</label>
                            <input v-model="q.help_text" class="w-full rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:ring-0" placeholder="Penerangan tambahan..." />
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-xs">
                        <input type="checkbox" v-model="q.required" class="rounded border-gray-300" /> Wajib diisi
                    </label>

                    <!-- Options for select/radio/checkbox -->
                    <div v-if="['select', 'radio', 'checkbox'].includes(q.type)" class="space-y-1.5 pl-2 border-l-2 border-indigo-200">
                        <p class="text-xs font-semibold text-gray-500">Pilihan</p>
                        <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2">
                            <input v-model="q.options[oi]" class="flex-1 rounded-lg border border-gray-200 px-3 py-1 text-xs focus:ring-0" placeholder="Pilihan..." />
                            <button @click="removeOption(q, oi)" class="text-xs text-red-400 hover:text-red-600">✕</button>
                        </div>
                        <button @click="addOption(q)" class="text-xs text-indigo-500 hover:text-indigo-600">+ Tambah pilihan</button>
                    </div>
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
                            <p v-if="formData.price" class="mt-2 inline-flex items-center rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-2 text-sm font-bold text-emerald-700">
                                RM {{ Number(formData.price).toFixed(2) }}
                            </p>
                        </div>

                        <hr class="border-gray-100" />

                        <div v-for="(q, qi) in formData.questions" :key="qi" class="space-y-1.5">
                            <label class="block text-sm font-semibold text-gray-700">
                                {{ q.label || '(Tanpa label)' }}
                                <span v-if="q.required" class="text-red-500">*</span>
                            </label>
                            <p v-if="q.help_text" class="text-xs text-gray-400">{{ q.help_text }}</p>

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
                            {{ formData.price ? `Hantar & Bayar RM ${Number(formData.price).toFixed(2)}` : 'Hantar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

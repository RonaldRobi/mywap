<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    poll: { type: Object, default: null },
    organizations: { type: Array, default: () => [] },
    usrahGroups: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
});

const isEditing = computed(() => !!props.poll);

const form = useForm({
    organization_id: props.poll?.organization_id ?? '',
    title: props.poll?.title ?? '',
    description: props.poll?.description ?? '',
    type: props.poll?.type ?? 'poll',
    target_type: props.poll?.target_type ?? 'all',
    ends_at: props.poll?.ends_at ?? '',
    show_results: props.poll?.show_results ?? true,
    questions: props.poll?.questions ?? [
        { question_text: '', type: 'single_choice', options: [{ option_text: '' }, { option_text: '' }] },
    ],
    target_members: props.poll?.target_members ?? [],
    target_usrah_groups: props.poll?.target_usrah_groups ?? [],
});

function addQuestion() {
    form.questions.push({
        question_text: '',
        type: 'single_choice',
        options: [{ option_text: '' }, { option_text: '' }],
    });
}

function removeQuestion(index) {
    if (form.questions.length > 1) {
        form.questions.splice(index, 1);
    }
}

function addOption(qIndex) {
    form.questions[qIndex].options.push({ option_text: '' });
}

function removeOption(qIndex, oIndex) {
    const opts = form.questions[qIndex].options;
    if (opts.length > 2) {
        opts.splice(oIndex, 1);
    }
}

function submitForm() {
    if (isEditing.value) {
        form.put(route('admin.polls.update', props.poll.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.polls.store'), {
            preserveScroll: true,
        });
    }
}

function toggleMember(id) {
    const idx = form.target_members.indexOf(id);
    if (idx === -1) {
        form.target_members.push(id);
    } else {
        form.target_members.splice(idx, 1);
    }
}

function toggleUsrah(id) {
    const idx = form.target_usrah_groups.indexOf(id);
    if (idx === -1) {
        form.target_usrah_groups.push(id);
    } else {
        form.target_usrah_groups.splice(idx, 1);
    }
}

const searchQuery = ref('');
const filteredMembers = computed(() => {
    if (!searchQuery.value) return props.members;
    const q = searchQuery.value.toLowerCase();
    return props.members.filter(m =>
        m.name.toLowerCase().includes(q) || m.email.toLowerCase().includes(q)
    );
});
</script>

<template>
    <Head :title="isEditing ? 'Edit Undian' : 'Cipta Undian'" />

    <AppLayout>
        <template #header>{{ isEditing ? 'Edit Undian' : 'Cipta Undian' }}</template>

        <div class="mx-auto max-w-4xl px-4 py-6 md:px-6">
            <form @submit.prevent="submitForm" class="space-y-6">
                <!-- Basic Info -->
                <section class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-4">
                    <h2 class="text-lg font-black text-gray-800">Maklumat Asas</h2>

                    <div v-if="organizations.length" class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Organisasi</label>
                            <select v-model="form.organization_id" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                <option value="">Pilih Organisasi</option>
                                <option v-for="org in organizations" :key="org.id" :value="org.id">{{ org.name }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</label>
                        <input v-model="form.title" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required />
                        <div v-if="form.errors.title" class="mt-1 text-xs text-red-500">{{ form.errors.title }}</div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Penerangan (opsyenal)</label>
                        <textarea v-model="form.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Jenis</label>
                            <div class="flex gap-2">
                                <button type="button" @click="form.type = 'poll'"
                                    :class="['flex-1 rounded-xl border px-3 py-2 text-sm font-semibold', form.type === 'poll' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']">
                                    Poll
                                </button>
                                <button type="button" @click="form.type = 'survey'"
                                    :class="['flex-1 rounded-xl border px-3 py-2 text-sm font-semibold', form.type === 'survey' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']">
                                    Survey
                                </button>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Tarikh Tamat (opsyenal)</label>
                            <input v-model="form.ends_at" type="datetime-local" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" />
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Sasaran</label>
                        <div class="flex gap-2">
                            <button type="button" @click="form.target_type = 'all'"
                                :class="['flex-1 rounded-xl border px-3 py-2 text-sm font-semibold', form.target_type === 'all' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']">
                                Semua Ahli
                            </button>
                            <button type="button" @click="form.target_type = 'members'"
                                :class="['flex-1 rounded-xl border px-3 py-2 text-sm font-semibold', form.target_type === 'members' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']">
                                Ahli Tertentu
                            </button>
                            <button type="button" @click="form.target_type = 'usrah'"
                                :class="['flex-1 rounded-xl border px-3 py-2 text-sm font-semibold', form.target_type === 'usrah' ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']">
                                Usrah Group
                            </button>
                        </div>
                    </div>

                    <div v-if="form.target_type === 'usrah'" class="rounded-xl border border-gray-200 p-4">
                        <label class="mb-2 block text-xs font-semibold text-gray-500">Pilih Usrah Group</label>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="g in usrahGroups" :key="g.id" type="button" @click="toggleUsrah(g.id)"
                                :class="['rounded-lg border px-3 py-1.5 text-xs font-semibold', form.target_usrah_groups.includes(g.id) ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 text-gray-600']"
                            >{{ g.name }}</button>
                            <p v-if="!usrahGroups.length" class="text-xs text-gray-400">Tiada usrah group.</p>
                        </div>
                    </div>

                    <div v-if="form.target_type === 'members'" class="rounded-xl border border-gray-200 p-4">
                        <label class="mb-2 block text-xs font-semibold text-gray-500">Pilih Ahli</label>
                        <input v-model="searchQuery" type="text" placeholder="Cari ahli..." class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-xs" />
                        <div class="max-h-48 overflow-y-auto space-y-1">
                            <button
                                v-for="m in filteredMembers" :key="m.id" type="button" @click="toggleMember(m.id)"
                                :class="['w-full text-left rounded-lg px-3 py-1.5 text-xs font-medium', form.target_members.includes(m.id) ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-50']"
                            >{{ m.name }} <span class="text-gray-400">({{ m.email }})</span></button>
                            <p v-if="!filteredMembers.length" class="text-xs text-gray-400">Tiada ahli.</p>
                        </div>
                        <p class="mt-2 text-xs text-gray-500">{{ form.target_members.length }} ahli dipilih.</p>
                    </div>

                    <label class="flex items-center gap-2">
                        <input v-model="form.show_results" type="checkbox" class="rounded border-gray-300" />
                        <span class="text-sm text-gray-700">Tunjukkan keputusan kepada ahli selepas menjawab</span>
                    </label>
                </section>

                <!-- Questions Builder -->
                <section v-for="(q, qi) in form.questions" :key="qi" class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-800">Soalan {{ qi + 1 }}</h3>
                        <button v-if="form.questions.length > 1" type="button" @click="removeQuestion(qi)" class="text-xs font-semibold text-red-500">Padam Soalan</button>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Teks Soalan</label>
                            <input v-model="q.question_text" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" required />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Jenis Jawapan</label>
                            <select v-model="q.type" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                <option value="single_choice">Pilih Satu</option>
                                <option value="multiple_choice">Pilih Pelbagai</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Pilihan Jawapan</label>
                        <div class="space-y-2">
                            <div v-for="(opt, oi) in q.options" :key="oi" class="flex items-center gap-2">
                                <input v-model="opt.option_text" type="text" class="flex-1 rounded-lg border border-gray-200 px-3 py-1.5 text-sm" placeholder="Pilihan {{ oi + 1 }}" required />
                                <button v-if="q.options.length > 2" type="button" @click="removeOption(qi, oi)" class="text-gray-400 hover:text-red-500">&times;</button>
                            </div>
                        </div>
                        <button type="button" @click="addOption(qi)" class="mt-2 text-xs font-semibold text-gray-500 hover:text-gray-800">+ Tambah Pilihan</button>
                    </div>
                </section>

                <div>
                    <button type="button" @click="addQuestion" class="mb-4 rounded-xl border border-dashed border-gray-300 px-4 py-2 text-sm font-semibold text-gray-500 hover:border-gray-900 hover:text-gray-900">
                        + Tambah Soalan
                    </button>
                </div>

                <div class="flex items-center gap-4">
                    <a :href="route('admin.polls.index')" class="rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700">Batal</a>
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
                        {{ form.processing ? 'Menyimpan...' : (isEditing ? 'Kemas Kini' : 'Cipta Undian') }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

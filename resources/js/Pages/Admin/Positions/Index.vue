<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({ positions: Array });

const editing = ref(null);
const form = useForm({ name: '', display_order: 0 });
const editForm = useForm({ name: '', display_order: 0 });

function submit() {
    form.post(route('positions.store'), { preserveScroll: true, onSuccess: () => form.reset() });
}

function startEdit(pos) {
    editing.value = pos.id;
    editForm.name = pos.name;
    editForm.display_order = pos.display_order;
}

function saveEdit(pos) {
    editForm.put(route('positions.update', pos.id), { preserveScroll: true, onSuccess: () => { editing.value = null; editForm.reset(); } });
}

function cancelEdit() {
    editing.value = null;
    editForm.reset();
}

function destroy(pos) {
    if (confirm(`Padam jawatan "${pos.name}"?`)) {
        useForm({}).delete(route('positions.destroy', pos.id), { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Jawatan Organisasi" />
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Jawatan Organisasi</template>
        <div class="mx-auto max-w-4xl px-4 py-6">
            <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">Senarai Jawatan</h3>
                <p class="mt-1 text-xs text-gray-400">Jawatan ini akan dipaparkan dalam borang profil ahli.</p>

                <div class="mt-4 space-y-2">
                    <div v-for="pos in positions" :key="pos.id" class="flex items-center gap-3 rounded-xl border border-gray-100 px-4 py-3">
                        <template v-if="editing === pos.id">
                            <input v-model="editForm.name" class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-indigo-400 focus:ring-0">
                            <input v-model="editForm.display_order" type="number" class="w-16 rounded-lg border border-gray-200 px-2 py-2 text-sm text-center focus:border-indigo-400 focus:ring-0">
                            <button @click="saveEdit(pos)" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Simpan</button>
                            <button @click="cancelEdit" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50">Batal</button>
                        </template>
                        <template v-else>
                            <span class="flex-1 text-sm font-medium text-gray-700">{{ pos.name }}</span>
                            <span class="text-xs text-gray-400">#{{ pos.display_order }}</span>
                            <button @click="startEdit(pos)" class="text-xs text-indigo-600 hover:underline">Edit</button>
                            <button @click="destroy(pos)" class="text-xs text-red-500 hover:underline">Padam</button>
                        </template>
                    </div>
                    <p v-if="!positions.length" class="py-4 text-center text-sm text-gray-400">Belum ada jawatan. Tambah jawatan baru di bawah.</p>
                </div>

                <form @submit.prevent="submit" class="mt-6 flex items-center gap-3 border-t border-gray-100 pt-4">
                    <input v-model="form.name" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:border-indigo-400 focus:ring-0" placeholder="Nama jawatan" required>
                    <input v-model="form.display_order" type="number" class="w-20 rounded-xl border border-gray-200 px-3 py-2.5 text-sm text-center focus:border-indigo-400 focus:ring-0" placeholder="Turutan">
                    <button type="submit" :disabled="form.processing" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">Tambah</button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>

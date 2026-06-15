<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';

const props = defineProps({
    polls: { type: Object, required: true },
});

const confirmRef = ref(null);
const deletingId = ref(null);

function confirmDelete(id) {
    deletingId.value = id;
    confirmRef.value.show();
}

function handleDelete() {
    if (!deletingId.value) return;
    router.delete(route('admin.polls.destroy', deletingId.value), {
        preserveScroll: true,
        onSuccess: () => { deletingId.value = null; },
    });
}
</script>

<template>
    <Head title="Pengurusan Undian" />

    <AppLayout>
        <template #header>Pengurusan Undian</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div class="flex items-center justify-between">
                <h2 class="text-lg font-black text-gray-800">Senarai Undian</h2>
                <a :href="route('admin.polls.create')" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Cipta Undian</a>
            </div>

            <div class="rounded-3xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div v-if="!polls.data?.length" class="p-10 text-center text-sm text-gray-500">Belum ada undian.</div>

                <table v-else class="w-full text-sm">
                    <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-semibold uppercase text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Tajuk</th>
                            <th class="px-5 py-3">Jenis</th>
                            <th class="px-5 py-3">Sasaran</th>
                            <th class="px-5 py-3">Tamat</th>
                            <th class="px-5 py-3">Respon</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="poll in polls.data" :key="poll.id" class="hover:bg-gray-50">
                            <td class="px-5 py-4 font-semibold text-gray-900">{{ poll.title }}</td>
                            <td class="px-5 py-4">
                                <span :class="['rounded-full px-2 py-0.5 text-xs font-semibold', poll.type === 'poll' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700']">
                                    {{ poll.type === 'poll' ? 'Poll' : 'Survey' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-xs text-gray-500 capitalize">{{ poll.target_type === 'all' ? 'Semua' : poll.target_type }}</td>
                            <td class="px-5 py-4 text-xs text-gray-500">{{ poll.ends_at_formatted || '-' }}</td>
                            <td class="px-5 py-4 text-xs text-gray-500">{{ poll.response_count }}</td>
                            <td class="px-5 py-4">
                                <span v-if="poll.is_expired" class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Tamat</span>
                                <span v-else class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Aktif</span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1">
                                    <a :href="route('admin.polls.results', poll.id)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">Keputusan</a>
                                    <a v-if="poll.response_count === 0" :href="route('admin.polls.edit', poll.id)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">Edit</a>
                                    <a :href="route('admin.polls.export', poll.id)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-gray-600 hover:bg-gray-100">CSV</a>
                                    <button @click="confirmDelete(poll.id)" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-red-600 hover:bg-red-50">Padam</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="polls.last_page > 1" class="flex items-center justify-center gap-2">
                <a v-for="link in polls.links" :key="link.label" :href="link.url"
                    :class="['rounded-lg px-3 py-1.5 text-sm', link.active ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100']"
                    v-html="link.label"
                />
            </div>

            <ConfirmDialog
                ref="confirmRef"
                title="Padam Undian?"
                message="Adakah anda pasti mahu memadam undian ini? Tindakan ini tidak boleh dibatalkan."
                @confirm="handleDelete"
            />
        </div>
    </AppLayout>
</template>

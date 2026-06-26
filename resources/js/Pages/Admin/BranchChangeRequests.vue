<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    requests: Array,
    branches: Array,
});

const user = usePage().props.auth.user;
const showRejectModal = ref(false);
const rejectTarget = ref(null);
const rejectForm = useForm({ rejection_reason: '' });

function openReject(r) {
    rejectTarget.value = r;
    rejectForm.rejection_reason = '';
    showRejectModal.value = true;
}

function submitReject() {
    rejectForm.post(route('branch-change-requests.reject', rejectTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRejectModal.value = false;
            rejectTarget.value = null;
        },
    });
}

function approve(r) {
    if (!confirm(`Luluskan pertukaran cawangan untuk ${r.user.name}?`)) return;
    useForm({}).post(route('branch-change-requests.approve', r.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard" title="Permohonan Tukar Cawangan">
        <Head title="Permohonan Tukar Cawangan" />

        <template #header>
            Permohonan Tukar Cawangan
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Permohonan Tukar Cawangan</h2>
            </div>

            <div v-if="requests.length === 0" class="rounded-2xl border border-dashed border-gray-200 bg-white p-12 text-center">
                <p class="text-gray-500">Tiada permohonan yang menunggu kelulusan.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="r in requests"
                    :key="r.id"
                    class="rounded-2xl border border-gray-100 bg-white p-4 md:p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-gray-900">{{ r.user.name }}</p>
                            <p class="text-xs text-gray-500">{{ r.user.email }} · {{ r.user.member_no }}</p>
                            <div class="mt-2 flex items-center gap-2 text-sm">
                                <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">
                                    {{ r.from_branch || 'Tiada Cawangan' }}
                                </span>
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                                <span class="rounded-lg bg-indigo-100 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                    {{ r.to_branch }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-gray-400">Dihantar {{ r.submitted_at }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button
                                @click="approve(r)"
                                class="rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition"
                            >
                                Approve
                            </button>
                            <button
                                @click="openReject(r)"
                                class="rounded-xl border border-red-200 bg-white px-4 py-2 text-xs font-bold text-red-600 hover:bg-red-50 transition"
                            >
                                Reject
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reject Modal -->
        <div v-if="showRejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showRejectModal = false">
            <div class="mx-4 w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <h3 class="text-lg font-bold text-gray-900">Tolak Permohonan</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Tolak pertukaran cawangan untuk <strong>{{ rejectTarget?.user.name }}</strong>?
                </p>
                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-gray-500">Sebab (optional)</label>
                    <textarea
                        v-model="rejectForm.rejection_reason"
                        rows="3"
                        class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-900 focus:ring-0"
                        placeholder="Nyatakan sebab penolakan..."
                    ></textarea>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button @click="showRejectModal = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition">
                        Batal
                    </button>
                    <button @click="submitReject" :disabled="rejectForm.processing" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700 transition disabled:opacity-50">
                        {{ rejectForm.processing ? 'Memproses...' : 'Tolak' }}
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

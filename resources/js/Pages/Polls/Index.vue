<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    availablePolls: { type: Array, default: () => [] },
    answeredPolls: { type: Array, default: () => [] },
});

const tab = ref('available');

const items = computed(() =>
    tab.value === 'available' ? props.availablePolls : props.answeredPolls
);
</script>

<template>
    <Head title="Undian" />

    <AppLayout>
        <template #header>Undian</template>

        <div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div class="flex items-center gap-2 border-b border-gray-200 pb-3">
                <button
                    @click="tab = 'available'"
                    :class="['rounded-xl px-4 py-2 text-sm font-semibold transition', tab === 'available' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-800']"
                >Belum Dijawab ({{ availablePolls.length }})</button>
                <button
                    @click="tab = 'answered'"
                    :class="['rounded-xl px-4 py-2 text-sm font-semibold transition', tab === 'answered' ? 'bg-gray-900 text-white' : 'text-gray-500 hover:text-gray-800']"
                >Sudah Dijawab ({{ answeredPolls.length }})</button>
            </div>

            <div v-if="items.length === 0" class="rounded-3xl border border-gray-100 bg-white p-10 text-center text-sm text-gray-500">
                Tiada undian.
            </div>

            <div v-for="poll in items" :key="poll.id" class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span :class="['rounded-full px-2.5 py-0.5 text-xs font-semibold', poll.type === 'poll' ? 'bg-indigo-100 text-indigo-700' : 'bg-emerald-100 text-emerald-700']">
                                {{ poll.type === 'poll' ? 'Poll' : 'Survey' }}
                            </span>
                            <span v-if="poll.is_expired" class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700">Tamat</span>
                        </div>
                        <h3 class="text-base font-bold text-gray-900">{{ poll.title }}</h3>
                        <p v-if="poll.description" class="mt-1 text-sm text-gray-600 line-clamp-2">{{ poll.description }}</p>
                        <p class="mt-2 text-xs text-gray-400">
                            {{ poll.ends_at ? 'Tamat: ' + poll.ends_at_formatted : 'Tiada tarikh tamat' }}
                            &middot; {{ poll.response_count }} respon
                        </p>
                    </div>
                    <div class="shrink-0">
                        <a v-if="!poll.has_responded && !poll.is_expired"
                            :href="route('member.polls.show', poll.id)"
                            class="inline-flex items-center rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white"
                        >Jawab</a>
                        <a v-else-if="poll.show_results || poll.is_expired"
                            :href="route('member.polls.results', poll.id)"
                            class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700"
                        >Lihat Keputusan</a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

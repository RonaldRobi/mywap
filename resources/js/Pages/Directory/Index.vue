<script setup>
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { reactive } from 'vue';

const props = defineProps({
    users: Object,
    industries: Array,
    filters: Object,
});

const filters = reactive({
    search: props.filters?.search ?? '',
    industry: props.filters?.industry ?? '',
});

let timer = null;
function applySearch() {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(route('directory.index'), filters, { preserveState: true, replace: true });
    }, 250);
}

function applyFilter() {
    router.get(route('directory.index'), filters, { preserveState: true, replace: true });
}

function expertiseTags(value) {
    if (!value) return [];
    return value.split(',').map((v) => v.trim()).filter(Boolean);
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map((v) => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="Networking Directory" />

    <AppLayout>
        <template #header>Networking Directory</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-5">
            <section class="rounded-3xl border border-gray-100 bg-white/90 p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row">
                    <div class="flex-1">
                        <input
                            v-model="filters.search"
                            @input="applySearch"
                            type="text"
                            placeholder="Cari nama, industri, kepakaran..."
                            class="w-full rounded-2xl border border-gray-200 px-4 py-2.5 text-sm focus:ring-0 focus:border-gray-500"
                        >
                    </div>
                    <div class="sm:w-64">
                        <select v-model="filters.industry" @change="applyFilter" class="w-full rounded-2xl border border-gray-200 px-4 py-2.5 text-sm focus:ring-0 focus:border-gray-500">
                            <option value="">Semua Industri</option>
                            <option v-for="industry in industries" :key="industry" :value="industry">{{ industry }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                <article
                    v-for="member in users.data"
                    :key="member.id"
                    class="bg-white rounded-3xl shadow-sm border border-gray-100 hover:shadow-md transition p-5"
                >
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-700">
                            {{ initials(member.name) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black text-gray-800">{{ member.name }}</p>
                            <span
                                class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                :class="{
                                    'bg-indigo-50 text-indigo-700': member.organization?.slug === 'pkpim',
                                    'bg-emerald-50 text-emerald-700': member.organization?.slug === 'abim',
                                    'bg-amber-50 text-amber-700': member.organization?.slug === 'wadah',
                                }"
                            >
                                {{ member.organization?.name || 'Organization' }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <p class="text-xs text-gray-500">Industri: <span class="font-semibold text-gray-700">{{ member.industry || '—' }}</span></p>
                        <div class="flex flex-wrap gap-1.5">
                            <span
                                v-for="tag in expertiseTags(member.expertise)"
                                :key="tag"
                                class="bg-gray-50 text-gray-600 rounded-full px-2 py-1 text-xs"
                            >
                                {{ tag }}
                            </span>
                            <span v-if="!expertiseTags(member.expertise).length" class="bg-gray-50 text-gray-500 rounded-full px-2 py-1 text-xs">No expertise listed</span>
                        </div>
                    </div>

                    <a
                        v-if="member.linkedin_url"
                        :href="member.linkedin_url"
                        target="_blank"
                        class="mt-4 inline-flex items-center text-xs font-semibold text-blue-600 hover:text-blue-700"
                    >
                        View LinkedIn →
                    </a>
                </article>

                <div v-if="!users.data.length" class="col-span-full rounded-2xl bg-gray-50 px-4 py-10 text-center text-sm text-gray-500">
                    Tiada ahli ditemui berdasarkan carian semasa.
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    members: { type: Array, default: () => [] },
});

const createForm = useForm({
    name: '',
    description: '',
    meeting_day: '',
    meeting_time: '',
});

const assignForm = useForm({
    members: [],
});

const activeAssignGroupId = ref(null);

function submitGroup() {
    createForm.post(route('admin.usrah.groups.store'), {
        preserveScroll: true,
        onSuccess: () => createForm.reset(),
    });
}

function openAssign(groupId) {
    activeAssignGroupId.value = groupId;
    assignForm.members = [];
}

function toggleMember(userId, isChecked) {
    const exists = assignForm.members.find((item) => item.user_id === userId);
    if (isChecked && !exists) {
        assignForm.members.push({ user_id: userId, is_naqib: false });
    }
    if (!isChecked && exists) {
        assignForm.members = assignForm.members.filter((item) => item.user_id !== userId);
    }
}

function setNaqib(userId, isNaqib) {
    const entry = assignForm.members.find((item) => item.user_id === userId);
    if (entry) entry.is_naqib = isNaqib;
}

function submitAssign() {
    if (!activeAssignGroupId.value) return;
    assignForm.post(route('admin.usrah.groups.assign', activeAssignGroupId.value), {
        preserveScroll: true,
        onSuccess: () => {
            activeAssignGroupId.value = null;
            assignForm.reset();
        },
    });
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map((v) => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="Usrah Management" />

    <AppLayout>
        <template #header>Usrah / Halaqah Management</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Cipta Kumpulan Usrah</h2>
                <form class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2" @submit.prevent="submitGroup">
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Kumpulan</label>
                        <input v-model="createForm.name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-500" required>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Hari Mesyuarat</label>
                        <input v-model="createForm.meeting_day" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-500" placeholder="Contoh: Jumaat">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Masa</label>
                        <input v-model="createForm.meeting_time" type="time" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold text-gray-500">Deskripsi</label>
                        <textarea v-model="createForm.description" rows="3" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <button type="submit" :disabled="createForm.processing" class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                            {{ createForm.processing ? 'Menyimpan...' : 'Cipta Kumpulan' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Senarai Kumpulan</h2>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <article v-for="group in groups" :key="group.id" class="rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                        <h3 class="text-sm font-bold text-gray-800">{{ group.name }}</h3>
                        <p class="mt-1 text-xs text-gray-500">{{ group.meeting_day || 'Hari belum ditetapkan' }} · {{ group.meeting_time || 'Masa TBD' }}</p>
                        <p class="mt-2 text-sm text-gray-600 line-clamp-2">{{ group.description || 'Tiada deskripsi.' }}</p>
                        <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                            <span>{{ group.members_count }} ahli</span>
                            <span v-if="group.naqib_name" class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700">Naqib: {{ group.naqib_name }}</span>
                        </div>
                        <button @click="openAssign(group.id)" class="mt-3 w-full rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                            Assign Ahli
                        </button>
                    </article>
                </div>
            </section>

            <section v-if="activeAssignGroupId" class="rounded-3xl border border-gray-100 bg-white/90 p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Assign Ahli ke Kumpulan</h2>
                <p class="mt-1 text-xs text-gray-500">Tandakan ahli dan pilih Naqib.</p>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                    <label v-for="member in members" :key="member.id" class="rounded-2xl border border-gray-100 p-3 flex items-start gap-3">
                        <input type="checkbox" class="mt-1 rounded border-gray-300" @change="toggleMember(member.id, $event.target.checked)">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ member.name }}</p>
                            <p class="text-xs text-gray-500 truncate">{{ member.email }}</p>
                            <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-gray-600">
                                <input type="checkbox" class="rounded border-gray-300" @change="setNaqib(member.id, $event.target.checked)">
                                Set sebagai Naqib
                            </label>
                        </div>
                    </label>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button @click="submitAssign" :disabled="assignForm.processing" class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                        {{ assignForm.processing ? 'Menyimpan...' : 'Simpan Assign' }}
                    </button>
                    <button @click="activeAssignGroupId = null" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Tutup
                    </button>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

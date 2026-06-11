<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    group: {
        type: Object,
        default: null,
    },
    isNaqib: {
        type: Boolean,
        default: false,
    },
});

const logForm = useForm({});

function logAttendance() {
    if (!props.group) return;
    logForm.post(route('member.usrah.attendance.log', props.group.id), {
        preserveScroll: true,
    });
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map((v) => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="My Usrah" />

    <AppLayout>
        <template #header>My Usrah Group</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.info" class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-700">
                {{ $page.props.flash.info }}
            </div>

            <section v-if="group" class="rounded-3xl border border-gray-100 bg-white/90 p-6 shadow-sm">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Usrah Group</p>
                        <h2 class="mt-1 text-2xl font-black text-gray-800">{{ group.name }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ group.description || 'Tiada deskripsi.' }}</p>
                        <p class="mt-2 text-xs text-gray-500">{{ group.meeting_day || 'Hari TBD' }} · {{ group.meeting_time || 'Masa TBD' }}</p>
                    </div>
                    <div v-if="isNaqib" class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 w-full md:w-72">
                        <p class="text-xs font-semibold text-indigo-700">My Usrah Group</p>
                        <p class="mt-1 text-sm text-indigo-700">Jumlah ahli: <span class="font-black">{{ group.members.length }}</span></p>
                        <button @click="logAttendance" :disabled="logForm.processing" class="mt-3 w-full rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                            {{ logForm.processing ? 'Logging...' : 'Log Attendance' }}
                        </button>
                    </div>
                </div>

                <div class="mt-6">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Rakan Usrah</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <div
                            v-for="member in group.members"
                            :key="member.id"
                            class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5"
                        >
                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-gray-100 text-[10px] font-bold text-gray-700">
                                {{ initials(member.name) }}
                            </span>
                            <span class="text-xs font-semibold text-gray-700">{{ member.name }}</span>
                            <span v-if="member.is_naqib" class="text-[10px] rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700">Naqib</span>
                        </div>
                    </div>
                </div>
            </section>

            <section v-else class="rounded-3xl border border-gray-100 bg-white/90 p-6 shadow-sm text-center">
                <h2 class="text-xl font-black text-gray-800">Belum ada kumpulan usrah</h2>
                <p class="mt-2 text-sm text-gray-500">Hubungi pentadbir untuk dimasukkan ke kumpulan usrah.</p>
            </section>
        </div>
    </AppLayout>
</template>

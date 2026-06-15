<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref, computed } from 'vue';

const props = defineProps({
    groups: {
        type: Array,
        default: () => [],
    },
    attendanceHistory: {
        type: Array,
        default: () => [],
    },
});

const showAttendanceModal = ref(false);
const activeGroupId = ref(null);

const attendanceForm = useForm({
    session_date: new Date().toISOString().slice(0, 10),
    attendances: [],
});

const activeGroup = computed(() => props.groups.find(g => g.id === activeGroupId.value));

function openAttendanceModal(group) {
    activeGroupId.value = group.id;
    attendanceForm.session_date = new Date().toISOString().slice(0, 10);
    attendanceForm.attendances = group.members.map((m) => ({
        user_id: m.id,
        status: 'hadir',
        notes: '',
    }));
    showAttendanceModal.value = true;
}

function setAttendanceStatus(userId, status) {
    const entry = attendanceForm.attendances.find((a) => a.user_id === userId);
    if (entry) entry.status = status;
}

function submitAttendance() {
    if (!activeGroupId.value) return;
    attendanceForm.post(route('member.usrah.attendance.log', activeGroupId.value), {
        preserveScroll: true,
        onSuccess: () => {
            showAttendanceModal.value = false;
        },
    });
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map((v) => v[0]).join('').toUpperCase();
}

function roleBadge(role) {
    if (role === 'leader') return 'rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700';
    if (role === 'sub_leader') return 'rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700';
    return '';
}

function roleLabel(role) {
    if (role === 'leader') return 'Naqib';
    if (role === 'sub_leader') return 'Sub-Naqib';
    return '';
}

function statusBadge(status) {
    const map = { hadir: 'bg-emerald-100 text-emerald-700', tidak_hadir: 'bg-red-100 text-red-700', uzur: 'bg-amber-100 text-amber-700' };
    return map[status] || 'bg-gray-100 text-gray-700';
}

function statusLabel(status) {
    const map = { hadir: 'Hadir', tidak_hadir: 'Tidak Hadir', uzur: 'Uzur' };
    return map[status] || status;
}
</script>

<template>
    <Head title="My Usrah" />

    <AppLayout>
        <template #header>Kumpulan Usrah Saya</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">
            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <template v-if="groups.length">
                <section v-for="group in groups" :key="group.id" class="rounded-3xl border border-gray-100 bg-white/90 p-6 shadow-sm">
                    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Kumpulan Usrah</p>
                            <h2 class="mt-1 text-2xl font-black text-gray-800">{{ group.name }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ group.description || 'Tiada deskripsi.' }}</p>
                            <p class="mt-2 text-xs text-gray-500">{{ group.meeting_day || 'Hari TBD' }} · {{ group.meeting_time || 'Masa TBD' }}</p>
                        </div>
                        <div v-if="group.is_leader" class="rounded-2xl border border-indigo-100 bg-indigo-50 p-4 w-full md:w-72">
                            <p class="text-xs font-semibold text-indigo-700">Pengurusan Kumpulan</p>
                            <p class="mt-1 text-sm text-indigo-700">Jumlah ahli: <span class="font-black">{{ group.members.length }}</span></p>
                            <button @click="openAttendanceModal(group)" :disabled="attendanceForm.processing" class="mt-3 w-full rounded-xl bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                                {{ attendanceForm.processing ? 'Logging...' : 'Log Attendance' }}
                            </button>
                        </div>
                    </div>

                    <div class="mt-6">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Ahli Kumpulan</p>
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
                                <span v-if="roleLabel(member.role)" class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="roleBadge(member.role)">{{ roleLabel(member.role) }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Attendance History -->
                <section v-if="attendanceHistory.length" class="rounded-3xl border border-gray-100 bg-white/90 p-6 shadow-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Rekod Kehadiran</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <div
                            v-for="record in attendanceHistory"
                            :key="record.date"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold"
                            :class="statusBadge(record.status)"
                        >
                            {{ record.date }}: {{ statusLabel(record.status) }}
                        </div>
                    </div>
                </section>
            </template>

            <section v-else class="rounded-3xl border border-gray-100 bg-white/90 p-6 shadow-sm text-center">
                <h2 class="text-xl font-black text-gray-800">Belum ada kumpulan usrah</h2>
                <p class="mt-2 text-sm text-gray-500">Hubungi pentadbir untuk dimasukkan ke kumpulan usrah.</p>
            </section>
        </div>
    </AppLayout>

    <!-- Attendance Modal -->
    <Teleport to="body">
        <div v-if="showAttendanceModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
            <div class="mx-4 w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <h3 class="text-lg font-black text-gray-800">Log Kehadiran</h3>
                <p class="mt-1 text-xs text-gray-500">Rekod kehadiran untuk sesi usrah.</p>

                <div class="mt-4">
                    <label class="mb-1 block text-xs font-semibold text-gray-500">Tarikh Sesi</label>
                    <input v-model="attendanceForm.session_date" type="date" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0 focus:border-gray-500">
                </div>

                <div class="mt-4 max-h-64 space-y-2 overflow-y-auto">
                    <div v-for="attendance in attendanceForm.attendances" :key="attendance.user_id" class="rounded-xl border border-gray-100 bg-gray-50 p-3">
                        <p class="text-sm font-semibold text-gray-800">{{ activeGroup?.members.find(m => m.id === attendance.user_id)?.name }}</p>
                        <div class="mt-2 flex gap-2">
                            <button @click="setAttendanceStatus(attendance.user_id, 'hadir')" class="rounded-lg px-3 py-1 text-xs font-semibold" :class="attendance.status === 'hadir' ? 'bg-emerald-600 text-white' : 'bg-gray-200 text-gray-600'">
                                Hadir
                            </button>
                            <button @click="setAttendanceStatus(attendance.user_id, 'tidak_hadir')" class="rounded-lg px-3 py-1 text-xs font-semibold" :class="attendance.status === 'tidak_hadir' ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-600'">
                                Tidak Hadir
                            </button>
                            <button @click="setAttendanceStatus(attendance.user_id, 'uzur')" class="rounded-lg px-3 py-1 text-xs font-semibold" :class="attendance.status === 'uzur' ? 'bg-amber-600 text-white' : 'bg-gray-200 text-gray-600'">
                                Uzur
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button @click="submitAttendance" :disabled="attendanceForm.processing" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60">
                        {{ attendanceForm.processing ? 'Menyimpan...' : 'Simpan Kehadiran' }}
                    </button>
                    <button @click="showAttendanceModal = false" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

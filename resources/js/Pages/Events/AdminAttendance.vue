<template>
  <div class="py-8 px-4 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Kehadiran Program</h1>
    <div class="mb-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-sm font-medium text-gray-700">Tarikh Mula</label>
        <input type="date" v-model="filters.start" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Tarikh Tamat</label>
        <input type="date" v-model="filters.end" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700">Status</label>
        <select v-model="filters.status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
          <option value="">Semua</option>
          <option value="hadir">Hadir</option>
          <option value="tidak_hadir">Tidak Hadir</option>
        </select>
      </div>
      <button @click="fetchAttendance" class="bg-emerald-600 text-white px-4 py-2 rounded-md font-semibold hover:bg-emerald-700">Tapis</button>
    </div>
    <div class="overflow-x-auto bg-white rounded-xl shadow">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Program</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tarikh</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lokasi</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jumlah Hadir</th>
            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(event, i) in filteredEvents" :key="event.id" class="hover:bg-gray-50">
            <td class="px-4 py-2">{{ i + 1 }}</td>
            <td class="px-4 py-2 font-semibold">{{ event.title }}</td>
            <td class="px-4 py-2">{{ event.start_formatted }}</td>
            <td class="px-4 py-2">{{ event.location_or_link || '—' }}</td>
            <td class="px-4 py-2">{{ event.attendance_count }}</td>
            <td class="px-4 py-2">
              <a :href="route('events.qr', { event: event.id })" class="inline-flex items-center gap-1 text-emerald-600 hover:underline mr-2">QR</a>
              <a :href="route('events.print', { event: event.id })" target="_blank" class="inline-flex items-center gap-1 text-gray-600 hover:underline">Senarai</a>
            </td>
          </tr>
          <tr v-if="filteredEvents.length === 0">
            <td colspan="6" class="px-4 py-8 text-center text-gray-400">Tiada program dijumpai untuk tapisan ini.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  adminAttendance: {
    type: Array,
    default: () => []
  }
});

const filters = ref({
  start: '',
  end: '',
  status: ''
});

const fetchAttendance = () => {
  router.reload({
    only: ['adminAttendance'],
    data: {
      start: filters.value.start,
      end: filters.value.end,
      status: filters.value.status
    }
  });
};

const filteredEvents = computed(() => {
  return props.adminAttendance;
});
</script>

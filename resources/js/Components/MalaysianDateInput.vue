<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    id: {
        type: String,
        default: 'dob',
    },
});

const emit = defineEmits(['update:modelValue']);
const days = Array.from({ length: 31 }, (_, index) => index + 1);
const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: currentYear - 1899 }, (_, index) => currentYear - index);
const day = ref('');
const month = ref('');
const year = ref('');

const formattedValue = computed(() => {
    if (!day.value || !month.value || !year.value) return '';
    return `${day.value} ${months[Number(month.value) - 1]} ${year.value}`;
});

function syncFromValue(value) {
    if (!value) {
        day.value = '';
        month.value = '';
        year.value = '';
        return;
    }

    const match = String(value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return;
    year.value = match[1];
    month.value = String(Number(match[2]));
    day.value = String(Number(match[3]));
}

function emitValue() {
    if (!day.value || !month.value || !year.value || year.value.length !== 4) {
        emit('update:modelValue', '');
        return;
    }

    const numericYear = Number(year.value);
    const numericMonth = Number(month.value);
    const numericDay = Number(day.value);
    const date = new Date(numericYear, numericMonth - 1, numericDay);

    if (
        date.getFullYear() !== numericYear
        || date.getMonth() !== numericMonth - 1
        || date.getDate() !== numericDay
    ) {
        emit('update:modelValue', '');
        return;
    }

    emit('update:modelValue', `${numericYear}-${String(numericMonth).padStart(2, '0')}-${String(numericDay).padStart(2, '0')}`);
}

watch(() => props.modelValue, syncFromValue, { immediate: true });
watch([day, month, year], emitValue);
</script>

<template>
    <div class="space-y-2">
        <div class="grid grid-cols-[0.8fr_1.4fr_1fr] gap-2">
            <select v-model="day" :id="`${id}_day`" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-[#2F6B32] focus:ring-[#6FBF8A]" aria-label="Hari">
                <option value="">Hari</option>
                <option v-for="value in days" :key="value" :value="String(value)">{{ String(value).padStart(2, '0') }}</option>
            </select>
            <select v-model="month" :id="`${id}_month`" class="w-full rounded-xl border border-gray-300 bg-white px-3 py-3 text-sm focus:border-[#2F6B32] focus:ring-[#6FBF8A]" aria-label="Bulan">
                <option value="">Bulan</option>
                <option v-for="(value, index) in months" :key="value" :value="String(index + 1)">{{ value }}</option>
            </select>
            <input v-model="year" :id="`${id}_year`" type="text" inputmode="numeric" maxlength="4" placeholder="1994" class="w-full rounded-xl border border-gray-300 px-3 py-3 text-sm focus:border-[#2F6B32] focus:ring-[#6FBF8A]" aria-label="Tahun">
        </div>
        <p v-if="formattedValue" class="text-xs text-gray-500">{{ formattedValue }}</p>
        <p v-else class="text-xs text-gray-400">Pilih hari dan bulan, kemudian masukkan tahun.</p>
    </div>
</template>

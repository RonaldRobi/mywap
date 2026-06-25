<script setup>
import { ref, watch, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Object,
        default: null,
    },
    placeholder: {
        type: String,
        default: 'Cari nama atau no ahli...',
    },
    inputClass: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const query = ref('');
const results = ref([]);
const loading = ref(false);
const selected = ref(props.modelValue);
const showDropdown = ref(false);
let debounceTimer = null;

const hasSelection = computed(() => selected.value !== null);

function onInput(val) {
    query.value = val;
    if (hasSelection.value) {
        selected.value = null;
        emit('update:modelValue', null);
    }
    clearTimeout(debounceTimer);
    if (val.length < 2) {
        results.value = [];
        showDropdown.value = false;
        return;
    }
    debounceTimer = setTimeout(() => search(val), 300);
}

function search(val) {
    loading.value = true;
    axios.get('/api/members/search', { params: { q: val } })
        .then((res) => {
            results.value = res.data;
            showDropdown.value = results.value.length > 0;
        })
        .catch(() => {
            results.value = [];
        })
        .finally(() => {
            loading.value = false;
        });
}

function select(member) {
    selected.value = member;
    query.value = member.name + ' (' + member.member_no + ')';
    showDropdown.value = false;
    emit('update:modelValue', member);
}

function clearSelection() {
    selected.value = null;
    query.value = '';
    results.value = [];
    showDropdown.value = false;
    emit('update:modelValue', null);
}

function onBlur() {
    setTimeout(() => { showDropdown.value = false; }, 200);
}

function onFocus() {
    if (results.value.length > 0) {
        showDropdown.value = true;
    }
}
</script>

<template>
    <div class="relative">
        <div class="relative">
            <input
                :value="query"
                @input="onInput($event.target.value)"
                @blur="onBlur"
                @focus="onFocus"
                :placeholder="placeholder"
                type="text"
                class="mt-1 block w-full rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-white placeholder:text-slate-300/80 focus:border-cyan-300 focus:ring-cyan-300"
                :class="[inputClass]"
            >
            <button
                v-if="hasSelection"
                @click="clearSelection"
                type="button"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition-colors"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </button>
            <div v-else-if="loading" class="absolute right-3 top-1/2 -translate-y-1/2">
                <svg class="h-4 w-4 animate-spin text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
            </div>
        </div>
        <ul
            v-if="showDropdown"
            class="absolute z-50 mt-1 w-full rounded-xl border border-white/15 bg-slate-900 py-1 shadow-xl max-h-48 overflow-y-auto"
        >
            <li
                v-for="member in results"
                :key="member.id"
                @mousedown.prevent="select(member)"
                class="flex cursor-pointer items-center justify-between px-4 py-2.5 text-sm text-slate-200 hover:bg-white/10 transition-colors"
            >
                <span>{{ member.name }}</span>
                <span class="text-xs text-slate-400 font-mono">{{ member.member_no }}</span>
            </li>
        </ul>
    </div>
</template>

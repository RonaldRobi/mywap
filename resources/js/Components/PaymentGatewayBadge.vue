<script setup>
// Branding payment gateway — data dibekalkan dari backend (config/payment-gateways.php).
// Komponen ini TIDAK hardcode sebarang nama/logo gateway; ia hanya paparkan
// apa yang dihantar sebagai prop, jadi gateway masa hadapan cukup dengan config.
const props = defineProps({
    gateway: {
        type: Object,
        default: () => ({}),
    },
});
</script>

<template>
    <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3">
        <!-- Logo (jika dikonfigur) -->
        <div v-if="gateway.logo" class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-white p-1.5 ring-1 ring-gray-100">
            <img :src="gateway.logo" :alt="gateway.name" class="h-full w-full object-contain" />
        </div>

        <!-- Badge teks (fallback jika tiada logo) -->
        <div v-else class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-900 text-white">
            <span class="text-[11px] font-black uppercase tracking-tight">{{ (gateway.name ?? 'GW').slice(0, 4) }}</span>
        </div>

        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-gray-900">{{ gateway.tagline ?? gateway.name }}</p>
            <p v-if="gateway.methods" class="text-xs text-gray-500">{{ gateway.methods }}</p>
        </div>
    </div>
</template>

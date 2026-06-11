<script setup>
const props = defineProps({
    campaign: {
        type: Object,
        required: true,
    },
});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}
</script>

<template>
    <article class="rounded-2xl border border-gray-100 bg-white/90 backdrop-blur-sm p-4 shadow-sm">
        <div class="flex items-start justify-between gap-2">
            <h4 class="text-sm font-bold text-gray-800 leading-snug">{{ campaign.title }}</h4>
            <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-700">
                Infaq
            </span>
        </div>

        <p class="mt-1 text-xs text-gray-500 line-clamp-2">{{ campaign.description || 'Kempen sokongan komuniti semasa.' }}</p>

        <div class="mt-3">
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100">
                <div
                    class="h-2 rounded-full bg-gradient-to-r from-emerald-400 to-teal-500 transition-all"
                    :style="{ width: `${campaign.progress_percent ?? 0}%` }"
                />
            </div>
            <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500">
                <span class="font-semibold text-gray-700">{{ campaign.progress_percent ?? 0 }}%</span>
                <span>{{ formatCurrency(campaign.current_amount) }} / {{ formatCurrency(campaign.target_amount) }}</span>
            </div>
        </div>
    </article>
</template>

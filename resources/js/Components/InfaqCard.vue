<script setup>
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    infaq: { type: Object, required: true },
    listView: { type: Boolean, default: false },
});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', { style: 'currency', currency: 'MYR', maximumFractionDigits: 2 }).format(value ?? 0);
}
</script>

<template>
    <template v-if="listView">
        <Link :href="infaq.public_url" class="group flex items-center gap-5 bg-white rounded-2xl border border-slate-100 p-4 hover:shadow-lg hover:shadow-emerald-900/5 transition-all duration-300">
            <div class="w-28 h-20 shrink-0 rounded-xl bg-slate-100 overflow-hidden">
                <img v-if="infaq.image_path" :src="infaq.image_path" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" alt="Cover" />
                <div v-else class="w-full h-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center">
                    <svg class="w-6 h-6 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span v-if="infaq.organization_name" class="text-[10px] font-bold text-slate-400 uppercase tracking-wide">
                        <svg class="h-3 w-3 inline -mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                        {{ infaq.organization_name }}
                    </span>
                    <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-bold text-emerald-700 uppercase">
                        {{ infaq.type === 'progress' ? 'Kutip Dana' : 'Bebas' }}
                    </span>
                </div>
                <h3 class="text-base font-black text-slate-900 group-hover:text-emerald-600 transition line-clamp-1 mb-1">{{ infaq.title }}</h3>
                <p v-if="infaq.days_running" class="text-xs text-slate-500">{{ infaq.days_running }} hari berjalan</p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Terkumpul</p>
                <p class="text-lg font-black text-emerald-600">{{ formatCurrency(infaq.collected_amount) }}</p>
                <template v-if="infaq.target_amount">
                    <div class="h-1.5 w-24 mt-1 overflow-hidden rounded-full bg-slate-100 ml-auto">
                        <div class="h-full rounded-full bg-emerald-500" :style="{ width: (infaq.progress_percent || 0) + '%' }"></div>
                    </div>
                </template>
            </div>
        </Link>
    </template>

    <!-- Grid View -->
    <Link v-else :href="infaq.public_url" class="group flex flex-col bg-white rounded-3xl border border-slate-100 overflow-hidden hover:shadow-xl hover:shadow-emerald-900/5 transition-all duration-300 hover:-translate-y-1">
        <div class="aspect-[16/10] bg-slate-100 relative overflow-hidden">
            <img v-if="infaq.image_path" :src="infaq.image_path" class="w-full h-full object-cover transition duration-500 group-hover:scale-105" alt="Cover" />
            <div v-else class="w-full h-full bg-gradient-to-br from-emerald-500 to-emerald-700 flex items-center justify-center">
                <svg class="w-12 h-12 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-80"></div>
            <div class="absolute bottom-3 left-4">
                <span class="inline-flex items-center rounded-full bg-emerald-500/90 px-2 py-1 text-[10px] font-bold text-white uppercase tracking-wider backdrop-blur-sm shadow-sm">
                    {{ infaq.type === 'progress' ? 'Kutip Dana' : 'Bebas' }}
                </span>
            </div>
        </div>

        <div class="p-5 flex flex-col flex-1">
            <div v-if="infaq.organization_name" class="flex items-center text-xs font-bold text-slate-400 mb-2 gap-1 uppercase tracking-wide">
                <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                {{ infaq.organization_name }}
            </div>

            <h3 class="text-lg font-black text-slate-900 leading-tight mb-4 group-hover:text-emerald-600 transition line-clamp-2">{{ infaq.title }}</h3>

            <div class="mt-auto pt-4 border-t border-slate-50 space-y-3">
                <div class="flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Terkumpul</p>
                        <p class="text-base font-black text-emerald-600">{{ formatCurrency(infaq.collected_amount) }}</p>
                    </div>
                    <div class="text-right" v-if="infaq.target_amount">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Matlamat</p>
                        <p class="text-sm font-bold text-slate-700">{{ formatCurrency(infaq.target_amount) }}</p>
                    </div>
                </div>

                <div v-if="infaq.target_amount" class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                    <div class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-600 transition-all duration-700" :style="{ width: (infaq.progress_percent || 0) + '%' }"></div>
                </div>

                <div v-if="infaq.days_running || infaq.progress_percent" class="flex items-center justify-between text-xs text-slate-400">
                    <span v-if="infaq.days_running">{{ infaq.days_running }} hari berjalan</span>
                    <span v-else></span>
                    <span v-if="infaq.progress_percent">{{ infaq.progress_percent }}% tercapai</span>
                </div>
            </div>
        </div>
    </Link>
</template>

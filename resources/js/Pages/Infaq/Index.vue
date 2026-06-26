<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const systemLogo = computed(() => page.props.brand?.system_logo_path ?? null);

defineProps({
    infaqs: {
        type: Array,
        required: true,
    },
});

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 2,
    }).format(value ?? 0);
}
</script>

<template>
    <Head title="Semua Kempen Infaq" />

    <div class="min-h-screen bg-slate-50 font-sans pb-16 md:pb-8">
        <!-- Navigation -->
        <nav class="sticky top-0 z-50 w-full bg-white/80 backdrop-blur-md border-b border-slate-200">
            <div class="mx-auto max-w-5xl px-4 py-4 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-2 text-sm font-semibold text-slate-600 hover:text-emerald-600 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Kembali ke Laman Utama
                </Link>
                <img v-if="systemLogo" :src="systemLogo" alt="Logo" class="h-8 w-auto" />
                <span v-else class="text-sm font-black text-slate-800">myWAP</span>
            </div>
        </nav>

        <div class="mx-auto max-w-5xl px-4 py-8">
            <div class="mb-10 text-center md:text-left">
                <h1 class="text-3xl md:text-4xl font-black text-slate-900 mb-2">Kempen Infaq</h1>
                <p class="text-slate-500 font-semibold text-sm md:text-base">Sertai kami dalam menyumbang dan membantu mereka yang memerlukan.</p>
            </div>

            <div v-if="infaqs.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link 
                    v-for="item in infaqs" 
                    :key="item.id" 
                    :href="item.public_url" 
                    class="group flex flex-col bg-white rounded-3xl border border-slate-100 overflow-hidden hover:shadow-xl hover:shadow-emerald-900/5 transition-all duration-300"
                >
                    <div class="aspect-[16/10] bg-slate-100 relative overflow-hidden">
                        <img 
                            v-if="item.image_path" 
                            :src="item.image_path" 
                            class="w-full h-full object-cover transition duration-500 group-hover:scale-105" 
                            alt="Cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent opacity-80"></div>
                        <div class="absolute bottom-3 left-4">
                            <span class="inline-flex items-center rounded-full bg-emerald-500/90 px-2 py-1 text-[10px] font-bold text-white uppercase tracking-wider backdrop-blur-sm shadow-sm">
                                {{ item.type === 'progress' ? 'Kutip Dana' : 'Bebas' }}
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-5 flex flex-col flex-1">
                        <div class="flex items-center text-xs font-bold text-slate-400 mb-2 gap-1 uppercase tracking-wide">
                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path></svg>
                            {{ item.organization_name || 'Global' }}
                        </div>
                        
                        <h3 class="text-lg font-black text-slate-900 leading-tight mb-4 group-hover:text-emerald-600 transition">{{ item.title }}</h3>
                        
                        <div class="mt-auto pt-4 border-t border-slate-50 space-y-3">
                            <div class="flex justify-between items-end">
                                <div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Terkumpul</p>
                                    <p class="text-base font-black text-emerald-600">{{ formatCurrency(item.collected_amount) }}</p>
                                </div>
                                <div class="text-right" v-if="item.type === 'progress'">
                                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Matlamat</p>
                                    <p class="text-sm font-bold text-slate-700">{{ formatCurrency(item.target_amount) }}</p>
                                </div>
                            </div>

                            <div v-if="item.type === 'progress'" class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-emerald-500" :style="{ width: item.progress_percent + '%' }"></div>
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <div v-else class="text-center py-20 bg-white rounded-3xl border border-slate-100 shadow-sm">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="h-10 w-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-slate-900 mb-1">Tiada Kempen Infaq</h3>
                <p class="text-sm text-slate-500">Belum ada kempen infaq yang aktif buat masa ini.</p>
            </div>
        </div>
    </div>
</template>

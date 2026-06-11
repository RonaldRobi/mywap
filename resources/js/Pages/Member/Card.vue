<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import html2canvas from 'html2canvas';

const props = defineProps({
    card: {
        type: Object,
        required: true,
    },
});

function printAsPdf() {
    window.print();
}

async function downloadAsJpg() {
    const target = document.getElementById('membership-card');
    if (!target) return;

    const canvas = await html2canvas(target, {
        scale: 2,
        backgroundColor: '#ffffff',
        useCORS: true,
    });

    const link = document.createElement('a');
    link.download = `membership-card-${props.card.id}.jpg`;
    link.href = canvas.toDataURL('image/jpeg', 0.95);
    link.click();
}

async function shareCard() {
    if (navigator.share) {
        await navigator.share({
            title: 'myWAP Membership Card',
            text: 'Kad keahlian digital saya.',
            url: window.location.href,
        });
    }
}

function initials(name) {
    return (name || 'U').split(' ').slice(0, 2).map((v) => v[0]).join('').toUpperCase();
}
</script>

<template>
    <Head title="Kad Keahlian" />

    <AppLayout>
        <template #header>Kad Keahlian Digital</template>

        <div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-5">
            <div class="flex flex-wrap gap-2 print:hidden">
                <button @click="printAsPdf" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">View / Share as PDF</button>
                <button @click="downloadAsJpg" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Download as JPG</button>
                <button @click="shareCard" class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Share Link</button>
            </div>

            <div id="membership-card" class="mx-auto max-w-2xl rounded-3xl border border-gray-100 bg-white p-6 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl border border-gray-200 bg-gray-50">
                            <img
                                v-if="card.system_logo_path"
                                :src="card.system_logo_path"
                                alt="myWAP Logo"
                                class="h-8 w-8 object-contain"
                            >
                            <span v-else class="text-[10px] font-bold text-gray-400">MM</span>
                        </div>

                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-gray-400">myWAP</p>
                            <h1 class="mt-1 text-2xl font-black text-gray-800">Membership Card</h1>
                            <p class="mt-1 text-xs text-gray-500">Member since {{ card.member_since || '—' }}</p>
                        </div>
                    </div>

                    <div class="inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-xs font-semibold"
                        :class="{
                            'bg-indigo-50 text-indigo-700': card.organization?.slug === 'pkpim',
                            'bg-emerald-50 text-emerald-700': card.organization?.slug === 'abim',
                            'bg-amber-50 text-amber-700': card.organization?.slug === 'wadah',
                        }">
                        <img
                            v-if="card.organization?.logo_path"
                            :src="card.organization.logo_path"
                            :alt="card.organization?.name || 'Organization logo'"
                            class="h-5 w-5 rounded-full object-contain bg-white/70"
                        >
                        <span>
                            {{ card.organization?.name || 'Organization' }}
                        </span>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-[120px_1fr] items-start">
                    <div class="mx-auto sm:mx-0">
                        <img v-if="card.photo_url" :src="card.photo_url" alt="Profile" class="h-28 w-28 rounded-2xl object-cover border border-gray-200" />
                        <div v-else class="h-28 w-28 rounded-2xl bg-gray-100 border border-gray-200 flex items-center justify-center text-xl font-black text-gray-500">
                            {{ initials(card.name) }}
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Name</p>
                            <p class="font-semibold text-gray-800">{{ card.name }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Email</p>
                            <p class="font-semibold text-gray-800">{{ card.email || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Phone</p>
                            <p class="font-semibold text-gray-800">{{ card.phone || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Branch</p>
                            <p class="font-semibold text-gray-800">{{ card.branch_name || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Profession</p>
                            <p class="font-semibold text-gray-800">{{ card.profession || '—' }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wide text-gray-400">Locality</p>
                            <p class="font-semibold text-gray-800">{{ card.locality || '—' }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
@media print {
    .print\:hidden {
        display: none !important;
    }
}
</style>

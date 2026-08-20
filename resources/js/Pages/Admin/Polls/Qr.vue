<script setup>
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { ref } from 'vue';

const props = defineProps({
    poll: { type: Object, required: true },
    qrSvg: { type: String, required: true },
    publicUrl: { type: String, required: true },
});

const copied = ref(false);

function copyUrl() {
    if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(props.publicUrl).then(() => {
            copied.value = true;
            setTimeout(() => (copied.value = false), 2000);
        });
    }
}
</script>

<template>
    <Head :title="'QR - ' + poll.title" />

    <AppLayout :back-route="route('admin.polls.index')" back-label="Kembali">
        <template #header>QR Undian: {{ poll.title }}</template>

        <div class="mx-auto max-w-md px-4 py-6 md:px-6 space-y-5">
            <div class="rounded-3xl border border-gray-100 bg-white p-6 shadow-sm text-center">
                <p class="text-sm font-bold text-gray-900">Maklum Balas Peserta Program</p>
                <p class="mt-1 text-xs text-gray-500">Imbas kod QR untuk membuka halaman maklum balas tanpa perlu log masuk. Sesuai untuk dicetak pada poster program.</p>

                <div class="mx-auto mt-6 w-56 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm">
                    <div class="[&_svg]:block [&_svg]:mx-auto [&_svg]:h-auto [&_svg]:w-full [&_svg]:max-w-full" v-html="qrSvg"></div>
                </div>

                <div class="mt-5 flex items-center gap-2">
                    <code class="min-w-0 flex-1 rounded-lg bg-gray-50 px-3 py-2 text-left text-xs text-gray-600 break-all">{{ publicUrl }}</code>
                    <button @click="copyUrl" class="shrink-0 rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white">
                        {{ copied ? 'Disalin!' : 'Salin' }}
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-2 gap-2">
                    <a :href="route('admin.polls.qr.png', poll.id)" class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                        Muat Turun PNG
                    </a>
                    <a :href="publicUrl" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Pratonton
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    referralLink: String,
    memberNo: String,
    qrSvg: String,
    stats: Object,
    referredMembers: Array,
});

const copySuccess = ref(false);
const qrCopySuccess = ref(false);

function copyLink() {
    navigator.clipboard.writeText(props.referralLink).then(() => {
        copySuccess.value = true;
        setTimeout(() => { copySuccess.value = false; }, 2000);
    });
}

function copyQrLink() {
    navigator.clipboard.writeText(props.referralLink).then(() => {
        qrCopySuccess.value = true;
        setTimeout(() => { qrCopySuccess.value = false; }, 2000);
    });
}

function shareWhatsApp() {
    const text = encodeURIComponent(`Sertai myWAP! Daftar sebagai ahli di pautan ini:\n${props.referralLink}`);
    window.open(`https://wa.me/?text=${text}`, '_blank');
}

function shareTelegram() {
    const text = encodeURIComponent(`Sertai myWAP! Daftar sebagai ahli di pautan ini:\n${props.referralLink}`);
    window.open(`https://t.me/share/url?url=${encodeURIComponent(props.referralLink)}&text=${text}`, '_blank');
}

function shareFacebook() {
    window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.referralLink)}`, '_blank');
}

function shareTwitter() {
    const text = encodeURIComponent('Sertai myWAP! Daftar sekarang:');
    window.open(`https://twitter.com/intent/tweet?text=${text}&url=${encodeURIComponent(props.referralLink)}`, '_blank');
}

function shareEmail() {
    const subject = encodeURIComponent('Jemputan Mendaftar myWAP');
    const body = encodeURIComponent(`Assalamualaikum,\n\nSila daftar sebagai ahli myWAP melalui pautan di bawah:\n\n${props.referralLink}\n\nTerima kasih.`);
    window.open(`mailto:?subject=${subject}&body=${body}`, '_blank');
}

function formatDate(dateString) {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('ms-MY', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}
</script>

<template>
    <Head title="Jemput Ahli Baru" />

    <AppLayout>
        <template #header>Jemput Ahli Baru</template>

        <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 lg:px-8 space-y-6">
            <!-- Stats Cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="rounded-2xl border border-gray-100 bg-white p-5 text-center shadow-sm">
                    <p class="text-3xl font-black text-gray-900">{{ stats.total }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Jumlah Dijemput</p>
                </div>
                <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-5 text-center shadow-sm">
                    <p class="text-3xl font-black text-emerald-700">{{ stats.active }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-emerald-500">Aktif</p>
                </div>
                <div class="rounded-2xl border border-amber-100 bg-amber-50 p-5 text-center shadow-sm">
                    <p class="text-3xl font-black text-amber-700">{{ stats.pending }}</p>
                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-amber-500">Pending</p>
                </div>
            </div>

            <!-- Referral Link + QR -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-bold text-gray-900">Pautan Rujukan Anda</h3>
                    <p class="mt-1 text-xs text-gray-400">No Ahli: {{ memberNo }}</p>
                    <p class="mt-2 text-sm text-gray-600">Kongsi pautan di bawah untuk menjemput rakan mendaftar sebagai ahli.</p>

                    <div class="mt-4 flex gap-2">
                        <input
                            type="text"
                            :value="referralLink"
                            readonly
                            class="flex-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-700"
                        >
                        <button
                            type="button"
                            class="rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-gray-800"
                            @click="copyLink"
                        >
                            {{ copySuccess ? 'Disalin!' : 'Salin' }}
                        </button>
                    </div>

                    <div class="mt-5">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mb-3">Kongsi melalui</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                style="background-color: #25D366"
                                @click="shareWhatsApp"
                                title="WhatsApp"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                style="background-color: #0088cc"
                                @click="shareTelegram"
                                title="Telegram"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                                Telegram
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                style="background-color: #1877F2"
                                @click="shareFacebook"
                                title="Facebook"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                                Facebook
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                style="background-color: #000000"
                                @click="shareTwitter"
                                title="X (Twitter)"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                X
                            </button>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold text-white transition hover:opacity-90"
                                style="background-color: #666"
                                @click="shareEmail"
                                title="Email"
                            >
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                Email
                            </button>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm text-center">
                    <h3 class="text-lg font-bold text-gray-900">Kod QR</h3>
                    <p class="mt-1 text-xs text-gray-400">Imbas untuk daftar</p>
                    <div
                        v-if="qrSvg"
                        v-html="qrSvg"
                        class="mx-auto mt-3 w-56 h-56 flex items-center justify-center [&_svg]:w-full [&_svg]:h-full [&_svg]:max-w-full"
                    >
                    </div>
                    <button
                        type="button"
                        class="mt-4 inline-flex items-center gap-1.5 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-gray-800"
                        @click="copyQrLink"
                    >
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        {{ qrCopySuccess ? 'Disalin!' : 'Salin Pautan' }}
                    </button>
                </div>
            </div>

            <!-- Referred Members List -->
            <div class="rounded-2xl border border-gray-100 bg-white shadow-sm overflow-hidden">
                <div class="border-b border-gray-50 p-6">
                    <h3 class="text-lg font-bold text-gray-900">Ahli Dijemput ({{ stats.total }})</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-400">
                            <tr>
                                <th class="px-6 py-4">Nama</th>
                                <th class="px-6 py-4">No Ahli</th>
                                <th class="px-6 py-4">Organisasi</th>
                                <th class="px-6 py-4">Tarikh Daftar</th>
                                <th class="px-6 py-4">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="member in referredMembers" :key="member.id" class="hover:bg-gray-50/50 transition-colors">
                                <td class="whitespace-nowrap px-6 py-4 font-medium text-gray-900">
                                    {{ member.name }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-500">
                                    {{ member.member_no || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-500">
                                    {{ member.organization || '—' }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-gray-500">
                                    {{ formatDate(member.registered_at) }}
                                </td>
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                                        :class="member.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                                    >
                                        {{ member.status === 'active' ? 'Aktif' : 'Pending' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!referredMembers.length">
                                <td colspan="5" class="px-6 py-10 text-center text-gray-400">
                                    Tiada ahli dijemput. Kongsi pautan rujukan anda untuk mula menjemput.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    share: {
        type: Object,
        required: true,
    },
});

const copied = ref('');

const organizationName = computed(() => props.share.organization?.name ?? 'myWAP');
const organizationColor = computed(() => props.share.organization?.color_theme ?? '#2F6B32');
const organizationInitial = computed(() => organizationName.value.charAt(0).toUpperCase());

function markCopied(key) {
    copied.value = key;
    setTimeout(() => {
        if (copied.value === key) copied.value = '';
    }, 2000);
}

function copyText(value, key) {
    if (!navigator.clipboard?.writeText) return;
    navigator.clipboard.writeText(value).then(() => markCopied(key));
}

function shareNative() {
    const payload = {
        title: props.share.title,
        text: props.share.description,
        url: props.share.share_url,
    };

    if (navigator.share) {
        navigator.share(payload).catch(() => {});
        return;
    }

    window.open(`https://wa.me/?text=${encodeURIComponent(props.share.message)}`, '_blank', 'noopener');
}

function shareWhatsApp() {
    window.open(`https://wa.me/?text=${encodeURIComponent(props.share.message)}`, '_blank', 'noopener');
}

function shareTelegram() {
    window.open(
        `https://t.me/share/url?url=${encodeURIComponent(props.share.share_url)}&text=${encodeURIComponent(props.share.description)}`,
        '_blank',
        'noopener',
    );
}

function shareFacebook() {
    window.open(
        `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(props.share.share_url)}`,
        '_blank',
        'noopener',
    );
}

function shareX() {
    window.open(
        `https://twitter.com/intent/tweet?text=${encodeURIComponent(props.share.title)}&url=${encodeURIComponent(props.share.share_url)}`,
        '_blank',
        'noopener',
    );
}

function shareEmail() {
    const subject = encodeURIComponent(props.share.title);
    const body = encodeURIComponent(props.share.message);
    window.location.href = `mailto:?subject=${subject}&body=${body}`;
}
</script>

<template>
    <Head :title="`Jemput Ahli - ${organizationName}`" />

    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <template #header>Jemput Ahli Baharu</template>

        <div class="mx-auto max-w-6xl space-y-5 px-4 py-6 md:px-6">
            <section class="rounded-3xl border border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-white p-5 shadow-sm sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            v-if="share.organization?.logo_url"
                            class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-2xl border border-white bg-white p-2 shadow-sm"
                        >
                            <img :src="share.organization.logo_url" :alt="`Logo ${organizationName}`" class="h-full w-full object-contain">
                        </div>
                        <div
                            v-else
                            class="flex h-16 w-16 items-center justify-center rounded-2xl text-xl font-black text-white shadow-sm"
                            :style="{ backgroundColor: organizationColor }"
                        >
                            {{ organizationInitial }}
                        </div>
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-emerald-700">Pendaftaran Ahli</p>
                            <h1 class="mt-1 text-xl font-black text-gray-900 sm:text-2xl">Kongsi jemputan {{ organizationName }}</h1>
                            <p class="mt-1 text-sm text-gray-600">Pendaftaran percuma. Organisasi ahli ditentukan secara automatik mengikut tahun lahir.</p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-gray-800"
                        @click="shareNative"
                    >
                        Kongsi pautan
                    </button>
                </div>
            </section>

            <div class="grid gap-5 lg:grid-cols-[1.15fr_0.85fr]">
                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Pratonton Pautan</p>
                            <p class="mt-1 text-sm text-gray-600">Pratonton ini muncul apabila pautan dikongsi di WhatsApp, Facebook dan Telegram.</p>
                        </div>
                        <a
                            :href="share.poster_download_url"
                            class="shrink-0 rounded-xl border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                        >
                            Muat turun poster
                        </a>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-2xl border border-gray-100 bg-gray-50">
                        <img
                            :src="share.poster_url"
                            :alt="`Poster jemputan ahli ${organizationName}`"
                            width="1200"
                            height="630"
                            class="block h-auto w-full"
                        >
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl bg-[#25D366] px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90"
                            @click="shareWhatsApp"
                        >
                            WhatsApp
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-[#0088cc] px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90"
                            @click="shareTelegram"
                        >
                            Telegram
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-[#1877F2] px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90"
                            @click="shareFacebook"
                        >
                            Facebook
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-black px-3.5 py-2 text-xs font-semibold text-white transition hover:opacity-90"
                            @click="shareX"
                        >
                            X
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border border-gray-200 px-3.5 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                            @click="shareEmail"
                        >
                            Emel
                        </button>
                    </div>
                </section>

                <div class="space-y-5">
                    <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Kod QR</p>
                        <p class="mt-1 text-sm text-gray-600">Cetak pada poster atau tunjuk pada skrin program.</p>

                        <div class="mx-auto mt-4 w-48 rounded-2xl border border-gray-100 bg-white p-3 shadow-sm [&_svg]:mx-auto [&_svg]:block [&_svg]:h-auto [&_svg]:w-full" v-html="share.qr_svg"></div>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a
                                :href="share.qr_png_url"
                                class="inline-flex items-center justify-center rounded-xl bg-gray-900 px-3 py-2.5 text-xs font-semibold text-white transition hover:bg-gray-800"
                            >
                                Muat turun QR
                            </a>
                            <button
                                type="button"
                                class="rounded-xl border border-gray-200 px-3 py-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                                @click="copyText(share.register_url, 'register')"
                            >
                                {{ copied === 'register' ? 'Disalin!' : 'Salin pautan borang' }}
                            </button>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Pautan Kongsi</p>

                        <div class="mt-3 flex items-center gap-2">
                            <input
                                type="text"
                                :value="share.share_url"
                                readonly
                                class="min-w-0 flex-1 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600"
                                aria-label="Pautan kongsi pendaftaran"
                            >
                            <button
                                type="button"
                                class="shrink-0 rounded-xl bg-gray-900 px-3 py-2 text-xs font-semibold text-white transition hover:bg-gray-800"
                                @click="copyText(share.share_url, 'link')"
                            >
                                {{ copied === 'link' ? 'Disalin!' : 'Salin' }}
                            </button>
                        </div>

                        <p class="mt-4 text-[11px] font-semibold uppercase tracking-[0.15em] text-gray-400">Mesej Jemputan</p>
                        <textarea
                            :value="share.message"
                            readonly
                            rows="4"
                            class="mt-2 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-xs leading-relaxed text-gray-600"
                            aria-label="Mesej jemputan untuk dikongsi"
                        ></textarea>
                        <button
                            type="button"
                            class="mt-2 w-full rounded-xl border border-gray-200 px-3 py-2.5 text-xs font-semibold text-gray-700 transition hover:bg-gray-50"
                            @click="copyText(share.message, 'message')"
                        >
                            {{ copied === 'message' ? 'Mesej disalin!' : 'Salin mesej' }}
                        </button>
                    </section>
                </div>
            </div>

            <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs leading-relaxed text-amber-900">
                Nota: pendaftaran percuma untuk semua organisasi. Ahli baharu akan dimasukkan ke PKPIM, ABIM atau WADAH mengikut tahun lahir, dan emel selamat datang akan dihantar secara automatik.
            </p>
        </div>
    </AppLayout>
</template>

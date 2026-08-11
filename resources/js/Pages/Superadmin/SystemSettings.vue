<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'myWAP',
    },
    systemLogoPath: {
        type: String,
        default: null,
    },
    ogImagePath: {
        type: String,
        default: null,
    },
    loginImagePath: {
        type: String,
        default: null,
    },
    chatbotLogoPath: {
        type: String,
        default: null,
    },
    canManageSystemLogo: {
        type: Boolean,
        default: false,
    },
    splashImagePath: {
        type: String,
        default: null,
    },
    splashBackgroundColor: {
        type: String,
        default: '#0f172a',
    },
    splashTitle: {
        type: String,
        default: 'myWAP',
    },
    splashDurationMs: {
        type: Number,
        default: 1800,
    },
    splashEnabled: {
        type: Boolean,
        default: true,
    },
    adminContactEmail: {
        type: String,
        default: '',
    },
    adminContactPhone: {
        type: String,
        default: '',
    },
    hasResendKey: {
        type: Boolean,
        default: false,
    },
    hasGeminiKey: {
        type: Boolean,
        default: false,
    },
    mailFromAddress: {
        type: String,
        default: '',
    },
    mailFromName: {
        type: String,
        default: '',
    },
    mailMailer: {
        type: String,
        default: 'log',
    },
    mailSmtpHost: {
        type: String,
        default: '',
    },
    mailSmtpPort: {
        type: String,
        default: '',
    },
    mailSmtpUsername: {
        type: String,
        default: '',
    },
    mailSmtpEncryption: {
        type: String,
        default: '',
    },
});

const form = useForm({
    system_logo: null,
});

const ogForm = useForm({
    og_image: null,
});

const loginImageForm = useForm({
    login_image: null,
});

const chatbotForm = useForm({
    chatbot_logo: null,
});

const splashForm = useForm({
    splash_image: null,
    splash_background_color: props.splashBackgroundColor || '#0f172a',
    splash_title: props.splashTitle || 'myWAP',
    splash_duration_ms: props.splashDurationMs || 1800,
    splash_enabled: props.splashEnabled,
});
const splashPreviewUrl = ref(null);

function uploadSystemLogo() {
    form.post(route('superadmin.settings.system-logo.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => form.reset('system_logo'),
    });
}

function uploadOgImage() {
    ogForm.post(route('superadmin.settings.og-image.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => ogForm.reset('og_image'),
    });
}

function uploadLoginImage() {
    loginImageForm.post(route('superadmin.settings.login-image.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => loginImageForm.reset('login_image'),
    });
}

function removeLoginImage() {
    if (! confirm('Buang gambar halaman log masuk?')) {
        return;
    }

    loginImageForm.delete(route('superadmin.settings.login-image.remove'), {
        preserveScroll: true,
    });
}

function uploadChatbotLogo() {
    chatbotForm.post(route('superadmin.settings.chatbot-logo.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => chatbotForm.reset('chatbot_logo'),
    });
}

function saveSplashSettings() {
    splashForm.post(route('superadmin.settings.splash.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => splashForm.reset('splash_image'),
    });
}

function onSplashImageSelected(event) {
    const file = event.target.files?.[0] ?? null;
    splashForm.splash_image = file;

    if (splashPreviewUrl.value) {
        URL.revokeObjectURL(splashPreviewUrl.value);
        splashPreviewUrl.value = null;
    }

    if (file) {
        splashPreviewUrl.value = URL.createObjectURL(file);
    }
}

const appNameForm = useForm({
    app_name: props.appName || 'myWAP',
});

function saveAppName() {
    appNameForm.post(route('superadmin.settings.app-name.update'), {
        preserveScroll: true,
    });
}

const contactForm = useForm({
    admin_contact_email: props.adminContactEmail || '',
    admin_contact_phone: props.adminContactPhone || '',
});

function saveContact() {
    contactForm.post(route('superadmin.settings.admin-contact.update'), {
        preserveScroll: true,
    });
}

const resendForm = useForm({
    resend_api_key: '',
    mail_from_address: props.mailFromAddress || '',
    mail_from_name: props.mailFromName || '',
});

const mailForm = useForm({
    mail_mailer: props.mailMailer || 'log',
    mail_from_address: props.mailFromAddress || '',
    mail_from_name: props.mailFromName || '',
    resend_api_key: '',
    mail_smtp_host: props.mailSmtpHost || '',
    mail_smtp_port: props.mailSmtpPort || '',
    mail_smtp_username: props.mailSmtpUsername || '',
    mail_smtp_password: '',
    mail_smtp_encryption: props.mailSmtpEncryption || '',
});

function saveMailSettings() {
    mailForm.post(route('superadmin.settings.mail.update'), {
        preserveScroll: true,
        onSuccess: () => {
            mailForm.reset('resend_api_key');
            mailForm.reset('mail_smtp_password');
        },
    });
}

const testMailForm = useForm({
    test_email: props.adminContactEmail || '',
});

function sendTestMail() {
    testMailForm.post(route('superadmin.settings.mail.test'), {
        preserveScroll: true,
    });
}

const geminiForm = useForm({
    gemini_api_key: '',
});

function saveGeminiKey() {
    geminiForm.post(route('superadmin.settings.gemini-key.update'), {
        preserveScroll: true,
    });
}

function saveResendKey() {
    resendForm.post(route('superadmin.settings.resend-key.update'), {
        preserveScroll: true,
    });
}

const mailerLabels = {
    resend: 'Resend (API)',
    smtp:   'SMTP (Gmail, Outlook, dll.)',
    log:    'Log (Ujian — tiada emel sebenar)',
};

onBeforeUnmount(() => {
    if (splashPreviewUrl.value) {
        URL.revokeObjectURL(splashPreviewUrl.value);
    }
});
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <Head title="myWAP Settings" />

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">myWAP Settings</h1>
                <p class="mt-1 text-sm text-gray-500">Tetapan peringkat sistem seperti logo rasmi myWAP.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="!canManageSystemLogo" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Tetapan ini memerlukan migration baru. Jalankan <strong>php artisan migrate</strong> dahulu.
            </div>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Nama Aplikasi</h2>
                <p class="mt-1 text-xs text-gray-500">Nama ini akan dipaparkan sebagai title di pelayar web, di pautan yang dikongsi (OG tag), dan di emel sistem.</p>

                <form class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="saveAppName">
                    <label class="flex-1">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Nama Aplikasi</span>
                        <input v-model="appNameForm.app_name" type="text" maxlength="100" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="myWAP" required>
                        <p v-if="appNameForm.errors.app_name" class="mt-1 text-xs text-red-500">{{ appNameForm.errors.app_name }}</p>
                    </label>
                    <button
                        type="submit"
                        :disabled="appNameForm.processing"
                        class="shrink-0 rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {{ appNameForm.processing ? 'Menyimpan...' : 'Simpan' }}
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Logo Sistem</h2>
                <p class="mt-1 text-xs text-gray-500">Cadangan saiz: <strong>512 × 512px</strong>, format PNG/SVG dengan latar transparen.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="systemLogoPath" :src="systemLogoPath" alt="System Logo" class="h-20 w-20 object-contain">
                        <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadSystemLogo">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="form.system_logo = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="form.errors.system_logo" class="text-xs text-red-500">{{ form.errors.system_logo }}</p>
                        <button
                            type="submit"
                            :disabled="form.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ form.processing ? 'Memuat naik...' : 'Simpan Logo myWAP' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Gambar OG (Open Graph)</h2>
                <p class="mt-1 text-xs text-gray-500">Gambar preview bila pautan sistem dikongsi di WhatsApp/Telegram/Messenger. Cadangan saiz: <strong>1200 × 630px</strong>, format JPG/PNG/WEBP.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-32 w-56 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="ogImagePath" :src="ogImagePath" alt="OG Image" class="h-28 w-52 rounded-xl object-cover">
                        <span v-else class="text-xs font-semibold text-gray-400">Tiada gambar</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadOgImage">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="ogForm.og_image = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="ogForm.errors.og_image" class="text-xs text-red-500">{{ ogForm.errors.og_image }}</p>
                        <button
                            type="submit"
                            :disabled="ogForm.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ ogForm.processing ? 'Memuat naik...' : 'Simpan Gambar OG' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Gambar Halaman Log Masuk</h2>
                <p class="mt-1 text-xs text-gray-500">Gambar yang dipaparkan pada panel kiri halaman log masuk. Cadangan saiz: <strong>1200 × 800px</strong> (nisbah 3:2), format JPG/PNG/WEBP. Jika tiada gambar, panel akan memaparkan reka bentuk lalai.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-32 w-56 shrink-0 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="loginImagePath" :src="loginImagePath" alt="Gambar Log Masuk" class="h-28 w-52 rounded-xl object-cover">
                        <span v-else class="text-xs font-semibold text-gray-400">Tiada gambar</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadLoginImage">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="loginImageForm.login_image = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="loginImageForm.errors.login_image" class="text-xs text-red-500">{{ loginImageForm.errors.login_image }}</p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="submit"
                                :disabled="loginImageForm.processing || !canManageSystemLogo"
                                class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                            >
                                {{ loginImageForm.processing ? 'Memuat naik...' : 'Simpan Gambar' }}
                            </button>
                            <button
                                v-if="loginImagePath"
                                type="button"
                                :disabled="loginImageForm.processing || !canManageSystemLogo"
                                @click="removeLoginImage"
                                class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 disabled:opacity-60"
                            >
                                Buang Gambar
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Logo Chatbot</h2>
                <p class="mt-1 text-xs text-gray-500">Logo yang dipaparkan pada butang chatbot terapung. Cadangan saiz: <strong>512 × 512px</strong>, format PNG/SVG dengan latar transparen.</p>

                <div class="mt-4 flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div class="flex h-24 w-24 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                        <img v-if="chatbotLogoPath" :src="chatbotLogoPath" alt="Chatbot Logo" class="h-20 w-20 rounded-full object-contain">
                        <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                    </div>

                    <form class="flex-1 space-y-2" @submit.prevent="uploadChatbotLogo">
                        <input
                            type="file"
                            accept="image/*"
                            :disabled="!canManageSystemLogo"
                            @change="chatbotForm.chatbot_logo = $event.target.files[0]"
                            class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                        >
                        <p v-if="chatbotForm.errors.chatbot_logo" class="text-xs text-red-500">{{ chatbotForm.errors.chatbot_logo }}</p>
                        <button
                            type="submit"
                            :disabled="chatbotForm.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ chatbotForm.processing ? 'Memuat naik...' : 'Simpan Logo Chatbot' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Splash Screen Web</h2>
                <p class="mt-1 text-xs text-gray-500">Dipaparkan sekali setiap sesi browser semasa aplikasi mula dibuka. Format imej: JPG, PNG, WEBP, SVG, GIF.</p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-gray-200 p-4">
                        <p class="text-xs font-bold uppercase tracking-wide text-gray-500">Preview</p>
                        <div
                            class="mt-3 flex h-48 items-center justify-center rounded-2xl"
                            :style="{ backgroundColor: splashForm.splash_background_color || '#0f172a' }"
                        >
                            <div class="text-center">
                                <img
                                    v-if="splashForm.splash_image || splashImagePath || systemLogoPath"
                                    :src="splashPreviewUrl || splashImagePath || systemLogoPath"
                                    class="mx-auto h-16 w-16 object-contain"
                                    alt="Splash preview"
                                >
                                <p class="mt-3 text-sm font-bold text-white">{{ splashForm.splash_title || 'myWAP' }}</p>
                            </div>
                        </div>
                    </div>

                    <form class="space-y-3" @submit.prevent="saveSplashSettings">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Imej Splash (optional)</span>
                            <input
                                type="file"
                                accept="image/*"
                                :disabled="!canManageSystemLogo"
                                @change="onSplashImageSelected"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <p v-if="splashForm.errors.splash_image" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_image }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Warna Latar</span>
                            <input v-model="splashForm.splash_background_color" type="color" class="h-10 w-full rounded-xl border border-gray-200 p-1">
                            <p v-if="splashForm.errors.splash_background_color" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_background_color }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Tajuk</span>
                            <input v-model="splashForm.splash_title" type="text" maxlength="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <p v-if="splashForm.errors.splash_title" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_title }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Tempoh (ms)</span>
                            <input v-model.number="splashForm.splash_duration_ms" type="number" min="300" max="6000" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <p v-if="splashForm.errors.splash_duration_ms" class="mt-1 text-xs text-red-500">{{ splashForm.errors.splash_duration_ms }}</p>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input v-model="splashForm.splash_enabled" type="checkbox" class="rounded border-gray-300">
                            Aktifkan splash screen
                        </label>

                        <button
                            type="submit"
                            :disabled="splashForm.processing || !canManageSystemLogo"
                            class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ splashForm.processing ? 'Menyimpan...' : 'Simpan Tetapan Splash' }}
                        </button>
                    </form>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Kunci API Gemini</h2>
                <p class="mt-1 text-xs text-gray-500">Digunakan untuk chatbot MyWAP AI. Dapatkan kunci percuma dari <strong>ai.google.dev</strong>. Had percuma 60 permintaan seminit.</p>

                <form class="mt-4 space-y-3" @submit.prevent="saveGeminiKey">
                    <p v-if="hasGeminiKey" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                        ✓ Kunci API telah disimpan sebelumnya. Masukkan kunci baru untuk menggantikan, atau kosongkan untuk memadam.
                    </p>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Kunci API Gemini</span>
                        <input v-model="geminiForm.gemini_api_key" type="password" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" :placeholder="hasGeminiKey ? 'Klik untuk tukar (kunci sedia ada dikekalkan)' : 'AIzaSy...'">
                        <p v-if="geminiForm.errors.gemini_api_key" class="mt-1 text-xs text-red-500">{{ geminiForm.errors.gemini_api_key }}</p>
                    </label>

                    <button
                        type="submit"
                        :disabled="geminiForm.processing"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {{ geminiForm.processing ? 'Menyimpan...' : 'Simpan Kunci API Gemini' }}
                    </button>
                </form>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Tetapan Emel (Mailer)</h2>
                <p class="mt-1 text-xs text-gray-500">Pilih cara sistem menghantar emel (OTP, jemputan borang, pemberitahuan). Tetapan ini menggantikan <strong>MAIL_MAILER</strong> dalam fail .env.</p>

                <form class="mt-4 space-y-3" @submit.prevent="saveMailSettings">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Kaedah Penghantaran</span>
                        <select v-model="mailForm.mail_mailer" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                            <option value="resend">Resend (API) — disyorkan untuk production</option>
                            <option value="smtp">SMTP (Gmail, Outlook, dll.)</option>
                            <option value="log">Log (Ujian — tiada emel sebenar)</option>
                        </select>
                        <p v-if="mailForm.errors.mail_mailer" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_mailer }}</p>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Emel Pengirim (From Address)</span>
                            <input v-model="mailForm.mail_from_address" type="email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="onboarding@resend.dev">
                            <p v-if="mailForm.errors.mail_from_address" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_from_address }}</p>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Nama Pengirim (From Name)</span>
                            <input v-model="mailForm.mail_from_name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="myWAP">
                            <p v-if="mailForm.errors.mail_from_name" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_from_name }}</p>
                        </label>
                    </div>

                    <!-- Resend fields -->
                    <template v-if="mailForm.mail_mailer === 'resend'">
                        <p v-if="hasResendKey" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                            ✓ Kunci API telah disimpan. Masukkan kunci baru untuk menggantikan, atau biarkan kosong untuk kekalkan yang sedia ada.
                        </p>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Kunci API Resend</span>
                            <input v-model="mailForm.resend_api_key" type="password" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" :placeholder="hasResendKey ? 'Biarkan kosong untuk kekalkan' : 're_xxxxxxxxxxxxxx'">
                            <p v-if="mailForm.errors.resend_api_key" class="mt-1 text-xs text-red-500">{{ mailForm.errors.resend_api_key }}</p>
                        </label>
                    </template>

                    <!-- SMTP fields -->
                    <template v-if="mailForm.mail_mailer === 'smtp'">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">SMTP Host</span>
                                <input v-model="mailForm.mail_smtp_host" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" placeholder="smtp.gmail.com">
                                <p v-if="mailForm.errors.mail_smtp_host" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_smtp_host }}</p>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">SMTP Port</span>
                                <input v-model="mailForm.mail_smtp_port" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" placeholder="587">
                                <p v-if="mailForm.errors.mail_smtp_port" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_smtp_port }}</p>
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Username</span>
                                <input v-model="mailForm.mail_smtp_username" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="nama@example.com">
                                <p v-if="mailForm.errors.mail_smtp_username" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_smtp_username }}</p>
                            </label>
                            <label class="block">
                                <span class="mb-1 block text-xs font-semibold text-gray-500">Password</span>
                                <input v-model="mailForm.mail_smtp_password" type="password" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm font-mono" :placeholder="mailForm.mail_smtp_password ? '' : 'Biarkan kosong untuk kekalkan'">
                                <p v-if="mailForm.errors.mail_smtp_password" class="mt-1 text-xs text-red-500">{{ mailForm.errors.mail_smtp_password }}</p>
                            </label>
                        </div>
                        <label class="block">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Encryption</span>
                            <select v-model="mailForm.mail_smtp_encryption" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                                <option value="">Tiada</option>
                            </select>
                        </label>
                    </template>

                    <button
                        type="submit"
                        :disabled="mailForm.processing"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {{ mailForm.processing ? 'Menyimpan...' : 'Simpan Tetapan Emel' }}
                    </button>
                </form>

                <!-- Test email -->
                <div class="mt-6 border-t border-gray-100 pt-4">
                    <p class="text-sm font-semibold text-gray-700">Hantar Emel Ujian</p>
                    <p class="mt-0.5 text-xs text-gray-500">Sahkan konfigurasi berfungsi dengan menghantar emel ujian.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                        <label class="flex-1">
                            <span class="mb-1 block text-xs font-semibold text-gray-500">Emel Ujian</span>
                            <input v-model="testMailForm.test_email" type="email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="admin@example.com" required>
                            <p v-if="testMailForm.errors.test_email" class="mt-1 text-xs text-red-500">{{ testMailForm.errors.test_email }}</p>
                        </label>
                        <button
                            type="button"
                            :disabled="testMailForm.processing"
                            class="shrink-0 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {{ testMailForm.processing ? 'Menghantar...' : 'Hantar Emel Ujian' }}
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-black text-gray-800">Maklumat Hubungi Admin</h2>
                <p class="mt-1 text-xs text-gray-500">Dipaparkan kepada ahli yang menghadapi masalah log masuk (emel hilang/tiada akses).</p>

                <form class="mt-4 space-y-3" @submit.prevent="saveContact">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">Emel Admin</span>
                        <input v-model="contactForm.admin_contact_email" type="email" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="admin@mywap.my">
                        <p v-if="contactForm.errors.admin_contact_email" class="mt-1 text-xs text-red-500">{{ contactForm.errors.admin_contact_email }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold text-gray-500">No Telefon Admin</span>
                        <input v-model="contactForm.admin_contact_phone" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm" placeholder="+60123456789">
                        <p v-if="contactForm.errors.admin_contact_phone" class="mt-1 text-xs text-red-500">{{ contactForm.errors.admin_contact_phone }}</p>
                    </label>

                    <button
                        type="submit"
                        :disabled="contactForm.processing"
                        class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                    >
                        {{ contactForm.processing ? 'Menyimpan...' : 'Simpan Maklumat Admin' }}
                    </button>
                </form>
            </section>
        </div>
    </AppLayout>
</template>

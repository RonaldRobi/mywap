<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    organizations: {
        type: Array,
        default: () => [],
    },
    capabilities: {
        type: Object,
        default: () => ({
            logo: false,
            sort_order: false,
        }),
    },
});

const editForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({
            name: organization.name,
            color_theme: organization.color_theme,
            min_age: organization.min_age,
            max_age: organization.max_age,
            sort_order: organization.sort_order,
            payment_gateway: organization.payment_gateway ?? '',
            bayarcash_api_token: organization.bayarcash_api_token ?? '',
            bayarcash_portal_key: organization.bayarcash_portal_key ?? '',
            bayarcash_secret_key: organization.bayarcash_secret_key ?? '',
            bayarcash_environment: organization.bayarcash_environment ?? 'sandbox',
            doku_client_id: organization.doku_client_id ?? '',
            doku_api_key: organization.doku_api_key ?? '',
            doku_secret_key: organization.doku_secret_key ?? '',
            doku_environment: organization.doku_environment ?? 'sandbox',
            website_url: organization.website_url ?? '',
            facebook_url: organization.facebook_url ?? '',
            instagram_url: organization.instagram_url ?? '',
            twitter_url: organization.twitter_url ?? '',
            youtube_url: organization.youtube_url ?? '',
            tiktok_url: organization.tiktok_url ?? '',
        }),
    ])
);

const logoForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({ organization_logo: null }),
    ])
);

const dokuWebhookUrl = computed(() => {
    if (typeof window === 'undefined') return '/doku/callback';
    return `${window.location.origin}/doku/callback`;
});

const sortedOrganizations = computed(() =>
    [...props.organizations].sort((left, right) => {
        const leftOrder = left.sort_order ?? Number.MAX_SAFE_INTEGER;
        const rightOrder = right.sort_order ?? Number.MAX_SAFE_INTEGER;

        if (leftOrder !== rightOrder) return leftOrder - rightOrder;
        return left.min_age - right.min_age;
    })
);

function updateOrganization(organization) {
    editForms[organization.id].put(route('superadmin.organizations.update', organization.id), {
        preserveScroll: true,
    });
}

function updateOrganizationLogo(organization) {
    logoForms[organization.id].post(route('superadmin.organizations.logo.update', organization.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => logoForms[organization.id].reset('organization_logo'),
    });
}
</script>

<template>
    <AppLayout :back-route="route('admin.dashboard')" back-label="Kembali ke Dashboard">
        <Head title="Organization Management" />

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 md:px-6">
            <div>
                <h1 class="text-2xl font-black text-gray-900">Organization Management</h1>
                <p class="mt-1 text-sm text-gray-500">Urus nama organisasi, logo, umur tier, warna tema, susunan paparan, payment gateway (BayarCash / DOKU), dan semak jumlah ahli.</p>
            </div>

            <div v-if="$page.props.flash?.success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ $page.props.flash.success }}
            </div>

            <div v-if="$page.props.flash?.error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $page.props.flash.error }}
            </div>

            <div v-if="!capabilities.logo || !capabilities.sort_order" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Sebahagian fungsi belum aktif kerana migration belum dijalankan sepenuhnya. Jalankan <strong>php artisan migrate</strong>.
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <article
                    v-for="organization in sortedOrganizations"
                    :key="organization.id"
                    class="rounded-3xl border border-gray-100 bg-white p-5 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-lg font-black text-gray-800">{{ organization.name }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ organization.slug }}</p>
                        </div>
                        <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">
                            Ahli: {{ organization.member_count }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex h-20 w-20 items-center justify-center rounded-2xl border border-gray-200 bg-gray-50">
                            <img v-if="organization.logo_path" :src="organization.logo_path" :alt="organization.name + ' logo'" class="h-16 w-16 object-contain">
                            <span v-else class="text-xs font-semibold text-gray-400">No logo</span>
                        </div>

                        <form class="flex-1 space-y-2" @submit.prevent="updateOrganizationLogo(organization)">
                            <p class="text-[11px] text-gray-500">Cadangan saiz logo: <strong>512 × 512px</strong> (PNG/SVG transparen).</p>
                            <input
                                type="file"
                                accept="image/*"
                                :disabled="!capabilities.logo"
                                @change="logoForms[organization.id].organization_logo = $event.target.files[0]"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-xs file:mr-3 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700"
                            >
                            <button
                                type="submit"
                                :disabled="logoForms[organization.id].processing || !capabilities.logo"
                                class="rounded-xl border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-60"
                            >
                                {{ logoForms[organization.id].processing ? 'Memuat naik...' : 'Simpan Logo' }}
                            </button>
                            <p v-if="logoForms[organization.id].errors.organization_logo" class="text-xs text-red-500">
                                {{ logoForms[organization.id].errors.organization_logo }}
                            </p>
                        </form>
                    </div>

                    <form class="mt-5 space-y-3" @submit.prevent="updateOrganization(organization)">
                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Nama Organisasi</label>
                            <input v-model="editForms[organization.id].name" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            <p v-if="editForms[organization.id].errors.name" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Min Umur</label>
                                <input v-model.number="editForms[organization.id].min_age" type="number" min="0" max="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold text-gray-500">Max Umur</label>
                                <input v-model.number="editForms[organization.id].max_age" type="number" min="0" max="120" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="Kosong = tiada had">
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Tema Warna (Hex)</label>
                            <input v-model="editForms[organization.id].color_theme" type="text" placeholder="#10b981" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        </div>

                        <div v-if="capabilities.sort_order">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Susunan Paparan</label>
                            <input v-model.number="editForms[organization.id].sort_order" type="number" min="1" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                        </div>

                        <div class="rounded-xl border border-gray-200 p-3">
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Payment Gateway Aktif</label>
                            <select v-model="editForms[organization.id].payment_gateway" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                <option value="">Auto (guna yang dikonfigur)</option>
                                <option value="bayarcash">BayarCash (FPX + Direct Debit)</option>
                                <option value="doku">DOKU (FPX + E-Wallet)</option>
                            </select>
                            <p class="mt-1 text-[11px] text-gray-500">
                                Gateway yang akan menerima wang untuk organisasi ini.
                                <span v-if="organization.active_gateway" class="font-semibold text-emerald-600">Sedang aktif: {{ organization.active_gateway.toUpperCase() }}</span>
                                <span v-else class="font-semibold text-amber-600">Tiada gateway dikonfigur — mod dummy.</span>
                            </p>
                            <p v-if="editForms[organization.id].errors.payment_gateway" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.payment_gateway }}</p>
                            <p class="mt-1 text-[11px] text-amber-600">Nota: Sumbangan berkala (recurring) hanya disokong oleh BayarCash.</p>
                        </div>

                        <details class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900">BayarCash Payment Gateway</summary>
                            <div class="space-y-3 border-t border-gray-100 p-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">API Token</label>
                                    <input v-model="editForms[organization.id].bayarcash_api_token" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="Personal Access Token">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Portal Key</label>
                                    <input v-model="editForms[organization.id].bayarcash_portal_key" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="Portal key dari BayarCash console">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Secret Key</label>
                                    <input v-model="editForms[organization.id].bayarcash_secret_key" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="API Secret Key untuk checksum">
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Environment</label>
                                    <select v-model="editForms[organization.id].bayarcash_environment" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                        <option value="sandbox">Sandbox (Ujian)</option>
                                        <option value="live">Live (Produksi)</option>
                                    </select>
                                </div>
                            </div>
                        </details>

                        <details class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900">DOKU Payment Gateway</summary>
                            <div class="space-y-3 border-t border-gray-100 p-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Client ID</label>
                                    <input v-model="editForms[organization.id].doku_client_id" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="BRN-XXXX-XXXXXXXXXX">
                                    <p v-if="editForms[organization.id].errors.doku_client_id" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.doku_client_id }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">API Key</label>
                                    <input v-model="editForms[organization.id].doku_api_key" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="doku_ak_...">
                                    <p v-if="editForms[organization.id].errors.doku_api_key" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.doku_api_key }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Secret Key</label>
                                    <input v-model="editForms[organization.id].doku_secret_key" type="text" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="Secret key untuk signature (disulitkan)">
                                    <p v-if="editForms[organization.id].errors.doku_secret_key" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.doku_secret_key }}</p>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Environment</label>
                                    <select v-model="editForms[organization.id].doku_environment" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0">
                                        <option value="sandbox">Sandbox (Ujian)</option>
                                        <option value="production">Production (Produksi)</option>
                                    </select>
                                </div>
                                <p class="text-[11px] text-gray-500">
                                    Webhook URL (Notification): salin ke DOKU Back Office &rarr; Settings &rarr; Webhook:
                                    <code class="rounded bg-gray-100 px-1 py-0.5 text-[10px]">{{ dokuWebhookUrl }}</code>
                                </p>
                            </div>
                        </details>

                        <details class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900">Pautan & Media Sosial</summary>
                            <div class="space-y-3 border-t border-gray-100 p-3">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold text-gray-500">Laman Web (URL)</label>
                                    <input v-model="editForms[organization.id].website_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://www.contoh.com">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Facebook</label>
                                        <input v-model="editForms[organization.id].facebook_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://facebook.com/...">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Instagram</label>
                                        <input v-model="editForms[organization.id].instagram_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://instagram.com/...">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">Twitter / X</label>
                                        <input v-model="editForms[organization.id].twitter_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://x.com/...">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">YouTube</label>
                                        <input v-model="editForms[organization.id].youtube_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://youtube.com/@...">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-semibold text-gray-500">TikTok</label>
                                        <input v-model="editForms[organization.id].tiktok_url" type="url" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0" placeholder="https://tiktok.com/@...">
                                    </div>
                                </div>
                            </div>
                        </details>

                        <button
                            type="submit"
                            :disabled="editForms[organization.id].processing"
                            class="w-full rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60"
                        >
                            {{ editForms[organization.id].processing ? 'Menyimpan...' : 'Simpan Organisasi' }}
                        </button>
                    </form>
                </article>
            </div>
        </div>
    </AppLayout>
</template>

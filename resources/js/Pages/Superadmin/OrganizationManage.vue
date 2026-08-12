<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
            description: organization.description ?? '',
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

// ─── Carta Organisasi ─────────────────────────────────────────────────────
const chartAddForms = Object.fromEntries(
    props.organizations.map((organization) => [
        organization.id,
        useForm({ name: '', position: '', email: '', image: null }),
    ])
);

const editingChart = ref(null);
const chartEditForm = useForm({ name: '', position: '', email: '', image: null });
const confirmDeleteChart = ref(null);

function openEditChart(organizationId, member) {
    editingChart.value = { organizationId, member };
    chartEditForm.reset();
    chartEditForm.clearErrors();
    Object.assign(chartEditForm, {
        name: member.name,
        position: member.position,
        email: member.email || '',
        image: null,
    });
}

function closeEditChart() {
    editingChart.value = null;
    chartEditForm.reset();
}

function submitAddChart(organization) {
    chartAddForms[organization.id].post(route('superadmin.organizations.chart.store', organization.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => chartAddForms[organization.id].reset(),
    });
}

function submitEditChart(organization) {
    chartEditForm.put(route('superadmin.organizations.chart.update', [organization.id, editingChart.value.member.id]), {
        preserveScroll: true,
        onSuccess: closeEditChart,
    });
}

function requestDeleteChart(member) {
    confirmDeleteChart.value = member;
}

function confirmDeleteChartMember() {
    const member = confirmDeleteChart.value;
    useForm({}).delete(route('superadmin.organizations.chart.destroy', [member.organization_id, member.id]), {
        preserveScroll: true,
        onSuccess: () => { confirmDeleteChart.value = null; },
        onError: () => { confirmDeleteChart.value = null; },
    });
}

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
                <p class="mt-1 text-sm text-gray-500">Urus nama organisasi, penerangan (Info), logo, umur tier, warna tema, susunan paparan, payment gateway (BayarCash / DOKU), Carta Organisasi, dan semak jumlah ahli.</p>
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

                        <div>
                            <label class="mb-1 block text-xs font-semibold text-gray-500">Penerangan (Info Organisasi)</label>
                            <textarea
                                v-model="editForms[organization.id].description"
                                rows="4"
                                class="w-full resize-none rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-gray-500 focus:ring-0"
                                placeholder="Penerangan ringkas tentang organisasi — dipaparkan pada page Info."
                            ></textarea>
                            <p v-if="editForms[organization.id].errors.description" class="mt-1 text-xs text-red-500">{{ editForms[organization.id].errors.description }}</p>
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

                        <details class="rounded-xl border border-gray-200">
                            <summary class="cursor-pointer px-3 py-2 text-xs font-semibold text-gray-600 hover:text-gray-900">
                                Carta Organisasi
                                <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">{{ organization.chart_members.length }}</span>
                            </summary>
                            <div class="space-y-4 border-t border-gray-100 p-3">
                                <!-- List -->
                                <div v-if="organization.chart_members.length" class="space-y-2">
                                    <div
                                        v-for="member in organization.chart_members"
                                        :key="member.id"
                                        class="flex items-center gap-3 rounded-xl border border-gray-100 bg-gray-50 p-2"
                                    >
                                        <div class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-gray-200">
                                            <img v-if="member.image_path" :src="member.image_path" :alt="member.name" class="h-full w-full object-cover">
                                            <div v-else class="grid h-full w-full place-items-center text-sm font-black text-gray-400">
                                                {{ (member.name || '?').charAt(0).toUpperCase() }}
                                            </div>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ member.name }}</p>
                                            <p class="truncate text-xs text-gray-500">{{ member.position }}</p>
                                            <a v-if="member.email" :href="`mailto:${member.email}`" class="text-[11px] font-medium text-emerald-700 hover:underline">{{ member.email }}</a>
                                        </div>
                                        <button
                                            type="button"
                                            @click="openEditChart(organization.id, member)"
                                            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-gray-500 shadow-sm hover:text-gray-800"
                                            title="Edit"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" /></svg>
                                        </button>
                                        <button
                                            type="button"
                                            @click="requestDeleteChart({ ...member, organization_id: organization.id })"
                                            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-white text-red-500 shadow-sm hover:text-red-600"
                                            title="Padam"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                                <p v-else class="text-xs text-gray-400">Tiada entri carta organisasi untuk organisasi ini.</p>

                                <!-- Edit form -->
                                <div v-if="editingChart && editingChart.organizationId === organization.id" class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3">
                                    <div class="mb-2 flex items-center justify-between">
                                        <p class="text-xs font-bold text-emerald-800">Edit — {{ editingChart.member.name }}</p>
                                        <button type="button" @click="closeEditChart" class="text-xs font-semibold text-gray-500 hover:text-gray-800">Batal</button>
                                    </div>
                                    <div class="space-y-2">
                                        <input v-model="chartEditForm.name" type="text" placeholder="Nama" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                        <input v-model="chartEditForm.position" type="text" placeholder="Jawatan" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                        <input v-model="chartEditForm.email" type="email" placeholder="Emel" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                        <input type="file" accept="image/jpeg,image/png,image/webp" @change="chartEditForm.image = $event.target.files[0]" class="w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                                        <button type="button" @click="submitEditChart(organization)" :disabled="chartEditForm.processing" class="w-full rounded-xl bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-60">
                                            {{ chartEditForm.processing ? 'Menyimpan...' : 'Kemas Kini' }}
                                        </button>
                                    </div>
                                </div>

                                <!-- Add form -->
                                <form @submit.prevent="submitAddChart(organization)" class="space-y-2 rounded-xl border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-bold text-gray-600">Tambah Ahli Baharu</p>
                                    <input v-model="chartAddForms[organization.id].name" type="text" placeholder="Nama" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                    <p v-if="chartAddForms[organization.id].errors.name" class="text-xs text-red-500">{{ chartAddForms[organization.id].errors.name }}</p>
                                    <input v-model="chartAddForms[organization.id].position" type="text" placeholder="Jawatan" required class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                    <p v-if="chartAddForms[organization.id].errors.position" class="text-xs text-red-500">{{ chartAddForms[organization.id].errors.position }}</p>
                                    <input v-model="chartAddForms[organization.id].email" type="email" placeholder="Emel (mailto)" class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:border-emerald-400 focus:ring-0">
                                    <p v-if="chartAddForms[organization.id].errors.email" class="text-xs text-red-500">{{ chartAddForms[organization.id].errors.email }}</p>
                                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="chartAddForms[organization.id].image = $event.target.files[0]" class="w-full text-xs file:mr-2 file:rounded-lg file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-gray-700">
                                    <p v-if="chartAddForms[organization.id].errors.image" class="text-xs text-red-500">{{ chartAddForms[organization.id].errors.image }}</p>
                                    <button type="submit" :disabled="chartAddForms[organization.id].processing" class="w-full rounded-xl bg-gray-900 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800 disabled:opacity-60">
                                        {{ chartAddForms[organization.id].processing ? 'Menyimpan...' : 'Tambah' }}
                                    </button>
                                </form>
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

        <!-- ── Confirm Delete Chart Modal ─────────────────────────────────── -->
        <transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="confirmDeleteChart" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm" @click.self="confirmDeleteChart = null">
                <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl" @click.stop>
                    <h3 class="text-base font-bold text-gray-900">Padam entri carta?</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Anda pasti mahu memadam <strong>"{{ confirmDeleteChart.name }}"</strong> daripada Carta Organisasi? Tindakan ini tidak boleh dibatalkan.
                    </p>
                    <div class="mt-5 flex items-center gap-3">
                        <button @click="confirmDeleteChartMember" class="flex-1 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 transition-colors">
                            Ya, Padam
                        </button>
                        <button @click="confirmDeleteChart = null" class="flex-1 rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                            Batal
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </AppLayout>
</template>

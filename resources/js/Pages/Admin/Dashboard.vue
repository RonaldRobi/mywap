<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ExportCsvButton from '@/Components/ExportCsvButton.vue';
import { t, locale } from '@/i18n';

const props = defineProps({
    organization: { type: Object, required: true },
    overview: { type: Object, required: true },
    managementLinks: { type: Object, required: true },
    registrationShare: { type: Object, required: true },
    isBranchAdmin: Boolean,
});

const shareCopied = ref(false);
const breakdownMode = ref('state');

const isManagementView = computed(() => props.organization?.slug === 'management');
const scopeLabel = computed(() => {
    if (isManagementView.value) return t('Keseluruhan organisasi');
    if (props.isBranchAdmin) return t('Cawangan di bawah seliaan anda');
    return props.organization?.name ?? t('Organisasi anda');
});
const chartMax = computed(() => Math.max(1, ...(props.overview?.program_chart ?? []).map((item) => Number(item.value ?? 0))));
const activityItems = computed(() => props.overview?.recent_activities ?? []);
const alertItems = computed(() => props.overview?.alerts ?? []);
const stateItems = computed(() => props.overview?.members_by_state ?? []);
const organizationItems = computed(() => props.overview?.org_member_counts ?? []);
const memberBreakdown = computed(() => {
    if (!isManagementView.value) {
        return stateItems.value.map((item) => ({
            label: item.state ?? t('Tidak dinyatakan'),
            count: item.count ?? 0,
            color: '#2F6B32',
        }));
    }

    const key = breakdownMode.value === 'branch' ? 'branches' : 'states';
    const label = breakdownMode.value === 'branch' ? 'name' : 'state';

    return (props.overview?.org_member_stats ?? [])
        .flatMap((org) => (org[key] ?? []).map((item) => ({
            label: item[label] ?? t('Tidak dinyatakan'),
            count: item.count ?? 0,
            organization: org.name,
            color: org.color_theme ?? '#334155',
        })))
        .sort((a, b) => b.count - a.count)
        .slice(0, 12);
});
const breakdownMax = computed(() => Math.max(1, ...memberBreakdown.value.map((item) => Number(item.count ?? 0))));
const managementActions = computed(() => [
    { label: t('Urus ahli'), description: t('Semak rekod dan profil'), href: props.managementLinks.information_hub_manage_url, tone: 'primary' },
    { label: t('Tambah program'), description: t('Cipta program baharu'), href: props.managementLinks.create_program_url, tone: 'plain' },
    { label: t('Urus program'), description: t('Peserta dan kehadiran'), href: props.managementLinks.create_event_url, tone: 'plain' },
    { label: t('Semak yuran'), description: t('Status bayaran ahli'), href: props.managementLinks.fees_members_url, tone: 'plain' },
    { label: t('Urus usrah'), description: t('Kumpulan dan ahli'), href: props.managementLinks.usrah_manage_url, tone: 'plain' },
    { label: t('Hantar siaran'), description: t('Makluman kepada ahli'), href: props.managementLinks.broadcasts_url, tone: 'plain' },
    { label: t('Urus infaq'), description: t('Kempen dan infaq'), href: props.managementLinks.infaq_url, tone: 'plain' },
    { label: t('Urus banner'), description: t('Paparan utama aplikasi'), href: props.managementLinks.banners_url, tone: 'plain' },
    { label: t('Urus pendukung'), description: t('Rekod dan hubungan'), href: props.managementLinks.supporters_url, tone: 'plain' },
].filter((item) => item.href));

function copyShareLink() {
    if (!navigator.clipboard?.writeText) return;

    navigator.clipboard.writeText(props.registrationShare.share_url).then(() => {
        shareCopied.value = true;
        setTimeout(() => (shareCopied.value = false), 2000);
    });
}

function shareRegistration() {
    const share = props.registrationShare;
    const payload = { title: share.title, text: share.description, url: share.share_url };

    if (navigator.share) {
        navigator.share(payload).catch(() => {});
        return;
    }

    window.open(`https://wa.me/?text=${encodeURIComponent(share.message)}`, '_blank', 'noopener');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('ms-MY', {
        style: 'currency',
        currency: 'MYR',
        maximumFractionDigits: 0,
    }).format(value ?? 0);
}

function formatRelativeTime(value) {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';

    const minutes = Math.floor((Date.now() - date.getTime()) / 60000);
    if (minutes < 1) return t('baru sahaja');
    if (minutes < 60) return t('{n} min lalu', { n: minutes });
    if (minutes < 1440) return t('{n} jam lalu', { n: Math.floor(minutes / 60) });
    return t('{n} hari lalu', { n: Math.floor(minutes / 1440) });
}

function alertDescription(alert) {
    if (alert.key === 'pending_bookings') return t('{n} tempahan masih berstatus pending.', { n: alert.count });
    if (alert.key === 'fees_due') return t('{n} ahli belum membuat bayaran yuran untuk tahun ini.', { n: alert.count });
    if (alert.key === 'no_programs') return t('Tiada program berjadual bulan ini. Pertimbangkan perancangan segera.');
    return alert.description;
}

function activityTone(type = '') {
    if (type === 'payment') return 'bg-emerald-50 text-emerald-800';
    if (type === 'booking') return 'bg-amber-50 text-amber-800';
    return 'bg-slate-100 text-slate-700';
}
</script>

<template>
    <Head :title="locale === 'en' ? 'Admin Dashboard' : 'Dashboard Pentadbir'" />

    <AppLayout>
        <template #header>{{ t('Dashboard Pentadbir') }}</template>

        <main class="mx-auto w-full max-w-[1500px] px-4 py-5 sm:px-6 lg:py-7">
            <section class="overflow-hidden rounded-2xl border border-slate-800 bg-[#071525] text-white">
                <div class="grid lg:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.9fr)]">
                    <div class="p-5 sm:p-7">
                        <p class="text-xs font-semibold text-[#B8D9C0]">{{ scopeLabel }}</p>
                        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ organization?.name }}</h2>
                                <p class="mt-1 max-w-xl text-sm leading-6 text-slate-300">{{ t('Ringkasan operasi yang memerlukan perhatian dan tindakan pentadbiran.') }}</p>
                            </div>
                            <ExportCsvButton :href="route('admin.members.export')" />
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-px overflow-hidden rounded-xl border border-white/10 bg-white/10 sm:grid-cols-4">
                            <div class="min-w-0 bg-[#0c2032] p-3.5 sm:p-4">
                                <dt class="text-xs text-slate-300">{{ t('Jumlah Ahli') }}</dt>
                                <dd class="mt-1 text-2xl font-black tabular-nums">{{ overview.total_members }}</dd>
                            </div>
                            <div class="min-w-0 bg-[#0c2032] p-3.5 sm:p-4">
                                <dt class="text-xs text-slate-300">{{ t('Ahli Aktif') }}</dt>
                                <dd class="mt-1 text-2xl font-black text-[#B8D9C0] tabular-nums">{{ overview.active_members }}</dd>
                            </div>
                            <div class="min-w-0 bg-[#0c2032] p-3.5 sm:p-4">
                                <dt class="text-xs text-slate-300">{{ t('Ahli Baharu (30h)') }}</dt>
                                <dd class="mt-1 text-2xl font-black tabular-nums">{{ overview.new_members_30d }}</dd>
                            </div>
                            <div class="min-w-0 bg-[#0c2032] p-3.5 sm:p-4">
                                <dt class="text-xs text-slate-300">{{ t('Yuran Bulan Ini') }}</dt>
                                <dd class="mt-1 text-xl font-black text-[#B8D9C0] tabular-nums sm:text-2xl">{{ formatCurrency(overview.fees_collected_month) }}</dd>
                            </div>
                        </dl>
                    </div>

                    <aside class="border-t border-white/10 bg-[#0b1b2b] p-5 lg:border-l lg:border-t-0 sm:p-6" aria-labelledby="registration-heading">
                        <div class="flex gap-4">
                            <div class="w-20 shrink-0 rounded-xl bg-white p-1.5 [&_svg]:h-auto [&_svg]:w-full" v-html="registrationShare.qr_svg"></div>
                            <div class="min-w-0">
                                <p id="registration-heading" class="text-sm font-bold">{{ t('Pendaftaran ahli') }}</p>
                                <p class="mt-1 text-xs leading-5 text-slate-300">{{ t('Kongsi pautan rasmi untuk pendaftaran kendiri.') }}</p>
                            </div>
                        </div>
                        <p class="mt-4 truncate rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-xs text-slate-200" :title="registrationShare.share_url">{{ registrationShare.share_url }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" class="min-h-11 rounded-lg bg-white px-3 text-sm font-bold text-[#071525] transition hover:bg-[#E4EBE5] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" @click="copyShareLink">
                                {{ shareCopied ? t('Pautan disalin') : t('Salin pautan') }}
                            </button>
                            <button type="button" class="min-h-11 rounded-lg border border-white/20 px-3 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white" @click="shareRegistration">
                                {{ t('Kongsi pautan') }}
                            </button>
                        </div>
                        <Link v-if="!isBranchAdmin" :href="route('admin.jemput-ahli')" class="mt-2 flex min-h-11 items-center justify-center rounded-lg border border-white/20 px-3 text-sm font-bold text-white transition hover:bg-white/10 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                            {{ t('Buka halaman jemputan') }}
                        </Link>
                    </aside>
                </div>
            </section>

            <div class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(330px,0.85fr)]">
                <div class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="actions-heading">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 id="actions-heading" class="text-lg font-black text-slate-900">{{ t('Tindakan utama') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Akses terus kepada tugasan pengurusan harian.') }}</p>
                            </div>
                            <Link v-if="managementLinks.directory_url" :href="managementLinks.directory_url" class="mt-3 inline-flex min-h-11 items-center text-sm font-bold text-[#123D2A] hover:text-[#2F6B32] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32] sm:mt-0">
                                {{ t('Buka direktori') }}
                            </Link>
                        </div>
                        <div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <Link v-for="action in managementActions" :key="action.label" :href="action.href" class="group min-h-28 rounded-xl border p-4 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="action.tone === 'primary' ? 'border-[#071525] bg-[#071525] text-white hover:bg-[#123D2A]' : 'border-slate-200 bg-white text-slate-900 hover:border-[#6FBF8A] hover:bg-[#F4F6F1]'">
                                <p class="text-sm font-bold">{{ action.label }}</p>
                                <p class="mt-1 text-xs leading-5" :class="action.tone === 'primary' ? 'text-slate-300' : 'text-slate-600'">{{ action.description }}</p>
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white" aria-labelledby="breakdown-heading">
                        <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                            <div>
                                <h2 id="breakdown-heading" class="text-lg font-black text-slate-900">
                                    {{ isManagementView ? t('Liputan ahli mengikut organisasi') : t('Liputan ahli mengikut negeri') }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Rekod dengan jumlah tertinggi dipaparkan untuk semakan pantas.') }}</p>
                            </div>
                            <div v-if="isManagementView" class="inline-flex rounded-lg bg-slate-100 p-1" aria-label="Pilih pecahan ahli">
                                <button type="button" class="min-h-11 rounded-md px-3 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="breakdownMode === 'state' ? 'bg-[#071525] text-white' : 'text-slate-600 hover:text-slate-900'" @click="breakdownMode = 'state'">{{ t('Negeri') }}</button>
                                <button type="button" class="min-h-11 rounded-md px-3 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="breakdownMode === 'branch' ? 'bg-[#071525] text-white' : 'text-slate-600 hover:text-slate-900'" @click="breakdownMode = 'branch'">{{ t('Cawangan') }}</button>
                            </div>
                        </div>
                        <div v-if="memberBreakdown.length" class="grid gap-px bg-slate-100 sm:grid-cols-2">
                            <div v-for="item in memberBreakdown" :key="`${item.organization ?? ''}-${item.label}`" class="bg-white p-4 sm:p-5">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-slate-900">{{ item.label ?? item.state }}</p>
                                        <p v-if="item.organization" class="mt-0.5 truncate text-xs text-slate-500">{{ item.organization }}</p>
                                    </div>
                                    <span class="shrink-0 text-lg font-black text-slate-900 tabular-nums">{{ item.count }}</span>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#2F6B32]" :style="{ width: `${Math.max(4, (Number(item.count) / breakdownMax) * 100)}%`, backgroundColor: item.color }"></div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-6 text-sm text-slate-600">
                            {{ t('Belum ada rekod ahli untuk paparan ini.') }}
                        </div>
                        <div class="flex justify-end border-t border-slate-100 px-5 py-3 sm:px-6">
                            <a :href="route('admin.members.export.states')" class="inline-flex min-h-11 items-center text-sm font-bold text-[#123D2A] hover:text-[#2F6B32] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]">{{ t('Muat turun laporan negeri') }}</a>
                        </div>
                    </section>
                </div>

                <div class="space-y-5">
                    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="attention-heading">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 id="attention-heading" class="text-lg font-black text-slate-900">{{ t('Perlu perhatian') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Status yang mungkin memerlukan tindakan anda.') }}</p>
                            </div>
                            <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-700 tabular-nums">{{ alertItems.length }}</span>
                        </div>
                        <div v-if="alertItems.length" class="mt-5 space-y-3">
                            <div v-for="(alert, index) in alertItems" :key="index" class="rounded-xl border p-4" :class="alert.type === 'high' ? 'border-rose-200 bg-rose-50' : 'border-amber-200 bg-amber-50'">
                                <p class="text-sm font-bold" :class="alert.type === 'high' ? 'text-rose-900' : 'text-amber-900'">{{ t(alert.title) }}</p>
                                <p class="mt-1 text-xs leading-5" :class="alert.type === 'high' ? 'text-rose-800' : 'text-amber-800'">{{ alertDescription(alert) }}</p>
                            </div>
                        </div>
                        <div v-else class="mt-5 rounded-xl bg-[#F4F6F1] p-4 text-sm leading-6 text-[#123D2A]">
                            {{ t('Tiada tindakan tertunggak pada masa ini.') }}
                        </div>
                    </section>

                    <section v-if="organizationItems.length" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="organizations-heading">
                        <div class="flex items-baseline justify-between gap-3">
                            <div>
                                <h2 id="organizations-heading" class="text-lg font-black text-slate-900">{{ t('Mengikut organisasi') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Jumlah ahli dalam setiap organisasi.') }}</p>
                            </div>
                        </div>
                        <div class="mt-4 divide-y divide-slate-100">
                            <div v-for="org in organizationItems" :key="org.id" class="flex items-center justify-between gap-3 py-3">
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: org.color_theme ?? '#334155' }"></span>
                                    <span class="truncate text-sm font-bold text-slate-800">{{ org.name }}</span>
                                </div>
                                <span class="text-lg font-black text-slate-900 tabular-nums">{{ org.member_count }}</span>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="activity-heading">
                        <div class="flex items-baseline justify-between gap-3">
                            <div>
                                <h2 id="activity-heading" class="text-lg font-black text-slate-900">{{ t('Aktiviti terkini') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Perubahan terbaharu dalam skop anda.') }}</p>
                            </div>
                        </div>
                        <div v-if="activityItems.length" class="mt-4 divide-y divide-slate-100">
                            <div v-for="activity in activityItems" :key="activity.id" class="flex gap-3 py-3 first:pt-0 last:pb-0">
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="activityTone(activity.type)"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-slate-900">{{ t(activity.title) }}</p>
                                    <p class="mt-0.5 truncate text-xs text-slate-600">{{ activity.description }}</p>
                                </div>
                                <time class="shrink-0 text-xs text-slate-500" :datetime="activity.created_at">{{ formatRelativeTime(activity.created_at) }}</time>
                            </div>
                        </div>
                        <p v-else class="mt-4 rounded-xl bg-slate-50 p-4 text-sm leading-6 text-slate-600">{{ t('Belum ada aktiviti direkodkan.') }}</p>
                    </section>

                    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" aria-labelledby="program-heading">
                        <h2 id="program-heading" class="text-lg font-black text-slate-900">{{ t('Ringkasan modul') }}</h2>
                        <div class="mt-4 space-y-4">
                            <div v-for="item in overview.program_chart" :key="item.label">
                                <div class="flex justify-between gap-3 text-sm">
                                    <span class="font-semibold text-slate-700">{{ t(item.label) }}</span>
                                    <span class="font-black text-slate-900 tabular-nums">{{ item.value }}</span>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-[#2F6B32]" :style="{ width: `${Math.max(4, (Number(item.value ?? 0) / chartMax) * 100)}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </main>
    </AppLayout>
</template>

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
    managedBranchNames: { type: Array, default: () => [] },
});

const shareCopied = ref(false);
const breakdownMode = ref('state');

const isManagementView = computed(() => props.organization?.slug === 'management');
const scopeLabel = computed(() => {
    if (isManagementView.value) return t('Keseluruhan organisasi');
    if (props.isBranchAdmin) return t('Cawangan/Negeri di bawah seliaan anda');
    return props.organization?.name ?? t('Organisasi anda');
});
const dashboardTitle = computed(() => {
    if (props.isBranchAdmin && props.managedBranchNames.length) {
        return props.managedBranchNames.join(', ');
    }

    return props.organization?.name;
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
    if (type === 'payment') return 'bg-emerald-600';
    if (type === 'booking') return 'bg-amber-500';
    return 'bg-indigo-600';
}
</script>

<template>
    <Head :title="locale === 'en' ? 'Admin Dashboard' : 'Dashboard Pentadbir'" />

    <AppLayout>
        <template #header>{{ t('Dashboard Pentadbir') }}</template>

        <main class="mx-auto w-full max-w-[1600px] space-y-5 px-4 py-5 sm:px-6 lg:py-7">
            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
                <section class="lg:col-span-8 rounded-2xl border border-slate-800 bg-[radial-gradient(110%_120%_at_95%_10%,#1e3a2f_0%,#0f172a_58%,#090e1a_100%)] p-5 text-white sm:p-7">
                    <div>
                        <p class="text-xs font-semibold text-[#B8D9C0]">{{ scopeLabel }}</p>
                        <div class="mt-2 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ dashboardTitle }}</h2>
                                <p class="mt-1 max-w-xl text-sm leading-6 text-slate-300">{{ t('Ringkasan operasi yang memerlukan perhatian dan tindakan pentadbiran.') }}</p>
                            </div>
                            <ExportCsvButton :href="route('admin.members.export')" />
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
                            <div class="min-w-0 rounded-xl border border-white/10 bg-white/[0.07] p-3.5">
                                <dt class="text-xs text-slate-300">{{ t('Jumlah Ahli') }}</dt>
                                <dd class="mt-1 text-2xl font-black tabular-nums">{{ overview.total_members }}</dd>
                            </div>
                            <div class="min-w-0 rounded-xl border border-white/10 bg-white/[0.07] p-3.5">
                                <dt class="text-xs text-slate-300">{{ t('Ahli Aktif') }}</dt>
                                <dd class="mt-1 text-2xl font-black text-[#B8D9C0] tabular-nums">{{ overview.active_members }}</dd>
                            </div>
                            <div class="min-w-0 rounded-xl border border-white/10 bg-white/[0.07] p-3.5">
                                <dt class="text-xs text-slate-300">{{ t('Ahli Baharu (30h)') }}</dt>
                                <dd class="mt-1 text-2xl font-black tabular-nums">{{ overview.new_members_30d }}</dd>
                            </div>
                            <div class="min-w-0 rounded-xl border border-white/10 bg-white/[0.07] p-3.5">
                                <dt class="text-xs text-slate-300">{{ t('Pengguna aplikasi') }}</dt>
                                <dd class="mt-1 text-2xl font-black text-[#B8D9C0] tabular-nums">{{ overview.app_users }}</dd>
                            </div>
                            <div class="col-span-2 min-w-0 rounded-xl border border-amber-400/30 bg-amber-500/15 p-3.5 sm:col-span-1">
                                <dt class="text-xs text-amber-200">{{ t('Yuran Tertunggak') }}</dt>
                                <dd class="mt-1 text-2xl font-black text-amber-300 tabular-nums">{{ overview.fees_due_count }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

                <aside class="flex flex-col justify-between rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-4" aria-labelledby="registration-heading">
                    <div class="flex gap-4">
                        <div class="w-20 shrink-0 rounded-xl border border-slate-200 bg-slate-50 p-1.5 [&_svg]:h-auto [&_svg]:w-full" v-html="registrationShare.qr_svg"></div>
                        <div class="min-w-0">
                            <p class="text-xs font-bold uppercase tracking-wide text-[#2F6B32]">{{ t('Pendaftaran awam') }}</p>
                            <h2 id="registration-heading" class="mt-1 text-base font-black text-slate-900">{{ t('Pautan dan kod QR keahlian') }}</h2>
                            <p class="mt-1 text-xs leading-5 text-slate-600">{{ t('Kongsi pautan rasmi untuk pendaftaran kendiri.') }}</p>
                        </div>
                    </div>
                    <p class="mt-5 truncate rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700" :title="registrationShare.share_url">{{ registrationShare.share_url }}</p>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        <button type="button" class="min-h-11 cursor-pointer rounded-xl bg-slate-900 px-3 text-sm font-bold text-white transition hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900" @click="copyShareLink">{{ shareCopied ? t('Pautan disalin') : t('Salin pautan') }}</button>
                        <button type="button" class="min-h-11 cursor-pointer rounded-xl bg-[#2F6B32] px-3 text-sm font-bold text-white transition hover:bg-[#123D2A] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" @click="shareRegistration">{{ t('Kongsi pautan') }}</button>
                    </div>
                    <Link v-if="!isBranchAdmin" :href="route('admin.jemput-ahli')" class="mt-2 flex min-h-11 items-center justify-center rounded-xl border border-slate-200 text-sm font-bold text-slate-700 transition hover:bg-slate-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]">{{ t('Buka halaman jemputan') }}</Link>
                </aside>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:items-stretch lg:grid-cols-12">
                <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-4" aria-labelledby="actions-heading">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                            <div>
                                <h2 id="actions-heading" class="text-lg font-black text-slate-900">{{ t('Tindakan utama') }}</h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Akses terus kepada tugasan pengurusan harian.') }}</p>
                            </div>
                            <Link v-if="managementLinks.directory_url" :href="managementLinks.directory_url" class="mt-3 inline-flex min-h-11 items-center text-sm font-bold text-[#123D2A] hover:text-[#2F6B32] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32] sm:mt-0">
                                {{ t('Buka direktori') }}
                            </Link>
                        </div>
                        <div class="mt-5 grid flex-1 grid-cols-2 gap-2.5">
                            <Link v-for="action in managementActions" :key="action.label" :href="action.href" class="group flex min-h-0 flex-col justify-between rounded-xl border p-3 transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="action.tone === 'primary' ? 'border-[#071525] bg-[#071525] text-white hover:bg-[#123D2A]' : 'border-slate-200 bg-slate-50 text-slate-900 hover:border-[#6FBF8A] hover:bg-[#F4F6F1]'">
                                <p class="text-sm font-bold">{{ action.label }}</p>
                                <p class="mt-1 text-xs leading-5" :class="action.tone === 'primary' ? 'text-slate-300' : 'text-slate-600'">{{ action.description }}</p>
                            </Link>
                        </div>
                </section>

                <section class="flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-4" aria-labelledby="organization-heading">
                    <div class="flex items-baseline justify-between gap-3">
                        <div>
                            <h2 id="organization-heading" class="text-lg font-black text-slate-900">{{ isManagementView ? t('Pecahan mengikut entiti') : t('Pecahan program') }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ isManagementView ? t('Taburan ahli setiap organisasi.') : t('Status modul dalam organisasi anda.') }}</p>
                        </div>
                        <span class="text-sm font-black text-slate-900 tabular-nums">{{ overview.total_members }}</span>
                    </div>
                    <div v-if="organizationItems.length" class="mt-5 space-y-4">
                        <div v-for="org in organizationItems" :key="org.id">
                            <div class="flex justify-between gap-3 text-sm">
                                <span class="flex min-w-0 items-center gap-2 font-bold text-slate-800"><span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: org.color_theme ?? '#334155' }"></span>{{ org.name }}</span>
                                <span class="font-black text-slate-900 tabular-nums">{{ org.member_count }}</span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full" :style="{ width: `${Math.max(4, (Number(org.member_count) / Math.max(1, ...organizationItems.map((item) => Number(item.member_count)))) * 100)}%`, backgroundColor: org.color_theme ?? '#334155' }"></div></div>
                        </div>
                    </div>
                    <div v-else class="mt-5 space-y-4">
                        <div v-for="item in overview.program_chart" :key="item.label">
                            <div class="flex justify-between gap-3 text-sm"><span class="font-bold text-slate-800">{{ t(item.label) }}</span><span class="font-black text-slate-900 tabular-nums">{{ item.value }}</span></div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-[#2F6B32]" :style="{ width: `${Math.max(4, (Number(item.value ?? 0) / chartMax) * 100)}%` }"></div></div>
                        </div>
                    </div>
                </section>

                <section class="h-full rounded-2xl border border-slate-200 bg-white p-5 shadow-sm md:col-span-2 lg:col-span-4" aria-labelledby="attention-heading">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="attention-heading" class="text-lg font-black text-slate-900">{{ t('Metrik operasi dan yuran') }}</h2>
                            <p class="mt-1 text-sm text-slate-600">{{ t('Status transaksi dan rekod semasa.') }}</p>
                        </div>
                        <span class="rounded-md bg-[#E4EBE5] px-2 py-1 text-xs font-bold text-[#123D2A]">{{ t('Aktif') }}</span>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-2.5">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><dt class="text-xs text-slate-600">{{ t('Jumlah Program') }}</dt><dd class="mt-1 text-xl font-black text-slate-900 tabular-nums">{{ overview.total_programs }}</dd></div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><dt class="text-xs text-slate-600">{{ t('Program Bulan Ini') }}</dt><dd class="mt-1 text-xl font-black text-slate-900 tabular-nums">{{ overview.events_this_month }}</dd></div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><dt class="text-xs text-slate-600">{{ t('Tempahan Pending') }}</dt><dd class="mt-1 text-xl font-black text-slate-900 tabular-nums">{{ overview.pending_facility_bookings }}</dd></div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3"><dt class="text-xs text-slate-600">{{ t('Yuran Tertunggak') }}</dt><dd class="mt-1 text-xl font-black text-slate-900 tabular-nums">{{ overview.fees_due_count }}</dd></div>
                    </dl>
                    <div v-if="alertItems.length" class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3.5"><p class="text-sm font-bold text-rose-900">{{ t(alertItems[0].title) }}</p><p class="mt-1 text-xs text-rose-800">{{ alertDescription(alertItems[0]) }}</p></div>
                    <div v-else class="mt-3 rounded-xl border border-[#D5E3D8] bg-[#F4F6F1] p-3.5 text-sm font-bold text-[#123D2A]">{{ t('Tiada tindakan tertunggak pada masa ini.') }}</div>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
                <section class="rounded-2xl border border-slate-200 bg-white lg:col-span-8" aria-labelledby="breakdown-heading">
                    <div class="flex flex-col gap-4 border-b border-slate-100 p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
                            <div>
                                <h2 id="breakdown-heading" class="text-lg font-black text-slate-900">
                                    {{ isManagementView ? t('Liputan ahli mengikut organisasi') : t('Liputan ahli mengikut negeri') }}
                                </h2>
                                <p class="mt-1 text-sm text-slate-600">{{ t('Rekod dengan jumlah tertinggi dipaparkan untuk semakan pantas.') }}</p>
                            </div>
                            <div v-if="isManagementView" class="inline-flex self-start rounded-lg bg-slate-100 p-1" aria-label="Pilih pecahan ahli">
                                <button type="button" class="min-h-11 cursor-pointer rounded-md px-3 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="breakdownMode === 'state' ? 'bg-[#071525] text-white' : 'text-slate-600 hover:text-slate-900'" :aria-pressed="breakdownMode === 'state'" @click="breakdownMode = 'state'">{{ t('Negeri') }}</button>
                                <button type="button" class="min-h-11 cursor-pointer rounded-md px-3 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="breakdownMode === 'branch' ? 'bg-[#071525] text-white' : 'text-slate-600 hover:text-slate-900'" :aria-pressed="breakdownMode === 'branch'" @click="breakdownMode = 'branch'">{{ t('Cawangan/Negeri') }}</button>
                            </div>
                        </div>
                        <div v-if="isManagementView" class="flex flex-wrap gap-2 border-b border-slate-100 px-5 py-3 sm:px-6" aria-label="Tapis organisasi">
                            <button v-for="entity in [{ key: 'all', label: t('Semua') }, { key: 'wadah', label: 'WADAH' }, { key: 'abim', label: 'ABIM' }, { key: 'pkpim', label: 'PKPIM' }]" :key="entity.key" type="button" class="min-h-11 cursor-pointer rounded-lg border px-3 text-sm font-bold transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#2F6B32]" :class="activeEntity === entity.key ? 'border-[#071525] bg-[#071525] text-white' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" :aria-pressed="activeEntity === entity.key" @click="activeEntity = entity.key">{{ entity.label }}</button>
                        </div>
                        <div v-if="memberBreakdown.length" class="grid max-h-[440px] gap-2.5 overflow-y-auto p-3 sm:grid-cols-2 sm:p-4">
                            <div v-for="item in memberBreakdown" :key="`${item.organization ?? ''}-${item.label}`" class="rounded-xl border border-slate-200 bg-slate-50/70 p-4 transition-colors hover:bg-slate-100 sm:p-5">
                                <div class="flex items-baseline justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-[#123D2A]" aria-hidden="true">
                                            <svg v-if="breakdownMode === 'branch'" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M9 7h1m4 0h1M9 11h1m4 0h1M9 15h1m4 0h1" /></svg>
                                            <svg v-else class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s7-5.18 7-12a7 7 0 10-14 0c0 6.82 7 12 7 12z" /><circle cx="12" cy="9" r="2" /></svg>
                                        </span>
                                        <div class="min-w-0"><p class="truncate text-sm font-bold text-slate-900">{{ item.label ?? item.state }}</p>
                                        <p v-if="item.organization" class="mt-0.5 truncate text-xs text-slate-500">{{ item.organization }}</p>
                                        </div>
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

                <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-4 sm:p-6" aria-labelledby="activity-heading">
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
            </div>
        </main>
    </AppLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    event: Object,
    registrations: Object, // paginator
    organizations: Array,
    filters: Object,
    stats: Object,
    items_to_prepare: { type: Array, default: () => [] },
});

const f = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    payment: props.filters?.payment || '',
    org: props.filters?.org || '',
    has_items: ['1', 'true', true].includes(props.filters?.has_items),
    sort: props.filters?.sort || '',
});

function apply(extra = {}) {
    const data = { ...f, ...extra };
    if (!data.has_items) delete data.has_items;
    Object.keys(data).forEach((k) => {
        if (data[k] === '' || data[k] === null || data[k] === undefined || data[k] === false) delete data[k];
    });
    router.get(route('admin.events.registrations', props.event.id), data, {
        preserveState: true,
        replace: true,
    });
}

function reset() {
    f.search = '';
    f.status = '';
    f.payment = '';
    f.org = '';
    f.has_items = false;
    f.sort = '';
    apply({ search: '', status: '', payment: '', org: '', has_items: '', sort: '' });
}

const hasFilters = computed(() => !!(f.search || f.status || f.payment || f.org || f.has_items || f.sort));

function updateStatus(registration, status) {
    const verb = status === 'cancelled' ? 'Batalkan' : 'Pulihkan';
    if (!confirm(`${verb} pendaftaran ${registration.name} (${registration.registration_no})?`)) return;

    router.patch(
        route('admin.events.registrations.update', registration.id),
        { status },
        { preserveScroll: true },
    );
}

function markPaid(registration) {
    if (!confirm(`Tandakan bayaran ${registration.name} (${registration.registration_no}) sebagai BERJAYA?`)) return;

    router.post(
        route('admin.events.registrations.mark-paid', registration.id),
        {},
        { preserveScroll: true },
    );
}

const dateFmt = (value) => {
    if (!value) return '—';
    return new Date(value).toLocaleString('ms-MY', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
};

const statusBadge = {
    confirmed: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    pending: 'border-amber-200 bg-amber-50 text-amber-700',
    cancelled: 'border-rose-200 bg-rose-50 text-rose-600',
    walkin: 'border-sky-200 bg-sky-50 text-sky-700',
};

const paymentBadge = {
    free: { label: 'Percuma', class: 'border-gray-200 bg-gray-50 text-gray-600' },
    paid: { label: 'Dibayar', class: 'border-emerald-200 bg-emerald-50 text-emerald-700' },
    unpaid: { label: 'Menunggu Bayaran', class: 'border-amber-200 bg-amber-50 text-amber-700' },
};

const payment = (r) => paymentBadge[r.payment_state] ?? paymentBadge.free;
</script>

<template>
    <Head :title="`Pendaftaran: ${event.title}`" />

    <AppLayout>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <div class="mb-1 flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <a :href="route('admin.events.show', event.id)" class="text-xs font-semibold text-gray-500 hover:text-gray-800">
                        &larr; Kembali ke program
                    </a>
                    <h1 class="mt-1 text-2xl font-black text-gray-900">Pendaftaran</h1>
                    <p class="text-sm text-gray-500">{{ event.title }}</p>
                </div>
                <a
                    :href="route('admin.events.registrations.export', event.id)"
                    class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Export CSV
                </a>
            </div>

            <div
                class="mt-3 rounded-2xl border px-4 py-3 text-xs font-medium"
                :class="event.registration_open
                    ? 'border-emerald-100 bg-emerald-50 text-emerald-700'
                    : 'border-amber-200 bg-amber-50 text-amber-800'"
            >
                <p>
                    <template v-if="event.registration_open">
                        Pendaftaran dibuka sehingga {{ dateFmt(event.registration_closes_at) }}.
                    </template>
                    <template v-else>
                        Pendaftaran telah ditutup pada {{ dateFmt(event.registration_closes_at) }}.
                    </template>
                </p>
                <p class="mt-0.5 text-[11px] opacity-80">
                    QR kehadiran hanya boleh diimbas {{ dateFmt(event.attendance_opens_at) }} hingga {{ dateFmt(event.attendance_closes_at) }} (24 jam sebelum hingga 24 jam selepas program).
                </p>
            </div>

            <div
                v-if="$page.props.flash?.success"
                class="mt-4 rounded-xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800"
            >
                {{ $page.props.flash.success }}
            </div>
            <div
                v-if="$page.props.flash?.error"
                class="mt-4 rounded-xl border border-red-100 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700"
            >
                {{ $page.props.flash.error }}
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <button
                    type="button"
                    class="rounded-2xl border border-gray-100 bg-white p-4 text-center shadow-sm hover:border-gray-300"
                    @click="reset"
                >
                    <p class="text-2xl font-black text-gray-900">{{ stats.total }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">Jumlah</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border p-4 text-center shadow-sm"
                    :class="f.status === 'confirmed' ? 'border-emerald-500 bg-emerald-50' : 'border-emerald-100 bg-emerald-50'"
                    @click="f.status = f.status === 'confirmed' ? '' : 'confirmed'; apply()"
                >
                    <p class="text-2xl font-black text-emerald-600">{{ stats.confirmed }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-500">Diterima</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border p-4 text-center shadow-sm"
                    :class="f.status === 'pending' ? 'border-amber-500 bg-amber-50' : 'border-amber-100 bg-amber-50'"
                    @click="f.status = f.status === 'pending' ? '' : 'pending'; apply()"
                >
                    <p class="text-2xl font-black text-amber-600">{{ stats.pending }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-amber-500">Menunggu Bayaran</p>
                </button>
                <button
                    type="button"
                    class="rounded-2xl border p-4 text-center shadow-sm"
                    :class="f.has_items ? 'border-sky-500 bg-sky-50' : 'border-sky-100 bg-sky-50'"
                    @click="f.has_items = !f.has_items; apply()"
                >
                    <p class="text-2xl font-black text-sky-600">{{ stats.with_items }}</p>
                    <p class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-sky-500">Ada Item</p>
                </button>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    <input
                        v-model="f.search"
                        type="search"
                        placeholder="Cari nama / No. IC / no daftar"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0"
                        @keyup.enter="apply()"
                    />
                    <select v-model="f.status" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Semua status</option>
                        <option value="confirmed">Diterima</option>
                        <option value="pending">Menunggu Bayaran</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                    <select v-model="f.payment" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Semua bayaran</option>
                        <option value="free">Percuma</option>
                        <option value="paid">Dibayar</option>
                        <option value="unpaid">Belum Dibayar</option>
                    </select>
                    <select v-if="organizations.length" v-model="f.org" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Semua organisasi</option>
                        <option v-for="o in organizations" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                    </select>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <select v-model="f.sort" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-0">
                        <option value="">Terbaru dahulu</option>
                        <option value="name">Nama (A-Z)</option>
                        <option value="items">Item dahulu</option>
                    </select>
                    <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700">
                        <input v-model="f.has_items" type="checkbox" class="rounded border-gray-300" />
                        Ada item sahaja
                    </label>
                    <button type="button" @click="apply()" class="rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        Tapis
                    </button>
                    <button
                        v-if="hasFilters"
                        type="button"
                        @click="reset"
                        class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div v-if="items_to_prepare.length" class="mt-4 rounded-2xl border border-sky-100 bg-sky-50 p-4">
                <h2 class="text-sm font-bold text-sky-900">Senarai sediaan item</h2>
                <p class="mt-0.5 text-xs text-sky-700">Jumlah item yang telah dibayar (tidak termasuk pendaftaran dibatalkan).</p>
                <ul class="mt-3 grid grid-cols-1 gap-1.5 sm:grid-cols-2">
                    <li
                        v-for="(item, i) in items_to_prepare"
                        :key="i"
                        class="flex items-center justify-between rounded-lg bg-white px-3 py-1.5 text-sm"
                    >
                        <span class="text-gray-700">{{ item.name }}<span v-if="item.variant_name" class="text-gray-400"> ({{ item.variant_name }})</span></span>
                        <span class="font-black text-sky-700">&times;{{ item.quantity }}</span>
                    </li>
                </ul>
            </div>

            <div class="mt-6 overflow-x-auto rounded-2xl border border-gray-100 bg-white shadow-sm">
                <table class="w-full min-w-[820px] text-sm">
                    <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Peserta</th>
                            <th class="px-4 py-3">Organisasi</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Bayaran</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-right">Tindakan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="r in registrations.data" :key="r.id">
                            <td class="px-4 py-3">
                                <p class="font-semibold text-gray-800">{{ r.name }}</p>
                                <p class="font-mono text-xs text-gray-400">
                                    {{ r.registration_no }}<span v-if="r.member_no"> &middot; {{ r.member_no }}</span>
                                </p>
                                <p v-if="r.ic_number" class="text-xs text-gray-400">IC: {{ r.ic_number }}</p>
                                <p v-if="r.phone" class="text-xs text-gray-400">{{ r.phone }}</p>
                                <span v-if="r.ticket_type" class="mt-1 inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600">{{ r.ticket_type }}</span>
                                <a
                                    v-if="r.document_path"
                                    :href="`/storage/${r.document_path}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="mt-1 block text-xs text-indigo-600 underline hover:text-indigo-700"
                                >
                                    Lihat dokumen
                                </a>
                            </td>
                            <td class="px-4 py-3 text-gray-600">{{ r.organization_name || '—' }}</td>
                            <td class="px-4 py-3">
                                <div v-if="r.items && r.items.length" class="space-y-0.5">
                                    <p v-for="(it, i) in r.items" :key="i" class="text-xs text-gray-700">
                                        {{ it.name }}<span v-if="it.variant_name" class="text-gray-400"> ({{ it.variant_name }})</span>
                                        <span class="font-semibold">&times;{{ it.quantity }}</span>
                                    </p>
                                    <p class="text-[11px] font-bold text-gray-500">RM {{ Number(r.addons_amount || 0).toFixed(2) }}</p>
                                    <span v-if="Number(r.addons_amount || 0) > 0" class="inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-bold text-sky-700">Item berbayar</span>
                                </div>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="payment(r).class">
                                    {{ payment(r).label }}<span v-if="r.payment_is_manual" class="ml-1 font-black">*manual</span>
                                </span>
                                <p v-if="Number(r.total_amount || 0) > 0" class="mt-1 text-[11px] text-gray-400">RM {{ Number(r.total_amount).toFixed(2) }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold" :class="statusBadge[r.status] ?? statusBadge.pending">
                                    {{ r.status_label }}
                                </span>
                                <p v-if="r.attended" class="mt-1 text-[10px] font-semibold text-emerald-600">Hadir</p>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="inline-flex flex-col items-end gap-1.5">
                                    <button
                                        v-if="r.payment_state === 'unpaid' && r.status !== 'cancelled'"
                                        type="button"
                                        class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-100"
                                        @click="markPaid(r)"
                                    >
                                        Tanda Dibayar
                                    </button>
                                    <button
                                        v-if="r.status !== 'cancelled'"
                                        type="button"
                                        class="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-50"
                                        @click="updateStatus(r, 'cancelled')"
                                    >
                                        Batal
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100"
                                        @click="updateStatus(r, 'confirmed')"
                                    >
                                        Pulihkan
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="registrations.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">Tiada peserta untuk penapis ini.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="registrations.links && registrations.links.length > 3" class="mt-4 flex flex-wrap justify-center gap-1">
                <template v-for="(link, i) in registrations.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        v-html="link.label"
                        preserve-scroll
                        class="rounded-lg border px-3 py-1.5 text-sm"
                        :class="link.active ? 'border-gray-900 bg-gray-900 text-white' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'"
                    />
                    <span v-else v-html="link.label" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-1.5 text-sm text-gray-300" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

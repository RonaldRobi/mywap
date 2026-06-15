<script setup>
import { ref, computed, reactive } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import AppSplashScreen from '@/Components/AppSplashScreen.vue';
import PwaInstallPrompt from '@/Components/PwaInstallPrompt.vue';

// ─── Auth & Theme ────────────────────────────────────────────────────────────

const page = usePage();
const auth = computed(() => page.props.auth);
const user = computed(() => auth.value?.user);
const org  = computed(() => user.value?.organization);
const currentUrl = computed(() => page.url ?? '');

function queryParam(key) {
    const query = currentUrl.value.includes('?')
        ? currentUrl.value.split('?')[1]
        : '';

    return new URLSearchParams(query).get(key);
}

/**
 * Dynamic theming: each NGO slug maps to a set of Tailwind utility classes.
 * The sidebar active highlight, badge colour, and bottom-nav indicator all
 * respect the currently authenticated user's organisation.
 */
const themeMap = {
    management: {
        accent:     'bg-slate-500',
        accentText: 'text-slate-600',
        accentBg:   'bg-slate-50',
        badge:      'bg-slate-100 text-slate-700',
        dot:        'bg-slate-500',
        ring:       'ring-slate-400',
    },
    pkpim: {
        accent:     'bg-indigo-500',
        accentText: 'text-indigo-600',
        accentBg:   'bg-indigo-50',
        badge:      'bg-indigo-100 text-indigo-700',
        dot:        'bg-indigo-500',
        ring:       'ring-indigo-400',
    },
    abim: {
        accent:     'bg-emerald-500',
        accentText: 'text-emerald-600',
        accentBg:   'bg-emerald-50',
        badge:      'bg-emerald-100 text-emerald-700',
        dot:        'bg-emerald-500',
        ring:       'ring-emerald-400',
    },
    wadah: {
        accent:     'bg-amber-500',
        accentText: 'text-amber-600',
        accentBg:   'bg-amber-50',
        badge:      'bg-amber-100 text-amber-700',
        dot:        'bg-amber-500',
        ring:       'ring-amber-400',
    },
};

const theme = computed(() =>
    themeMap[org.value?.slug] ?? themeMap['abim']
);

// ─── State ───────────────────────────────────────────────────────────────────

const sidebarOpen      = ref(true);
const profileMenuOpen  = ref(false);
const notifMenuOpen    = ref(false);
const mobileMenuOpen   = ref(false);
const groupOpenState   = reactive({
    ecommerce: true,
    facilities: true,
    adminFacilities: true,
});

// ─── Role helpers ───────────────────────────────────────────────────────────

const roles = computed(() => user.value?.roles ?? []);
const isSuperadmin = computed(() => roles.value.includes('Superadmin'));
const isAdmin      = computed(() => roles.value.includes('Admin'));
const isMember     = computed(() => roles.value.includes('Member'));
const notifications = computed(() => page.props.notifications ?? { unread_count: 0, recent: [] });

function openNotifications() {
    notifMenuOpen.value = !notifMenuOpen.value;
    if (notifMenuOpen.value && notifications.value.unread_count > 0) {
        router.post(route('notifications.read-all'), {}, { preserveScroll: true, preserveState: true });
    }
}

// ─── Navigation ──────────────────────────────────────────────────────────────

const ecommerceChildren = computed(() => {
    if (isAdmin.value || isSuperadmin.value) {
        return [
            {
                label: 'Produk',
                href: route('products.index'),
                active: route().current('products.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/></svg>`,
            },
            {
                label: 'Kategori',
                href: route('categories.index'),
                active: route().current('categories.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>`,
            },
            {
                label: 'Pesanan',
                href: route('orders.index'),
                active: route().current('orders.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>`,
            },
        ];
    }

    if (isMember.value) {
        return [
            {
                label: 'Produk',
                href: route('products.index'),
                active: route().current('products.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 12h18M3 17h18"/></svg>`,
            },
            {
                label: 'Pesanan Saya',
                href: route('orders.index'),
                active: route().current('orders.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>`,
            },
        ];
    }

    return [];
});

const ecommerceActive = computed(() => ecommerceChildren.value.some((c) => c.active));
const memberFacilityChildren = computed(() => {
    if (isMember.value && !isAdmin.value && !isSuperadmin.value) {
        return [
            {
                label: 'Tempahan Ruang',
                href: route('member.facilities.index'),
                active: route().current('member.facilities.*') && queryParam('view') !== 'history',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>`,
            },
            {
                label: 'Sejarah Tempahan',
                href: route('member.facilities.index', { view: 'history' }),
                active: route().current('member.facilities.index') && queryParam('view') === 'history',
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
            },
        ];
    }

    return [];
});
const facilitiesActive = computed(() => memberFacilityChildren.value.some((c) => c.active));
const adminFacilityChildren = computed(() => {
    if (isAdmin.value || isSuperadmin.value) {
        return [
            {
                label: 'Urus Ruang',
                href: route('admin.facilities.manage'),
                active: route().current('admin.facilities.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>`,
            },
            {
                label: 'Senarai Tempahan',
                href: route('admin.facility-bookings.index'),
                active: route().current('admin.facility-bookings.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
            },
        ];
    }

    return [];
});
const adminFacilitiesActive = computed(() => adminFacilityChildren.value.some((c) => c.active));

function toggleGroup(groupKey) {
    if (!groupKey) return;
    groupOpenState[groupKey] = !groupOpenState[groupKey];
}

function isGroupOpen(groupKey) {
    if (!groupKey) return false;
    return !!groupOpenState[groupKey];
}

const navItems = computed(() => [
    {
        label:  'Dashboard',
        href:   route('dashboard'),
        active: route().current('dashboard') || route().current('admin.dashboard') || route().current('member.dashboard'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
               </svg>`,
    },
    {
        label:  'Program',
        href:   route('events.index'),
        active: route().current('events.*'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
               </svg>`,
    },
    {
        label:  'Info Terkini',
        href:   isAdmin.value || isSuperadmin.value ? route('admin.news.manage') : route('news.index'),
        active: route().current('news.*') || route().current('admin.news.*'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M19 21H9a2 2 0 01-2-2V5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2zM7 7H5a2 2 0 00-2 2v10a2 2 0 002 2h2M12 7h5M12 11h5M12 15h5"/>
               </svg>`,
    },
    {
        label:  'Undian',
        href:   isAdmin.value || isSuperadmin.value ? route('admin.polls.index') : route('member.polls.index'),
        active: route().current('*polls.*'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
               </svg>`,
    },
        ...(isAdmin.value || isSuperadmin.value ? [{
                label:  'Members',
                href:   route('admin.hub.manage'),
                active: route().current('admin.hub.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                             </svg>`,
        }] : []),
        ...(isAdmin.value || isSuperadmin.value ? [{
                label:  'Yuran Ahli',
                href:   route('admin.fees.members'),
                active: route().current('admin.fees.members'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
        }, {
                label:  'Kewangan',
                href:   route('admin.finance.index'),
                active: route().current('admin.finance.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>`,
        }, {
                label:  'Cawangan',
                href:   route('branches.index'),
                active: route().current('branches.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
        }, {
                label:  'Jawatan',
                href:   route('positions.index'),
                active: route().current('positions.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,
        }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'Pustaka Manage',
                        href:   route('admin.library.manage'),
                        active: route().current('admin.library.manage'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-6.747-9.62h13.494M4.5 19.5h15a2 2 0 002-2v-11a2 2 0 00-2-2h-15a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                                 </svg>`,
                    }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'Artikel Manage',
                        href:   route('admin.articles.manage'),
                        active: route().current('admin.articles.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9.5L18.5 7H20a2 2 0 012 2v10a2 2 0 01-2 2z"/>
                                 </svg>`,
                    }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'Banner Manage',
                        href:   route('superadmin.banners.index'),
                        active: route().current('superadmin.banners.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 17h18M7 3v18m10-18v18"/>
                                 </svg>`,
                    }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'Infaq',
                        href:   route('superadmin.infaq.index'),
                        active: route().current('superadmin.infaq.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 1.12-3 2.5S10.343 13 12 13s3 1.12 3 2.5S13.657 18 12 18m0-10v10m0-10c1.11 0 2.08.402 2.599 1M12 8c-1.11 0-2.08.402-2.599 1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                 </svg>`,
                    }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'Organizations',
                        href:   route('superadmin.organizations.index'),
                        active: route().current('superadmin.organizations.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M3 17h18M7 7v10m10-10v10M8 17l8-10"/>
                                 </svg>`,
                    }] : []),
                    ...(isSuperadmin.value ? [{
                        label:  'myWAP Settings',
                        href:   route('superadmin.settings.index'),
                        active: route().current('superadmin.settings.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317a1.724 1.724 0 013.35 0 1.724 1.724 0 002.573 1.066 1.724 1.724 0 012.36 2.36 1.724 1.724 0 001.065 2.572 1.724 1.724 0 010 3.35 1.724 1.724 0 00-1.066 2.573 1.724 1.724 0 01-2.36 2.36 1.724 1.724 0 00-2.572 1.065 1.724 1.724 0 01-3.35 0 1.724 1.724 0 00-2.573-1.066 1.724 1.724 0 01-2.36-2.36 1.724 1.724 0 00-1.065-2.572 1.724 1.724 0 010-3.35 1.724 1.724 0 001.066-2.573 1.724 1.724 0 012.36-2.36 1.724 1.724 0 002.572-1.065z"/>
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                 </svg>`,
                    }, {
                        label:  'Template Emel',
                        href:   route('admin.email-templates.index'),
                        active: route().current('admin.email-templates.*'),
                        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                 </svg>`,
                    }] : []),
        {
                label:  'Usrah',
                href:   isMember.value ? route('member.usrah') : route('admin.usrah.index'),
                active: route().current('member.usrah') || route().current('admin.usrah.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-5.747-8.62h11.494M4.5 19.5h15a2 2 0 002-2v-11a2 2 0 00-2-2h-15a2 2 0 00-2 2v11a2 2 0 002 2z"/>
                             </svg>`,
        },
        {
                label:  'Directory',
                href:   route('directory.index'),
                active: route().current('directory.index'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M12 12a4 4 0 100-8 4 4 0 000 8zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                             </svg>`,
        },
        ...(isAdmin.value || isSuperadmin.value ? [{
                label: 'Broadcast',
                href: route('admin.broadcasts.index'),
                active: route().current('admin.broadcasts.*'),
                icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                 <path stroke-linecap="round" stroke-linejoin="round" d="M11 5h2m-1 0v14m-7-7h14"/>
                             </svg>`,
        }] : []),
    {
        label:  'Yuran & Bayaran',
        href:   isSuperadmin.value
                    ? route('superadmin.fees.index')
                    : isAdmin.value
                        ? route('admin.transactions')
                        : route('member.financial.overview'),
        active: route().current('superadmin.fees.*')
             || route().current('superadmin.transactions')
             || route().current('admin.transactions')
             || route().current('member.financial.overview'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
               </svg>`,
    },
    ...(ecommerceChildren.value.length ? [{
        type: 'group',
        label: 'Ecommerce',
        groupKey: 'ecommerce',
        active: ecommerceActive.value,
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 7H19M7 13l-2-8m5 15a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2z"/></svg>`,
        children: ecommerceChildren.value,
    }] : []),
    ...(adminFacilityChildren.value.length ? [{
        type: 'group',
        label: 'Facilities',
        groupKey: 'adminFacilities',
        active: adminFacilitiesActive.value,
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>`,
        children: adminFacilityChildren.value,
    }] : []),
    ...(memberFacilityChildren.value.length ? [{
        type: 'group',
        label: 'Facilities',
        groupKey: 'facilities',
        active: facilitiesActive.value,
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18M5 7v13h14V7M9 7V4h6v3M9 13h6"/></svg>`,
        children: memberFacilityChildren.value,
    }] : []),
    ...(isMember.value ? [{
        label: 'Pengumuman',
        href: route('member.announcements'),
        active: route().current('member.announcements') || route().current('member.hub'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5h2m-6 4h10M6 13h12M8 17h8"/></svg>`,
    }, {
        label: 'Pustaka',
        href: route('member.library'),
        active: route().current('member.library'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v11.494m-6.747-9.62h13.494M4.5 19.5h15a2 2 0 002-2v-11a2 2 0 00-2-2h-15a2 2 0 00-2 2v11a2 2 0 002 2z"/></svg>`,
    }, {
        label: 'Kad Ahli',
        href: route('member.card'),
        active: route().current('member.card'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18M7 15h1m3 0h2m-9 5h16a2 2 0 002-2V6a2 2 0 00-2-2H4a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>`,
    }, {
        label: 'Jemput Ahli',
        href: route('member.referral'),
        active: route().current('member.referral'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>`,
    }] : []),
    {
        label:  'Profile',
        href:   route('profile.edit'),
        active: route().current('profile.edit'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
               </svg>`,
    },
    {
        label:  'Journey',
        href:   route('profile.show'),
        active: route().current('profile.show'),
        icon: `<svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                 <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
               </svg>`,
    },
]);

// Auto-open grouped menus when inside them
if (ecommerceActive.value) {
    groupOpenState.ecommerce = true;
}
if (adminFacilitiesActive.value) {
    groupOpenState.adminFacilities = true;
}
if (facilitiesActive.value) {
    groupOpenState.facilities = true;
}

// Bottom nav items (mobile) — keep concise: 4 items max
const bottomNavItems = computed(() => [
    { label: 'Home',     href: route('dashboard'),     active: route().current('dashboard') || route().current('admin.dashboard') || route().current('member.dashboard'),   icon: navItems.value[0].icon },
    { label: 'Program',  href: route('events.index'),  active: route().current('events.*'),    icon: navItems.value[1].icon },
    { label: isMember.value ? 'Pustaka' : 'Usrah', href: isMember.value ? route('member.library') : (isAdmin.value || isSuperadmin.value ? route('admin.usrah.index') : route('member.usrah')), active: isMember.value ? route().current('member.library') : (route().current('member.usrah') || route().current('admin.usrah.*')), icon: isMember.value ? navItems.value.find(i => i.label === 'Pustaka')?.icon : navItems.value.find(i => i.label === 'Usrah')?.icon },
    { label: isMember.value ? 'Kad' : 'Profile',  href: isMember.value ? route('member.card') : route('profile.edit'),  active: isMember.value ? route().current('member.card') : route().current('profile.edit'), icon: isMember.value ? navItems.value.find(i => i.label === 'Kad Ahli')?.icon : navItems.value.find(i => i.label === 'Profile')?.icon },
]);
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  DESKTOP SIDEBAR  (hidden on mobile)                             -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <aside
            :class="[
                'hidden md:flex flex-col fixed inset-y-0 left-0 z-40 transition-all duration-300',
                sidebarOpen ? 'w-64' : 'w-16',
                'bg-white border-r border-gray-100 shadow-sm'
            ]"
        >
            <!-- Logo / Brand -->
            <div class="flex items-center gap-3 px-4 h-16 border-b border-gray-100 shrink-0">
                <ApplicationLogo class="w-8 h-8 shrink-0" />
                <transition name="fade">
                    <span v-if="sidebarOpen" class="text-sm font-bold text-gray-800 tracking-tight truncate">
                        myWAP
                    </span>
                </transition>
            </div>

            <!-- Organisation badge -->
            <div v-if="sidebarOpen && org" class="mx-3 mt-3 mb-1">
                <span :class="['inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full', theme.badge]">
                    <span :class="['w-1.5 h-1.5 rounded-full', theme.dot]"></span>
                    {{ org.name }}
                </span>
            </div>

            <!-- Nav Links -->
            <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
                <template v-for="item in navItems" :key="item.label">
                    <button
                        v-if="item.type === 'group'"
                        type="button"
                        @click="toggleGroup(item.groupKey)"
                        :class="[
                            'relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-150 text-left',
                            item.active
                                ? [theme.accentBg, theme.accentText, 'font-semibold']
                                : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                        ]"
                    >
                        <span
                            v-if="item.active"
                            :class="['absolute left-0 w-1 h-6 rounded-r-full', theme.accent]"
                        ></span>
                        <span class="shrink-0" v-html="item.icon" />
                        <transition name="fade">
                            <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                        </transition>
                        <span class="ms-auto shrink-0 text-gray-400" v-if="sidebarOpen">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" :class="isGroupOpen(item.groupKey) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div v-if="item.type === 'group' && isGroupOpen(item.groupKey) && sidebarOpen" class="ml-6 space-y-0.5">
                        <Link
                            v-for="child in item.children"
                            :key="`${item.label}-${child.label}`"
                            :href="child.href"
                            :class="[
                                'relative flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors duration-150',
                                child.active
                                    ? [theme.accentBg, theme.accentText, 'font-semibold']
                                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                            ]"
                        >
                            <span
                                v-if="child.active"
                                :class="['absolute left-0 w-1 h-5 rounded-r-full', theme.accent]"
                            ></span>
                            <span class="shrink-0" v-html="child.icon" />
                            <span class="truncate">{{ child.label }}</span>
                        </Link>
                    </div>

                    <Link
                        v-else-if="item.type !== 'group'"
                        :href="item.href"
                        :class="[
                            'relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-150',
                            item.active
                                ? [theme.accentBg, theme.accentText, 'font-semibold']
                                : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                        ]"
                    >
                        <span
                            v-if="item.active"
                            :class="['absolute left-0 w-1 h-6 rounded-r-full', theme.accent]"
                        ></span>
                        <span class="shrink-0" v-html="item.icon" />
                        <transition name="fade">
                            <span v-if="sidebarOpen" class="truncate">{{ item.label }}</span>
                        </transition>
                    </Link>
                </template>
            </nav>

            <!-- Sidebar Toggle -->
            <div class="border-t border-gray-100 p-3">
                <button
                    @click="sidebarOpen = !sidebarOpen"
                    class="w-full flex items-center justify-center p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-50 transition-colors"
                    :title="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 transition-transform duration-300" :class="sidebarOpen ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                    </svg>
                </button>
            </div>
        </aside>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  MAIN CONTENT AREA  (offset by sidebar on desktop)               -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <div
            :class="[
                'flex flex-col flex-1 transition-all duration-300',
                'md:pl-64', // always reserve full sidebar width for layout simplicity
            ]"
        >
            <!-- ─── TOP HEADER (glassmorphism) ─────────────────────────────── -->
            <header class="sticky top-0 z-30 backdrop-blur-md bg-white/70 border-b border-gray-100/80 shadow-sm">
                <div class="flex items-center justify-between h-16 px-4 md:px-6">

                    <!-- Left: hamburger (mobile) + page title -->
                    <div class="flex items-center gap-3 min-w-0">
                        <!-- Hamburger button — mobile only -->
                        <button
                            @click="mobileMenuOpen = true"
                            class="md:hidden p-2 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors shrink-0"
                            aria-label="Open navigation menu"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <h1 class="text-base font-semibold text-gray-800 truncate">
                            <slot name="header">Dashboard</slot>
                        </h1>
                    </div>

                    <div class="flex items-center gap-2 md:gap-3">

                        <!-- Notification Bell -->
                        <div class="relative">
                            <button @click="openNotifications" class="relative p-2 rounded-xl text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                                <span
                                    v-if="notifications.unread_count > 0"
                                    class="absolute top-1.5 right-1.5 h-2.5 w-2.5 rounded-full bg-red-500"
                                ></span>
                            </button>

                            <transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-if="notifMenuOpen"
                                    v-click-outside="() => notifMenuOpen = false"
                                    class="absolute right-0 mt-2 w-80 rounded-2xl border border-white/60 backdrop-blur-md bg-white/90 shadow-lg z-50 overflow-hidden"
                                >
                                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                        <p class="text-sm font-bold text-gray-800">Notifications</p>
                                        <span class="text-xs text-gray-500">{{ notifications.unread_count }} unread</span>
                                    </div>
                                    <div class="max-h-80 overflow-y-auto">
                                        <div
                                            v-for="item in notifications.recent"
                                            :key="item.id"
                                            class="px-4 py-3 border-b border-gray-50"
                                            :class="item.is_read ? 'bg-white' : 'bg-blue-50/60'"
                                        >
                                            <p class="text-sm font-semibold text-gray-800">{{ item.title }}</p>
                                            <p class="mt-1 text-xs text-gray-600 line-clamp-2">{{ item.content }}</p>
                                            <p class="mt-1 text-[11px] text-gray-400">{{ item.created_at }}</p>
                                        </div>
                                        <div v-if="!notifications.recent.length" class="px-4 py-8 text-center text-sm text-gray-500">
                                            Tiada notifikasi.
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </div>

                        <!-- Profile Dropdown -->
                        <div class="relative">
                            <button
                                @click="profileMenuOpen = !profileMenuOpen"
                                class="flex items-center gap-2 pl-2 pr-3 py-1.5 rounded-xl hover:bg-gray-100 transition-colors"
                            >
                                <!-- Avatar initials -->
                                <span :class="['flex h-8 w-8 items-center justify-center rounded-full text-white text-xs font-bold shrink-0', theme.accent]">
                                    {{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                                </span>
                                <div class="hidden sm:block text-left">
                                    <p class="text-xs font-semibold text-gray-800 leading-none">{{ user?.name ?? 'User' }}</p>
                                    <p class="text-[11px] text-gray-400 mt-0.5 leading-none">{{ org?.name ?? '—' }}</p>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400 hidden sm:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <!-- Dropdown panel -->
                            <transition
                                enter-active-class="transition ease-out duration-100"
                                enter-from-class="opacity-0 scale-95"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition ease-in duration-75"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <div
                                    v-if="profileMenuOpen"
                                    v-click-outside="() => profileMenuOpen = false"
                                    class="absolute right-0 mt-2 w-52 bg-white rounded-2xl shadow-lg border border-gray-100 py-1 z-50"
                                >
                                    <Link
                                        :href="route('profile.edit')"
                                        class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50"
                                        @click="profileMenuOpen = false"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                        My Profile
                                    </Link>
                                    <div class="my-1 border-t border-gray-100"></div>
                                    <Link
                                        :href="route('logout')"
                                        method="post"
                                        as="button"
                                        class="flex w-full items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50"
                                        @click="profileMenuOpen = false"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                                        </svg>
                                        Log Out
                                    </Link>
                                </div>
                            </transition>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ─── PAGE SLOT ──────────────────────────────────────────────── -->
            <main class="flex-1 pb-20 md:pb-6">
                <slot />
            </main>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  MOBILE DRAWER — full slide-out menu (hidden on desktop)         -->
        <!-- ══════════════════════════════════════════════════════════════════ -->

        <!-- Backdrop overlay -->
        <transition
            enter-active-class="transition-opacity duration-300 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileMenuOpen"
                class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm md:hidden"
                @click="mobileMenuOpen = false"
                aria-hidden="true"
            />
        </transition>

        <!-- Slide-out drawer panel -->
        <transition
            enter-active-class="transition-transform duration-300 ease-out"
            enter-from-class="-translate-x-full"
            enter-to-class="translate-x-0"
            leave-active-class="transition-transform duration-200 ease-in"
            leave-from-class="translate-x-0"
            leave-to-class="-translate-x-full"
        >
            <aside
                v-if="mobileMenuOpen"
                class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-white shadow-2xl md:hidden"
            >
                <!-- Drawer header: logo + close button -->
                <div class="flex items-center justify-between px-4 h-16 border-b border-gray-100 shrink-0">
                    <div class="flex items-center gap-3">
                        <ApplicationLogo class="w-12 h-12 max-w-[48px] max-h-[48px] shrink-0" />
                        <span class="text-sm font-bold text-gray-800 tracking-tight">myWAP</span>
                    </div>
                    <button
                        @click="mobileMenuOpen = false"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-700 hover:bg-gray-100 transition-colors"
                        aria-label="Close menu"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Org badge -->
                <div v-if="org" class="mx-3 mt-3 mb-1">
                    <span :class="['inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full', theme.badge]">
                        <span :class="['w-1.5 h-1.5 rounded-full', theme.dot]"></span>
                        {{ org.name }}
                    </span>
                </div>

                <!-- Nav links -->
                <nav class="flex-1 overflow-y-auto py-3 px-2 space-y-0.5">
                    <template v-for="item in navItems" :key="item.label">
                        <button
                            v-if="item.type === 'group'"
                            type="button"
                            @click="toggleGroup(item.groupKey)"
                            :class="[
                                'relative w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-150 text-left',
                                item.active
                                    ? [theme.accentBg, theme.accentText, 'font-semibold']
                                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                            ]"
                        >
                            <span
                                v-if="item.active"
                                :class="['absolute left-0 w-1 h-6 rounded-r-full', theme.accent]"
                            ></span>
                            <span class="shrink-0" v-html="item.icon" />
                            <span class="truncate">{{ item.label }}</span>
                            <span class="ms-auto shrink-0 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" :class="isGroupOpen(item.groupKey) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </span>
                        </button>

                        <div v-if="item.type === 'group' && isGroupOpen(item.groupKey)" class="ml-6 space-y-0.5">
                            <Link
                                v-for="child in item.children"
                                :key="`${item.label}-${child.label}`"
                                :href="child.href"
                                @click="mobileMenuOpen = false"
                                :class="[
                                    'relative flex items-center gap-3 px-3 py-2 rounded-xl text-sm font-medium transition-colors duration-150',
                                    child.active
                                        ? [theme.accentBg, theme.accentText, 'font-semibold']
                                        : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                                ]"
                            >
                                <span
                                    v-if="child.active"
                                    :class="['absolute left-0 w-1 h-5 rounded-r-full', theme.accent]"
                                ></span>
                                <span class="shrink-0" v-html="child.icon" />
                                <span class="truncate">{{ child.label }}</span>
                            </Link>
                        </div>

                        <Link
                            v-else-if="item.type !== 'group'"
                            :href="item.href"
                            @click="mobileMenuOpen = false"
                            :class="[
                                'relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors duration-150',
                                item.active
                                    ? [theme.accentBg, theme.accentText, 'font-semibold']
                                    : 'text-gray-500 hover:text-gray-800 hover:bg-gray-50'
                            ]"
                        >
                            <span
                                v-if="item.active"
                                :class="['absolute left-0 w-1 h-6 rounded-r-full', theme.accent]"
                            ></span>
                            <span class="shrink-0" v-html="item.icon" />
                            <span class="truncate">{{ item.label }}</span>
                        </Link>
                    </template>
                </nav>

                <!-- Logout at the bottom -->
                <div class="border-t border-gray-100 p-3 shrink-0 safe-area-bottom">
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        class="flex w-full items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm text-red-600 hover:bg-red-50 transition-colors"
                        @click="mobileMenuOpen = false"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Log Out
                    </Link>
                </div>
            </aside>
        </transition>

        <!-- ══════════════════════════════════════════════════════════════════ -->
        <!--  MOBILE BOTTOM NAVIGATION  (hidden on desktop)                   -->
        <!-- ══════════════════════════════════════════════════════════════════ -->
        <nav class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/80 backdrop-blur-md border-t border-gray-100 safe-area-bottom">
            <div class="grid grid-cols-4 h-16">
                <Link
                    v-for="item in bottomNavItems"
                    :key="item.label"
                    :href="item.href"
                    :class="[
                        'flex flex-col items-center justify-center gap-1 text-[10px] font-medium transition-colors duration-150',
                        item.active ? [theme.accentText, 'font-semibold'] : 'text-gray-400 hover:text-gray-600'
                    ]"
                >
                    <span :class="item.active ? theme.accentText : ''" v-html="item.icon" />
                    {{ item.label }}
                    <!-- Active dot indicator -->
                    <span v-if="item.active" :class="['w-1 h-1 rounded-full', theme.dot]"></span>
                </Link>
            </div>
        </nav>

        <!-- PWA Install Prompt -->
        <PwaInstallPrompt />
        <AppSplashScreen />
    </div>
</template>

<style scoped>
/* Smooth sidebar text fade */
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to        { opacity: 0; }

/* Ensure bottom nav avoids iPhone home indicator */
.safe-area-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
</style>

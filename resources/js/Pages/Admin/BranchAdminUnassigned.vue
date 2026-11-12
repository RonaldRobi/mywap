<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
</script>

<template>
    <Head title="Akaun Belum Ditugaskan" />

    <AppLayout>
        <div class="mx-auto flex w-full max-w-2xl flex-col items-center px-4 py-16 text-center">
            <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.29 3.86l-8.02 14a2 2 0 001.74 3h16.02a2 2 0 001.74-3l-8.02-14a2 2 0 00-3.48 0z" />
                </svg>
            </div>

            <h1 class="mt-5 text-2xl font-black text-gray-900">Akaun admin cawangan belum ditugaskan</h1>
            <p class="mt-2 max-w-lg text-sm text-gray-500">
                Akaun ini mempunyai akses Admin Cawangan tetapi belum dilantik ke mana-mana cawangan.
                Sila hubungi admin organisasi untuk menetapkan cawangan anda melalui halaman Cawangan.
            </p>

            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <Link :href="route('profile.edit')" class="rounded-xl bg-gray-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-700">
                    Buka Profil
                </Link>
                <Link :href="route('logout')" method="post" as="button" class="rounded-xl border border-gray-200 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Log Keluar
                </Link>
            </div>
        </div>
    </AppLayout>
</template>

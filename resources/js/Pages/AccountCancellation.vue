<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DeleteUserForm from '@/Pages/Profile/Partials/DeleteUserForm.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Pembatalan Akaun" />

    <AppLayout :back-route="route('profile.edit')" back-label="Kembali ke Profil">
        <template #header>Pembatalan Akaun</template>

        <div class="mx-auto max-w-3xl px-4 py-4 md:px-6 md:py-6">
            <div class="space-y-5 md:space-y-6">

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <h2 class="text-lg font-black text-gray-900">Maklumat Pembatalan Akaun</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Anda boleh membatalkan (memadam) akaun myWAP anda pada bila-bila masa.
                        Tindakan ini adalah kekal dan tidak boleh dibatalkan.
                    </p>
                    <ul class="mt-4 list-disc space-y-2 pl-5 text-sm text-gray-600">
                        <li>Semua data peribadi anda akan dipadam daripada sistem.</li>
                        <li>Data yang dikehendaki dikekalkan oleh undang-undang akan dikekalkan secara tanpa nama.</li>
                        <li>Selepas pembatalan, anda tidak lagi boleh log masuk atau mengakses perkhidmatan myWAP.</li>
                    </ul>
                </section>

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <h2 class="text-lg font-black text-gray-900">Langkah Pembatalan</h2>
                    <ol class="mt-4 list-decimal space-y-2 pl-5 text-sm text-gray-600">
                        <li>Pastikan anda telah menyimpan sebarang maklumat yang diperlukan.</li>
                        <li>Klik butang "Padam Akaun" di bawah.</li>
                        <li>Masukkan kata laluan anda untuk pengesahan.</li>
                    </ol>
                </section>

                <DeleteUserForm />

                <section class="rounded-3xl border border-gray-100 bg-white p-4 shadow-sm md:p-6">
                    <h2 class="text-lg font-black text-gray-900">Pembatalan Melalui Sokongan</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Jika anda menghadapi sebarang masalah, sila hubungi pasukan sokongan kami:
                    </p>
                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <p class="font-semibold text-gray-800">myWAP (MyMarhalah)</p>
                        <p>
                            E-mel:
                            <a href="mailto:support@mywap.my" class="text-emerald-600 hover:underline">support@mywap.my</a>
                        </p>
                        <p class="mt-2 text-xs text-slate-400">Kami akan memberikan respons dalam tempoh 14 hari bekerja.</p>
                    </div>
                    <p class="mt-4 text-xs text-slate-400">
                        Untuk maklumat lanjut mengenai pemprosesan data peribadi anda, sila rujuk
                        <Link :href="route('privacy')" class="text-emerald-600 hover:underline">Polisi Privasi</Link>
                        dan
                        <Link :href="route('terms')" class="text-emerald-600 hover:underline">Terma &amp; Syarat</Link>.
                    </p>
                </section>

            </div>
        </div>
    </AppLayout>
</template>

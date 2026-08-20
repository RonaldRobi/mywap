<?php

/**
 * Branding paparan pembayaran mengikut payment gateway organisasi.
 *
 * Setiap gateway (termasuk gateway masa hadapan) hanya perlu ditambah di sini —
 * frontend tidak perlu diubah kerana ia membaca maklumat ini secara dinamik.
 *
 * `logo` adalah laluan relatif (dari public/). Jika tiada fail logo, paparan
 * akan guna badge teks dengan `name`.
 */
return [

    'senangpay' => [
        'name' => 'SenangPay',
        'tagline' => 'Pay Securely with SenangPay',
        'logo' => '/images/gateways/senangpay.svg',
        'methods' => 'FPX & QR Pay',
    ],

    'bayarcash' => [
        'name' => 'BayarCash',
        'tagline' => 'Pay Securely with BayarCash',
        'logo' => '/images/gateways/bayarcash.svg',
        'methods' => 'FPX & QR Pay',
    ],

    'doku' => [
        'name' => 'DOKU',
        'tagline' => 'Pay Securely with DOKU',
        'logo' => '/images/gateways/doku.svg',
        'methods' => 'FPX, E-Wallet & QR',
    ],

    'dummy' => [
        'name' => 'MyWAP',
        'tagline' => 'Pembayaran dalam talian yang selamat',
        'logo' => null,
        'methods' => 'Pembayaran dalam talian',
    ],

];

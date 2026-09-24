<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Deep link konfigurasi — Universal Links (iOS) & App Links (Android).
 *
 * Endpoint ini mesti boleh diakses tanpa auth dan menyajikan JSON dengan
 * Content-Type yang betul:
 *   - https://mywap.my/.well-known/apple-app-site-association
 *   - https://mywap.my/.well-known/assetlinks.json
 *
 * Nilai diambil dari config/deeplink.php (lalai = produksi sebenar; boleh
 * di-override melalui .env).
 */
class DeepLinkController extends Controller
{
    public function apple(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [
                    [
                        'appID' => config('deeplink.ios_app_id'),
                        'paths' => config('deeplink.ios_paths'),
                    ],
                ],
            ],
        ])->header('Content-Type', 'application/json');
    }

    public function android(): JsonResponse
    {
        $fingerprints = config('deeplink.android_sha256_fingerprints');

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('deeplink.android_package_name'),
                    'sha256_cert_fingerprints' => $fingerprints !== []
                        ? $fingerprints
                        : ['REPLACE_WITH_SHA256'],
                ],
            ],
        ])->header('Content-Type', 'application/json');
    }
}

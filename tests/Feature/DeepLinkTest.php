<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeepLinkTest extends TestCase
{
    public function test_apple_app_site_association_is_served_as_json(): void
    {
        $response = $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.apps', [])
            ->assertJsonPath('applinks.details.0.appID', 'BW4B5LCN9S.com.mywap.mywapMobile')
            ->assertJsonPath('applinks.details.0.paths.0', '/events/*');

        $this->assertContains(
            '/reset-password/*',
            $response->json('applinks.details.0.paths')
        );
    }

    public function test_assetlinks_is_served_as_json(): void
    {
        $response = $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonPath('0.target.package_name', 'com.mywap.mywap_mobile');

        $fingerprints = $response->json('0.target.sha256_cert_fingerprints');

        foreach ([
            '7C:17:27:F2:60:1A:56:AD:2C:9B:04:06:4B:EE:FA:8A:AD:BC:93:BC:6D:EE:D7:E3:B0:19:DF:03:32:CA:49:A4',
            '1A:A1:78:91:4C:4A:CB:03:B8:68:5E:17:3C:0B:38:BF:E7:07:A9:43:18:60:94:A9:07:6A:0F:16:09:D7:A7:F9',
            'B1:70:09:A9:5E:B2:44:5C:A9:0A:25:BF:E2:EA:06:BA:CE:15:78:62:5D:0E:F0:5A:BD:15:F2:E9:E3:ED:AD:6C',
        ] as $fingerprint) {
            $this->assertContains($fingerprint, $fingerprints);
        }
    }
}

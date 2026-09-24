<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeepLinkTest extends TestCase
{
    public function test_apple_app_site_association_is_served_as_json(): void
    {
        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('applinks.apps', [])
            ->assertJsonPath('applinks.details.0.appID', 'BW4B5LCN9S.com.mywap.mywapMobile')
            ->assertJsonPath('applinks.details.0.paths.0', '/events/*');
    }

    public function test_assetlinks_is_served_as_json(): void
    {
        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('0.relation.0', 'delegate_permission/common.handle_all_urls')
            ->assertJsonPath('0.target.package_name', 'com.mywap.mywap_mobile')
            ->assertJsonPath(
                '0.target.sha256_cert_fingerprints.0',
                '1A:A1:78:91:4C:4A:CB:03:B8:68:5E:17:3C:0B:38:BF:E7:07:A9:43:18:60:94:A9:07:6A:0F:16:09:D7:A7:F9'
            );
    }
}

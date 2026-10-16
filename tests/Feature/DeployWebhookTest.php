<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeployWebhookTest extends TestCase
{
    public function test_invalid_token_is_rejected(): void
    {
        config(['app.deploy_token' => 'secret-token']);

        $this->postJson('/__deploy/wrong-token')->assertStatus(401);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['app.deploy_token' => 'secret-token']);

        $this->postJson('/__deploy/secret-token', [], [
            'X-Hub-Signature-256' => 'sha256=deadbeef',
        ])->assertStatus(401);
    }
}

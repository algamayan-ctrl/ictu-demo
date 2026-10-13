<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    public function test_development_configuration_cannot_be_certified_for_cloud_deployment(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.driver' => 'array',
            'cache.default' => 'array', 'queue.default' => 'sync', 'filesystems.protected' => 'local']);
        $this->artisan('spmu:deployment-readiness')->expectsOutputToContain('NOT READY')->assertFailed();
    }

    public function test_readiness_output_does_not_print_credentials_or_claim_live_provider_evidence(): void
    {
        config(['app.key' => 'SENSITIVE-KEY-DO-NOT-PRINT', 'database.default' => 'sqlite']);
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('spmu:deployment-readiness', ['--json' => true]));
        $output = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringNotContainsString('SENSITIVE-KEY-DO-NOT-PRINT', $output);
        $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('configuration only', $result['scope']);
        $this->assertContains('Live OAuth and email delivery', $result['not_verified']);
        $this->assertContains('ICTU nine deliverables and institutional hosting approval', $result['not_verified']);
        $this->assertSame('NOT READY', $result['gate']);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class CorsTest extends TestCase
{
    public function test_api_preflight_allows_configured_frontend_origin(): void
    {
        config([
            'cors.allowed_origins' => ['https://kalbek.davidolurebi.workers.dev'],
            'cors.allowed_headers' => ['*'],
            'cors.allowed_methods' => ['*'],
            'cors.paths' => ['api/*', 'auth/google/*', 'sanctum/csrf-cookie'],
        ]);

        $this->withHeaders([
            'Origin' => 'https://kalbek.davidolurebi.workers.dev',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'authorization,content-type,accept',
        ])->options('/api/v1/auth/user')
            ->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'https://kalbek.davidolurebi.workers.dev')
            ->assertHeader('Access-Control-Allow-Headers');
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Http\Middleware\TrustProxies;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    public function test_trusted_proxy_headers_are_honored_when_proxies_are_configured(): void
    {
        TrustProxies::at('*');
        config(['app.force_https' => true]);

        $response = $this->call(
            'GET',
            'http://sici-bonds.local/login',
            server: [
                'REMOTE_ADDR' => '10.0.0.1',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_X_FORWARDED_FOR' => '10.0.0.1',
                'HTTP_HOST' => 'sici-bonds.local',
            ],
        );

        $response->assertOk();
    }

    public function test_http_requests_redirect_to_https_when_forced_without_trusted_proxy(): void
    {
        config(['app.force_https' => true]);

        $this->get('http://sici-bonds.local/login')
            ->assertRedirect('https://sici-bonds.local/login');
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForceHttpsTest extends TestCase
{
    public function test_http_requests_redirect_to_https_when_forced(): void
    {
        config(['app.force_https' => true]);

        $response = $this->get('http://sici-bonds.local/login');

        $response->assertRedirect('https://sici-bonds.local/login');
    }

    public function test_https_requests_are_not_redirected_when_forced(): void
    {
        config(['app.force_https' => true]);

        $response = $this->get('https://sici-bonds.local/login');

        $response->assertOk();
    }

    public function test_http_requests_are_allowed_when_https_is_not_forced(): void
    {
        config(['app.force_https' => false]);

        $response = $this->get('http://sici-bonds.local/login');

        $response->assertOk();
    }
}

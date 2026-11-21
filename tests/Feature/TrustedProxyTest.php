<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The app can run behind a reverse proxy that serves it under a sub-path
 * (the l0 app manager serves it at https://l0.ee/<path>/, strips the prefix
 * and sends it as X-Forwarded-Prefix). TRUSTED_PROXIES decides whether
 * those forwarded headers are honoured.
 */
class TrustedProxyTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $forwardedHeaders = [
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'l0.ee',
        'X-Forwarded-Prefix' => '/davor-lifeos',
    ];

    public function test_forwarded_headers_are_ignored_when_no_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => null]);

        $response = $this->withHeaders($this->forwardedHeaders)->get('/');

        $response->assertRedirect('http://localhost/login');
    }

    public function test_generated_urls_include_the_forwarded_prefix_when_all_proxies_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->withHeaders($this->forwardedHeaders)->get('/');

        $response->assertRedirect('https://l0.ee/davor-lifeos/login');
    }

    public function test_only_listed_proxy_addresses_and_ranges_are_trusted(): void
    {
        config(['trustedproxy.proxies' => '192.168.0.1, 10.0.0.0/8']);

        $this->withHeaders($this->forwardedHeaders)
            ->get('/')
            ->assertRedirect('http://localhost/login');

        $this->withHeaders($this->forwardedHeaders)
            ->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get('/')
            ->assertRedirect('https://l0.ee/davor-lifeos/login');
    }

    public function test_client_ip_is_the_address_the_trusted_proxy_appended_not_a_spoofed_one(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8']);
        Route::get('/_trusted-proxy-test/ip', fn (Request $request): ?string => $request->ip());

        $response = $this->withHeaders(['X-Forwarded-For' => '6.6.6.6, 203.0.113.7'])
            ->withServerVariables(['REMOTE_ADDR' => '10.1.2.3'])
            ->get('/_trusted-proxy-test/ip');

        $response->assertOk();
        $response->assertContent('203.0.113.7');
    }

    public function test_layout_exposes_the_forwarded_prefix_as_base_path(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->withHeaders($this->forwardedHeaders)->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="base-path" content="/davor-lifeos">', false);
    }

    public function test_layout_base_path_is_empty_when_served_from_the_root(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<meta name="base-path" content="">', false);
    }
}

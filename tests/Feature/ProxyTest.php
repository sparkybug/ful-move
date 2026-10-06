<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_test/proxy', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'host' => $request->getHost(),
            'client_ip' => $request->ip(),
            'login_url' => route('login'),
        ]);
    }

    public function test_trusted_ingress_preserves_https_without_trusting_forwarded_host(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-For' => '203.0.113.50',
                'X-Forwarded-Host' => 'untrusted.example',
            ])
            ->get('http://ful-move.onrender.com/_test/proxy')
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'host' => 'ful-move.onrender.com',
                'client_ip' => '203.0.113.50',
                'login_url' => 'https://ful-move.onrender.com/login',
            ]);
    }

    public function test_direct_requests_ignore_forwarded_headers_by_default(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.10'])
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-For' => '203.0.113.50',
            ])
            ->get('http://localhost/_test/proxy')
            ->assertOk()
            ->assertExactJson([
                'secure' => false,
                'host' => 'localhost',
                'client_ip' => '10.0.0.10',
                'login_url' => 'http://localhost/login',
            ]);
    }
}

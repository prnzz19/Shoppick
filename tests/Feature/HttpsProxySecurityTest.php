<?php

namespace Tests\Feature;
use Illuminate\Support\Facades\Route;

use Illuminate\Http\Request;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class HttpsProxySecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Request::setTrustedHosts([]);
        parent::tearDown();
    }

    private function proxyContext(string $remote, string $host, array $forwarded): array
    {
        $server = [
            'REMOTE_ADDR' => $remote,
            'HTTP_HOST' => $host,
            'REQUEST_URI' => '/login',
            'REQUEST_METHOD' => 'GET',
        ];
        foreach ($forwarded as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }
        $request = Request::create('/login', 'GET', [], [], [], $server);
        $response = (new TrustProxies)->handle($request, fn (Request $request) => new Response(json_encode([
            'secure' => $request->isSecure(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'client' => $request->getClientIp(),
        ], JSON_THROW_ON_ERROR)));

        return json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_configured_loopback_proxy_produces_secure_https_request_context(): void
    {
        $context = $this->proxyContext('127.0.0.1', 'shoppickmarket.shop', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-For' => '203.0.113.10',
        ]);

        $this->assertTrue($context['secure']);
        $this->assertSame('https', $context['scheme']);
        $this->assertSame('shoppickmarket.shop', $context['host']);
        $this->assertSame(443, $context['port']);
    }

    public function test_untrusted_client_cannot_forge_forwarded_https_context_or_host(): void
    {
        $context = $this->proxyContext('198.51.100.10', 'shoppickmarket.shop', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Port' => '443',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-For' => '203.0.113.10',
        ]);

        $this->assertFalse($context['secure']);
        $this->assertSame('http', $context['scheme']);
        $this->assertSame('shoppickmarket.shop', $context['host']);
        $this->assertSame(80, $context['port']);
        $this->assertSame('198.51.100.10', $context['client']);
    }

    public function test_production_trusted_hosts_reject_unexpected_host(): void
    {
        Request::setTrustedHosts(['^shoppickmarket\\.shop$', '^www\\.shoppickmarket\\.shop$']);
        $this->expectException(\Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException::class);
        Request::create('https://evil.example/login', 'GET')->getHost();
    }

    public function test_secure_session_cookies_are_secure_when_enabled(): void
    {
        config(['session.secure' => true]);
        $response = $this->withHeaders(['Host' => 'shoppickmarket.shop'])->get('/login')->assertOk();
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);
        foreach ($cookies as $cookie) {
            $this->assertTrue($cookie->isSecure());
        }
    }

    public function test_secure_session_cookies_are_host_only_when_domain_is_null(): void
    {
        config(['session.secure' => true, 'session.domain' => null]);
        $response = $this->withHeaders(['Host' => 'shoppickmarket.shop'])->get('/login')->assertOk();
        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);
        foreach ($cookies as $cookie) {
            $this->assertNull($cookie->getDomain());
        }
    }

    public function test_https_url_generation_uses_forwarded_scheme(): void
    {
        Route::get('/__https-url-regression', fn () => response()->json(['url' => url('/login')]));
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'shoppickmarket.shop',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/__https-url-regression');

        $response->assertOk();
        $this->assertStringStartsWith('https://', (string) $response->json('url'));
    }

    public function test_oauth_redirect_uses_https_callback_scheme_for_trusted_host(): void
    {
        config([
            'services.google.client_id' => 'fake-client',
            'services.google.client_secret' => 'fake-secret',
            'services.google.redirect_production' => 'https://shoppickmarket.shop/auth/google/callback',
        ]);
        $response = $this->get('https://shoppickmarket.shop/auth/google/redirect');
        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertStringStartsWith('https://', (string) ($query['redirect_uri'] ?? ''));
    }

    public function test_trusted_https_login_does_not_loop(): void
    {
        $response = $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'shoppickmarket.shop',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/login');

        $response->assertOk();
        $this->assertFalse($response->isRedirection());
    }

    public function test_trusted_https_request_redirects_to_https_login(): void
    {
        $response = $this->withHeaders(['Host' => 'shoppickmarket.shop'])->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'shoppickmarket.shop',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('/admin/dashboard');

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', (string) $response->headers->get('Location'));
    }
}

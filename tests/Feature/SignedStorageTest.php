<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\Response;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Controllers\SignedStorageController;
use Jengo\Storage\Security\HmacUrlSigner;
use Jengo\Storage\Storage;

class SignedStorageTest extends CIUnitTestCase
{
    protected string $signingKey = 'test-secret-key-signed-storage-12345';
    protected StorageConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new StorageConfig();
        $this->config->signingKey = $this->signingKey;
        $this->config->signedRoutePrefix = 'storage/signed';
        $this->config->default = 'memory';

        // Register custom config into CI4 config factory
        Services::injectMock('storage', new \Jengo\Storage\FilesystemManager($this->config));
    }

    protected function tearDown(): void
    {
        Storage::clearResolvedInstances();
        Services::reset();
        parent::tearDown();
    }

    public function test_downloads_valid_signed_file(): void
    {
        $fakeDisk = Storage::fake('memory');
        $fakeDisk->put('documents/secret.pdf', 'confidential pdf data');

        $signer = new HmacUrlSigner($this->signingKey, '/storage/signed');
        $expiration = time() + 3600;
        $url = $signer->sign('documents/secret.pdf', $expiration);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);

        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost' . $url),
            null,
            new UserAgent()
        );
        $request->setGlobal('get', $queryParams);

        $controller = new SignedStorageController();
        $controller->initController($request, new Response(new \Config\App()), Services::logger());

        $response = $controller->download('documents', 'secret.pdf');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('confidential pdf data', $response->getBody());
    }

    public function test_rejects_expired_signed_url(): void
    {
        $fakeDisk = Storage::fake('memory');
        $fakeDisk->put('documents/secret.pdf', 'confidential pdf data');

        $signer = new HmacUrlSigner($this->signingKey, '/storage/signed');
        $expiration = time() - 300; // 5 minutes ago
        $url = $signer->sign('documents/secret.pdf', $expiration);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);

        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost' . $url),
            null,
            new UserAgent()
        );
        $request->setGlobal('get', $queryParams);

        $controller = new SignedStorageController();
        $controller->initController($request, new Response(new \Config\App()), Services::logger());

        $response = $controller->download('documents', 'secret.pdf');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('expired', $response->getBody());
    }

    public function test_rejects_tampered_signed_url(): void
    {
        $fakeDisk = Storage::fake('memory');
        $fakeDisk->put('documents/secret.pdf', 'confidential pdf data');

        $signer = new HmacUrlSigner($this->signingKey, '/storage/signed');
        $expiration = time() + 3600;
        $url = $signer->sign('documents/secret.pdf', $expiration);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);
        $queryParams['signature'] = 'tampered-signature-12345';

        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost' . $url),
            null,
            new UserAgent()
        );
        $request->setGlobal('get', $queryParams);

        $controller = new SignedStorageController();
        $controller->initController($request, new Response(new \Config\App()), Services::logger());

        $response = $controller->download('documents', 'secret.pdf');

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('Invalid URL signature', $response->getBody());
    }

    public function test_returns_404_for_missing_signed_file(): void
    {
        Storage::fake('memory');

        $signer = new HmacUrlSigner($this->signingKey, '/storage/signed');
        $expiration = time() + 3600;
        $url = $signer->sign('missing.pdf', $expiration);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);

        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost' . $url),
            null,
            new UserAgent()
        );
        $request->setGlobal('get', $queryParams);

        $controller = new SignedStorageController();
        $controller->initController($request, new Response(new \Config\App()), Services::logger());

        $response = $controller->download('missing.pdf');

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_supports_http_range_requests(): void
    {
        $fakeDisk = Storage::fake('memory');
        $fakeDisk->put('video.mp4', '0123456789abcdefghij');

        $signer = new HmacUrlSigner($this->signingKey, '/storage/signed');
        $expiration = time() + 3600;
        $url = $signer->sign('video.mp4', $expiration);

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);

        $request = new IncomingRequest(
            new \Config\App(),
            new URI('http://localhost' . $url),
            null,
            new UserAgent()
        );
        $request->setGlobal('get', $queryParams);
        $request->setHeader('Range', 'bytes=0-9');

        $controller = new SignedStorageController();
        $controller->initController($request, new Response(new \Config\App()), Services::logger());

        $response = $controller->download('video.mp4');

        $this->assertSame(206, $response->getStatusCode());
        $this->assertSame('bytes 0-9/20', $response->getHeaderLine('Content-Range'));
        $this->assertSame('0123456789', $response->getBody());
    }
}

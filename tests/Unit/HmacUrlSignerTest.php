<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Storage\Security\HmacUrlSigner;
use PHPUnit\Framework\TestCase;

class HmacUrlSignerTest extends TestCase
{
    protected HmacUrlSigner $signer;
    protected string $secretKey = 'super-secret-testing-signing-key-12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->signer = new HmacUrlSigner($this->secretKey, 'https://example.com/storage/signed');
    }

    public function test_generates_valid_signed_url(): void
    {
        $expiration = time() + 3600;
        $url = $this->signer->sign('documents/invoice.pdf', $expiration);

        $this->assertStringStartsWith('https://example.com/storage/signed/documents/invoice.pdf?', $url);
        $this->assertStringContainsString("expires={$expiration}", $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_validates_correct_signature(): void
    {
        $expiration = time() + 3600;
        $url = $this->signer->sign('vault/tax.pdf', $expiration, ['disk' => 'private']);

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        $signature = $params['signature'];
        unset($params['signature']);

        $isValid = $this->signer->isValid('vault/tax.pdf', $expiration, $signature, $params);
        $this->assertTrue($isValid);
    }

    public function test_rejects_expired_signature(): void
    {
        $pastExpiration = time() - 60;
        $url = $this->signer->sign('vault/tax.pdf', $pastExpiration);

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        $signature = $params['signature'];
        unset($params['signature']);

        $isValid = $this->signer->isValid('vault/tax.pdf', $pastExpiration, $signature, $params);
        $this->assertFalse($isValid);
    }

    public function test_rejects_tampered_signature_or_path(): void
    {
        $expiration = time() + 3600;
        $url = $this->signer->sign('vault/tax.pdf', $expiration);

        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $params);

        $signature = $params['signature'];
        unset($params['signature']);

        // Tampered path
        $this->assertFalse($this->signer->isValid('vault/other.pdf', $expiration, $signature, $params));

        // Tampered signature
        $this->assertFalse($this->signer->isValid('vault/tax.pdf', $expiration, 'invalid-signature', $params));
    }
}

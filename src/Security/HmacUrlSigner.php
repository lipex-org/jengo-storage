<?php

declare(strict_types=1);

namespace Jengo\Storage\Security;

use DateTimeInterface;
use Jengo\Storage\Contracts\UrlSignerInterface;

class HmacUrlSigner implements UrlSignerInterface
{
    public function __construct(
        protected string $secretKey,
        protected string $baseUrl = ''
    ) {
        if ($this->secretKey === '') {
            // Default fallback to app encryption key or standard hash
            $this->secretKey = (string) config('Encryption')->key;
            if ($this->secretKey === '') {
                $this->secretKey = 'jengo-default-storage-signing-secret-key';
            }
        }
    }

    /**
     * Generate an HMAC-SHA256 signed URL for a file path with an expiration timestamp.
     */
    public function sign(string $path, int|DateTimeInterface $expiration, array $parameters = []): string
    {
        $expires = $expiration instanceof DateTimeInterface
            ? $expiration->getTimestamp()
            : $expiration;

        $sanitizedPath = FileSanitizer::sanitizePath($path);

        $params = $parameters;
        $params['expires'] = $expires;
        ksort($params);

        $payload = $this->buildPayload($sanitizedPath, $params);
        $signature = hash_hmac('sha256', $payload, $this->secretKey);
        $params['signature'] = $signature;

        $queryString = http_build_query($params);
        $base = rtrim($this->baseUrl, '/');

        return "{$base}/{$sanitizedPath}?{$queryString}";
    }

    /**
     * Verify if the given path, expiration, and signature are valid.
     */
    public function isValid(string $path, int $expires, string $signature, array $parameters = []): bool
    {
        if (time() > $expires) {
            return false;
        }

        $sanitizedPath = FileSanitizer::sanitizePath($path);

        $params = $parameters;
        $params['expires'] = $expires;
        unset($params['signature']);
        ksort($params);

        $payload = $this->buildPayload($sanitizedPath, $params);
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Build standard payload string for hashing.
     */
    protected function buildPayload(string $path, array $params): string
    {
        return $path . '?' . http_build_query($params);
    }
}

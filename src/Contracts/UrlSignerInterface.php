<?php

declare(strict_types=1);

namespace Jengo\Storage\Contracts;

use DateTimeInterface;

interface UrlSignerInterface
{
    /**
     * Generate an HMAC-SHA256 signed URL for a file path with an expiration timestamp.
     */
    public function sign(string $path, int|DateTimeInterface $expiration, array $parameters = []): string;

    /**
     * Verify if the given path, expiration, and signature are valid.
     */
    public function isValid(string $path, int $expires, string $signature, array $parameters = []): bool;
}

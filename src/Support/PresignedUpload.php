<?php

declare(strict_types=1);

namespace Jengo\Storage\Support;

class PresignedUpload
{
    /**
     * @param array<string, string> $headers
     * @param array<string, string> $fields
     */
    public function __construct(
        public readonly string $url,
        public readonly string $method,
        public readonly array $headers,
        public readonly string $key,
        public readonly int $expiresAt,
        public readonly array $fields = []
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function toArray(): array
    {
        return [
            'url'        => $this->url,
            'method'     => $this->method,
            'headers'    => $this->headers,
            'key'        => $this->key,
            'expires_at' => $this->expiresAt,
            'fields'     => $this->fields,
        ];
    }
}

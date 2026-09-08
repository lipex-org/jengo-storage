<?php

declare(strict_types=1);

namespace Jengo\Storage\Contracts;

use DateTimeInterface;
use Jengo\Storage\Support\PresignedUpload;

interface CloudFilesystemInterface extends FilesystemInterface
{
    /**
     * Create a pre-signed direct upload ticket for direct client-to-cloud uploads.
     *
     * @param array{
     *     expires?: int|DateTimeInterface,
     *     contentType?: string,
     *     maxSize?: int,
     *     visibility?: string,
     *     metadata?: array<string, string>
     * } $options
     */
    public function createUploadUrl(string $path, array $options = []): PresignedUpload;
}

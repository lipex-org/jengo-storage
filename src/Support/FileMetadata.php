<?php

declare(strict_types=1);

namespace Jengo\Storage\Support;

class FileMetadata
{
    public function __construct(
        public readonly string $path,
        public readonly int $size,
        public readonly ?string $mimeType = null,
        public readonly ?int $lastModified = null,
        public readonly ?string $visibility = null,
        public readonly ?string $checksum = null,
        public readonly array $extra = []
    ) {
    }

    public function toArray(): array
    {
        return [
            'path'         => $this->path,
            'size'         => $this->size,
            'mime_type'    => $this->mimeType,
            'last_modified'=> $this->lastModified,
            'visibility'   => $this->visibility,
            'checksum'     => $this->checksum,
            'extra'        => $this->extra,
        ];
    }
}

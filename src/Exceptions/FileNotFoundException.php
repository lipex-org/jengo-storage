<?php

declare(strict_types=1);

namespace Jengo\Storage\Exceptions;

class FileNotFoundException extends StorageException
{
    public static function forPath(string $path): self
    {
        return new self("File not found at path: [{$path}]");
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Storage\Security;

use InvalidArgumentException;

class FileSanitizer
{
    /**
     * Normalize a path and prevent directory traversal attacks.
     */
    public static function sanitizePath(string $path): string
    {
        // Disallow null bytes
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException("Path contains invalid null byte characters.");
        }

        // Replace backslashes with forward slashes
        $path = str_replace('\\', '/', $path);

        // Disallow path traversal sequences
        $parts = explode('/', $path);
        $safeParts = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                throw new InvalidArgumentException("Path traversal sequence '..' is not allowed in storage paths.");
            }

            $safeParts[] = $part;
        }

        return implode('/', $safeParts);
    }

    /**
     * Generate a cryptographically random, collision-resistant hash name.
     */
    public static function hashName(?string $originalPath = null, ?string $extension = null): string
    {
        $hash = bin2hex(random_bytes(20));

        if ($extension === null && $originalPath !== null) {
            $ext = pathinfo($originalPath, PATHINFO_EXTENSION);
            if ($ext !== '') {
                $extension = strtolower($ext);
            }
        }

        return $extension !== null && $extension !== '' ? "{$hash}.{$extension}" : $hash;
    }

    /**
     * Sanitize a filename, stripping non-printable or dangerous characters.
     */
    public static function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^\w\.\-\_]/', '_', $filename) ?? 'file';

        return ltrim($filename, '.');
    }
}

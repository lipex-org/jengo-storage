<?php

declare(strict_types=1);

use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\FilesystemManager;
use Jengo\Storage\Storage;

if (! function_exists('storage')) {
    /**
     * Get the storage manager or a specific disk instance.
     *
     * @return FilesystemInterface|FilesystemManager
     */
    function storage(?string $disk = null): mixed
    {
        if ($disk !== null) {
            return Storage::disk($disk);
        }

        return Storage::getFacadeRoot();
    }
}

if (! function_exists('storage_url')) {
    /**
     * Get the public URL for a file.
     */
    function storage_url(string $path, ?string $disk = null): string
    {
        return Storage::disk($disk)->url($path);
    }
}

if (! function_exists('storage_temporary_url')) {
    /**
     * Get a temporary signed URL for a file.
     */
    function storage_temporary_url(string $path, int|DateTimeInterface $expiration, ?string $disk = null, array $options = []): string
    {
        return Storage::disk($disk)->temporaryUrl($path, $expiration, $options);
    }
}

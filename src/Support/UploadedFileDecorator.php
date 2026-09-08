<?php

declare(strict_types=1);

namespace Jengo\Storage\Support;

use CodeIgniter\HTTP\Files\UploadedFile;
use Jengo\Storage\Security\FileSanitizer;
use Jengo\Storage\Storage;

class UploadedFileDecorator
{
    public function __construct(
        protected UploadedFile $file
    ) {
    }

    /**
     * Store the uploaded file on a storage disk with an automatically generated hash name.
     */
    public function store(string $path = '', ?string $disk = null, array $options = []): string|false
    {
        $name = $this->hashName();

        return $this->storeAs($path, $name, $disk, $options);
    }

    /**
     * Store the uploaded file on a disk with a specific filename.
     */
    public function storeAs(string $path, string $name, ?string $disk = null, array $options = []): string|false
    {
        if ($this->file->getError() !== UPLOAD_ERR_OK || ! is_file($this->file->getTempName())) {
            return false;
        }

        $cleanPath = trim($path, '/');
        $cleanName = FileSanitizer::sanitizeFilename($name);
        $fullPath  = $cleanPath !== '' ? "{$cleanPath}/{$cleanName}" : $cleanName;

        $stream = fopen($this->file->getTempName(), 'rb');
        if (! is_resource($stream)) {
            return false;
        }

        $visibility = $options['visibility'] ?? null;
        $success = Storage::disk($disk)->writeStream($fullPath, $stream, $visibility);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $success ? $fullPath : false;
    }

    /**
     * Generate a cryptographic hash name for the file.
     */
    public function hashName(?string $path = null): string
    {
        $ext = $this->file->getClientExtension();
        if ($ext === '' || $ext === null) {
            $ext = $this->file->guessExtension();
        }

        $hash = FileSanitizer::hashName(extension: (string) $ext);

        return $path !== null && $path !== '' ? rtrim($path, '/') . '/' . $hash : $hash;
    }

    /**
     * Get the underlying UploadedFile instance.
     */
    public function getOriginalFile(): UploadedFile
    {
        return $this->file;
    }

    /**
     * Forward calls to the underlying CodeIgniter UploadedFile.
     */
    public function __call(string $method, array $arguments): mixed
    {
        return $this->file->{$method}(...$arguments);
    }
}

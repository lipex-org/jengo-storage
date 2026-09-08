<?php

declare(strict_types=1);

namespace Jengo\Storage\Contracts;

use DateTimeInterface;
use Jengo\Storage\Images\ImagePipeline;

interface FilesystemInterface
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_PRIVATE = 'private';

    /**
     * Determine if a file exists.
     */
    public function exists(string $path): bool;

    /**
     * Determine if a file is missing.
     */
    public function missing(string $path): bool;

    /**
     * Get the contents of a file.
     */
    public function get(string $path): string;

    /**
     * Retrieve a read-stream for a file.
     *
     * @return resource|false
     */
    public function readStream(string $path);

    /**
     * Write contents to a file.
     */
    public function put(string $path, string $contents, ?string $visibility = null): bool;

    /**
     * Write a resource stream to a file.
     *
     * @param resource $resource
     */
    public function writeStream(string $path, $resource, ?string $visibility = null): bool;

    /**
     * Get the visibility of a file.
     */
    public function getVisibility(string $path): string;

    /**
     * Set the visibility of a file.
     */
    public function setVisibility(string $path, string $visibility): bool;

    /**
     * Prepend contents to a file.
     */
    public function prepend(string $path, string $data): bool;

    /**
     * Append contents to a file.
     */
    public function append(string $path, string $data): bool;

    /**
     * Delete a file or array of files.
     *
     * @param string|array<int, string> $paths
     */
    public function delete(string|array $paths): bool;

    /**
     * Copy a file to a new location.
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the size of a file in bytes.
     */
    public function size(string $path): int;

    /**
     * Get the last modified timestamp of a file.
     */
    public function lastModified(string $path): int;

    /**
     * Get the mime type of a file.
     */
    public function mimeType(string $path): string|false;

    /**
     * Get the cryptographic checksum (MD5 or SHA256) of a file.
     */
    public function checksum(string $path, array $options = []): string;

    /**
     * Get an array of all files in a directory.
     *
     * @return array<int, string>
     */
    public function files(?string $directory = null, bool $recursive = false): array;

    /**
     * Get an array of all files in a directory recursively.
     *
     * @return array<int, string>
     */
    public function allFiles(?string $directory = null): array;

    /**
     * Get an array of all directories within a directory.
     *
     * @return array<int, string>
     */
    public function directories(?string $directory = null, bool $recursive = false): array;

    /**
     * Get all directories within a directory recursively.
     *
     * @return array<int, string>
     */
    public function allDirectories(?string $directory = null): array;

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path): bool;

    /**
     * Recursively delete a directory.
     */
    public function deleteDirectory(string $directory): bool;

    /**
     * Get the publicly accessible URL for the given path.
     */
    public function url(string $path): string;

    /**
     * Get a temporary signed URL for the given path.
     */
    public function temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = []): string;

    /**
     * Get the full filesystem path for the given file if supported.
     */
    public function path(string $path): string;

    /**
     * Begin an image transformation pipeline for the given file.
     */
    public function image(string $path): ImagePipeline;
}

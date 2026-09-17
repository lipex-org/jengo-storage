<?php

declare(strict_types=1);

namespace Jengo\Storage;

use Aws\S3\S3Client;
use DateTimeInterface;
use Jengo\Storage\Contracts\CloudFilesystemInterface;
use Jengo\Storage\Contracts\UrlSignerInterface;
use Jengo\Storage\Images\ImagePipeline;
use Jengo\Storage\Security\FileSanitizer;
use Jengo\Storage\Support\PresignedUpload;
use League\Flysystem\Config;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCheckDirectoryExistence;
use League\Flysystem\UnableToCheckFileExistence;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToProvideChecksum;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;

class Filesystem implements CloudFilesystemInterface
{
    public function __construct(
        protected FilesystemOperator $operator,
        protected array $config = [],
        protected ?UrlSignerInterface $signer = null,
        protected ?S3Client $s3Client = null
    ) {
    }

    public function getOperator(): FilesystemOperator
    {
        return $this->operator;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function exists(string $path): bool
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->has($clean);
        } catch (UnableToCheckFileExistence|UnableToCheckDirectoryExistence) {
            return false;
        }
    }

    public function missing(string $path): bool
    {
        return ! $this->exists($path);
    }

    public function get(string $path): string
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->read($clean);
        } catch (UnableToReadFile $e) {
            throw Exceptions\FileNotFoundException::forPath($path);
        }
    }

    public function readStream(string $path)
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->readStream($clean);
        } catch (UnableToReadFile $e) {
            throw Exceptions\FileNotFoundException::forPath($path);
        }
    }

    public function put(string $path, string $contents, ?string $visibility = null): bool
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            $options = $this->buildConfig($visibility);
            $this->operator->write($clean, $contents, $options);
            return true;
        } catch (UnableToWriteFile) {
            return false;
        }
    }

    public function writeStream(string $path, $resource, ?string $visibility = null): bool
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            $options = $this->buildConfig($visibility);
            $this->operator->writeStream($clean, $resource, $options);
            return true;
        } catch (UnableToWriteFile) {
            return false;
        }
    }

    public function putStream(string $path, $resource, ?string $visibility = null): bool
    {
        return $this->writeStream($path, $resource, $visibility);
    }

    public function getVisibility(string $path): string
    {
        $clean = FileSanitizer::sanitizePath($path);
        return $this->operator->visibility($clean);
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            $this->operator->setVisibility($clean, $visibility);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function prepend(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $data . $this->get($path));
        }

        return $this->put($path, $data);
    }

    public function append(string $path, string $data): bool
    {
        if ($this->exists($path)) {
            return $this->put($path, $this->get($path) . $data);
        }

        return $this->put($path, $data);
    }

    public function delete(string|array $paths): bool
    {
        $paths = is_array($paths) ? $paths : [$paths];
        $success = true;

        foreach ($paths as $path) {
            $clean = FileSanitizer::sanitizePath($path);
            try {
                $this->operator->delete($clean);
            } catch (UnableToDeleteFile) {
                $success = false;
            }
        }

        return $success;
    }

    public function copy(string $from, string $to): bool
    {
        $cleanFrom = FileSanitizer::sanitizePath($from);
        $cleanTo = FileSanitizer::sanitizePath($to);

        try {
            $this->operator->copy($cleanFrom, $cleanTo);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function move(string $from, string $to): bool
    {
        $cleanFrom = FileSanitizer::sanitizePath($from);
        $cleanTo = FileSanitizer::sanitizePath($to);

        try {
            $this->operator->move($cleanFrom, $cleanTo);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function size(string $path): int
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->fileSize($clean);
        } catch (UnableToRetrieveMetadata) {
            throw Exceptions\FileNotFoundException::forPath($path);
        }
    }

    public function lastModified(string $path): int
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->lastModified($clean);
        } catch (UnableToRetrieveMetadata) {
            throw Exceptions\FileNotFoundException::forPath($path);
        }
    }

    public function mimeType(string $path): string|false
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            return $this->operator->mimeType($clean);
        } catch (\Throwable) {
            return false;
        }
    }

    public function checksum(string $path, array $options = []): string
    {
        $clean = FileSanitizer::sanitizePath($path);
        $algo = $options['checksum_algo'] ?? $options['algo'] ?? 'sha256';
        $options['checksum_algo'] = $algo;

        try {
            if (method_exists($this->operator, 'checksum')) {
                return $this->operator->checksum($clean, $options);
            }
        } catch (UnableToProvideChecksum|\BadMethodCallException) {
            // Fall back to computing checksum via stream
        }

        $stream = $this->readStream($clean);
        $context = hash_init($algo);

        while (! feof($stream)) {
            $chunk = fread($stream, 1048576); // 1 MB buffer
            if ($chunk === false || $chunk === '') {
                break;
            }
            hash_update($context, $chunk);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }

        return hash_final($context);
    }

    public function files(?string $directory = null, bool $recursive = false): array
    {
        $dir = $directory !== null ? FileSanitizer::sanitizePath($directory) : '';
        $items = $this->operator->listContents($dir, $recursive);

        $files = [];
        /** @var StorageAttributes $item */
        foreach ($items as $item) {
            if ($item->isFile()) {
                $files[] = $item->path();
            }
        }

        return $files;
    }

    public function allFiles(?string $directory = null): array
    {
        return $this->files($directory, true);
    }

    public function directories(?string $directory = null, bool $recursive = false): array
    {
        $dir = $directory !== null ? FileSanitizer::sanitizePath($directory) : '';
        $items = $this->operator->listContents($dir, $recursive);

        $dirs = [];
        /** @var StorageAttributes $item */
        foreach ($items as $item) {
            if ($item->isDir()) {
                $dirs[] = $item->path();
            }
        }

        return $dirs;
    }

    public function allDirectories(?string $directory = null): array
    {
        return $this->directories($directory, true);
    }

    public function makeDirectory(string $path): bool
    {
        $clean = FileSanitizer::sanitizePath($path);
        try {
            $this->operator->createDirectory($clean);
            return true;
        } catch (UnableToCreateDirectory) {
            return false;
        }
    }

    public function deleteDirectory(string $directory): bool
    {
        $clean = FileSanitizer::sanitizePath($directory);
        try {
            $this->operator->deleteDirectory($clean);
            return true;
        } catch (UnableToDeleteDirectory) {
            return false;
        }
    }

    public function url(string $path): string
    {
        $clean = FileSanitizer::sanitizePath($path);

        if (isset($this->config['url']) && $this->config['url'] !== '') {
            return rtrim($this->config['url'], '/') . '/' . ltrim($clean, '/');
        }

        if ($this->s3Client !== null && isset($this->config['bucket'])) {
            return (string) $this->s3Client->getObjectUrl($this->config['bucket'], $clean);
        }

        return '/' . ltrim($clean, '/');
    }

    public function temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = []): string
    {
        $clean = FileSanitizer::sanitizePath($path);

        if ($this->s3Client !== null && isset($this->config['bucket'])) {
            $cmd = $this->s3Client->getCommand('GetObject', array_merge([
                'Bucket' => $this->config['bucket'],
                'Key'    => $clean,
            ], $options));

            $request = $this->s3Client->createPresignedRequest($cmd, $expiration);

            return (string) $request->getUri();
        }

        if ($this->signer !== null) {
            return $this->signer->sign($clean, $expiration, $options);
        }

        throw new Exceptions\StorageException("Temporary URLs are not supported on this disk (no URL signer or S3 client configured).");
    }

    public function createUploadUrl(string $path, array $options = []): PresignedUpload
    {
        $clean = FileSanitizer::sanitizePath($path);
        $expires = $options['expires'] ?? time() + 900;
        $contentType = $options['contentType'] ?? 'application/octet-stream';
        $visibility = $options['visibility'] ?? 'private';

        if ($this->s3Client !== null && isset($this->config['bucket'])) {
            $commandParams = [
                'Bucket'      => $this->config['bucket'],
                'Key'         => $clean,
                'ContentType' => $contentType,
            ];

            if ($visibility === 'public') {
                $commandParams['ACL'] = 'public-read';
            }

            $cmd = $this->s3Client->getCommand('PutObject', $commandParams);
            $request = $this->s3Client->createPresignedRequest($cmd, $expires);

            return new PresignedUpload(
                url: (string) $request->getUri(),
                method: 'PUT',
                headers: [
                    'Content-Type' => $contentType,
                ],
                key: $clean,
                expiresAt: $expires instanceof DateTimeInterface ? $expires->getTimestamp() : (int) $expires
            );
        }

        // For local or other disks, sign a route to upload endpoint
        $expiresTimestamp = $expires instanceof DateTimeInterface ? $expires->getTimestamp() : (int) $expires;
        $signedUrl = $this->signer !== null
            ? $this->signer->sign("upload/{$clean}", $expiresTimestamp, ['contentType' => $contentType])
            : $this->url("upload/{$clean}");

        return new PresignedUpload(
            url: $signedUrl,
            method: 'POST',
            headers: [
                'Content-Type' => 'multipart/form-data',
            ],
            key: $clean,
            expiresAt: $expiresTimestamp
        );
    }

    public function path(string $path): string
    {
        $clean = FileSanitizer::sanitizePath($path);

        if (isset($this->config['root'])) {
            return rtrim($this->config['root'], DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $clean;
        }

        return $clean;
    }

    public function image(string $path): ImagePipeline
    {
        return new ImagePipeline($this, $path);
    }

    protected function buildConfig(?string $visibility = null): array
    {
        $config = [];
        $vis = $visibility ?? $this->config['visibility'] ?? null;

        if ($vis !== null) {
            $config['visibility'] = $vis;
        }

        return $config;
    }
}

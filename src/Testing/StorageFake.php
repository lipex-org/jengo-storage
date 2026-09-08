<?php

declare(strict_types=1);

namespace Jengo\Storage\Testing;

use DateTimeInterface;
use Jengo\Storage\Contracts\CloudFilesystemInterface;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Drivers\MemoryDriver;
use Jengo\Storage\Images\ImagePipeline;
use Jengo\Storage\Support\PresignedUpload;

class StorageFake implements CloudFilesystemInterface
{
    use AssertionsTrait;

    protected FilesystemInterface $inner;
    protected array $recordedWrites = [];
    protected array $recordedDeletes = [];

    public function __construct(
        public readonly string $diskName = 'fake',
        array $config = []
    ) {
        $this->inner = MemoryDriver::create(array_merge([
            'url' => "/storage/{$this->diskName}",
        ], $config));
    }

    public function exists(string $path): bool
    {
        return $this->inner->exists($path);
    }

    public function missing(string $path): bool
    {
        return $this->inner->missing($path);
    }

    public function get(string $path): string
    {
        return $this->inner->get($path);
    }

    public function readStream(string $path)
    {
        return $this->inner->readStream($path);
    }

    public function put(string $path, string $contents, ?string $visibility = null): bool
    {
        $this->recordedWrites[$path] = $contents;
        return $this->inner->put($path, $contents, $visibility);
    }

    public function writeStream(string $path, $resource, ?string $visibility = null): bool
    {
        $contents = stream_get_contents($resource);
        $this->recordedWrites[$path] = $contents;

        $temp = fopen('php://memory', 'r+');
        fwrite($temp, $contents);
        rewind($temp);

        $res = $this->inner->writeStream($path, $temp, $visibility);
        fclose($temp);

        return $res;
    }

    public function getVisibility(string $path): string
    {
        return $this->inner->getVisibility($path);
    }

    public function setVisibility(string $path, string $visibility): bool
    {
        return $this->inner->setVisibility($path, $visibility);
    }

    public function prepend(string $path, string $data): bool
    {
        return $this->inner->prepend($path, $data);
    }

    public function append(string $path, string $data): bool
    {
        return $this->inner->append($path, $data);
    }

    public function delete(string|array $paths): bool
    {
        $pathsList = is_array($paths) ? $paths : [$paths];
        foreach ($pathsList as $p) {
            $this->recordedDeletes[] = $p;
        }

        return $this->inner->delete($paths);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->inner->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->inner->move($from, $to);
    }

    public function size(string $path): int
    {
        return $this->inner->size($path);
    }

    public function lastModified(string $path): int
    {
        return $this->inner->lastModified($path);
    }

    public function mimeType(string $path): string|false
    {
        return $this->inner->mimeType($path);
    }

    public function checksum(string $path, array $options = []): string
    {
        return $this->inner->checksum($path, $options);
    }

    public function files(?string $directory = null, bool $recursive = false): array
    {
        return $this->inner->files($directory, $recursive);
    }

    public function allFiles(?string $directory = null): array
    {
        return $this->inner->allFiles($directory);
    }

    public function directories(?string $directory = null, bool $recursive = false): array
    {
        return $this->inner->directories($directory, $recursive);
    }

    public function allDirectories(?string $directory = null): array
    {
        return $this->inner->allDirectories($directory);
    }

    public function makeDirectory(string $path): bool
    {
        return $this->inner->makeDirectory($path);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->inner->deleteDirectory($directory);
    }

    public function url(string $path): string
    {
        return $this->inner->url($path);
    }

    public function temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = []): string
    {
        return $this->inner->temporaryUrl($path, $expiration, $options);
    }

    public function createUploadUrl(string $path, array $options = []): PresignedUpload
    {
        return $this->inner->createUploadUrl($path, $options);
    }

    public function path(string $path): string
    {
        return $this->inner->path($path);
    }

    public function image(string $path): ImagePipeline
    {
        return $this->inner->image($path);
    }

    public function getRecordedWrites(): array
    {
        return $this->recordedWrites;
    }

    public function getRecordedDeletes(): array
    {
        return $this->recordedDeletes;
    }
}

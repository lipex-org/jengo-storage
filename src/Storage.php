<?php

declare(strict_types=1);

namespace Jengo\Storage;

use DateTimeInterface;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Images\ImagePipeline;
use Jengo\Storage\Support\PresignedUpload;
use Jengo\Storage\Testing\StorageFake;

/**
 * @method static bool exists(string $path)
 * @method static bool missing(string $path)
 * @method static string get(string $path)
 * @method static resource|false readStream(string $path)
 * @method static bool put(string $path, string $contents, ?string $visibility = null)
 * @method static bool writeStream(string $path, $resource, ?string $visibility = null)
 * @method static string getVisibility(string $path)
 * @method static bool setVisibility(string $path, string $visibility)
 * @method static bool prepend(string $path, string $data)
 * @method static bool append(string $path, string $data)
 * @method static bool delete(string|array $paths)
 * @method static bool copy(string $from, string $to)
 * @method static bool move(string $from, string $to)
 * @method static int size(string $path)
 * @method static int lastModified(string $path)
 * @method static string|false mimeType(string $path)
 * @method static string checksum(string $path, array $options = [])
 * @method static array files(?string $directory = null, bool $recursive = false)
 * @method static array allFiles(?string $directory = null)
 * @method static array directories(?string $directory = null, bool $recursive = false)
 * @method static array allDirectories(?string $directory = null)
 * @method static bool makeDirectory(string $path)
 * @method static bool deleteDirectory(string $directory)
 * @method static string url(string $path)
 * @method static string temporaryUrl(string $path, int|DateTimeInterface $expiration, array $options = [])
 * @method static PresignedUpload createUploadUrl(string $path, array $options = [])
 * @method static string path(string $path)
 * @method static ImagePipeline image(string $path)
 */
class Storage
{
    protected static ?FilesystemManager $instance = null;

    /**
     * Get the resolved FilesystemManager instance.
     */
    public static function getFacadeRoot(): FilesystemManager
    {
        if (static::$instance !== null) {
            return static::$instance;
        }

        if (function_exists('service')) {
            /** @var FilesystemManager $manager */
            $manager = service('storage');
            return static::$instance = $manager;
        }

        return static::$instance = new FilesystemManager();
    }

    /**
     * Explicitly set the FilesystemManager instance (e.g. for isolated testing).
     */
    public static function swap(FilesystemManager $manager): void
    {
        static::$instance = $manager;
    }

    /**
     * Access a specific disk.
     */
    public static function disk(?string $name = null): FilesystemInterface
    {
        return static::getFacadeRoot()->disk($name);
    }

    /**
     * Replace one or multiple disks with in-memory test doubles.
     *
     * @param string|array<int, string> $diskNames
     * @return StorageFake|array<string, StorageFake>
     */
    public static function fake(string|array $diskNames = 'local'): StorageFake|array
    {
        return static::getFacadeRoot()->fake($diskNames);
    }

    /**
     * Reset the static instance cache.
     */
    public static function clearResolvedInstances(): void
    {
        static::$instance = null;
    }

    /**
     * Dynamically forward static calls to the default disk.
     */
    public static function __callStatic(string $method, array $parameters): mixed
    {
        return static::getFacadeRoot()->disk()->{$method}(...$parameters);
    }
}

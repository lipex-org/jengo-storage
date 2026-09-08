<?php

declare(strict_types=1);

namespace Jengo\Storage;

use Closure;
use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Drivers\LocalDriver;
use Jengo\Storage\Drivers\MemoryDriver;
use Jengo\Storage\Drivers\S3Driver;
use Jengo\Storage\Exceptions\DiskNotFoundException;
use Jengo\Storage\Testing\StorageFake;

class FilesystemManager
{
    /**
     * Cache of resolved filesystem instances.
     *
     * @var array<string, FilesystemInterface>
     */
    protected array $disks = [];

    /**
     * Custom driver creator closures.
     *
     * @var array<string, Closure>
     */
    protected array $customCreators = [];

    public function __construct(
        protected ?StorageConfig $config = null
    ) {
        $this->config ??= config('Storage') ?? new StorageConfig();
    }

    /**
     * Get a filesystem disk instance by name.
     */
    public function disk(?string $name = null): FilesystemInterface
    {
        $name ??= $this->getDefaultDriver();

        if (isset($this->disks[$name])) {
            return $this->disks[$name];
        }

        return $this->disks[$name] = $this->resolve($name);
    }

    /**
     * Bind an explicit disk instance into the manager (e.g. for testing fakes).
     */
    public function set(string $name, FilesystemInterface $disk): static
    {
        $this->disks[$name] = $disk;
        return $this;
    }

    /**
     * Replace one or multiple disks with in-memory test doubles.
     *
     * @param string|array<int, string> $diskNames
     * @return StorageFake|array<string, StorageFake>
     */
    public function fake(string|array $diskNames = 'local'): StorageFake|array
    {
        $names = is_array($diskNames) ? $diskNames : [$diskNames];
        $fakes = [];

        foreach ($names as $name) {
            $fake = new StorageFake($name);
            $this->set($name, $fake);
            $fakes[$name] = $fake;
        }

        return is_array($diskNames) ? $fakes : $fakes[$diskNames];
    }

    /**
     * Purge resolved disk instances.
     */
    public function purge(?string $name = null): void
    {
        if ($name === null) {
            $this->disks = [];
            return;
        }

        unset($this->disks[$name]);
    }

    /**
     * Register a custom driver creator closure.
     */
    public function extend(string $driver, Closure $callback): static
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Resolve the given disk instance.
     */
    protected function resolve(string $name): FilesystemInterface
    {
        $config = $this->getConfig($name);

        if ($config === null) {
            throw DiskNotFoundException::forDisk($name);
        }

        $driver = $config['driver'] ?? 'local';

        if (isset($this->customCreators[$driver])) {
            return $this->customCreators[$driver]($config, $this);
        }

        return match ($driver) {
            'local'  => LocalDriver::create(
                $config,
                $this->config->signingKey ?? '',
                $this->config->signedRoutePrefix ?? 'storage/signed'
            ),
            's3'     => S3Driver::create($config),
            'memory' => MemoryDriver::create(
                $config,
                $this->config->signingKey ?? 'memory-secret'
            ),
            default  => throw new Exceptions\StorageException("Driver [{$driver}] is not supported by Jengo Storage."),
        };
    }

    /**
     * Get the configuration for a disk.
     */
    public function getConfig(string $name): ?array
    {
        return $this->config->disks[$name] ?? null;
    }

    /**
     * Get the default driver/disk name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->default ?? 'local';
    }

    /**
     * Get the underlying Storage configuration object.
     */
    public function getStorageConfig(): StorageConfig
    {
        return $this->config;
    }

    /**
     * Dynamically pass methods to the default disk.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->{$method}(...$parameters);
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Storage\Drivers;

use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Filesystem;
use Jengo\Storage\Security\HmacUrlSigner;
use League\Flysystem\Filesystem as FlysystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;

class MemoryDriver
{
    /**
     * In-memory adapter instances registry for test/in-process retrieval across serializations.
     *
     * @var array<int, InMemoryFilesystemAdapter>
     */
    public static array $adapterRegistry = [];

    public static function create(array $config = [], string $signingKey = 'test-signing-key'): FilesystemInterface
    {
        $adapter = new InMemoryFilesystemAdapter();
        static::$adapterRegistry[spl_object_id($adapter)] = $adapter;

        $operator = new FlysystemOperator($adapter);
        $signer = new HmacUrlSigner($signingKey, $config['url'] ?? '/storage/memory');

        return new Filesystem($operator, $config, $signer);
    }
}

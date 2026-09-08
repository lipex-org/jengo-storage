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
    public static function create(array $config = [], string $signingKey = 'test-signing-key'): FilesystemInterface
    {
        $adapter = new InMemoryFilesystemAdapter();
        $operator = new FlysystemOperator($adapter);
        $signer = new HmacUrlSigner($signingKey, $config['url'] ?? '/storage/memory');

        return new Filesystem($operator, $config, $signer);
    }
}

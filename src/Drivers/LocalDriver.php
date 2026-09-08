<?php

declare(strict_types=1);

namespace Jengo\Storage\Drivers;

use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Filesystem;
use Jengo\Storage\Security\HmacUrlSigner;
use League\Flysystem\Filesystem as FlysystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnixVisibility\PortableVisibilityConverter;

class LocalDriver
{
    public static function create(array $config, string $signingKey = '', string $signedRoutePrefix = 'storage/signed'): FilesystemInterface
    {
        $root = $config['root'] ?? WRITEPATH . 'storage/app';

        if (! is_dir($root)) {
            @mkdir($root, 0755, true);
        }

        $visibilityConverter = PortableVisibilityConverter::fromArray([
            'file' => [
                'public'  => 0644,
                'private' => 0600,
            ],
            'dir' => [
                'public'  => 0755,
                'private' => 0700,
            ],
        ]);

        $adapter = new LocalFilesystemAdapter(
            location: $root,
            visibility: $visibilityConverter,
            writeFlags: LOCK_EX
        );

        $operator = new FlysystemOperator($adapter);

        $baseUrl = $config['url'] ?? '/' . trim($signedRoutePrefix, '/');
        $signer = new HmacUrlSigner($signingKey, $baseUrl);

        return new Filesystem($operator, $config, $signer);
    }
}

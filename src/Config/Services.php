<?php

declare(strict_types=1);

namespace Jengo\Storage\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\FilesystemManager;

class Services extends BaseService
{
    /**
     * Return the FilesystemManager instance.
     */
    public static function storage(?Storage $config = null, bool $getShared = true): FilesystemManager
    {
        if ($getShared) {
            return static::getSharedInstance('storage', $config);
        }

        return new FilesystemManager($config);
    }

    /**
     * Return a specific disk instance directly.
     */
    public static function storageDisk(?string $disk = null, bool $getShared = true): FilesystemInterface
    {
        return static::storage(null, $getShared)->disk($disk);
    }
}

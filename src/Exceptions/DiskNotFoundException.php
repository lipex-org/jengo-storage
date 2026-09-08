<?php

declare(strict_types=1);

namespace Jengo\Storage\Exceptions;

class DiskNotFoundException extends StorageException
{
    public static function forDisk(string $disk): self
    {
        return new self("Disk [{$disk}] is not defined in storage configuration.");
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Storage\Config;

use Jengo\Storage\Installers\StorageInstaller;

class Registrar
{
    public static function JengoBase(): array
    {
        return [
            'installers' => [
                StorageInstaller::class,
            ],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Jengo\Storage\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class StorageInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'storage';
    }

    public static function description(): string
    {
        return 'Install Jengo Storage filesystem abstraction, asset management, and publish configuration';
    }

    public static function reasonForSkipping(): string
    {
        return 'Storage configuration already published in app/Config/Storage.php.';
    }

    public function shouldRun(): bool
    {
        return ! file_exists(APPPATH . 'Config/Storage.php');
    }

    public function install(): void
    {
        $this->addRun();

        // Enforce prerequisite: either GD or Imagick must be installed
        if (! extension_loaded('gd') && ! extension_loaded('imagick')) {
            CLI::error('jengo/storage requires either the GD (ext-gd) or Imagick (ext-imagick) PHP extension to be enabled.');
            CLI::write('Please install and enable either php-gd or php-imagick in your environment before installing.', 'yellow');
            return;
        }

        $dest = APPPATH . 'Config/Storage.php';
        if (file_exists($dest)) {
            CLI::write('Config/Storage.php already exists, skipping.', 'yellow');
            return;
        }

        $source = __DIR__ . '/../Config/Storage.php';
        $content = (string) file_get_contents($source);
        $content = str_replace(
            "namespace Jengo\\Storage\\Config;\n\nuse CodeIgniter\\Config\\BaseConfig;",
            "namespace Config;\n\nuse Jengo\\Storage\\Config\\Storage as BaseStorage;",
            $content
        );
        $content = str_replace(
            "class Storage extends BaseConfig",
            "class Storage extends BaseStorage",
            $content
        );

        $this->writeFile($dest, $content);
        CLI::write('Published Config/Storage.php successfully.', 'green');

        // Automatically connect public storage symlink if possible
        $publicDir = WRITEPATH . 'storage/app/public';
        if (! is_dir($publicDir)) {
            @mkdir($publicDir, 0755, true);
        }

        $target = ROOTPATH . 'public/storage';
        if (! file_exists($target) && ! is_link($target)) {
            if (@symlink($publicDir, $target)) {
                CLI::write('Connected public/storage symbolic link successfully.', 'green');
            }
        }
    }
}

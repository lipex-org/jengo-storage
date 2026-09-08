<?php

declare(strict_types=1);

namespace Jengo\Storage\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class StorageLinkCommand extends BaseCommand
{
    protected $group       = 'Storage';
    protected $name        = 'storage:link';
    protected $description = 'Create the symbolic link connecting public/storage to writable/storage/app/public.';
    protected $usage       = 'storage:link [--force]';
    protected $options     = [
        '--force' => 'Recreate existing symlink if it already exists.',
    ];

    public function run(array $params)
    {
        $target = ROOTPATH . 'public/storage';
        $source = WRITEPATH . 'storage/app/public';

        if (! is_dir($source)) {
            @mkdir($source, 0755, true);
        }

        $force = array_key_exists('force', $params) || CLI::getOption('force');

        if (file_exists($target) || is_link($target)) {
            if (! $force) {
                CLI::write("The [public/storage] link already exists.", 'yellow');
                return;
            }

            if (is_link($target)) {
                unlink($target);
            } else {
                CLI::error("The [public/storage] target is a directory. Please remove it manually before creating a symlink.");
                return;
            }
        }

        if (@symlink($source, $target)) {
            CLI::write("The [public/storage] link has been connected to [{$source}].", 'green');
        } else {
            CLI::error("Failed to create symbolic link from [{$source}] to [{$target}].");
        }
    }
}

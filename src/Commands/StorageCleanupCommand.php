<?php

declare(strict_types=1);

namespace Jengo\Storage\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class StorageCleanupCommand extends BaseCommand
{
    protected $group       = 'Storage';
    protected $name        = 'storage:cleanup';
    protected $description = 'Prune orphaned temporary files and stale storage artifacts.';
    protected $usage       = 'storage:cleanup [--hours=24]';
    protected $options     = [
        '--hours' => 'Age threshold in hours for purging temporary files. Defaults to 24.',
    ];

    public function run(array $params)
    {
        $hours = (int) (CLI::getOption('hours') ?? $params['hours'] ?? 24);
        $threshold = time() - ($hours * 3600);

        $tempDir = WRITEPATH . 'storage/temp';

        if (! is_dir($tempDir)) {
            CLI::write("No temporary directory found at [{$tempDir}]. Nothing to clean.", 'yellow');
            return;
        }

        $files = scandir($tempDir);
        $deletedCount = 0;
        $freedBytes = 0;

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }

            $fullPath = $tempDir . DIRECTORY_SEPARATOR . $file;

            if (is_file($fullPath) && filemtime($fullPath) < $threshold) {
                $freedBytes += filesize($fullPath);
                if (@unlink($fullPath)) {
                    $deletedCount++;
                }
            } elseif (is_dir($fullPath) && filemtime($fullPath) < $threshold) {
                // Recursively clean old chunk folders
                $subFiles = scandir($fullPath) ?: [];
                foreach ($subFiles as $sub) {
                    if ($sub !== '.' && $sub !== '..') {
                        $subPath = $fullPath . DIRECTORY_SEPARATOR . $sub;
                        if (is_file($subPath)) {
                            $freedBytes += filesize($subPath);
                            @unlink($subPath);
                            $deletedCount++;
                        }
                    }
                }
                @rmdir($fullPath);
            }
        }

        $mb = round($freedBytes / (1024 * 1024), 2);
        CLI::write("Cleaned up {$deletedCount} orphaned temporary files/parts ({$mb} MB freed).", 'green');
    }
}

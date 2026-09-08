<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Jengo\Storage\Commands\StorageCleanupCommand;
use Jengo\Storage\Commands\StorageLinkCommand;

class StorageCommandsTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_storage_link_command(): void
    {
        $command = new StorageLinkCommand(service('logger'), service('commands'));

        $this->assertNull($command->run(['force' => true]));
    }

    public function test_storage_cleanup_command(): void
    {
        $tempDir = WRITEPATH . 'storage/temp';
        @mkdir($tempDir, 0755, true);

        // Create an expired file (2 days old)
        $oldFile = $tempDir . '/old_file.tmp';
        file_put_contents($oldFile, 'expired-data');
        touch($oldFile, time() - (48 * 3600));

        // Create a recent file
        $recentFile = $tempDir . '/recent_file.tmp';
        file_put_contents($recentFile, 'fresh-data');

        $command = new StorageCleanupCommand(service('logger'), service('commands'));
        $command->run(['hours' => 24]);

        $this->assertFileDoesNotExist($oldFile);
        $this->assertFileExists($recentFile);

        @unlink($recentFile);
    }
}

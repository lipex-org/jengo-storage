<?php

declare(strict_types=1);

namespace Tests\Unit;

use Closure;
use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Exceptions\DiskNotFoundException;
use Jengo\Storage\Exceptions\StorageException;
use Jengo\Storage\FilesystemManager;
use Jengo\Storage\Testing\StorageFake;
use PHPUnit\Framework\TestCase;

class FilesystemManagerTest extends TestCase
{
    private function createConfig(): StorageConfig
    {
        $config = new StorageConfig();
        $config->default = 'local';
        $config->signingKey = 'test-signing-key';
        $config->disks = [
            'local' => [
                'driver'     => 'local',
                'root'       => sys_get_temp_dir() . '/storage_test_local',
                'visibility' => 'private',
            ],
            'public' => [
                'driver'     => 'local',
                'root'       => sys_get_temp_dir() . '/storage_test_public',
                'url'        => 'http://localhost:8080/storage',
                'visibility' => 'public',
            ],
            'custom_s3' => [
                'driver'   => 's3',
                'key'      => 'fake-key',
                'secret'   => 'fake-secret',
                'region'   => 'us-east-1',
                'bucket'   => 'my-bucket',
                'endpoint' => 'http://localhost:9000',
            ],
            'in_memory' => [
                'driver' => 'memory',
            ],
            'invalid_driver' => [
                'driver' => 'ftp',
            ],
        ];

        return $config;
    }

    public function test_resolves_default_disk(): void
    {
        $manager = new FilesystemManager($this->createConfig());
        $disk = $manager->disk();

        $this->assertInstanceOf(FilesystemInterface::class, $disk);
        $this->assertSame('local', $manager->getDefaultDriver());
    }

    public function test_resolves_named_disks_and_caches_instance(): void
    {
        $manager = new FilesystemManager($this->createConfig());
        $disk1 = $manager->disk('public');
        $disk2 = $manager->disk('public');

        $this->assertInstanceOf(FilesystemInterface::class, $disk1);
        $this->assertSame($disk1, $disk2);
    }

    public function test_throws_exception_for_non_existent_disk(): void
    {
        $manager = new FilesystemManager($this->createConfig());

        $this->expectException(DiskNotFoundException::class);
        $this->expectExceptionMessage("Disk [non_existent] is not defined in storage configuration.");

        $manager->disk('non_existent');
    }

    public function test_throws_exception_for_unsupported_driver(): void
    {
        $manager = new FilesystemManager($this->createConfig());

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Driver [ftp] is not supported by Jengo Storage.");

        $manager->disk('invalid_driver');
    }

    public function test_can_extend_with_custom_driver(): void
    {
        $manager = new FilesystemManager($this->createConfig());

        $mockDisk = $this->createMock(FilesystemInterface::class);

        $manager->extend('ftp', function (array $config, FilesystemManager $m) use ($mockDisk) {
            return $mockDisk;
        });

        $resolved = $manager->disk('invalid_driver');
        $this->assertSame($mockDisk, $resolved);
    }

    public function test_can_bind_explicit_disk_instance(): void
    {
        $manager = new FilesystemManager($this->createConfig());
        $customFake = new StorageFake('custom');

        $manager->set('custom', $customFake);

        $this->assertSame($customFake, $manager->disk('custom'));
    }

    public function test_can_fake_single_or_multiple_disks(): void
    {
        $manager = new FilesystemManager($this->createConfig());

        $fakeLocal = $manager->fake('local');
        $this->assertInstanceOf(StorageFake::class, $fakeLocal);
        $this->assertSame($fakeLocal, $manager->disk('local'));

        $fakes = $manager->fake(['public', 'in_memory']);
        $this->assertIsArray($fakes);
        $this->assertCount(2, $fakes);
        $this->assertInstanceOf(StorageFake::class, $fakes['public']);
        $this->assertInstanceOf(StorageFake::class, $fakes['in_memory']);
    }

    public function test_can_purge_cached_disks(): void
    {
        $manager = new FilesystemManager($this->createConfig());

        $disk1 = $manager->disk('local');
        $manager->purge('local');
        $disk2 = $manager->disk('local');

        $this->assertNotSame($disk1, $disk2);

        $manager->purge(); // Purge all
        $disk3 = $manager->disk('local');
        $this->assertNotSame($disk2, $disk3);
    }

    public function test_proxies_method_calls_to_default_disk(): void
    {
        $manager = new FilesystemManager($this->createConfig());
        $fake = $manager->fake('local');

        $manager->put('hello.txt', 'world');

        $this->assertTrue($fake->exists('hello.txt'));
        $this->assertSame('world', $manager->get('hello.txt'));
    }
}

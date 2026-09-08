<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Storage\Storage;

class StorageFacadeTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Storage::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_facade_interacts_with_fake_disk(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('readme.txt', 'test-data');
        $this->assertTrue(Storage::disk('public')->exists('readme.txt'));
        $this->assertSame('test-data', Storage::disk('public')->get('readme.txt'));

        Storage::disk('public')->assertExists('readme.txt');
    }

    public function test_storage_helper_function(): void
    {
        Storage::fake('public');

        storage('public')->put('hello.txt', 'world');
        $this->assertSame('world', storage('public')->get('hello.txt'));

        $url = storage_url('hello.txt', 'public');
        $this->assertStringContainsString('hello.txt', $url);
    }

    public function test_presigned_upload_creation(): void
    {
        $fake = Storage::fake('memory');

        $upload = $fake->createUploadUrl('uploads/video.mp4', [
            'expires'     => time() + 600,
            'contentType' => 'video/mp4',
        ]);

        $this->assertSame('uploads/video.mp4', $upload->getKey());
        $this->assertNotEmpty($upload->getUrl());
        $this->assertArrayHasKey('Content-Type', $upload->getHeaders());
    }
}

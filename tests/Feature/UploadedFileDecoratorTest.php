<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use Jengo\Storage\Storage;
use Jengo\Storage\Support\UploadedFileDecorator;
use Jengo\Storage\Testing\FileFactory;

class UploadedFileDecoratorTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Storage::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_stores_file_with_auto_hash_name(): void
    {
        Storage::fake('memory');

        $uploaded = FileFactory::create('document.pdf', 5, 'application/pdf');
        $decorator = new UploadedFileDecorator($uploaded);

        $storedPath = $decorator->store('vault', 'memory');

        $this->assertIsString($storedPath);
        $this->assertStringStartsWith('vault/', $storedPath);
        $this->assertStringEndsWith('.pdf', $storedPath);

        $this->assertTrue(Storage::disk('memory')->exists($storedPath));
    }

    public function test_stores_file_with_explicit_name(): void
    {
        Storage::fake('memory');

        $uploaded = FileFactory::create('report.txt', 2, 'text/plain');
        $decorator = new UploadedFileDecorator($uploaded);

        $storedPath = $decorator->storeAs('reports/2026', 'final-summary.txt', 'memory');

        $this->assertSame('reports/2026/final-summary.txt', $storedPath);
        $this->assertTrue(Storage::disk('memory')->exists('reports/2026/final-summary.txt'));
    }

    public function test_generates_hash_name_with_extension(): void
    {
        $uploaded = FileFactory::image('avatar.png', 50, 50, 'png');
        $decorator = new UploadedFileDecorator($uploaded);

        $hash = $decorator->hashName('users');
        $this->assertStringStartsWith('users/', $hash);
        $this->assertStringEndsWith('.png', $hash);
    }
}

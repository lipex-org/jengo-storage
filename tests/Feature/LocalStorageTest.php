<?php

declare(strict_types=1);

namespace Tests\Feature;

use Jengo\Storage\Drivers\LocalDriver;
use PHPUnit\Framework\TestCase;

class LocalStorageTest extends TestCase
{
    protected string $testDir;
    protected $disk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testDir = sys_get_temp_dir() . '/jengo_storage_test_' . uniqid();
        @mkdir($this->testDir, 0755, true);

        $this->disk = LocalDriver::create([
            'root' => $this->testDir,
            'url'  => 'https://example.com/storage',
        ], 'test-secret-key-12345');
    }

    protected function tearDown(): void
    {
        $this->deleteRecursive($this->testDir);
        parent::tearDown();
    }

    protected function deleteRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteRecursive($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    public function test_basic_put_get_exists_delete(): void
    {
        $this->assertTrue($this->disk->put('notes.txt', 'Hello Jengo'));
        $this->assertTrue($this->disk->exists('notes.txt'));
        $this->assertFalse($this->disk->missing('notes.txt'));
        $this->assertSame('Hello Jengo', $this->disk->get('notes.txt'));

        $this->assertTrue($this->disk->delete('notes.txt'));
        $this->assertFalse($this->disk->exists('notes.txt'));
        $this->assertTrue($this->disk->missing('notes.txt'));
    }

    public function test_prepend_and_append(): void
    {
        $this->disk->put('log.txt', 'middle');
        $this->disk->prepend('log.txt', 'start-');
        $this->disk->append('log.txt', '-end');

        $this->assertSame('start-middle-end', $this->disk->get('log.txt'));
    }

    public function test_copy_and_move(): void
    {
        $this->disk->put('source.txt', 'copy-move-test');

        $this->assertTrue($this->disk->copy('source.txt', 'backup/copied.txt'));
        $this->assertTrue($this->disk->exists('source.txt'));
        $this->assertTrue($this->disk->exists('backup/copied.txt'));

        $this->assertTrue($this->disk->move('source.txt', 'moved.txt'));
        $this->assertFalse($this->disk->exists('source.txt'));
        $this->assertTrue($this->disk->exists('moved.txt'));
    }

    public function test_stream_reading_and_writing(): void
    {
        $source = fopen('php://memory', 'r+');
        fwrite($source, 'streamed-file-content');
        rewind($source);

        $this->assertTrue($this->disk->writeStream('streamed.txt', $source));
        fclose($source);

        $readStream = $this->disk->readStream('streamed.txt');
        $this->assertIsResource($readStream);
        $this->assertSame('streamed-file-content', stream_get_contents($readStream));
        fclose($readStream);
    }

    public function test_directory_operations_and_listing(): void
    {
        $this->disk->put('reports/2026/q1.pdf', 'q1');
        $this->disk->put('reports/2026/q2.pdf', 'q2');
        $this->disk->put('reports/2025/annual.pdf', 'annual');

        $files = $this->disk->files('reports/2026');
        $this->assertCount(2, $files);

        $allFiles = $this->disk->allFiles('reports');
        $this->assertCount(3, $allFiles);

        $this->assertTrue($this->disk->deleteDirectory('reports/2025'));
        $this->assertFalse($this->disk->exists('reports/2025/annual.pdf'));
    }

    public function test_file_metadata_and_url(): void
    {
        $this->disk->put('public/image.png', 'fake-image-bytes');

        $this->assertGreaterThan(0, $this->disk->size('public/image.png'));
        $this->assertGreaterThan(0, $this->disk->lastModified('public/image.png'));
        $this->assertSame('https://example.com/storage/public/image.png', $this->disk->url('public/image.png'));
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Jengo\Storage\Drivers\MemoryDriver;
use Jengo\Storage\Exceptions\FileNotFoundException;
use Jengo\Storage\Exceptions\StorageException;
use Jengo\Storage\Filesystem;
use Jengo\Storage\Security\HmacUrlSigner;
use League\Flysystem\Filesystem as FlysystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;

class FilesystemExtendedTest extends TestCase
{
    private function createFilesystem(array $config = []): Filesystem
    {
        $adapter = new InMemoryFilesystemAdapter();
        $operator = new FlysystemOperator($adapter);
        $signer = new HmacUrlSigner('secret-key-123', '/storage/signed');

        return new Filesystem($operator, $config, $signer);
    }

    public function test_get_operator_and_config(): void
    {
        $fs = $this->createFilesystem(['root' => '/tmp/storage', 'visibility' => 'public', 'url' => 'https://cdn.example.com']);

        $this->assertInstanceOf(FlysystemOperator::class, $fs->getOperator());
        $this->assertSame(['root' => '/tmp/storage', 'visibility' => 'public', 'url' => 'https://cdn.example.com'], $fs->getConfig());
    }

    public function test_missing_method(): void
    {
        $fs = $this->createFilesystem();

        $this->assertTrue($fs->missing('nonexistent.txt'));
        $fs->put('exists.txt', 'content');
        $this->assertFalse($fs->missing('exists.txt'));
    }

    public function test_get_throws_file_not_found(): void
    {
        $fs = $this->createFilesystem();

        $this->expectException(FileNotFoundException::class);
        $fs->get('missing.txt');
    }

    public function test_read_stream_and_write_stream(): void
    {
        $fs = $this->createFilesystem();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'streaming content data');
        rewind($stream);

        $this->assertTrue($fs->writeStream('streamed.txt', $stream));
        fclose($stream);

        $this->assertTrue($fs->exists('streamed.txt'));

        $readStream = $fs->readStream('streamed.txt');
        $this->assertIsResource($readStream);
        $this->assertSame('streaming content data', stream_get_contents($readStream));
        fclose($readStream);
    }

    public function test_read_stream_throws_file_not_found(): void
    {
        $fs = $this->createFilesystem();

        $this->expectException(FileNotFoundException::class);
        $fs->readStream('does_not_exist.txt');
    }

    public function test_prepend_and_append(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('file.txt', 'Middle');
        $this->assertTrue($fs->prepend('file.txt', 'Start: '));
        $this->assertSame('Start: Middle', $fs->get('file.txt'));

        $this->assertTrue($fs->append('file.txt', ' :End'));
        $this->assertSame('Start: Middle :End', $fs->get('file.txt'));

        // Prepend and append to non-existent file
        $this->assertTrue($fs->prepend('new_prep.txt', 'First'));
        $this->assertSame('First', $fs->get('new_prep.txt'));

        $this->assertTrue($fs->append('new_app.txt', 'Last'));
        $this->assertSame('Last', $fs->get('new_app.txt'));
    }

    public function test_delete_single_and_multiple(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('a.txt', '1');
        $fs->put('b.txt', '2');
        $fs->put('c.txt', '3');

        $this->assertTrue($fs->delete('a.txt'));
        $this->assertFalse($fs->exists('a.txt'));

        $this->assertTrue($fs->delete(['b.txt', 'c.txt']));
        $this->assertFalse($fs->exists('b.txt'));
        $this->assertFalse($fs->exists('c.txt'));
    }

    public function test_copy_and_move(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('source.txt', 'original');

        $this->assertTrue($fs->copy('source.txt', 'copied.txt'));
        $this->assertTrue($fs->exists('source.txt'));
        $this->assertTrue($fs->exists('copied.txt'));
        $this->assertSame('original', $fs->get('copied.txt'));

        $this->assertTrue($fs->move('copied.txt', 'moved.txt'));
        $this->assertFalse($fs->exists('copied.txt'));
        $this->assertTrue($fs->exists('moved.txt'));
        $this->assertSame('original', $fs->get('moved.txt'));
    }

    public function test_size_and_last_modified(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('meta.txt', '12345');
        $this->assertSame(5, $fs->size('meta.txt'));
        $this->assertIsInt($fs->lastModified('meta.txt'));
        $this->assertGreaterThan(0, $fs->lastModified('meta.txt'));
    }

    public function test_size_throws_file_not_found(): void
    {
        $fs = $this->createFilesystem();

        $this->expectException(FileNotFoundException::class);
        $fs->size('missing_size.txt');
    }

    public function test_last_modified_throws_file_not_found(): void
    {
        $fs = $this->createFilesystem();

        $this->expectException(FileNotFoundException::class);
        $fs->lastModified('missing_modified.txt');
    }

    public function test_mime_type(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('doc.json', '{"key":"value"}');
        $mime = $fs->mimeType('doc.json');
        $this->assertNotEmpty($mime);
    }

    public function test_checksum_calculation(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('check.txt', 'checksum verification text');
        $sha256 = $fs->checksum('check.txt');
        $this->assertSame(hash('sha256', 'checksum verification text'), $sha256);

        $md5 = $fs->checksum('check.txt', ['algo' => 'md5']);
        $this->assertSame(hash('md5', 'checksum verification text'), $md5);
    }

    public function test_files_and_directories_listing(): void
    {
        $fs = $this->createFilesystem();

        $fs->put('dir1/file1.txt', '1');
        $fs->put('dir1/sub/file2.txt', '2');
        $fs->put('dir2/file3.txt', '3');

        $shallowFiles = $fs->files('dir1');
        $this->assertCount(1, $shallowFiles);
        $this->assertContains('dir1/file1.txt', $shallowFiles);

        $allFiles = $fs->allFiles('dir1');
        $this->assertCount(2, $allFiles);
        $this->assertContains('dir1/file1.txt', $allFiles);
        $this->assertContains('dir1/sub/file2.txt', $allFiles);

        $dirs = $fs->directories();
        $this->assertContains('dir1', $dirs);
        $this->assertContains('dir2', $dirs);

        $allDirs = $fs->allDirectories();
        $this->assertContains('dir1/sub', $allDirs);
    }

    public function test_make_and_delete_directory(): void
    {
        $fs = $this->createFilesystem();

        $this->assertTrue($fs->makeDirectory('new_folder'));
        $this->assertTrue($fs->deleteDirectory('new_folder'));
    }

    public function test_url_and_temporary_url(): void
    {
        $fs = $this->createFilesystem(['url' => 'https://assets.mycdn.com']);

        $this->assertSame('https://assets.mycdn.com/photos/dog.jpg', $fs->url('photos/dog.jpg'));

        $expires = time() + 3600;
        $tempUrl = $fs->temporaryUrl('photos/dog.jpg', $expires);
        $this->assertStringContainsString('/storage/signed', $tempUrl);
        $this->assertStringContainsString('signature=', $tempUrl);
    }

    public function test_temporary_url_throws_when_no_signer(): void
    {
        $adapter = new InMemoryFilesystemAdapter();
        $operator = new FlysystemOperator($adapter);
        $fs = new Filesystem($operator);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Temporary URLs are not supported on this disk");

        $fs->temporaryUrl('file.txt', time() + 60);
    }

    public function test_create_upload_url(): void
    {
        $fs = $this->createFilesystem(['url' => 'http://localhost/storage']);

        $upload = $fs->createUploadUrl('uploads/avatar.png', ['expires' => time() + 1800]);

        $this->assertSame('POST', $upload->method);
        $this->assertSame('uploads/avatar.png', $upload->key);
        $this->assertStringContainsString('signature=', $upload->url);
    }

    public function test_path_resolution(): void
    {
        $fsWithRoot = $this->createFilesystem(['root' => '/var/data/storage']);
        $this->assertSame('/var/data/storage/documents/report.pdf', $fsWithRoot->path('documents/report.pdf'));

        $fsWithoutRoot = $this->createFilesystem();
        $this->assertSame('documents/report.pdf', $fsWithoutRoot->path('documents/report.pdf'));
    }

    public function test_visibility_management(): void
    {
        $fs = $this->createFilesystem(['visibility' => 'public']);

        $fs->put('pub.txt', 'public content');
        $this->assertSame('public', $fs->getVisibility('pub.txt'));

        $this->assertTrue($fs->setVisibility('pub.txt', 'private'));
        $this->assertSame('private', $fs->getVisibility('pub.txt'));
    }
}

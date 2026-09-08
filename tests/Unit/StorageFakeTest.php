<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Storage\Testing\StorageFake;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

class StorageFakeTest extends TestCase
{
    protected StorageFake $fake;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fake = new StorageFake('avatars');
    }

    public function test_records_writes_and_deletes(): void
    {
        $this->fake->put('users/1.jpg', 'avatar-content');
        $this->assertTrue($this->fake->exists('users/1.jpg'));
        $this->assertSame('avatar-content', $this->fake->get('users/1.jpg'));

        $this->assertArrayHasKey('users/1.jpg', $this->fake->getRecordedWrites());

        $this->fake->delete('users/1.jpg');
        $this->assertTrue($this->fake->missing('users/1.jpg'));
        $this->assertContains('users/1.jpg', $this->fake->getRecordedDeletes());
    }

    public function test_assert_exists_and_assert_missing(): void
    {
        $this->fake->put('file1.txt', 'test');

        $this->fake->assertExists('file1.txt');
        $this->fake->assertMissing('file2.txt');
    }

    public function test_assert_exists_fails_when_file_missing(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->fake->assertExists('non_existent.txt');
    }

    public function test_assert_directory_file_count(): void
    {
        $this->fake->put('uploads/a.png', 'a');
        $this->fake->put('uploads/b.png', 'b');
        $this->fake->put('uploads/c.png', 'c');

        $this->fake->assertDirectoryFileCount('uploads', 3);
    }

    public function test_assert_size_and_checksum(): void
    {
        $content = 'hello world from jengo storage';
        $this->fake->put('greeting.txt', $content);

        $this->fake->assertSize('greeting.txt', strlen($content));
        $this->fake->assertChecksum('greeting.txt', hash('sha256', $content), ['algo' => 'sha256']);
    }
}

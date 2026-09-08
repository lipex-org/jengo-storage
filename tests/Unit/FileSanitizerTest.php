<?php

declare(strict_types=1);

namespace Tests\Unit;

use InvalidArgumentException;
use Jengo\Storage\Security\FileSanitizer;
use PHPUnit\Framework\TestCase;

class FileSanitizerTest extends TestCase
{
    public function test_sanitizes_normal_paths(): void
    {
        $this->assertSame('avatars/user.png', FileSanitizer::sanitizePath('avatars/user.png'));
        $this->assertSame('avatars/user.png', FileSanitizer::sanitizePath('/avatars//user.png'));
        $this->assertSame('avatars/user.png', FileSanitizer::sanitizePath('avatars\\user.png'));
    }

    public function test_rejects_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Path traversal sequence '..' is not allowed");

        FileSanitizer::sanitizePath('../secret/passwords.txt');
    }

    public function test_rejects_nested_path_traversal(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Path traversal sequence '..' is not allowed");

        FileSanitizer::sanitizePath('uploads/../../etc/passwd');
    }

    public function test_rejects_null_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Path contains invalid null byte characters");

        FileSanitizer::sanitizePath("uploads/avatar.png\0.php");
    }

    public function test_generates_cryptographic_hash_name(): void
    {
        $hash1 = FileSanitizer::hashName('profile.jpg');
        $hash2 = FileSanitizer::hashName('profile.jpg');

        $this->assertNotSame($hash1, $hash2);
        $this->assertStringEndsWith('.jpg', $hash1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}\.jpg$/', $hash1);
    }

    public function test_sanitizes_dangerous_filenames(): void
    {
        $this->assertSame('my_cool_document_.pdf', FileSanitizer::sanitizeFilename('my cool document?.pdf'));
        $this->assertSame('evil.php', FileSanitizer::sanitizeFilename('../../../evil.php'));
    }
}

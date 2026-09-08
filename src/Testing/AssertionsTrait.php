<?php

declare(strict_types=1);

namespace Jengo\Storage\Testing;

use PHPUnit\Framework\Assert as PHPUnit;

trait AssertionsTrait
{
    /**
     * Assert that the given file(s) exist on disk.
     */
    public function assertExists(string|array $paths): static
    {
        $paths = is_array($paths) ? $paths : [$paths];

        foreach ($paths as $path) {
            PHPUnit::assertTrue(
                $this->exists($path),
                "Unable to find file: [{$path}] on disk."
            );
        }

        return $this;
    }

    /**
     * Assert that the given file(s) are missing from disk.
     */
    public function assertMissing(string|array $paths): static
    {
        $paths = is_array($paths) ? $paths : [$paths];

        foreach ($paths as $path) {
            PHPUnit::assertFalse(
                $this->exists($path),
                "Found unexpected file: [{$path}] on disk."
            );
        }

        return $this;
    }

    /**
     * Assert the total number of files within a directory.
     */
    public function assertDirectoryFileCount(string $directory, int $expectedCount): static
    {
        $actualCount = count($this->files($directory));

        PHPUnit::assertSame(
            $expectedCount,
            $actualCount,
            "Expected {$expectedCount} files in directory [{$directory}], but found {$actualCount}."
        );

        return $this;
    }

    /**
     * Assert the exact file size.
     */
    public function assertSize(string $path, int $expectedBytes): static
    {
        $this->assertExists($path);
        $actualBytes = $this->size($path);

        PHPUnit::assertSame(
            $expectedBytes,
            $actualBytes,
            "Expected file [{$path}] to have size of {$expectedBytes} bytes, but got {$actualBytes}."
        );

        return $this;
    }

    /**
     * Assert the cryptographic checksum matches.
     */
    public function assertChecksum(string $path, string $expectedChecksum, array $options = []): static
    {
        $this->assertExists($path);
        $actual = $this->checksum($path, $options);

        PHPUnit::assertSame(
            $expectedChecksum,
            $actual,
            "Expected file [{$path}] checksum to be [{$expectedChecksum}], but got [{$actual}]."
        );

        return $this;
    }
}

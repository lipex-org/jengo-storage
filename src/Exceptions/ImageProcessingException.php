<?php

declare(strict_types=1);

namespace Jengo\Storage\Exceptions;

class ImageProcessingException extends StorageException
{
    public static function unsupportedFormat(string $format): self
    {
        return new self("Unsupported image format: [{$format}].");
    }

    public static function driverUnavailable(string $driver): self
    {
        return new self("Image processing extension for [{$driver}] is not installed or enabled in PHP.");
    }

    public static function decodingFailed(): self
    {
        return new self("Failed to decode image from binary stream.");
    }
}

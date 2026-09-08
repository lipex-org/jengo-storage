<?php

declare(strict_types=1);

namespace Jengo\Storage\Exceptions;

class InvalidSignatureException extends StorageException
{
    public static function expired(): self
    {
        return new self("The signed download URL has expired.");
    }

    public static function invalid(): self
    {
        return new self("The URL signature is invalid or tampered with.");
    }
}

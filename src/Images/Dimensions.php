<?php

declare(strict_types=1);

namespace Jengo\Storage\Images;

class Dimensions
{
    public function __construct(
        public readonly int $width,
        public readonly int $height
    ) {
    }

    public function getAspectRatio(): float
    {
        return $this->height > 0 ? $this->width / $this->height : 1.0;
    }

    /**
     * Calculate new dimensions preserving aspect ratio to fit within maximum bounds.
     */
    public function calculateFitDimensions(int $targetWidth, int $targetHeight): self
    {
        $aspectRatio = $this->getAspectRatio();

        if ($targetWidth / $targetHeight > $aspectRatio) {
            $newWidth = (int) round($targetHeight * $aspectRatio);
            $newHeight = $targetHeight;
        } else {
            $newWidth = $targetWidth;
            $newHeight = (int) round($targetWidth / $aspectRatio);
        }

        return new self(max(1, $newWidth), max(1, $newHeight));
    }

    /**
     * Calculate dimensions when scaling by width or height while preserving aspect ratio.
     */
    public function calculateProportionalDimensions(?int $width, ?int $height): self
    {
        if ($width === null && $height === null) {
            return new self($this->width, $this->height);
        }

        $aspectRatio = $this->getAspectRatio();

        if ($width !== null && $height === null) {
            return new self($width, max(1, (int) round($width / $aspectRatio)));
        }

        if ($width === null && $height !== null) {
            return new self(max(1, (int) round($height * $aspectRatio)), $height);
        }

        return new self($width, $height);
    }
}

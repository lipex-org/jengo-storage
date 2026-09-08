<?php

declare(strict_types=1);

namespace Jengo\Storage\Contracts;

interface ImageTransformerInterface
{
    /**
     * Load an image from binary string or resource.
     */
    public function load(string $binary): static;

    /**
     * Resize the image to given dimensions.
     */
    public function resize(int $width, ?int $height = null, bool $preserveAspect = true): static;

    /**
     * Crop the image to given dimensions and offset.
     */
    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): static;

    /**
     * Crop and resize to best fit within given dimensions without distortion.
     */
    public function fit(int $width, int $height, string $position = 'center'): static;

    /**
     * Apply a watermark onto the image.
     */
    public function watermark(string $watermarkBinary, string $position = 'bottom-right', int $opacity = 100): static;

    /**
     * Set target output format to WebP.
     */
    public function toWebp(int $quality = 80): static;

    /**
     * Set target output format to AVIF.
     */
    public function toAvif(int $quality = 80): static;

    /**
     * Set target output format to JPEG.
     */
    public function toJpeg(int $quality = 85): static;

    /**
     * Set target output format to PNG.
     */
    public function toPng(): static;

    /**
     * Set compression quality (1-100).
     */
    public function quality(int $quality): static;

    /**
     * Encode and return the transformed image binary.
     */
    public function encode(?string $format = null, ?int $quality = null): string;

    /**
     * Get width in pixels.
     */
    public function getWidth(): int;

    /**
     * Get height in pixels.
     */
    public function getHeight(): int;
}

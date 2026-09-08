<?php

declare(strict_types=1);

namespace Jengo\Storage\Images\Drivers;

use Imagick;
use Jengo\Storage\Contracts\ImageTransformerInterface;
use Jengo\Storage\Exceptions\ImageProcessingException;
use Jengo\Storage\Images\Dimensions;

class ImagickDriver implements ImageTransformerInterface
{
    protected ?Imagick $imagick = null;
    protected string $format = 'jpeg';
    protected int $quality = 85;

    public function __construct()
    {
        if (! extension_loaded('imagick') || ! class_exists(Imagick::class)) {
            throw ImageProcessingException::driverUnavailable('imagick');
        }
    }

    public function __destruct()
    {
        if ($this->imagick instanceof Imagick) {
            $this->imagick->clear();
            $this->imagick->destroy();
            $this->imagick = null;
        }
    }

    public function load(string $binary): static
    {
        try {
            $im = new Imagick();
            $im->readImageBlob($binary);

            if ($this->imagick instanceof Imagick) {
                $this->imagick->clear();
                $this->imagick->destroy();
            }

            $this->imagick = $im;
        } catch (\Throwable $e) {
            throw ImageProcessingException::decodingFailed();
        }

        return $this;
    }

    public function getWidth(): int
    {
        $this->ensureLoaded();
        return $this->imagick->getImageWidth();
    }

    public function getHeight(): int
    {
        $this->ensureLoaded();
        return $this->imagick->getImageHeight();
    }

    public function resize(int $width, ?int $height = null, bool $preserveAspect = true): static
    {
        $this->ensureLoaded();
        $dim = new Dimensions($this->getWidth(), $this->getHeight());

        if ($preserveAspect) {
            $target = $dim->calculateProportionalDimensions($width, $height);
            $targetWidth = $target->width;
            $targetHeight = $target->height;
        } else {
            $targetWidth = $width;
            $targetHeight = $height ?? $this->getHeight();
        }

        $this->imagick->thumbnailImage($targetWidth, $targetHeight);

        return $this;
    }

    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): static
    {
        $this->ensureLoaded();
        $origWidth = $this->getWidth();
        $origHeight = $this->getHeight();

        $cropX = $x ?? (int) max(0, round(($origWidth - $width) / 2));
        $cropY = $y ?? (int) max(0, round(($origHeight - $height) / 2));

        $cropWidth = min($width, $origWidth - $cropX);
        $cropHeight = min($height, $origHeight - $cropY);

        $this->imagick->cropImage($cropWidth, $cropHeight, $cropX, $cropY);
        $this->imagick->setImagePage(0, 0, 0, 0);

        return $this;
    }

    public function fit(int $width, int $height, string $position = 'center'): static
    {
        $this->ensureLoaded();
        $this->imagick->cropThumbnailImage($width, $height);
        $this->imagick->setImagePage(0, 0, 0, 0);

        return $this;
    }

    public function watermark(string $watermarkBinary, string $position = 'bottom-right', int $opacity = 100): static
    {
        $this->ensureLoaded();
        $wm = new Imagick();
        $wm->readImageBlob($watermarkBinary);

        $wmWidth = $wm->getImageWidth();
        $wmHeight = $wm->getImageHeight();
        $padding = 20;

        $x = match ($position) {
            'top-left', 'left', 'bottom-left' => $padding,
            'top-right', 'right', 'bottom-right' => $this->getWidth() - $wmWidth - $padding,
            default => (int) round(($this->getWidth() - $wmWidth) / 2),
        };

        $y = match ($position) {
            'top-left', 'top', 'top-right' => $padding,
            'bottom-left', 'bottom', 'bottom-right' => $this->getHeight() - $wmHeight - $padding,
            default => (int) round(($this->getHeight() - $wmHeight) / 2),
        };

        if ($opacity < 100) {
            $wm->evaluateImage(Imagick::EVALUATE_MULTIPLY, $opacity / 100, Imagick::CHANNEL_ALPHA);
        }

        $this->imagick->compositeImage($wm, Imagick::COMPOSITE_OVER, max(0, $x), max(0, $y));
        $wm->clear();
        $wm->destroy();

        return $this;
    }

    public function toWebp(int $quality = 80): static
    {
        $this->format = 'webp';
        $this->quality = $quality;
        return $this;
    }

    public function toAvif(int $quality = 80): static
    {
        $this->format = 'avif';
        $this->quality = $quality;
        return $this;
    }

    public function toJpeg(int $quality = 85): static
    {
        $this->format = 'jpeg';
        $this->quality = $quality;
        return $this;
    }

    public function toPng(): static
    {
        $this->format = 'png';
        return $this;
    }

    public function quality(int $quality): static
    {
        $this->quality = max(1, min(100, $quality));
        return $this;
    }

    public function encode(?string $format = null, ?int $quality = null): string
    {
        $this->ensureLoaded();
        $format = strtolower($format ?? $this->format);
        $quality = $quality ?? $this->quality;

        $this->imagick->setImageFormat($format);
        $this->imagick->setImageCompressionQuality($quality);

        return $this->imagick->getImageBlob();
    }

    protected function ensureLoaded(): void
    {
        if (! $this->imagick instanceof Imagick) {
            throw new ImageProcessingException("No image is currently loaded in the transformer.");
        }
    }
}

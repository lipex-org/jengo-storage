<?php

declare(strict_types=1);

namespace Jengo\Storage\Images\Drivers;

use GdImage;
use Jengo\Storage\Contracts\ImageTransformerInterface;
use Jengo\Storage\Exceptions\ImageProcessingException;
use Jengo\Storage\Images\Dimensions;

class GdDriver implements ImageTransformerInterface
{
    protected ?GdImage $image = null;
    protected string $format = 'jpeg';
    protected int $quality = 85;

    public function __construct()
    {
        if (! extension_loaded('gd')) {
            throw ImageProcessingException::driverUnavailable('gd');
        }
    }

    public function __destruct()
    {
        if ($this->image instanceof GdImage) {
            imagedestroy($this->image);
            $this->image = null;
        }
    }

    public function load(string $binary): static
    {
        $loaded = @imagecreatefromstring($binary);
        if ($loaded === false) {
            throw ImageProcessingException::decodingFailed();
        }

        if ($this->image instanceof GdImage) {
            imagedestroy($this->image);
        }

        $this->image = $loaded;
        imagepalettetotruecolor($this->image);
        imagealphablending($this->image, true);
        imagesavealpha($this->image, true);

        return $this;
    }

    public function getWidth(): int
    {
        $this->ensureLoaded();
        return imagesx($this->image);
    }

    public function getHeight(): int
    {
        $this->ensureLoaded();
        return imagesy($this->image);
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

        $canvas = $this->createTransparentCanvas($targetWidth, $targetHeight);

        imagecopyresampled(
            $canvas,
            $this->image,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $this->getWidth(),
            $this->getHeight()
        );

        imagedestroy($this->image);
        $this->image = $canvas;

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

        $canvas = $this->createTransparentCanvas($cropWidth, $cropHeight);

        imagecopyresampled(
            $canvas,
            $this->image,
            0, 0,
            $cropX, $cropY,
            $cropWidth, $cropHeight,
            $cropWidth, $cropHeight
        );

        imagedestroy($this->image);
        $this->image = $canvas;

        return $this;
    }

    public function fit(int $width, int $height, string $position = 'center'): static
    {
        $this->ensureLoaded();
        $origWidth = $this->getWidth();
        $origHeight = $this->getHeight();

        $origAspect = $origWidth / $origHeight;
        $targetAspect = $width / $height;

        if ($origAspect > $targetAspect) {
            // Original is wider: fit height and crop width
            $scale = $height / $origHeight;
            $scaledWidth = (int) round($origWidth * $scale);
            $scaledHeight = $height;
            $this->resize($scaledWidth, $scaledHeight, false);

            $cropX = match ($position) {
                'left' => 0,
                'right' => $scaledWidth - $width,
                default => (int) round(($scaledWidth - $width) / 2),
            };

            return $this->crop($width, $height, $cropX, 0);
        }

        // Original is taller: fit width and crop height
        $scale = $width / $origWidth;
        $scaledWidth = $width;
        $scaledHeight = (int) round($origHeight * $scale);
        $this->resize($scaledWidth, $scaledHeight, false);

        $cropY = match ($position) {
            'top' => 0,
            'bottom' => $scaledHeight - $height,
            default => (int) round(($scaledHeight - $height) / 2),
        };

        return $this->crop($width, $height, 0, $cropY);
    }

    public function watermark(string $watermarkBinary, string $position = 'bottom-right', int $opacity = 100): static
    {
        $this->ensureLoaded();
        $wm = @imagecreatefromstring($watermarkBinary);
        if ($wm === false) {
            throw ImageProcessingException::decodingFailed();
        }

        imagepalettetotruecolor($wm);
        imagealphablending($wm, true);
        imagesavealpha($wm, true);

        $wmWidth = imagesx($wm);
        $wmHeight = imagesy($wm);
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

        $x = max(0, $x);
        $y = max(0, $y);

        if ($opacity >= 100) {
            imagecopy($this->image, $wm, $x, $y, 0, 0, $wmWidth, $wmHeight);
        } else {
            imagecopymerge($this->image, $wm, $x, $y, 0, 0, $wmWidth, $wmHeight, max(0, min(100, $opacity)));
        }

        imagedestroy($wm);

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

        ob_start();

        match ($format) {
            'webp' => imagewebp($this->image, null, $quality),
            'avif' => function_exists('imageavif')
                ? imageavif($this->image, null, $quality)
                : throw ImageProcessingException::unsupportedFormat('avif not compiled in GD'),
            'png'  => imagepng($this->image, null, (int) round((100 - $quality) / 10)),
            'jpeg', 'jpg' => imagejpeg($this->image, null, $quality),
            'gif'  => imagegif($this->image),
            default => throw ImageProcessingException::unsupportedFormat($format),
        };

        return (string) ob_get_clean();
    }

    protected function createTransparentCanvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);
        imagealphablending($canvas, true);

        return $canvas;
    }

    protected function ensureLoaded(): void
    {
        if (! $this->image instanceof GdImage) {
            throw new ImageProcessingException("No image is currently loaded in the transformer.");
        }
    }
}

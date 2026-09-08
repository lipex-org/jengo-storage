<?php

declare(strict_types=1);

namespace Jengo\Storage\Images;

use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Contracts\ImageTransformerInterface;
use Jengo\Storage\Images\Drivers\GdDriver;
use Jengo\Storage\Images\Drivers\ImagickDriver;

class ImagePipeline
{
    protected ?ImageTransformerInterface $transformer = null;
    protected ?string $binary = null;
    protected bool $loaded = false;

    public function __construct(
        protected ?FilesystemInterface $filesystem = null,
        protected ?string $sourcePath = null,
        ?string $binary = null,
        protected string $driver = 'gd'
    ) {
        if ($binary !== null) {
            $this->binary = $binary;
        }
    }

    public static function fromBinary(string $binary, string $driver = 'gd'): self
    {
        return new self(null, null, $binary, $driver);
    }

    public function resize(int $width, ?int $height = null, bool $preserveAspect = true): static
    {
        $this->ensureLoaded();
        $this->getTransformer()->resize($width, $height, $preserveAspect);
        return $this;
    }

    public function crop(int $width, int $height, ?int $x = null, ?int $y = null): static
    {
        $this->ensureLoaded();
        $this->getTransformer()->crop($width, $height, $x, $y);
        return $this;
    }

    public function fit(int $width, int $height, string $position = 'center'): static
    {
        $this->ensureLoaded();
        $this->getTransformer()->fit($width, $height, $position);
        return $this;
    }

    public function watermark(string $watermarkPathOrBinary, string $position = 'bottom-right', int $opacity = 100): static
    {
        $this->ensureLoaded();

        $watermarkBinary = $watermarkPathOrBinary;
        if ($this->filesystem !== null && $this->filesystem->exists($watermarkPathOrBinary)) {
            $watermarkBinary = $this->filesystem->get($watermarkPathOrBinary);
        } elseif (is_file($watermarkPathOrBinary)) {
            $watermarkBinary = (string) file_get_contents($watermarkPathOrBinary);
        }

        $this->getTransformer()->watermark($watermarkBinary, $position, $opacity);
        return $this;
    }

    public function toWebp(int $quality = 80): static
    {
        $this->getTransformer()->toWebp($quality);
        return $this;
    }

    public function toAvif(int $quality = 80): static
    {
        $this->getTransformer()->toAvif($quality);
        return $this;
    }

    public function toJpeg(int $quality = 85): static
    {
        $this->getTransformer()->toJpeg($quality);
        return $this;
    }

    public function toPng(): static
    {
        $this->getTransformer()->toPng();
        return $this;
    }

    public function quality(int $quality): static
    {
        $this->getTransformer()->quality($quality);
        return $this;
    }

    public function getWidth(): int
    {
        $this->ensureLoaded();
        return $this->getTransformer()->getWidth();
    }

    public function getHeight(): int
    {
        $this->ensureLoaded();
        return $this->getTransformer()->getHeight();
    }

    /**
     * Encode the transformed image into binary string.
     */
    public function encode(?string $format = null, ?int $quality = null): string
    {
        $this->ensureLoaded();
        return $this->getTransformer()->encode($format, $quality);
    }

    /**
     * Save the transformed image to the filesystem.
     */
    public function save(string $destinationPath, ?string $format = null, ?int $quality = null, ?string $visibility = null): bool
    {
        if ($this->filesystem === null) {
            throw new \RuntimeException("Cannot save image: No filesystem instance was provided.");
        }

        $encoded = $this->encode($format, $quality);

        return $this->filesystem->put($destinationPath, $encoded, $visibility);
    }

    /**
     * Generate responsive variants for responsive images (e.g. srcset).
     *
     * @param array<string, int> $breakpoints e.g. ['sm' => 640, 'md' => 1024, 'lg' => 1920]
     * @return array<string, string> Map of breakpoint names to saved file paths
     */
    public function generateResponsiveVariants(
        string $destinationDir,
        array $breakpoints,
        string $format = 'webp',
        int $quality = 80,
        ?string $visibility = null
    ): array {
        if ($this->filesystem === null) {
            throw new \RuntimeException("Cannot save responsive variants: No filesystem instance was provided.");
        }

        $this->ensureLoaded();
        $destinationDir = trim($destinationDir, '/');
        $results = [];

        // Save original binary to re-clone for each breakpoint
        $originalBinary = $this->binary ?? ($this->sourcePath ? $this->filesystem->get($this->sourcePath) : '');

        foreach ($breakpoints as $name => $width) {
            $clone = (new self($this->filesystem, null, $originalBinary, $this->driver))
                ->resize((int) $width);

            match ($format) {
                'webp' => $clone->toWebp($quality),
                'avif' => $clone->toAvif($quality),
                'png'  => $clone->toPng(),
                default=> $clone->toJpeg($quality),
            };

            $filename = "{$destinationDir}/variant_{$name}_{$width}.{$format}";
            $clone->save($filename, $format, $quality, $visibility);
            $results[$name] = $filename;
        }

        return $results;
    }

    protected function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        if ($this->binary === null && $this->filesystem !== null && $this->sourcePath !== null) {
            $this->binary = $this->filesystem->get($this->sourcePath);
        }

        if ($this->binary === null) {
            throw new \RuntimeException("No image source binary or valid path provided to ImagePipeline.");
        }

        $this->getTransformer()->load($this->binary);
        $this->loaded = true;
    }

    public function getTransformer(): ImageTransformerInterface
    {
        if ($this->transformer === null) {
            $this->transformer = match (strtolower($this->driver)) {
                'imagick' => new ImagickDriver(),
                default   => new GdDriver(),
            };
        }

        return $this->transformer;
    }
}

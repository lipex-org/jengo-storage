<?php

declare(strict_types=1);

namespace Jengo\Storage\Jobs;

use Jengo\Queues\Traits\Queueable;
use Jengo\Storage\Contracts\FilesystemInterface;
use Jengo\Storage\Images\ImagePipeline;

class GenerateResponsiveVariantsJob
{
    use Queueable;

    public function __construct(
        public FilesystemInterface $filesystem,
        public string $binary,
        public string $destinationDir,
        public array $breakpoints,
        public string $format = 'webp',
        public int $quality = 80,
        public ?string $visibility = null,
        public ?string $driver = null
    ) {
    }

    public function handle(): void
    {
        $pipeline = new ImagePipeline($this->filesystem, null, $this->binary, $this->driver);
        $pipeline->generateResponsiveVariants(
            $this->destinationDir,
            $this->breakpoints,
            $this->format,
            $this->quality,
            $this->visibility
        );
    }
}

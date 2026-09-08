<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Storage\Drivers\MemoryDriver;
use Jengo\Storage\Images\ImagePipeline;
use PHPUnit\Framework\TestCase;

class ImagePipelineTest extends TestCase
{
    protected string $sampleImageBinary;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a 200x100 test image in memory
        $im = imagecreatetruecolor(200, 100);
        $blue = imagecolorallocate($im, 0, 100, 200);
        imagefilledrectangle($im, 0, 0, 200, 100, $blue);

        ob_start();
        imagepng($im);
        $this->sampleImageBinary = (string) ob_get_clean();
        imagedestroy($im);
    }

    public function test_loads_image_and_inspects_dimensions(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);

        $this->assertSame(200, $pipeline->getWidth());
        $this->assertSame(100, $pipeline->getHeight());
    }

    public function test_resizes_image_proportionally(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $pipeline->resize(100); // Should scale height from 100 to 50

        $this->assertSame(100, $pipeline->getWidth());
        $this->assertSame(50, $pipeline->getHeight());
    }

    public function test_crops_image(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $pipeline->crop(50, 50);

        $this->assertSame(50, $pipeline->getWidth());
        $this->assertSame(50, $pipeline->getHeight());
    }

    public function test_fits_image(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $pipeline->fit(80, 80);

        $this->assertSame(80, $pipeline->getWidth());
        $this->assertSame(80, $pipeline->getHeight());
    }

    public function test_encodes_to_webp(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $webpBinary = $pipeline->toWebp(80)->encode();

        $this->assertNotEmpty($webpBinary);
        // Check WebP RIFF header
        $this->assertStringStartsWith('RIFF', $webpBinary);
        $this->assertStringContainsString('WEBP', substr($webpBinary, 8, 8));
    }

    public function test_encodes_to_jpeg_and_png(): void
    {
        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);

        $jpegBinary = $pipeline->toJpeg(90)->encode();
        $this->assertNotEmpty($jpegBinary);

        $pngBinary = $pipeline->toPng()->encode();
        $this->assertNotEmpty($pngBinary);
        // PNG magic number "\x89PNG"
        $this->assertStringStartsWith("\x89PNG", $pngBinary);
    }

    public function test_applies_watermark(): void
    {
        // Create 20x20 watermark
        $wm = imagecreatetruecolor(20, 20);
        $red = imagecolorallocate($wm, 255, 0, 0);
        imagefilledrectangle($wm, 0, 0, 20, 20, $red);
        ob_start();
        imagepng($wm);
        $wmBinary = (string) ob_get_clean();
        imagedestroy($wm);

        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $pipeline->watermark($wmBinary, 'bottom-right', 80);

        $encoded = $pipeline->encode('png');
        $this->assertNotEmpty($encoded);
    }

    public function test_generates_responsive_variants_on_filesystem(): void
    {
        $disk = MemoryDriver::create();
        $disk->put('original.png', $this->sampleImageBinary);

        $variants = $disk->image('original.png')->generateResponsiveVariants(
            'thumbnails',
            ['sm' => 100, 'xs' => 50],
            format: 'webp',
            quality: 85
        );

        $this->assertArrayHasKey('sm', $variants);
        $this->assertArrayHasKey('xs', $variants);

        $this->assertTrue($disk->exists('thumbnails/variant_sm_100.webp'));
        $this->assertTrue($disk->exists('thumbnails/variant_xs_50.webp'));
    }

    public function test_auto_detects_driver_at_runtime(): void
    {
        $driver = ImagePipeline::determineDriver();
        $this->assertContains($driver, ['gd', 'imagick']);

        $pipeline = ImagePipeline::fromBinary($this->sampleImageBinary);
        $this->assertInstanceOf(\Jengo\Storage\Contracts\ImageTransformerInterface::class, $pipeline->getTransformer());
    }
}

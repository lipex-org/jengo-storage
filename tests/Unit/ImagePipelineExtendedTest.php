<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Storage\Config\Storage as StorageConfig;
use Jengo\Storage\Exceptions\ImageProcessingException;
use Jengo\Storage\FilesystemManager;
use Jengo\Storage\Images\ImagePipeline;
use Jengo\Storage\Storage;
use PHPUnit\Framework\TestCase;

class ImagePipelineExtendedTest extends TestCase
{
    private function createSamplePng(int $width = 100, int $height = 100): string
    {
        $im = imagecreatetruecolor($width, $height);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefill($im, 0, 0, $red);

        ob_start();
        imagepng($im);
        $binary = ob_get_clean();
        imagedestroy($im);

        return (string) $binary;
    }

    public function test_image_pipeline_from_binary_dimensions_and_resize(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $binary = $this->createSamplePng(200, 100);
        $pipeline = ImagePipeline::fromBinary($binary, 'gd');

        $this->assertSame(200, $pipeline->getWidth());
        $this->assertSame(100, $pipeline->getHeight());

        $pipeline->resize(100, 50);
        $this->assertSame(100, $pipeline->getWidth());
        $this->assertSame(50, $pipeline->getHeight());
    }

    public function test_image_pipeline_crop_and_fit(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $binary = $this->createSamplePng(200, 200);
        $pipeline = ImagePipeline::fromBinary($binary, 'gd');

        $pipeline->crop(50, 50, 10, 10);
        $this->assertSame(50, $pipeline->getWidth());
        $this->assertSame(50, $pipeline->getHeight());

        $fitPipeline = ImagePipeline::fromBinary($binary, 'gd');
        $fitPipeline->fit(80, 80, 'center');
        $this->assertSame(80, $fitPipeline->getWidth());
        $this->assertSame(80, $fitPipeline->getHeight());
    }

    public function test_image_pipeline_format_conversions(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $binary = $this->createSamplePng(50, 50);

        $jpegPipeline = ImagePipeline::fromBinary($binary, 'gd')->toJpeg(90);
        $jpegEncoded = $jpegPipeline->encode();
        $this->assertNotEmpty($jpegEncoded);

        $pngPipeline = ImagePipeline::fromBinary($binary, 'gd')->toPng();
        $pngEncoded = $pngPipeline->encode();
        $this->assertNotEmpty($pngEncoded);

        if (function_exists('imagewebp')) {
            $webpPipeline = ImagePipeline::fromBinary($binary, 'gd')->toWebp(75);
            $webpEncoded = $webpPipeline->encode();
            $this->assertNotEmpty($webpEncoded);
        }
    }

    public function test_save_and_responsive_variants(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }

        $config = new StorageConfig();
        $config->default = 'memory';
        $manager = new FilesystemManager($config);
        $fakeDisk = $manager->fake('memory');

        $binary = $this->createSamplePng(400, 400);
        $fakeDisk->put('photos/hero.png', $binary);

        $pipeline = $fakeDisk->image('photos/hero.png');
        $saved = $pipeline->resize(200)->save('photos/hero_thumb.png');
        $this->assertTrue($saved);
        $this->assertTrue($fakeDisk->exists('photos/hero_thumb.png'));

        // Test responsive variants
        $variants = $pipeline->generateResponsiveVariants('photos/responsive', [
            'sm' => 100,
            'md' => 200,
        ], 'png');

        $this->assertCount(2, $variants);
        $this->assertArrayHasKey('sm', $variants);
        $this->assertArrayHasKey('md', $variants);
        $this->assertTrue($fakeDisk->exists($variants['sm']));
        $this->assertTrue($fakeDisk->exists($variants['md']));
    }

    public function test_throws_exception_on_unsupported_driver(): void
    {
        $this->expectException(ImageProcessingException::class);
        $pipeline = ImagePipeline::fromBinary($this->createSamplePng(), 'unsupported_driver');
        $pipeline->getWidth();
    }
}

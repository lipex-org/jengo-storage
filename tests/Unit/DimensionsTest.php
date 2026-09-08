<?php

declare(strict_types=1);

namespace Tests\Unit;

use Jengo\Storage\Images\Dimensions;
use PHPUnit\Framework\TestCase;

class DimensionsTest extends TestCase
{
    public function test_calculates_aspect_ratio(): void
    {
        $dim = new Dimensions(1920, 1080);
        $this->assertEqualsWithDelta(1.7777, $dim->getAspectRatio(), 0.001);
    }

    public function test_calculates_fit_dimensions_for_wide_image(): void
    {
        $dim = new Dimensions(800, 400); // 2:1
        $fit = $dim->calculateFitDimensions(400, 400);

        $this->assertSame(400, $fit->width);
        $this->assertSame(200, $fit->height);
    }

    public function test_calculates_fit_dimensions_for_tall_image(): void
    {
        $dim = new Dimensions(400, 800); // 1:2
        $fit = $dim->calculateFitDimensions(400, 400);

        $this->assertSame(200, $fit->width);
        $this->assertSame(400, $fit->height);
    }

    public function test_calculates_proportional_dimensions_by_width(): void
    {
        $dim = new Dimensions(1000, 500); // 2:1
        $scaled = $dim->calculateProportionalDimensions(500, null);

        $this->assertSame(500, $scaled->width);
        $this->assertSame(250, $scaled->height);
    }

    public function test_calculates_proportional_dimensions_by_height(): void
    {
        $dim = new Dimensions(1000, 500); // 2:1
        $scaled = $dim->calculateProportionalDimensions(null, 250);

        $this->assertSame(500, $scaled->width);
        $this->assertSame(250, $scaled->height);
    }
}

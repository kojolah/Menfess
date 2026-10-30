<?php

namespace Tests\Unit;

use App\Services\ImagickMenfessImageRenderer;
use RuntimeException;
use Tests\TestCase;

class ImagickMenfessImageRendererTest extends TestCase
{
    public function test_instagram_portrait_dimensions_are_fixed_at_four_to_five(): void
    {
        $this->assertSame(['width' => 1080, 'height' => 1350], ImagickMenfessImageRenderer::dimensionsFor('instagram_portrait'));
    }

    public function test_other_platform_dimensions_remain_unchanged(): void
    {
        $this->assertSame(['width' => 1080, 'height' => 1080], ImagickMenfessImageRenderer::dimensionsFor('instagram_square'));
        $this->assertSame(['width' => 1200, 'height' => 675], ImagickMenfessImageRenderer::dimensionsFor('x_landscape'));
    }

    public function test_imagick_requirement_is_reported_clearly_when_extension_is_missing(): void
    {
        if (extension_loaded('imagick')) {
            $this->assertNull(app(ImagickMenfessImageRenderer::class)->assertAvailable());

            return;
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PHP Imagick tidak tersedia');

        app(ImagickMenfessImageRenderer::class)->assertAvailable();
    }
}

<?php

namespace GuiBranco\GstracciniBotWebsite\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../src/includes/color-utils.php';

class ColorUtilsTest extends TestCase
{
    public function testLuminanceReturnsBlackTextForLightBackground(): void
    {
        $this->assertSame('#000', luminance('ffffff'));
        $this->assertSame('#000', luminance('ffff00'));
    }

    public function testLuminanceReturnsWhiteTextForDarkBackground(): void
    {
        $this->assertSame('#fff', luminance('000000'));
        $this->assertSame('#fff', luminance('0d1117'));
    }

    public function testLuminanceThrowsForInvalidColorFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        luminance('not-a-color');
    }

    public function testLuminanceThrowsForColorWithWrongLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        luminance('fff');
    }
}

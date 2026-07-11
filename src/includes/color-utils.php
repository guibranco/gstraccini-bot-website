<?php
/**
 * Color helper functions shared by pages that render GitHub label badges.
 */

/**
 * Picks a readable text color (black or white) for a background hex color,
 * based on the YIQ perceived brightness formula.
 *
 * @param  string $color 6-digit hex color, without the leading "#".
 * @return string        "#000" or "#fff".
 * @throws InvalidArgumentException When $color is not a 6-digit hex color.
 */
function luminance($color)
{
    if (!preg_match('/^[0-9A-Fa-f]{6}$/', $color)) {
        throw new InvalidArgumentException('Invalid color format. Expected 6-digit hex color.');
    }
    $red = hexdec(substr($color, 0, 2));
    $green = hexdec(substr($color, 2, 2));
    $blue = hexdec(substr($color, 4, 2));
    $yiq = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;
    return ($yiq >= 128) ? '#000' : '#fff';
}

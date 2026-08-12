<?php
declare(strict_types=1);

/**
 * ExchangePro PWA icon generator.
 * Draws the brand mark (gradient rounded square + "EX" monogram) with GD
 * and writes the PNG set used by manifest.webmanifest + apple-touch-icon.
 *
 * Usage: php scripts/gen_icons.php
 * Output: public/assets/img/{icon-192,icon-512,icon-512-maskable,apple-touch-icon,favicon-32}.png
 */

$outDir = dirname(__DIR__) . '/public/assets/img';

// ---- brand gradient (matches --primary-grad in app.css) ----
$c1 = [37, 99, 235];    // #2563eb
$c2 = [79, 70, 229];    // #4f46e5

/** Linear interpolation between two RGB colors. */
function lerp(array $a, array $b, float $t): array
{
    return [
        (int)round($a[0] + ($b[0] - $a[0]) * $t),
        (int)round($a[1] + ($b[1] - $a[1]) * $t),
        (int)round($a[2] + ($b[2] - $a[2]) * $t),
    ];
}

/** Find a usable TTF font (bold preferred) for the monogram. */
function findFont(): ?string
{
    $candidates = [
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/calibrib.ttf',
        'C:/Windows/Fonts/tahomabd.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    ];
    foreach ($candidates as $f) {
        if (is_file($f)) return $f;
    }
    // Scan Windows fonts dir for any bold ttf
    $dir = 'C:/Windows/Fonts';
    if (is_dir($dir)) {
        foreach (scandir($dir) as $name) {
            if (preg_match('/^.*(?:b|B)old.*\.ttf$/', $name) && is_file("$dir/$name")) {
                return "$dir/$name";
            }
        }
        foreach (scandir($dir) as $name) {
            if (str_ends_with(strtolower($name), '.ttf') && is_file("$dir/$name")) {
                return "$dir/$name";
            }
        }
    }
    return null;
}

/**
 * Build one icon canvas.
 * @param int   $size      canvas size (square)
 * @param float $radius    corner radius as fraction of size (0 = full bleed)
 * @param float $textScale font size as fraction of size
 * @param bool  $maskable  keep the monogram inside the 80% safe zone
 */
function buildIcon(int $size, float $radius, float $textScale, bool $maskable): GdImage
{
    global $c1, $c2;

    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);

    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefill($img, 0, 0, $transparent);

    // ---- diagonal gradient fill ----
    $grad = imagecreatetruecolor($size, $size);
    imagealphablending($grad, true);
    for ($y = 0; $y < $size; $y++) {
        $t = $y / max(1, $size - 1);
        for ($x = 0; $x < $size; $x++) {
            $t2 = ($x + $y) / (2 * max(1, $size - 1));
            [$r, $g, $b] = lerp($c1, $c2, $t2);
            $col = imagecolorallocate($grad, $r, $g, $b);
            imagesetpixel($grad, $x, $y, $col);
        }
    }

    // ---- rounded-corner alpha mask ----
    $mask = imagecreatetruecolor($size, $size);
    imagealphablending($mask, false);
    $opaque = imagecolorallocate($mask, 255, 255, 255);
    $clear  = imagecolorallocate($mask, 0, 0, 0);
    imagefilledrectangle($mask, 0, 0, $size, $size, $clear);
    $r = (int)round($radius * $size);
    imagefilledrectangle($mask, $r, 0, $size - $r - 1, $size, $opaque);          // body
    imagefilledrectangle($mask, 0, $r, $size, $size - $r - 1, $opaque);
    imagefilledellipse($mask, $r, $r, $r * 2, $r * 2, $opaque);                 // corners
    imagefilledellipse($mask, $size - $r - 1, $r, $r * 2, $r * 2, $opaque);
    imagefilledellipse($mask, $r, $size - $r - 1, $r * 2, $r * 2, $opaque);
    imagefilledellipse($mask, $size - $r - 1, $size - $r - 1, $r * 2, $r * 2, $opaque);

    // ---- apply mask to gradient ----
    for ($y = 0; $y < $size; $y++) {
        for ($x = 0; $x < $size; $x++) {
            $px = imagecolorat($mask, $x, $y);
            if (($px & 0xFF) < 128) {
                imagesetpixel($grad, $x, $y, $transparent);
            }
        }
    }
    imagecopy($img, $grad, 0, 0, 0, 0, $size, $size);

    // ---- subtle top gloss ----
    imagealphablending($img, true);
    $gloss = imagecolorallocatealpha($img, 255, 255, 255, 105);
    $gy = (int)round($size * 0.02);
    $gh = (int)round($size * 0.16);
    for ($y = $gy; $y < $gy + $gh; $y++) {
        $alpha = 110 - (int)(($y - $gy) / max(1, $gh) * 80);
        $col = imagecolorallocatealpha($img, 255, 255, 255, max(0, $alpha));
        imagefilledrectangle($img, (int)round($size * 0.1), $y, $size - (int)round($size * 0.1), $y, $col);
    }

    // ---- "EX" monogram ----
    $font = findFont();
    $cx = (int)round($size / 2);
    $cy = (int)round($size / 2);
    $fontSize = (int)round($size * $textScale);
    $white = imagecolorallocate($img, 255, 255, 255);

    if ($font !== null) {
        $box = imagettfbbox($fontSize, 0, $font, 'EX');
        $tw = abs($box[2] - $box[0]);
        $th = abs($box[5] - $box[1]);
        imagettftext($img, $fontSize, 0, (int)round($cx - $tw / 2), (int)round($cy + $th / 2 - $fontSize * 0.08), $white, $font, 'EX');
    } else {
        // Degenerate fallback: two letter-shaped blocks so the icon still brands
        $bw = (int)round($size * 0.14);
        $bh = (int)round($size * 0.34);
        $gap = (int)round($size * 0.09);
        $x0 = $cx - $bw - (int)round($gap / 2);
        $y0 = $cy - (int)round($bh / 2);
        imagefilledrectangle($img, $x0, $y0, $x0 + $bw, $y0 + $bh, $white);
        imagefilledrectangle($img, $x0, $y0, $x0 + $bw, $y0 + (int)round($bh * 0.5), $white);
        imagefilledrectangle($img, $x0 + $bw + $gap, $y0, $x0 + $bw + $gap + $bw, $y0 + $bh, $white);
        imagefilledrectangle($img, $x0 + $bw + $gap, $y0, $x0 + $bw + $gap + $bw, $y0 + (int)round($bh * 0.5), $white);
    }

    return $img;
}

function saveIcon(GdImage $img, string $path): void
{
    $dir = dirname($path);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    imagepng($img, $path);
    imagedestroy($img);
    echo "  wrote " . basename($path) . " (" . filesize($path) . " bytes)\n";
}

echo "Generating PWA icons...\n";
$fontFound = findFont();
echo $fontFound ? "  font: " . basename($fontFound) . "\n" : "  font: none (fallback shapes)\n";

saveIcon(buildIcon(192, 0.22, 0.30, false), "$outDir/icon-192.png");
saveIcon(buildIcon(512, 0.22, 0.30, false), "$outDir/icon-512.png");
saveIcon(buildIcon(512, 0.0, 0.20, true),  "$outDir/icon-512-maskable.png");
saveIcon(buildIcon(180, 0.22, 0.30, false), "$outDir/apple-touch-icon.png");
saveIcon(buildIcon(32,  0.24, 0.44, false), "$outDir/favicon-32.png");

echo "Done.\n";

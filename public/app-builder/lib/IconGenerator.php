<?php

class IconGenerator
{
    /**
     * Load image resource from file path or binary string.
     * Supports PNG, JPEG, WEBP.
     */
    public static function loadImage(string $filePathOrData): ?GdImage
    {
        if (is_file($filePathOrData)) {
            $data = file_get_contents($filePathOrData);
        } else {
            $data = $filePathOrData;
        }

        if (empty($data)) {
            return null;
        }

        $im = @imagecreatefromstring($data);
        if ($im instanceof GdImage) {
            imagealphablending($im, true);
            imagesavealpha($im, true);
            return $im;
        }

        return null;
    }

    /**
     * Resize image to exact width and height preserving aspect ratio and padding with transparent background.
     */
    public static function resizeSquare(GdImage $src, int $targetSize): GdImage
    {
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $dst = imagecreatetruecolor($targetSize, $targetSize);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetSize, $targetSize, $transparent);
        imagealphablending($dst, true);

        // Fit within square preserving aspect ratio
        if ($srcW >= $srcH) {
            $newW = $targetSize;
            $newH = (int)round(($srcH / $srcW) * $targetSize);
            $dstX = 0;
            $dstY = (int)round(($targetSize - $newH) / 2);
        } else {
            $newH = $targetSize;
            $newW = (int)round(($srcW / $srcH) * $targetSize);
            $dstY = 0;
            $dstX = (int)round(($targetSize - $newW) / 2);
        }

        imagecopyresampled($dst, $src, $dstX, $dstY, 0, 0, $newW, $newH, $srcW, $srcH);
        return $dst;
    }

    /**
     * Export GdImage to PNG binary string.
     */
    public static function toPngBytes(GdImage $im): string
    {
        ob_start();
        imagesavealpha($im, true);
        imagepng($im, null, 9);
        return (string)ob_get_clean();
    }

    /**
     * Create Windows .ICO binary string from an array of [size => PNG bytes].
     */
    public static function createIco(array $pngSizes): string
    {
        $count = count($pngSizes);
        $header = pack('vvv', 0, 1, $count);
        $entries = '';
        $offset = 6 + ($count * 16);
        $data = '';

        foreach ($pngSizes as $size => $pngBytes) {
            $w = ($size >= 256) ? 0 : $size;
            $h = ($size >= 256) ? 0 : $size;
            $len = strlen($pngBytes);
            $entries .= pack('CCCCvvVV', $w, $h, 0, 0, 1, 32, $len, $offset);
            $data .= $pngBytes;
            $offset += $len;
        }

        return $header . $entries . $data;
    }

    /**
     * Generate complete white-label icon and visual assets bundle.
     * Takes optional specific uploads and master logo, auto-generating all missing sizes.
     *
     * @param array $uploadedFiles Map of role => tmp_file_path
     * @param string $outputDir Target directory to write assets into
     * @return array Manifest of generated assets
     */
    public static function generateAllAssets(array $uploadedFiles, string $outputDir, string $primaryHex = '#4F46E5', string $brandText = 'POS'): array
    {
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Subdirectories
        $dirs = [
            $outputDir . '/web',
            $outputDir . '/web/icons',
            $outputDir . '/android/mipmap-mdpi',
            $outputDir . '/android/mipmap-hdpi',
            $outputDir . '/android/mipmap-xhdpi',
            $outputDir . '/android/mipmap-xxhdpi',
            $outputDir . '/android/mipmap-xxxhdpi',
            $outputDir . '/assets/icon',
            $outputDir . '/assets/images',
            $outputDir . '/windows',
            $outputDir . '/ios',
        ];
        foreach ($dirs as $d) {
            if (!is_dir($d)) {
                mkdir($d, 0755, true);
            }
        }

        // Master image priority: app_icon -> main_logo -> splash_logo
        $masterPath = $uploadedFiles['app_icon'] 
            ?? $uploadedFiles['main_logo'] 
            ?? $uploadedFiles['android_icon'] 
            ?? null;

        $masterImg = $masterPath ? self::loadImage($masterPath) : null;
        if (!$masterImg) {
            // Fallback branded placeholder with primary color and brand initial monogram
            $masterImg = imagecreatetruecolor(512, 512);
            imagealphablending($masterImg, false);
            imagesavealpha($masterImg, true);
            $cleanHex = ltrim($primaryHex, '#');
            if (strlen($cleanHex) === 3) {
                $cleanHex = $cleanHex[0].$cleanHex[0].$cleanHex[1].$cleanHex[1].$cleanHex[2].$cleanHex[2];
            }
            $r = hexdec(substr($cleanHex, 0, 2)) ?: 79;
            $g = hexdec(substr($cleanHex, 2, 2)) ?: 70;
            $b = hexdec(substr($cleanHex, 4, 2)) ?: 229;
            $primaryBg = imagecolorallocate($masterImg, $r, $g, $b);
            imagefilledrectangle($masterImg, 0, 0, 512, 512, $primaryBg);

            $initial = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $brandText), 0, 3)) ?: 'POS';
            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($initial);
            $textHeight = imagefontheight($font);
            $scale = 6;
            $scaledW = $textWidth * $scale;
            $scaledH = $textHeight * $scale;
            $x = (int)((512 - $scaledW) / 2);
            $y = (int)((512 - $scaledH) / 2);

            $temp = imagecreatetruecolor($textWidth, $textHeight);
            imagealphablending($temp, false);
            imagesavealpha($temp, true);
            $trans = imagecolorallocatealpha($temp, 0, 0, 0, 127);
            imagefilledrectangle($temp, 0, 0, $textWidth, $textHeight, $trans);
            $white = imagecolorallocate($temp, 255, 255, 255);
            imagestring($temp, $font, 0, 0, $initial, $white);
            imagecopyresampled($masterImg, $temp, max(0, $x), max(0, $y), 0, 0, $scaledW, $scaledH, $textWidth, $textHeight);
            imagedestroy($temp);
        }

        $manifest = [];

        // 1. Android Launcher Icons
        $androidSizes = [
            'mdpi'    => 48,
            'hdpi'    => 72,
            'xhdpi'   => 96,
            'xxhdpi'  => 144,
            'xxxhdpi' => 192,
        ];
        $androidImg = !empty($uploadedFiles['android_icon']) ? self::loadImage($uploadedFiles['android_icon']) : $masterImg;
        foreach ($androidSizes as $density => $sz) {
            $resized = self::resizeSquare($androidImg, $sz);
            $path = "android/mipmap-{$density}/ic_launcher.png";
            file_put_contents($outputDir . '/' . $path, self::toPngBytes($resized));
            $manifest[$path] = true;
        }

        // Flutter root launcher icon (512x512)
        $launcher512 = self::resizeSquare($androidImg, 512);
        file_put_contents($outputDir . '/assets/icon/launcher.png', self::toPngBytes($launcher512));
        $manifest['assets/icon/launcher.png'] = true;

        // 2. Web Icons & Favicon
        $favImg = !empty($uploadedFiles['favicon']) ? self::loadImage($uploadedFiles['favicon']) : $masterImg;
        $fav32 = self::resizeSquare($favImg, 32);
        file_put_contents($outputDir . '/web/favicon.png', self::toPngBytes($fav32));
        $manifest['web/favicon.png'] = true;

        $pwaImg = !empty($uploadedFiles['web_icon']) ? self::loadImage($uploadedFiles['web_icon']) : $masterImg;
        $icon192 = self::resizeSquare($pwaImg, 192);
        $icon512 = self::resizeSquare($pwaImg, 512);
        file_put_contents($outputDir . '/web/icons/Icon-192.png', self::toPngBytes($icon192));
        file_put_contents($outputDir . '/web/icons/Icon-512.png', self::toPngBytes($icon512));
        file_put_contents($outputDir . '/web/icons/Icon-maskable-192.png', self::toPngBytes($icon192));
        file_put_contents($outputDir . '/web/icons/Icon-maskable-512.png', self::toPngBytes($icon512));
        $manifest['web/icons/Icon-192.png'] = true;
        $manifest['web/icons/Icon-512.png'] = true;

        // 3. Windows Icon (.ICO multi-resolution)
        if (!empty($uploadedFiles['windows_icon']) && str_ends_with(strtolower($uploadedFiles['windows_icon']), '.ico')) {
            copy($uploadedFiles['windows_icon'], $outputDir . '/windows/app_icon.ico');
        } else {
            $winImg = !empty($uploadedFiles['windows_icon']) ? self::loadImage($uploadedFiles['windows_icon']) : $masterImg;
            $icoBytes = self::createIco([
                16  => self::toPngBytes(self::resizeSquare($winImg, 16)),
                32  => self::toPngBytes(self::resizeSquare($winImg, 32)),
                48  => self::toPngBytes(self::resizeSquare($winImg, 48)),
                256 => self::toPngBytes(self::resizeSquare($winImg, 256)),
            ]);
            file_put_contents($outputDir . '/windows/app_icon.ico', $icoBytes);
        }
        $manifest['windows/app_icon.ico'] = true;

        // 4. iOS App Icon (1024x1024 master icon)
        $iosImg = !empty($uploadedFiles['ios_icon']) ? self::loadImage($uploadedFiles['ios_icon']) : $masterImg;
        $ios1024 = self::resizeSquare($iosImg, 1024);
        file_put_contents($outputDir . '/ios/Icon-App-1024x1024@1x.png', self::toPngBytes($ios1024));
        $manifest['ios/Icon-App-1024x1024@1x.png'] = true;

        // 5. Main, Light, Dark, and Splash Logos
        $mainLogo = !empty($uploadedFiles['main_logo']) ? self::loadImage($uploadedFiles['main_logo']) : $masterImg;
        file_put_contents($outputDir . '/assets/images/app_logo.png', self::toPngBytes($mainLogo));
        $manifest['assets/images/app_logo.png'] = true;

        $lightLogo = !empty($uploadedFiles['light_logo']) ? self::loadImage($uploadedFiles['light_logo']) : $mainLogo;
        file_put_contents($outputDir . '/assets/images/app_logo_light.png', self::toPngBytes($lightLogo));
        $manifest['assets/images/app_logo_light.png'] = true;

        $darkLogo = !empty($uploadedFiles['dark_logo']) ? self::loadImage($uploadedFiles['dark_logo']) : $mainLogo;
        file_put_contents($outputDir . '/assets/images/app_logo_dark.png', self::toPngBytes($darkLogo));
        $manifest['assets/images/app_logo_dark.png'] = true;

        $splashLogo = !empty($uploadedFiles['splash_logo']) ? self::loadImage($uploadedFiles['splash_logo']) : $mainLogo;
        file_put_contents($outputDir . '/assets/images/splash_logo.png', self::toPngBytes($splashLogo));
        $manifest['assets/images/splash_logo.png'] = true;

        return $manifest;
    }
}

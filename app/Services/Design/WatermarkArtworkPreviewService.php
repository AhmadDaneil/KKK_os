<?php

namespace App\Services\Design;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WatermarkArtworkPreviewService
{
    public const VERSION = 7;

    private const TEXT = 'King Kad Kahwin . Preview';

    private const MAX_PREVIEW_DIMENSION = 900;

    private const PREVIEW_SCALE = 0.7;

    private const JPEG_QUALITY = 48;

    /**
     * Creates a watermarked customer-facing image preview. PDFs are displayed
     * through the protected customer review page instead and return null here.
     */
    public function create(string $sourcePath, string $destinationPath): ?string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        $disk = Storage::disk('local');
        $source = $disk->path($sourcePath);
        $destination = $disk->path($destinationPath);
        $sourceImage = match ($extension) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png' => @imagecreatefrompng($source),
        };

        if ($sourceImage === false) {
            throw new RuntimeException('Unable to prepare the customer preview watermark.');
        }

        try {
            $image = $this->resizeForCustomerPreview($sourceImage);
            imagedestroy($sourceImage);

            $this->applyCenteredWatermark($image);

            $directory = dirname($destination);
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('Unable to create the customer preview watermark folder.');
            }

            $written = imagejpeg($image, $destination, self::JPEG_QUALITY);

            if (! $written) {
                throw new RuntimeException('Unable to save the customer preview watermark.');
            }
        } finally {
            if (isset($image)) {
                imagedestroy($image);
            } else {
                imagedestroy($sourceImage);
            }
        }

        return $destinationPath;
    }

    private function resizeForCustomerPreview(\GdImage $source): \GdImage
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = 2 / 3;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = max(1, (int) round($sourceHeight * $targetRatio));
            $sourceX = max(0, (int) round(($sourceWidth - $cropWidth) / 2));
            $sourceY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = max(1, (int) round($sourceWidth / $targetRatio));
            $sourceX = 0;
            $sourceY = max(0, (int) round(($sourceHeight - $cropHeight) / 2));
        }

        $scale = min(
            self::PREVIEW_SCALE,
            self::MAX_PREVIEW_DIMENSION / max($cropWidth, $cropHeight)
        );
        $width = max(1, (int) round($cropWidth * $scale));
        $height = max(1, (int) round($cropHeight * $scale));
        $preview = imagecreatetruecolor($width, $height);

        if ($preview === false) {
            throw new RuntimeException('Unable to resize the customer preview.');
        }

        $white = imagecolorallocate($preview, 255, 255, 255);
        imagefill($preview, 0, 0, $white);
        imagecopyresampled(
            $preview,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $width,
            $height,
            $cropWidth,
            $cropHeight
        );

        return $preview;
    }

    private function applyCenteredWatermark(\GdImage $image): void
    {
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $font = $this->watermarkFont();

        if ($font !== null && function_exists('imagettftext')) {
            $fontSize = max(8, min(19, (int) round(min($width, $height) / 40)));
            $angle = -7;
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 84);
            $ink = imagecolorallocatealpha($image, 255, 255, 255, 58);
            $shadowOffset = max(1, (int) round($fontSize / 20));

            // Keep the complete label inside the card, even on narrow 4 x 6 previews.
            do {
                $box = imagettfbbox($fontSize, $angle, $font, self::TEXT);
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                $textWidth = $maxX - $minX;

                if ($textWidth <= $width * .42 || $fontSize <= 8) {
                    break;
                }

                $fontSize--;
            } while (true);

            $box = imagettfbbox($fontSize, $angle, $font, self::TEXT);
            $minX = min($box[0], $box[2], $box[4], $box[6]);
            $maxX = max($box[0], $box[2], $box[4], $box[6]);
            $minY = min($box[1], $box[3], $box[5], $box[7]);
            $maxY = max($box[1], $box[3], $box[5], $box[7]);
            $x = (int) round(($width / 2) - (($minX + $maxX) / 2));
            $y = (int) round(($height / 2) - (($minY + $maxY) / 2));

            imagettftext($image, $fontSize, $angle, $x + $shadowOffset, $y + $shadowOffset, $shadow, $font, self::TEXT);
            imagettftext($image, $fontSize, $angle, $x, $y, $ink, $font, self::TEXT);

            return;
        }

        // Fallback for installations without FreeType: keep one centred label.
        $colour = imagecolorallocatealpha($image, 255, 255, 255, 68);
        $textWidth = imagefontwidth(5) * strlen(self::TEXT);
        $textHeight = imagefontheight(5);
        imagestring($image, 5, max(0, (int) (($width - $textWidth) / 2)), max(0, (int) (($height - $textHeight) / 2)), self::TEXT, $colour);
    }

    private function watermarkFont(): ?string
    {
        foreach ([
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\segoeuib.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ] as $font) {
            if (is_file($font)) {
                return $font;
            }
        }

        return null;
    }
}

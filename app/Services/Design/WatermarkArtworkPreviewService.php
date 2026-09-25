<?php

namespace App\Services\Design;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WatermarkArtworkPreviewService
{
    private const TEXT = 'King Kad Kahwin';

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

            $this->applyLargeWatermark($image);

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
        $scale = min(
            self::PREVIEW_SCALE,
            self::MAX_PREVIEW_DIMENSION / max($sourceWidth, $sourceHeight)
        );
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $preview = imagecreatetruecolor($width, $height);

        if ($preview === false) {
            throw new RuntimeException('Unable to resize the customer preview.');
        }

        $white = imagecolorallocate($preview, 255, 255, 255);
        imagefill($preview, 0, 0, $white);
        imagecopyresampled($preview, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        return $preview;
    }

    private function applyLargeWatermark(\GdImage $image): void
    {
        imagealphablending($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $colour = imagecolorallocatealpha($image, 17, 75, 55, 62);
        $font = $this->watermarkFont();

        if ($font !== null && function_exists('imagettftext')) {
            $fontSize = max(24, min(180, (int) round($width / 5.5)));

            foreach ([-0.05, 0.5, 1.05] as $position) {
                $bounds = imagettfbbox($fontSize, -28, $font, self::TEXT);
                $textWidth = abs($bounds[2] - $bounds[0]);
                $textHeight = abs($bounds[7] - $bounds[1]);
                $x = (int) round(($width - $textWidth) / 2);
                $y = (int) round(($height * $position) + ($textHeight / 2));

                imagettftext($image, $fontSize, -28, $x, $y, $colour, $font, self::TEXT);
            }

            return;
        }

        // Fallback for installations without FreeType: repeat an opaque label
        // so that a full-resolution design is still not exposed.
        for ($y = 10; $y < $height; $y += 50) {
            for ($x = 10; $x < $width; $x += 120) {
                imagestring($image, 5, $x, $y, self::TEXT, $colour);
            }
        }
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

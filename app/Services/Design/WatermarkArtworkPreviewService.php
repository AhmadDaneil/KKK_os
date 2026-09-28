<?php

namespace App\Services\Design;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WatermarkArtworkPreviewService
{
    public const VERSION = 3;

    private const TEXT = 'KING KAD KAHWIN - PREVIEW';

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
        $font = $this->watermarkFont();

        if ($font !== null && function_exists('imagettftext')) {
            /*
             * Customer proofs need a visible, non-croppable mark.  A large
             * 45-degree brand is repeated through the centre: it overlaps
             * both the artwork and its background, while the 46% white ink
             * and soft shadow retain readability without obscuring the proof.
             */
            $fontSize = max(24, min(110, (int) round(min($width, $height) / 10)));
            $angle = -45;
            $shadow = imagecolorallocatealpha($image, 0, 0, 0, 84);
            $ink = imagecolorallocatealpha($image, 255, 255, 255, 68);
            $shadowOffset = max(2, (int) round($fontSize / 18));

            foreach ([0.42, 0.82] as $position) {
                $x = (int) round(-$width * 0.07);
                $y = (int) round($height * $position);

                imagettftext(
                    $image,
                    $fontSize,
                    $angle,
                    $x + $shadowOffset,
                    $y + $shadowOffset,
                    $shadow,
                    $font,
                    self::TEXT
                );
                imagettftext($image, $fontSize, $angle, $x, $y, $ink, $font, self::TEXT);
            }

            return;
        }

        // Fallback for installations without FreeType: tile the label so a
        // full-resolution design is still not exposed.
        $colour = imagecolorallocatealpha($image, 255, 255, 255, 68);
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

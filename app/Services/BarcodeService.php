<?php

namespace App\Services;

use Picqer\Barcode\BarcodeGeneratorSVG;
use Imagick;

class BarcodeService
{
    public static function generateBarcodeAsSvg(string $trackingNumber): ?string
    {
        try {
            $generator = new BarcodeGeneratorSVG();

            return $generator->getBarcode(
                $trackingNumber,
                $generator::TYPE_CODE_128,
                2,
                60
            );

        } catch (\Exception $e) {
            \Log::error('Barcode generation failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function convertSvgToPngFile(string $svgContent, string $filename): ?string
    {
        try {
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();

                // FIXED ORDER: Read the SVG first, then set properties
                $imagick->readImageBlob($svgContent);          // Read SVG first
                $imagick->setImageFormat('png');               // Set format
                $imagick->setBackgroundColor('white');         // Set background
                $imagick->setImageBackgroundColor('white');    // Set image background

                $tempFile = tempnam(sys_get_temp_dir(), 'barcode_' . $filename . '_') . '.png';
                $imagick->writeImage($tempFile);
                $imagick->clear();

                return $tempFile;
            }

            return null;

        } catch (\Exception $e) {
            \Log::error('SVG to PNG conversion failed: ' . $e->getMessage());
            return null;
        }
    }
}

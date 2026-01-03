<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeGeneratorService
{
    /**
     * Generate QR code image from code string.
     *
     * @param string $code The code string to encode in QR code
     * @param array $options Optional settings (size, format, etc.)
     * @return string Path to the generated QR code image
     */
    public function generate(string $code, array $options = []): string
    {
        $size = $options['size'] ?? 300;
        $format = $options['format'] ?? 'png';
        $margin = $options['margin'] ?? 2;
        $errorCorrection = $options['error_correction'] ?? 'H'; // L, M, Q, H

        // Generate QR code
        $qrCode = QrCode::format($format)
            ->size($size)
            ->margin($margin)
            ->errorCorrection($errorCorrection)
            ->generate($code);

        // Store in storage
        $directory = 'promo_qr_codes';
        $filename = 'qr_' . md5($code) . '_' . time() . '.' . $format;
        $path = $directory . '/' . $filename;

        Storage::disk('public')->put($path, $qrCode);

        return $path;
    }

    /**
     * Generate QR code and return as download response.
     *
     * @param string $code The code string to encode
     * @param array $options Optional settings
     * @return \Illuminate\Http\Response
     */
    public function download(string $code, array $options = []): \Illuminate\Http\Response
    {
        $path = $this->generate($code, $options);
        $fullPath = Storage::disk('public')->path($path);
        $filename = 'qr_code_' . $code . '.' . ($options['format'] ?? 'png');

        return response()->download($fullPath, $filename)->deleteFileAfterSend(false);
    }

    /**
     * Get QR code image URL.
     *
     * @param string $code The code string
     * @param array $options Optional settings
     * @return string URL to the QR code image
     */
    public function getUrl(string $code, array $options = []): string
    {
        $path = $this->generate($code, $options);
        return Storage::disk('public')->url($path);
    }
}


<?php

namespace App\Services;

use App\Models\BondRequest;
use App\Models\CertificateVersion;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use RuntimeException;

class QRCodeGenerationService
{
    public function verificationUrl(string $verificationToken): string
    {
        return url(route('certificate-verification.show', [
            'verification_token' => $verificationToken,
        ], absolute: false));
    }

    /**
     * Generate a QR PNG to a temporary path before the version record exists.
     */
    public function generateTemporary(
        BondRequest $bondRequest,
        int $versionNumber,
        string $verificationToken,
    ): string {
        if (! extension_loaded('gd')) {
            throw new RuntimeException('The GD extension is required to generate QR code images.');
        }

        $now = now();
        $relativePath = sprintf(
            'private/qr-codes/%s/%s/pending_request_%d_v%d.png',
            $now->format('Y'),
            $now->format('m'),
            $bondRequest->id,
            $versionNumber,
        );

        $this->writeQrCode($verificationToken, $relativePath);

        return $relativePath;
    }

    public function finalizeForVersion(CertificateVersion $version, string $temporaryRelativePath): string
    {
        $now = $version->generated_at ?? now();
        $finalRelativePath = sprintf(
            'private/qr-codes/%s/%s/certificate_version_%d.png',
            $now->format('Y'),
            $now->format('m'),
            $version->id,
        );

        $temporaryAbsolutePath = storage_path("app/{$temporaryRelativePath}");
        $finalAbsolutePath = storage_path("app/{$finalRelativePath}");
        $finalDirectory = dirname($finalAbsolutePath);

        if (! is_dir($finalDirectory)) {
            mkdir($finalDirectory, 0755, true);
        }

        if (! file_exists($temporaryAbsolutePath)) {
            $this->writeQrCode((string) $version->verification_token, $finalRelativePath);

            return $finalRelativePath;
        }

        if (! rename($temporaryAbsolutePath, $finalAbsolutePath)) {
            copy($temporaryAbsolutePath, $finalAbsolutePath);
            @unlink($temporaryAbsolutePath);
        }

        return $finalRelativePath;
    }

    /**
     * @return array{path: string, width: int, height: int, ratio: bool}
     */
    public function templateImageData(string $relativePath, int $size = 120): array
    {
        return [
            'path' => storage_path("app/{$relativePath}"),
            'width' => $size,
            'height' => $size,
            'ratio' => true,
        ];
    }

    private function writeQrCode(string $verificationToken, string $relativePath): void
    {
        $absolutePath = storage_path("app/{$relativePath}");
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $writer = new Writer(new GDLibRenderer(300, 4, 'png'));
        $writer->writeFile($this->verificationUrl($verificationToken), $absolutePath);
    }
}

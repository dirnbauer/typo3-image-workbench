<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

/**
 * Re-encodes image data into the format a target file extension promises.
 *
 * The editor hands back whatever the canvas produced — usually PNG, even
 * when the source was a JPEG — and the AI provider returns PNG as well.
 * Writing that binary under a .jpg name would leave a file whose contents
 * and extension disagree, which breaks thumbnails, downloads and every
 * consumer that trusts the extension. So the binary is sniffed and, when
 * it does not already match, decoded and written out again in the target
 * format.
 *
 * Pure: no FAL, no TYPO3, nothing but the binary in and the binary out.
 */
final readonly class ImageFormatConverter
{
    /**
     * The raster formats the workbench edits. An extension outside this
     * list is passed through untouched — the caller has already refused
     * anything the editor cannot open.
     *
     * @var array<string, string>
     */
    public const array MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    private const int QUALITY = 90;

    /**
     * @throws \RuntimeException when the data is not a supported image, or cannot be decoded or re-encoded
     */
    public function toExtension(string $binary, string $extension): string
    {
        $targetMime = self::MIME_BY_EXTENSION[strtolower($extension)] ?? null;
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if ($targetMime === null || $actualMime === $targetMime) {
            return $binary;
        }
        if (!in_array($actualMime, self::MIME_BY_EXTENSION, true)) {
            throw new \RuntimeException('Unsupported image data.', 1752910103);
        }

        $image = @imagecreatefromstring($binary);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('The image data could not be decoded.', 1752910104);
        }

        // No imagedestroy(): GdImage is garbage-collected since PHP 8.0 and
        // the call is deprecated as of 8.5.
        ob_start();
        $success = match (strtolower($extension)) {
            'png' => $this->writePng($image),
            'webp' => function_exists('imagewebp') && imagewebp($image, null, self::QUALITY),
            default => imagejpeg($image, null, self::QUALITY),
        };
        $converted = ob_get_clean();

        if (!$success || !is_string($converted) || $converted === '') {
            throw new \RuntimeException('The edited image could not be converted.', 1752910105);
        }

        return $converted;
    }

    private function writePng(\GdImage $image): bool
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng($image);
    }
}

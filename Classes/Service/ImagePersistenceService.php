<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class ImagePersistenceService
{
    private const MIME_BY_EXTENSION = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function __construct(private ProcessedFileRepository $processedFileRepository) {}

    public function saveCopy(File $source, string $binary, string $desiredName, ?string $extension = null): File
    {
        $folder = $source->getParentFolder();
        if (!$folder->checkActionPermission('write')) {
            throw new \RuntimeException('No write permission for the target folder.', 1752910101);
        }

        $extension ??= strtolower($source->getExtension());
        $binary = $this->ensureFormat($binary, $extension);
        $base = pathinfo(trim($desiredName), PATHINFO_FILENAME) ?: $source->getNameWithoutExtension();
        $targetName = $source->getStorage()->sanitizeFileName($base . '.' . $extension, $folder);
        $temporaryPath = GeneralUtility::tempnam('image_workbench_', '.' . $extension);

        try {
            GeneralUtility::writeFile($temporaryPath, $binary, true);
            return $source->getStorage()->addFile(
                $temporaryPath,
                $folder,
                $targetName,
                DuplicationBehavior::RENAME,
            );
        } finally {
            if (file_exists($temporaryPath)) {
                GeneralUtility::unlink_tempfile($temporaryPath);
            }
        }
    }

    public function overwrite(File $target, string $binary): File
    {
        if (!$target->checkActionPermission('write')) {
            throw new \RuntimeException('No write permission for the target file.', 1752910102);
        }

        $target->setContents($this->ensureFormat($binary, strtolower($target->getExtension())));
        foreach ($this->processedFileRepository->findAllByOriginalFile($target) as $processedFile) {
            $processedFile->delete(true);
        }

        return $target;
    }

    private function ensureFormat(string $binary, string $extension): string
    {
        $targetMime = self::MIME_BY_EXTENSION[$extension] ?? null;
        $actualMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($binary);
        if ($targetMime === null || $actualMime === $targetMime) {
            return $binary;
        }
        if (!in_array($actualMime, self::MIME_BY_EXTENSION, true)) {
            throw new \RuntimeException('Unsupported image data.', 1752910103);
        }

        $image = imagecreatefromstring($binary);
        if (!$image instanceof \GdImage) {
            throw new \RuntimeException('The image data could not be decoded.', 1752910104);
        }

        try {
            ob_start();
            $success = match ($extension) {
                'png' => $this->writePng($image),
                'webp' => function_exists('imagewebp') && imagewebp($image, null, 90),
                default => imagejpeg($image, null, 90),
            };
            $converted = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

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

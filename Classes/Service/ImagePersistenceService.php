<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFileRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class ImagePersistenceService
{
    public function __construct(
        private ProcessedFileRepository $processedFileRepository,
        private ImageFormatConverter $formatConverter,
    ) {}

    public function saveCopy(File $source, string $binary, string $desiredName, ?string $extension = null): File
    {
        $folder = $source->getParentFolder();
        if (!$folder->checkActionPermission('write')) {
            throw new \RuntimeException('No write permission for the target folder.', 1752910101);
        }

        $extension ??= strtolower($source->getExtension());
        $binary = $this->formatConverter->toExtension($binary, $extension);
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

        $target->setContents($this->formatConverter->toExtension($binary, $target->getExtension()));
        foreach ($this->processedFileRepository->findAllByOriginalFile($target) as $processedFile) {
            $processedFile->delete(true);
        }

        return $target;
    }
}

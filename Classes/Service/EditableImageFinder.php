<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceInterface;

/**
 * Resolves a combined identifier ("1:/path/image.jpg") to a file the
 * workbench may open: it exists, lives in a real storage and has one of the
 * raster formats the editor round-trips.
 *
 * Permissions are deliberately not part of this: reading, overwriting and
 * writing a sibling copy each need a different FAL check, and the callers
 * make the one that fits their action.
 */
final readonly class EditableImageFinder
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private ImageFormatConverter $formatConverter,
    ) {}

    public function find(string $combinedIdentifier): ?File
    {
        if (trim($combinedIdentifier) === '') {
            return null;
        }

        try {
            $resource = $this->resourceFactory->retrieveFileOrFolderObject($combinedIdentifier);
        } catch (\Exception) {
            // Unknown storage, missing file, malformed identifier: all mean
            // "there is no image to edit here".
            return null;
        }

        return $this->isEditable($resource) ? $resource : null;
    }

    /**
     * @phpstan-assert-if-true File $resource
     */
    public function isEditable(?ResourceInterface $resource): bool
    {
        return $resource instanceof File
            && !$resource->isMissing()
            // The fallback storage reaches files outside every file mount;
            // the Core file editor refuses it for the same reason.
            && !$resource->getStorage()->isFallbackStorage()
            && $this->formatConverter->supports($resource->getExtension());
    }
}

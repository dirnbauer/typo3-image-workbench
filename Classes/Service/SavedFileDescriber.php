<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;

/**
 * What the editor page needs to show a file it just wrote: its name, a
 * thumbnail and a link that opens it in the workbench.
 */
final readonly class SavedFileDescriber
{
    private const int PREVIEW_SIZE = 160;

    public function __construct(
        private UriBuilder $uriBuilder,
    ) {}

    /**
     * @return array{uid: int, name: string, identifier: string, editUrl: string, previewUrl: string}
     */
    public function describe(File $file, string $returnUrl = ''): array
    {
        $parameters = ['target' => $file->getCombinedIdentifier()];
        if ($returnUrl !== '') {
            $parameters['returnUrl'] = $returnUrl;
        }

        return [
            'uid' => $file->getUid(),
            'name' => $file->getName(),
            'identifier' => $file->getCombinedIdentifier(),
            'editUrl' => (string)$this->uriBuilder->buildUriFromRoute('image_workbench_edit', $parameters),
            'previewUrl' => $this->previewUrl($file),
        ];
    }

    private function previewUrl(File $file): string
    {
        try {
            $url = $file->process(
                ProcessedFile::CONTEXT_IMAGEPREVIEW,
                ['width' => self::PREVIEW_SIZE, 'height' => self::PREVIEW_SIZE],
            )->getPublicUrl();
        } catch (\Exception) {
            $url = null;
        }

        // Storages without public URLs still get a preview through the
        // workbench's own permission-checked source route.
        return $url ?: (string)$this->uriBuilder->buildUriFromRoute(
            'image_workbench_source',
            ['target' => $file->getCombinedIdentifier()],
        );
    }
}

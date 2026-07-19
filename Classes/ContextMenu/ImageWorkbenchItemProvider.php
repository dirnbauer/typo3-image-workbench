<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ImageWorkbenchItemProvider extends AbstractProvider
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    private ?File $file = null;

    public function canHandle(): bool
    {
        return $this->table === 'sys_file';
    }

    public function getPriority(): int
    {
        return 45;
    }

    protected function initialize(): void
    {
        parent::initialize();
        $this->itemsConfiguration = [
            'imageWorkbench' => [
                'label' => 'Bild bearbeiten',
                'iconIdentifier' => 'actions-image',
                'callbackAction' => 'open',
            ],
        ];

        try {
            $resource = GeneralUtility::makeInstance(ResourceFactory::class)
                ->retrieveFileOrFolderObject($this->identifier);
            $this->file = $resource instanceof File ? $resource : null;
        } catch (\Throwable) {
            $this->file = null;
        }
    }

    protected function canRender(string $itemName, string $type): bool
    {
        if ($itemName !== 'imageWorkbench' || in_array($itemName, $this->disabledItems, true)) {
            return false;
        }

        return $this->file instanceof File
            && in_array(strtolower($this->file->getExtension()), self::ALLOWED_EXTENSIONS, true)
            && $this->file->checkActionPermission('write')
            && (bool)($this->backendUser->getTSConfig()['options.']['imageWorkbench.']['enable'] ?? true);
    }

    /** @return array<string, string> */
    protected function getAdditionalAttributes(string $itemName): array
    {
        $url = (string)GeneralUtility::makeInstance(UriBuilder::class)
            ->buildUriFromRoute('image_workbench_edit');

        return [
            'data-callback-module' => '@webconsulting/image-workbench/context-menu-actions.js',
            'data-action-url' => $url,
        ];
    }
}

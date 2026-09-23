<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\ContextMenu;

use TYPO3\CMS\Backend\ContextMenu\ItemProviders\AbstractProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Resource\File;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;

/**
 * "Edit image" in the context menu of a JPEG, PNG or WebP file the editor
 * may write.
 */
final class ImageWorkbenchItemProvider extends AbstractProvider
{
    private ?File $file = null;

    public function __construct(
        private readonly EditableImageFinder $images,
        private readonly UriBuilder $uriBuilder,
    ) {
        parent::__construct();
    }

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
                'label' => 'LLL:EXT:image_workbench/Resources/Private/Language/locallang.xlf:action.edit',
                'iconIdentifier' => 'actions-brush',
                'callbackAction' => 'open',
            ],
        ];
        $this->file = $this->images->find($this->identifier);
    }

    protected function canRender(string $itemName, string $type): bool
    {
        return $itemName === 'imageWorkbench'
            && !in_array($itemName, $this->disabledItems, true)
            && $this->file !== null
            && $this->file->checkActionPermission('write')
            && WorkbenchSettings::fromBackendUser($this->backendUser)->enabled;
    }

    /**
     * @return array<string, string>
     */
    protected function getAdditionalAttributes(string $itemName): array
    {
        return [
            'data-callback-module' => '@webconsulting/image-workbench/context-menu-actions.js',
            'data-action-url' => (string)$this->uriBuilder->buildUriFromRoute('image_workbench_edit'),
        ];
    }
}

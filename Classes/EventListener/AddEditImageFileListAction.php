<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\EventListener;

use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ActionGroup;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;

/**
 * Puts "Edit image" into the action column of the file list, next to the
 * Core actions, for every image the context menu would offer it for.
 *
 * It sits in the "more" dropdown by default; listing `imageWorkbench` in
 * `options.file_list.primaryActions` moves it into the always-visible group,
 * exactly like the Core actions.
 */
#[AsEventListener(identifier: 'image-workbench/file-list-action')]
final readonly class AddEditImageFileListAction
{
    private const string ACTION = 'imageWorkbench';

    public function __construct(
        private EditableImageFinder $images,
        private ComponentFactory $componentFactory,
        private IconFactory $iconFactory,
        private UriBuilder $uriBuilder,
    ) {}

    public function __invoke(ProcessFileListActionsEvent $event): void
    {
        $file = $event->getResource();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$event->isFile()
            || !$backendUser instanceof BackendUserAuthentication
            || !$this->images->isEditable($file)
            || !$file->checkActionPermission('write')
            || !WorkbenchSettings::fromBackendUser($backendUser)->enabled
        ) {
            return;
        }

        // Back to exactly this listing (folder, page, sorting) after editing.
        $listUri = $event->getRequest()->getUri();
        $parameters = [
            'target' => $file->getCombinedIdentifier(),
            'returnUrl' => $listUri->getPath() . ($listUri->getQuery() !== '' ? '?' . $listUri->getQuery() : ''),
        ];

        $button = $this->componentFactory->createLinkButton()
            ->setHref((string)$this->uriBuilder->buildUriFromRoute('image_workbench_edit', $parameters))
            ->setTitle($this->label())
            ->setIcon($this->iconFactory->getIcon('actions-brush', IconSize::SMALL));

        // A missing anchor simply appends, so "after edit" is safe in either group.
        $event->setAction(
            $button,
            self::ACTION,
            $this->isPrimary($backendUser) ? ActionGroup::primary : ActionGroup::secondary,
            after: 'edit',
        );
    }

    private function isPrimary(BackendUserAuthentication $backendUser): bool
    {
        $primaryActions = $backendUser->getTSConfig()['options.']['file_list.']['primaryActions'] ?? '';

        return is_string($primaryActions)
            && in_array(self::ACTION, GeneralUtility::trimExplode(',', $primaryActions, true), true);
    }

    private function label(): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;

        return $languageService instanceof LanguageService
            ? (string)($languageService->translate('action.edit', 'image_workbench.messages') ?? 'Edit image')
            : 'Edit image';
    }
}

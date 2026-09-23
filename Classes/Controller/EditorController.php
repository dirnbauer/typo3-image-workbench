<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;
use Webconsulting\ImageWorkbench\Service\AiImageGenerator;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;

/**
 * The full-page editor for one image, opened from the file list.
 *
 * Saving lives in the document header like everywhere else in the backend;
 * the generation panel beside the canvas only appears for backend groups
 * that have it enabled.
 */
#[AsController]
final readonly class EditorController
{
    use BackendContextTrait;

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private ComponentFactory $componentFactory,
        private IconFactory $iconFactory,
        private PageRenderer $pageRenderer,
        private UriBuilder $uriBuilder,
        private EditableImageFinder $images,
        private AiImageGenerator $generator,
    ) {}

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $settings = WorkbenchSettings::fromBackendUser($this->backendUser());
        $query = $request->getQueryParams();
        $file = $this->images->find(self::stringParameter($query, 'target'));
        $returnUrl = GeneralUtility::sanitizeLocalUrl(self::stringParameter($query, 'returnUrl'), $request)
            ?: $this->fileListUrl($file);

        $view = $this->moduleTemplateFactory->create($request);
        $view->addButtonToButtonBar(
            // The editor asks before this discards unsaved edits.
            $this->componentFactory->createCloseButton($returnUrl)->setAttributes(['data-image-workbench-close' => 'true']),
            ButtonBar::BUTTON_POSITION_LEFT,
            10,
        );

        if ($file === null) {
            return $this->unavailable($view, 'unavailable.notFound', 404);
        }
        if (!$settings->enabled || !$file->checkActionPermission('write')) {
            return $this->unavailable($view, 'unavailable.accessDenied', 403);
        }

        $this->addSaveButtons($view);
        $this->pageRenderer->addCssFile('EXT:image_workbench/Resources/Public/JavaScript/Vendor/filerobot-image-editor.css');
        $this->pageRenderer->addCssFile('EXT:image_workbench/Resources/Public/Css/editor.css');
        $this->pageRenderer->loadJavaScriptModule('@webconsulting/image-workbench/editor.js');

        $view->setTitle($this->label('editor.title'), $file->getName());
        $view->assignMultiple([
            'file' => $file,
            'sourceUrl' => (string)$this->uriBuilder->buildUriFromRoute(
                'image_workbench_source',
                ['target' => $file->getCombinedIdentifier()],
            ),
            'returnUrl' => $returnUrl,
            'editorConfiguration' => json_encode($settings->editorConfiguration(), JSON_THROW_ON_ERROR),
            'ai' => $this->aiPanel($settings),
        ]);

        return $view->renderResponse('Editor/Edit');
    }

    /**
     * Both save actions start disabled; the editor enables them once the
     * image has loaded, so there is never a click on a canvas that is not
     * there yet.
     */
    private function addSaveButtons(ModuleTemplate $view): void
    {
        foreach ([
            20 => ['save-copy', 'action.saveCopy', 'actions-save-add'],
            30 => ['overwrite', 'action.overwrite', 'actions-save'],
        ] as $group => [$action, $label, $icon]) {
            $view->addButtonToButtonBar(
                $this->componentFactory->createGenericButton()
                    ->setTag('button')
                    ->setLabel($this->label($label))
                    ->setIcon($this->iconFactory->getIcon($icon, IconSize::SMALL))
                    ->setShowLabelText(true)
                    ->setAttributes([
                        'type' => 'button',
                        'disabled' => 'disabled',
                        'data-image-workbench-action' => $action,
                    ]),
                ButtonBar::BUTTON_POSITION_LEFT,
                $group,
            );
        }
    }

    /**
     * The panel shows for groups with generation enabled; while nr-llm has no
     * API key, only administrators see it (as a set-up hint).
     *
     * @return array{visible: bool, available: bool, configuration: string, model: string, sizes: list<array{value: string, label: string, selected: bool}>, defaultSize: string, minLength: int, maxLength: int, isAdmin: bool}
     */
    private function aiPanel(WorkbenchSettings $settings): array
    {
        $isAdmin = $this->backendUser()->isAdmin();
        $panel = [
            'visible' => $settings->aiEnabled && $isAdmin,
            'available' => false,
            'configuration' => $settings->aiConfiguration,
            'model' => '',
            'sizes' => [],
            'defaultSize' => '',
            'minLength' => AiImageGenerator::PROMPT_MIN_LENGTH,
            'maxLength' => AiImageGenerator::PROMPT_MAX_LENGTH,
            'isAdmin' => $isAdmin,
        ];
        if (!$settings->aiEnabled || !$this->generator->isAvailable()) {
            return $panel;
        }

        $model = $this->generator->modelFor($settings->aiConfiguration);
        $sizes = $this->generator->sizesFor($model);
        $defaultSize = in_array($settings->aiDefaultSize, $sizes, true) ? $settings->aiDefaultSize : ($sizes[0] ?? '');

        return [
            ...$panel,
            'visible' => true,
            'available' => true,
            'model' => $model,
            'sizes' => array_map(
                fn(string $size): array => [
                    'value' => $size,
                    'label' => $this->sizeLabel($size),
                    'selected' => $size === $defaultSize,
                ],
                $sizes,
            ),
            'defaultSize' => $defaultSize,
        ];
    }

    private function sizeLabel(string $size): string
    {
        if (preg_match('/^(\d+)x(\d+)$/', $size, $match) !== 1) {
            return $this->label('ai.size.auto');
        }
        $width = (int)$match[1];
        $height = (int)$match[2];
        $shape = match ($width <=> $height) {
            1 => 'landscape',
            -1 => 'portrait',
            0 => 'square',
        };

        return $this->label('ai.size.' . $shape, ['width' => $width, 'height' => $height]);
    }

    private function unavailable(ModuleTemplate $view, string $reason, int $status): ResponseInterface
    {
        $view->setTitle($this->label('editor.title'));
        $view->assign('reason', $reason);

        return $view->renderResponse('Editor/Unavailable')->withStatus($status);
    }

    private function fileListUrl(?File $file): string
    {
        $parameters = [];
        if ($file !== null) {
            $parameters['id'] = $file->getParentFolder()->getCombinedIdentifier();
        }

        return (string)$this->uriBuilder->buildUriFromRoute('media_management', $parameters);
    }
}

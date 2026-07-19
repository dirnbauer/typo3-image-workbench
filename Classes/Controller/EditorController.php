<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

#[Autoconfigure(public: true)]
final readonly class EditorController
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PageRenderer $pageRenderer,
        private ResourceFactory $resourceFactory,
        private UriBuilder $uriBuilder,
    ) {}

    public function edit(ServerRequestInterface $request): ResponseInterface
    {
        $target = (string)($request->getQueryParams()['target'] ?? '');
        $returnUrl = GeneralUtility::sanitizeLocalUrl(
            (string)($request->getQueryParams()['returnUrl'] ?? ''),
            $request
        );
        $resource = $this->resourceFactory->retrieveFileOrFolderObject($target);

        if (!$resource instanceof File
            || !in_array(strtolower($resource->getExtension()), self::ALLOWED_EXTENSIONS, true)
            || !$resource->checkActionPermission('write')
        ) {
            throw new InsufficientFileAccessPermissionsException('This image cannot be edited.', 1752910001);
        }

        $tsConfig = $GLOBALS['BE_USER']->getTSConfig()['options.']['imageWorkbench.'] ?? [];
        $aiConfig = is_array($tsConfig['ai.'] ?? null) ? $tsConfig['ai.'] : [];

        $this->pageRenderer->addCssFile(
            'EXT:image_workbench/Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.css'
        );
        $this->pageRenderer->addCssFile('EXT:image_workbench/Resources/Public/Css/editor.css');
        $this->pageRenderer->addJsFile(
            'EXT:image_workbench/Resources/Public/JavaScript/Vendor/filerobot-image-editor.bundle.js'
        );
        $this->pageRenderer->loadJavaScriptModule('@webconsulting/image-workbench/editor.js');

        $view = $this->moduleTemplateFactory->create($request);
        $view->setTitle('Image Workbench', $resource->getName());
        $view->assignMultiple([
            'target' => $target,
            'sourceUrl' => (string)$this->uriBuilder->buildUriFromRoute(
                'image_workbench_source',
                ['target' => $target]
            ),
            'returnUrl' => $returnUrl,
            'fileName' => $resource->getName(),
            'extension' => strtolower($resource->getExtension()),
            'aiEnabled' => (bool)($aiConfig['enable'] ?? true),
            'config' => json_encode([
                'tabs' => GeneralUtility::trimExplode(
                    ',',
                    (string)($tsConfig['tabs'] ?? 'adjust,finetune,filters,annotate,resize'),
                    true
                ),
                'cropPresets' => GeneralUtility::trimExplode(',', (string)($tsConfig['cropPresets'] ?? ''), true),
                'ai' => [
                    'configuration' => (string)($aiConfig['configuration'] ?? 'image-workbench'),
                    'size' => (string)($aiConfig['defaultSize'] ?? '1024x1024'),
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        return $view->renderResponse('Editor/Show');
    }
}

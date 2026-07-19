<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Netresearch\NrLlm\Specialized\Image\DallEImageService;
use Netresearch\NrLlm\Specialized\Option\ImageGenerationOptions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Webconsulting\ImageWorkbench\Http\JsonResponder;
use Webconsulting\ImageWorkbench\Service\ImagePersistenceService;

#[Autoconfigure(public: true)]
final readonly class AiImageController
{
    public function __construct(
        private DallEImageService $imageService,
        private ResourceFactory $resourceFactory,
        private ImagePersistenceService $persistence,
        private JsonResponder $json,
    ) {}

    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $prompt = trim((string)($body['prompt'] ?? ''));
        $configuration = trim((string)($body['configuration'] ?? 'image-workbench'));
        $size = trim((string)($body['size'] ?? '1024x1024'));

        if (mb_strlen($prompt) < 10 || mb_strlen($prompt) > 8_000) {
            return $this->json->respond([
                'success' => false,
                'message' => 'The prompt must contain between 10 and 8,000 characters.',
            ], 400);
        }
        if ($configuration === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{1,63}$/i', $configuration)) {
            return $this->json->respond(['success' => false, 'message' => 'Invalid nr-llm configuration.'], 400);
        }

        try {
            $resource = $this->resourceFactory->retrieveFileOrFolderObject((string)($body['target'] ?? ''));
        } catch (\Throwable) {
            return $this->json->respond(['success' => false, 'message' => 'Source file not found.'], 404);
        }
        if (!$resource instanceof File
            || !$resource->checkActionPermission('read')
            || !$resource->getParentFolder()->checkActionPermission('write')
        ) {
            return $this->json->respond(['success' => false, 'message' => 'Insufficient file permissions.'], 403);
        }

        try {
            $model = $this->imageService->resolveModelForConfiguration($configuration, 'gpt-image-2');
            $systemPrompt = trim($this->imageService->getConfigurationSystemPrompt($configuration));
            $effectivePrompt = trim($systemPrompt . ($systemPrompt !== '' ? "\n\n" : '') . $prompt);
            $result = $this->imageService->generate(
                $effectivePrompt,
                new ImageGenerationOptions(
                    model: $model,
                    size: $size,
                    quality: null,
                    style: null,
                    format: null,
                    configuration: $configuration,
                ),
            );
            $binary = $result->getBinaryContent() ?? $result->downloadFromUrl();
            if (!is_string($binary) || $binary === '') {
                throw new \RuntimeException('The generated image could not be downloaded.');
            }
            $name = $resource->getNameWithoutExtension() . '-ai-' . date('Ymd-His');
            $saved = $this->persistence->saveCopy($resource, $binary, $name, 'png');
        } catch (\Throwable $exception) {
            return $this->json->respond(['success' => false, 'message' => $exception->getMessage()], 502);
        }

        return $this->json->respond([
            'success' => true,
            'file' => ['uid' => $saved->getUid(), 'name' => $saved->getName()],
            'model' => $result->model,
            'configuration' => $configuration,
            'usageTrackedBy' => 'nr-llm',
        ]);
    }
}

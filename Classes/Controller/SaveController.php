<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use Webconsulting\ImageWorkbench\Http\JsonResponder;
use Webconsulting\ImageWorkbench\Service\ImagePersistenceService;

#[Autoconfigure(public: true)]
final readonly class SaveController
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private ImagePersistenceService $persistence,
        private JsonResponder $json,
    ) {}

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $mode = (string)($body['mode'] ?? 'copy') === 'overwrite' ? 'overwrite' : 'copy';

        try {
            $resource = $this->resourceFactory->retrieveFileOrFolderObject((string)($body['target'] ?? ''));
        } catch (\Throwable) {
            return $this->json->respond(['success' => false, 'message' => 'File not found.'], 404);
        }

        if (!$resource instanceof File || !$resource->checkActionPermission('read')) {
            return $this->json->respond(['success' => false, 'message' => 'File not accessible.'], 403);
        }

        $dataUrl = (string)($body['image'] ?? '');
        if (!str_contains($dataUrl, ',')) {
            return $this->json->respond(['success' => false, 'message' => 'Invalid image data.'], 400);
        }
        $binary = base64_decode(explode(',', $dataUrl, 2)[1], true);
        if (!is_string($binary) || $binary === '') {
            return $this->json->respond(['success' => false, 'message' => 'Invalid image data.'], 400);
        }

        try {
            $savedFile = $mode === 'overwrite'
                ? $this->persistence->overwrite($resource, $binary)
                : $this->persistence->saveCopy($resource, $binary, (string)($body['filename'] ?? ''));
        } catch (\Throwable $exception) {
            return $this->json->respond(['success' => false, 'message' => $exception->getMessage()], 500);
        }

        return $this->json->respond([
            'success' => true,
            'file' => ['uid' => $savedFile->getUid(), 'name' => $savedFile->getName()],
        ]);
    }
}

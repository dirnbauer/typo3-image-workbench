<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;

#[Autoconfigure(public: true)]
final readonly class ImageController
{
    public function __construct(
        private ResourceFactory $resourceFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function source(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $resource = $this->resourceFactory->retrieveFileOrFolderObject(
                (string)($request->getQueryParams()['target'] ?? '')
            );
        } catch (\Throwable) {
            return $this->responseFactory->createResponse(404);
        }

        if (!$resource instanceof File || !$resource->checkActionPermission('read')) {
            return $this->responseFactory->createResponse(403);
        }

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', $resource->getMimeType())
            ->withHeader('Cache-Control', 'private, no-store')
            ->withBody($this->streamFactory->createStream($resource->getContents()));
    }
}

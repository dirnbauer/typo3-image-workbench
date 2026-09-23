<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;
use Webconsulting\ImageWorkbench\Service\ImageFormatConverter;

/**
 * Streams the original image into the editor canvas.
 *
 * The editor needs same-origin pixels to export the canvas, and files in a
 * non-public storage have no URL at all, so the image comes through the
 * backend. Only the four raster formats the workbench edits are served:
 * this route must never hand out an HTML or SVG file under the backend's
 * origin.
 */
#[AsController]
final readonly class ImageController
{
    use BackendContextTrait;

    public function __construct(
        private EditableImageFinder $images,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    public function source(ServerRequestInterface $request): ResponseInterface
    {
        $file = $this->images->find(self::stringParameter($request->getQueryParams(), 'target'));
        if ($file === null) {
            return $this->responseFactory->createResponse(404);
        }
        if (!$file->checkActionPermission('read')) {
            return $this->responseFactory->createResponse(403);
        }

        return $this->responseFactory->createResponse()
            ->withHeader('Content-Type', ImageFormatConverter::MIME_BY_EXTENSION[strtolower($file->getExtension())])
            ->withHeader('Content-Disposition', 'inline')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Cache-Control', 'private, no-store')
            ->withBody($this->streamFactory->createStream($file->getContents()));
    }
}

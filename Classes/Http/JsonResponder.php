<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class JsonResponder
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {}

    /** @param array<string, mixed> $payload */
    public function respond(array $payload, int $status = 200): ResponseInterface
    {
        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($this->streamFactory->createStream(json_encode($payload, JSON_THROW_ON_ERROR)));
    }
}

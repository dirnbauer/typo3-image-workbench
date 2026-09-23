<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

/**
 * An image nr-llm returned: the raw bytes and the model that produced them.
 */
final readonly class GeneratedImage
{
    public function __construct(
        public string $binary,
        public string $model,
    ) {}
}

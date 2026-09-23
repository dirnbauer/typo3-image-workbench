<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Service;

use Netresearch\NrLlm\Specialized\Image\DallEImageService;
use Netresearch\NrLlm\Specialized\Option\ImageGenerationOptions;

/**
 * The one place the workbench talks to nr-llm.
 *
 * Model, system prompt, budget and usage tracking all stay in the nr-llm
 * configuration record; this class only resolves what that record says and
 * hands the backend user along, so nr-llm can enforce per-user budgets and
 * book the spend against the person who clicked.
 */
final readonly class AiImageGenerator
{
    /** Used when the configuration names no model nr-llm's image service accepts. */
    public const string FALLBACK_MODEL = 'gpt-image-2';

    public const int PROMPT_MIN_LENGTH = 10;

    public const int PROMPT_MAX_LENGTH = 8_000;

    public function __construct(
        private DallEImageService $imageService,
    ) {}

    /**
     * False until an administrator has stored the provider API key in nr-llm.
     */
    public function isAvailable(): bool
    {
        return $this->imageService->isAvailable();
    }

    public function modelFor(string $configuration): string
    {
        return $this->imageService->resolveModelForConfiguration($configuration, self::FALLBACK_MODEL);
    }

    /**
     * @return list<string> the sizes the model accepts, "auto" included where it exists
     */
    public function sizesFor(string $model): array
    {
        return array_values($this->imageService->getSupportedSizes($model));
    }

    /**
     * @throws \Throwable whatever nr-llm raises: missing key, budget exceeded, provider errors
     */
    public function generate(string $prompt, string $configuration, string $size, int $backendUserUid): GeneratedImage
    {
        $model = $this->modelFor($configuration);
        $systemPrompt = trim($this->imageService->getConfigurationSystemPrompt($configuration));
        $result = $this->imageService->generate(
            $systemPrompt === '' ? $prompt : $systemPrompt . "\n\n" . $prompt,
            new ImageGenerationOptions(
                model: $model,
                size: $size,
                quality: null,
                style: null,
                format: null,
                configuration: $configuration,
                beUserUid: $backendUserUid > 0 ? $backendUserUid : null,
            ),
        );

        $binary = $result->getBinaryContent() ?? $result->downloadFromUrl();
        if (!is_string($binary) || $binary === '') {
            throw new \RuntimeException('The generated image could not be downloaded.', 1752910201);
        }

        return new GeneratedImage($binary, $result->model);
    }
}

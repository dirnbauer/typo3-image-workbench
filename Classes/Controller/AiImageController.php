<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Netresearch\NrLlm\Exception\BudgetExceededException;
use Netresearch\NrLlm\Exception\GuardrailApprovalRequiredException;
use Netresearch\NrLlm\Exception\GuardrailPolicyException;
use Netresearch\NrLlm\Exception\GuardrailViolationException;
use Netresearch\NrLlm\Specialized\Exception\ServiceUnavailableException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;
use Webconsulting\ImageWorkbench\Service\AiImageGenerator;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;
use Webconsulting\ImageWorkbench\Service\ImagePersistenceService;
use Webconsulting\ImageWorkbench\Service\SavedFileDescriber;

/**
 * Generates a new image from a prompt and stores it as a PNG next to the
 * image that is open in the editor.
 *
 * Which nr-llm configuration pays comes from the user TSconfig of the
 * editor's backend group, never from the request.
 */
#[AsController]
final readonly class AiImageController
{
    use BackendContextTrait;

    public function __construct(
        private EditableImageFinder $images,
        private AiImageGenerator $generator,
        private ImagePersistenceService $persistence,
        private SavedFileDescriber $describer,
        private LoggerInterface $logger,
    ) {}

    public function generate(ServerRequestInterface $request): ResponseInterface
    {
        $settings = WorkbenchSettings::fromBackendUser($this->backendUser());
        if (!$settings->enabled || !$settings->aiEnabled) {
            return $this->failure('error.aiDisabled', 403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $prompt = self::stringParameter($body, 'prompt');
        $length = mb_strlen($prompt);
        if ($length < AiImageGenerator::PROMPT_MIN_LENGTH || $length > AiImageGenerator::PROMPT_MAX_LENGTH) {
            return $this->failure('error.promptLength', 400, [
                'min' => AiImageGenerator::PROMPT_MIN_LENGTH,
                'max' => AiImageGenerator::PROMPT_MAX_LENGTH,
            ]);
        }

        $source = $this->images->find(self::stringParameter($body, 'target'));
        if ($source === null) {
            return $this->failure('error.notFound', 404);
        }
        if (!$source->checkActionPermission('read') || !$source->getParentFolder()->checkActionPermission('write')) {
            return $this->failure('error.accessDenied', 403);
        }
        if (!$this->generator->isAvailable()) {
            return $this->failure('error.aiUnavailable', 503);
        }

        $size = self::stringParameter($body, 'size') ?: $settings->aiDefaultSize;
        if (!in_array($size, $this->generator->sizesFor($this->generator->modelFor($settings->aiConfiguration)), true)) {
            return $this->failure('error.size', 400);
        }

        try {
            $image = $this->generator->generate(
                $prompt,
                $settings->aiConfiguration,
                $size,
                $this->backendUser()->getUserId() ?? 0,
            );
            $saved = $this->persistence->saveCopy(
                $source,
                $image->binary,
                $source->getNameWithoutExtension() . '-ai-' . new \DateTimeImmutable()->format('Ymd-His'),
                'png',
            );
        } catch (BudgetExceededException $exception) {
            return $this->failure('error.budgetExceeded', 429, detail: $exception->getMessage());
        } catch (GuardrailViolationException|GuardrailPolicyException|GuardrailApprovalRequiredException $exception) {
            return $this->failure('error.promptRejected', 422, detail: $exception->getMessage());
        } catch (ServiceUnavailableException) {
            return $this->failure('error.aiUnavailable', 503);
        } catch (\Throwable $exception) {
            $this->logger->error('Image generation for {file} failed: {message}', [
                'file' => $source->getCombinedIdentifier(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            // Provider errors can quote request details; only administrators see them.
            return $this->failure(
                'error.generationFailed',
                502,
                detail: $this->backendUser()->isAdmin() ? $exception->getMessage() : '',
            );
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->label('ai.generated', ['name' => $saved->getName(), 'model' => $image->model]),
            'file' => $this->describer->describe(
                $saved,
                GeneralUtility::sanitizeLocalUrl(self::stringParameter($body, 'returnUrl'), $request),
            ),
            'model' => $image->model,
        ]);
    }

    /**
     * @param array<string, string|int> $arguments
     */
    private function failure(string $key, int $status, array $arguments = [], string $detail = ''): ResponseInterface
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->label($key, $arguments),
            'detail' => $detail,
        ], $status);
    }
}

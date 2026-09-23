<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;
use Webconsulting\ImageWorkbench\Enum\SaveMode;
use Webconsulting\ImageWorkbench\Service\EditableImageFinder;
use Webconsulting\ImageWorkbench\Service\ImagePersistenceService;
use Webconsulting\ImageWorkbench\Service\SavedFileDescriber;

/**
 * Writes the edited canvas back: as a new file next to the original by
 * default, over the original only when the request asks for it.
 */
#[AsController]
final readonly class SaveController
{
    use BackendContextTrait;

    public function __construct(
        private EditableImageFinder $images,
        private ImagePersistenceService $persistence,
        private SavedFileDescriber $describer,
        private LoggerInterface $logger,
    ) {}

    public function save(ServerRequestInterface $request): ResponseInterface
    {
        if (!WorkbenchSettings::fromBackendUser($this->backendUser())->enabled) {
            return $this->failure('error.accessDenied', 403);
        }

        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];
        $mode = SaveMode::fromRequestValue($body['mode'] ?? null);

        $source = $this->images->find(self::stringParameter($body, 'target'));
        if ($source === null) {
            return $this->failure('error.notFound', 404);
        }
        if (!$source->checkActionPermission('read')) {
            return $this->failure('error.accessDenied', 403);
        }

        $binary = $this->decodeDataUrl(self::stringParameter($body, 'image'));
        if ($binary === null) {
            return $this->failure('error.invalidImage', 400);
        }

        try {
            $saved = match ($mode) {
                SaveMode::Overwrite => $this->persistence->overwrite($source, $binary),
                SaveMode::Copy => $this->persistence->saveCopy($source, $binary, self::stringParameter($body, 'filename')),
            };
        } catch (\Throwable $exception) {
            $this->logger->error('Saving {file} ({mode}) failed: {message}', [
                'file' => $source->getCombinedIdentifier(),
                'mode' => $mode->value,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return $this->failure('error.saveFailed', 500, $exception->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => $this->label(
                $mode === SaveMode::Overwrite ? 'save.overwritten' : 'save.copied',
                ['name' => $saved->getName()],
            ),
            'file' => $this->describer->describe(
                $saved,
                GeneralUtility::sanitizeLocalUrl(self::stringParameter($body, 'returnUrl'), $request),
            ),
        ]);
    }

    /**
     * The canvas export arrives as a data: URL; only its base64 payload counts.
     */
    private function decodeDataUrl(string $dataUrl): ?string
    {
        if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,(?<payload>.+)$/is', $dataUrl, $match) !== 1) {
            return null;
        }
        $binary = base64_decode($match['payload'], true);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    private function failure(string $key, int $status, string $detail = ''): ResponseInterface
    {
        return new JsonResponse([
            'success' => false,
            'message' => $this->label($key),
            'detail' => $detail,
        ], $status);
    }
}

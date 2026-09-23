<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Controller;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Localization\LanguageService;

/**
 * The backend user and the workbench labels, for the controllers of this
 * extension. Labels live in the `image_workbench.messages` translation
 * domain (Resources/Private/Language/locallang.xlf).
 */
trait BackendContextTrait
{
    private function backendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @param array<string, string|int> $arguments ICU placeholders, e.g. ['name' => 'photo.jpg']
     */
    private function label(string $key, array $arguments = []): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if (!$languageService instanceof LanguageService) {
            return $key;
        }

        return (string)($languageService->translate($key, 'image_workbench.messages', $arguments) ?? $key);
    }

    /**
     * @param array<array-key, mixed> $parameters
     */
    private static function stringParameter(array $parameters, string $name): string
    {
        $value = $parameters[$name] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}

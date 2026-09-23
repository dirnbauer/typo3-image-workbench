<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Configuration;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The user TSconfig below `options.imageWorkbench`, read once and typed.
 *
 * Every endpoint reads its settings from here instead of trusting what the
 * editor page sends back, so a request cannot switch itself to another
 * nr-llm configuration or re-enable a panel its backend group has disabled.
 */
final readonly class WorkbenchSettings
{
    /** The editor tabs this workbench offers, in their display order. */
    public const array TABS = ['adjust', 'finetune', 'filters', 'annotate', 'resize', 'watermark'];

    private const array DEFAULT_TABS = ['adjust', 'finetune', 'filters', 'annotate', 'resize'];

    private const string DEFAULT_CONFIGURATION = 'image-workbench';

    private const string CONFIGURATION_PATTERN = '/^[a-z0-9][a-z0-9._-]{1,63}$/i';

    /**
     * @param list<value-of<self::TABS>> $tabs
     * @param list<array{label: non-empty-string, width: positive-int, height: positive-int}> $cropPresets
     */
    public function __construct(
        public bool $enabled = true,
        public array $tabs = self::DEFAULT_TABS,
        public array $cropPresets = [],
        public bool $aiEnabled = true,
        public string $aiConfiguration = self::DEFAULT_CONFIGURATION,
        public string $aiDefaultSize = '1024x1024',
    ) {}

    public static function fromBackendUser(BackendUserAuthentication $backendUser): self
    {
        $options = $backendUser->getTSConfig()['options.']['imageWorkbench.'] ?? null;
        $options = is_array($options) ? $options : [];
        $ai = is_array($options['ai.'] ?? null) ? $options['ai.'] : [];

        $configuration = trim(self::string($ai['configuration'] ?? null, self::DEFAULT_CONFIGURATION));

        return new self(
            enabled: self::flag($options['enable'] ?? null),
            tabs: self::tabs(self::string($options['tabs'] ?? null, implode(',', self::DEFAULT_TABS))),
            cropPresets: self::cropPresets(self::string($options['cropPresets'] ?? null, '')),
            // An identifier nr-llm would never accept switches generation off
            // instead of failing on every request.
            aiEnabled: self::flag($ai['enable'] ?? null) && preg_match(self::CONFIGURATION_PATTERN, $configuration) === 1,
            aiConfiguration: $configuration,
            aiDefaultSize: trim(self::string($ai['defaultSize'] ?? null, '1024x1024')),
        );
    }

    /**
     * The editor configuration handed to the page as JSON.
     *
     * @return array{tabs: list<string>, cropPresets: list<array{label: string, width: int, height: int}>}
     */
    public function editorConfiguration(): array
    {
        return [
            'tabs' => $this->tabs,
            'cropPresets' => $this->cropPresets,
        ];
    }

    private static function flag(mixed $value): bool
    {
        // Unset means "on": both switches default to enabled.
        return $value === null || (bool)$value;
    }

    private static function string(mixed $value, string $default): string
    {
        return is_scalar($value) ? (string)$value : $default;
    }

    /**
     * @return list<value-of<self::TABS>>
     */
    private static function tabs(string $list): array
    {
        $requested = GeneralUtility::trimExplode(',', strtolower($list), true);

        return array_values(array_filter(
            self::TABS,
            static fn(string $tab): bool => in_array($tab, $requested, true),
        ));
    }

    /**
     * "Story=9:16, Social=1:1" becomes two presets; malformed entries are
     * skipped rather than breaking the editor.
     *
     * @return list<array{label: non-empty-string, width: positive-int, height: positive-int}>
     */
    private static function cropPresets(string $list): array
    {
        $presets = [];
        foreach (GeneralUtility::trimExplode(',', $list, true) as $entry) {
            if (preg_match('/^(?<label>[^=]+?)\s*=\s*(?<width>\d{1,4})\s*:\s*(?<height>\d{1,4})$/', $entry, $match) !== 1) {
                continue;
            }
            $label = trim($match['label']);
            $width = (int)$match['width'];
            $height = (int)$match['height'];
            if ($label === '' || $width < 1 || $height < 1) {
                continue;
            }
            $presets[] = ['label' => $label, 'width' => $width, 'height' => $height];
        }

        return $presets;
    }
}

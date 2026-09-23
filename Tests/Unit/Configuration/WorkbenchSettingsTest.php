<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use Webconsulting\ImageWorkbench\Configuration\WorkbenchSettings;

/**
 * User TSconfig is free text an administrator types; these cases pin down
 * what the workbench makes of it, including what it refuses.
 */
final class WorkbenchSettingsTest extends TestCase
{
    #[Test]
    public function withoutTsConfigEverythingIsOnWithTheDefaults(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([]));

        self::assertTrue($settings->enabled);
        self::assertSame(['adjust', 'finetune', 'filters', 'annotate', 'resize'], $settings->tabs);
        self::assertSame([], $settings->cropPresets);
        self::assertTrue($settings->aiEnabled);
        self::assertSame('image-workbench', $settings->aiConfiguration);
        self::assertSame('1024x1024', $settings->aiDefaultSize);
    }

    #[Test]
    public function bothSwitchesCanBeTurnedOff(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([
            'enable' => '0',
            'ai.' => ['enable' => '0'],
        ]));

        self::assertFalse($settings->enabled);
        self::assertFalse($settings->aiEnabled);
    }

    #[Test]
    public function tabsKeepTheEditorOrderAndDropUnknownNames(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([
            'tabs' => 'resize, Watermark,unknown ,adjust',
        ]));

        self::assertSame(['adjust', 'resize', 'watermark'], $settings->tabs);
    }

    #[Test]
    public function cropPresetsAreParsedAndMalformedEntriesSkipped(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([
            'cropPresets' => 'Story=9:16, Social = 1:1, broken, Zero=0:5, Wide=16:9',
        ]));

        self::assertSame([
            ['label' => 'Story', 'width' => 9, 'height' => 16],
            ['label' => 'Social', 'width' => 1, 'height' => 1],
            ['label' => 'Wide', 'width' => 16, 'height' => 9],
        ], $settings->cropPresets);
        self::assertSame(['tabs' => $settings->tabs, 'cropPresets' => $settings->cropPresets], $settings->editorConfiguration());
    }

    #[Test]
    public function anIdentifierNrLlmWouldRefuseSwitchesGenerationOff(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([
            'ai.' => ['configuration' => '../other config'],
        ]));

        self::assertFalse($settings->aiEnabled);
    }

    #[Test]
    public function theConfiguredIdentifierAndSizeAreTaken(): void
    {
        $settings = WorkbenchSettings::fromBackendUser($this->user([
            'ai.' => ['configuration' => 'marketing-images', 'defaultSize' => ' 1536x1024 '],
        ]));

        self::assertTrue($settings->aiEnabled);
        self::assertSame('marketing-images', $settings->aiConfiguration);
        self::assertSame('1536x1024', $settings->aiDefaultSize);
    }

    /**
     * @param array<string, mixed> $options the options.imageWorkbench. branch
     */
    private function user(array $options): BackendUserAuthentication
    {
        $user = self::createStub(BackendUserAuthentication::class);
        $user->method('getTSConfig')->willReturn(
            $options === [] ? [] : ['options.' => ['imageWorkbench.' => $options]],
        );

        return $user;
    }
}

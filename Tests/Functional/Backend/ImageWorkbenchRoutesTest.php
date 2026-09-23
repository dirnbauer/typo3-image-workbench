<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Template\Components\ActionGroup;
use TYPO3\CMS\Backend\Template\Components\Buttons\LinkButton;
use TYPO3\CMS\Backend\Template\Components\ComponentGroup;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\Event\ProcessFileListActionsEvent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\ImageWorkbench\Controller\AiImageController;
use Webconsulting\ImageWorkbench\Controller\EditorController;
use Webconsulting\ImageWorkbench\Controller\ImageController;
use Webconsulting\ImageWorkbench\Controller\SaveController;
use Webconsulting\ImageWorkbench\EventListener\AddEditImageFileListAction;

/**
 * Boots the backend routes of the extension against a real FAL storage:
 * the route definitions are registered, their targets resolve out of the
 * container, the editor renders for an image an editor may write, and a
 * save writes a second file next to the original instead of overwriting
 * it.
 *
 * Everything here goes through FAL permission checks, which is the point —
 * the editor is only ever as safe as those checks.
 */
final class ImageWorkbenchRoutesTest extends FunctionalTestCase
{
    private const string FILE_NAME = 'workbench-fixture.png';

    protected array $coreExtensionsToLoad = ['filelist'];

    protected array $testExtensionsToLoad = [
        'netresearch/nr-vault',
        'netresearch/nr-llm',
        'webconsulting/image-workbench',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->loginAs(1);

        $fileadmin = Environment::getPublicPath() . '/fileadmin';
        GeneralUtility::mkdir_deep($fileadmin);
        GeneralUtility::writeFile($fileadmin . '/' . self::FILE_NAME, $this->pngBinary(), true);
        GeneralUtility::writeFile($fileadmin . '/notes.txt', 'plain text', true);
    }

    #[Test]
    public function bothRouteFilesAreRegisteredWithTheirTargets(): void
    {
        $routes = $this->get(Router::class)->getRoutes();

        $targets = [];
        foreach ($routes as $identifier => $route) {
            if (str_contains((string)$identifier, 'image_workbench_')) {
                self::assertInstanceOf(SymfonyRoute::class, $route);
                $targets[(string)$identifier] = [$route->getPath(), $route->getOption('target')];
            }
        }
        ksort($targets);

        self::assertSame([
            'ajax_image_workbench_generate' => ['/ajax/image-workbench/generate', AiImageController::class . '::generate'],
            'ajax_image_workbench_save' => ['/ajax/image-workbench/save', SaveController::class . '::save'],
            'image_workbench_edit' => ['/image-workbench/edit', EditorController::class . '::edit'],
            'image_workbench_source' => ['/image-workbench/source', ImageController::class . '::source'],
        ], $targets);
    }

    #[Test]
    public function everyRouteTargetIsResolvableFromTheContainer(): void
    {
        foreach ([EditorController::class, ImageController::class, SaveController::class, AiImageController::class] as $class) {
            self::assertInstanceOf($class, $this->get($class), $class . ' must be a public service');
        }
    }

    #[Test]
    public function theEditorRendersInsideTheModuleLayoutWithItsLabels(): void
    {
        $response = $this->get(EditorController::class)->edit($this->request([
            'target' => $this->fileIdentifier(),
            'returnUrl' => '/typo3/module/file/list',
        ]));
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('<h1>Edit ' . self::FILE_NAME . '</h1>', $body);
        self::assertStringContainsString('data-image-workbench', $body);
        self::assertStringContainsString('data-filename="' . self::FILE_NAME . '"', $body);
        self::assertStringContainsString('data-extension="png"', $body);
        self::assertStringContainsString('image-workbench/source', $body);
        self::assertStringContainsString('data-image-workbench-action="save-copy"', $body);
        self::assertStringContainsString('data-image-workbench-action="overwrite"', $body);
        self::assertStringContainsString('data-image-workbench-close="true"', $body);
        self::assertStringContainsString('module-docheader', $body);
    }

    #[Test]
    public function aGermanEditorGetsTheGermanLabels(): void
    {
        $this->loginAs(4);

        $body = (string)$this->get(EditorController::class)->edit($this->request([
            'target' => $this->fileIdentifier(),
        ]))->getBody();

        // Approved (state="final") XLIFF 2.0 targets only: a "translated"
        // state is ignored while requireApprovedLocalizations is on.
        self::assertStringContainsString('<h1>' . self::FILE_NAME . ' bearbeiten</h1>', $body);
        self::assertStringContainsString('Als Kopie speichern', $body);
    }

    #[Test]
    public function administratorsSeeHowToSetUpGenerationWhileNrLlmHasNoKey(): void
    {
        $body = (string)$this->get(EditorController::class)->edit($this->request([
            'target' => $this->fileIdentifier(),
        ]))->getBody();

        self::assertStringContainsString('AI generation is not set up', $body);
        self::assertStringNotContainsString('data-image-workbench-generate', $body);
    }

    #[Test]
    public function theGenerationPanelDisappearsWhenTheGroupSwitchedItOff(): void
    {
        $this->loginAs(3);

        $body = (string)$this->get(EditorController::class)->edit($this->request([
            'target' => $this->fileIdentifier(),
        ]))->getBody();

        self::assertStringNotContainsString('AI generation is not set up', $body);
        self::assertStringNotContainsString('image-workbench-with-panel', $body);
    }

    #[Test]
    public function aFileTheEditorCannotOpenGetsANotFoundPage(): void
    {
        $response = $this->get(EditorController::class)->edit($this->request(['target' => '1:/notes.txt']));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('This image cannot be edited', (string)$response->getBody());
    }

    #[Test]
    public function aMissingFileGetsANotFoundPage(): void
    {
        $response = $this->get(EditorController::class)->edit($this->request(['target' => '1:/does-not-exist.png']));

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function aGroupWithTheWorkbenchSwitchedOffIsRefused(): void
    {
        $this->loginAs(2);

        $response = $this->get(EditorController::class)->edit($this->request(['target' => $this->fileIdentifier()]));

        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('You are not allowed to change this file.', (string)$response->getBody());
    }

    #[Test]
    public function savingACopyWritesASecondFileNextToTheOriginal(): void
    {
        $before = $this->folderFileNames();

        $response = $this->save([
            'target' => $this->fileIdentifier(),
            'mode' => 'copy',
            'filename' => 'edited-fixture',
            'image' => $this->dataUrl($this->pngBinary(16, 9)),
        ]);
        $payload = $this->json($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success']);
        self::assertSame('Saved as edited-fixture.png.', $payload['message']);

        $after = $this->folderFileNames();
        self::assertCount(count($before) + 1, $after);
        self::assertContains(self::FILE_NAME, $after, 'The original must survive a copy');
        self::assertContains('edited-fixture.png', $after);
        self::assertIsArray($payload['file']);
        self::assertStringContainsString('image-workbench/edit', (string)$payload['file']['editUrl']);
    }

    #[Test]
    public function overwritingReplacesTheContentsInPlace(): void
    {
        $before = $this->folderFileNames();
        $replacement = $this->pngBinary(20, 10);

        $response = $this->save([
            'target' => $this->fileIdentifier(),
            'mode' => 'overwrite',
            'image' => $this->dataUrl($replacement),
        ]);

        self::assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        self::assertSame($before, $this->folderFileNames());
        $file = $this->get(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($this->fileIdentifier());
        self::assertInstanceOf(File::class, $file);
        self::assertSame($replacement, $file->getContents());
    }

    #[Test]
    public function invalidImageDataIsRejectedWithoutWriting(): void
    {
        $before = $this->folderFileNames();

        $response = $this->save([
            'target' => $this->fileIdentifier(),
            'mode' => 'copy',
            'image' => 'not-a-data-url',
        ]);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame($before, $this->folderFileNames());
    }

    #[Test]
    public function aNonImageTargetIsNeverWritten(): void
    {
        $before = $this->folderFileNames();

        $response = $this->save([
            'target' => '1:/notes.txt',
            'mode' => 'copy',
            'image' => $this->dataUrl($this->pngBinary()),
        ]);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame($before, $this->folderFileNames());
    }

    #[Test]
    public function theSourceRouteServesTheImageWithItsRealType(): void
    {
        $response = $this->get(ImageController::class)->source($this->request(['target' => $this->fileIdentifier()]));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('image/png', $response->getHeaderLine('Content-Type'));
        self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame($this->pngBinary(), (string)$response->getBody());
    }

    #[Test]
    public function theSourceRouteNeverServesOtherFileTypes(): void
    {
        $response = $this->get(ImageController::class)->source($this->request(['target' => '1:/notes.txt']));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string)$response->getBody());
    }

    #[Test]
    public function generationRejectsATooShortPrompt(): void
    {
        $response = $this->generate(['target' => $this->fileIdentifier(), 'prompt' => 'short']);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('The description must contain between 10 and 8000 characters.', $this->json($response)['message']);
    }

    #[Test]
    public function generationReportsThatNrLlmIsNotSetUp(): void
    {
        $response = $this->generate([
            'target' => $this->fileIdentifier(),
            'prompt' => 'A lighthouse at dusk, seen from the beach',
        ]);

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('AI generation is not available right now.', $this->json($response)['message']);
    }

    #[Test]
    public function generationIsRefusedWhenTheGroupSwitchedItOff(): void
    {
        $this->loginAs(3);

        $response = $this->generate([
            'target' => $this->fileIdentifier(),
            'prompt' => 'A lighthouse at dusk, seen from the beach',
        ]);

        self::assertSame(403, $response->getStatusCode());
    }

    #[Test]
    public function theFileListOffersEditImageForImagesOnly(): void
    {
        $listener = $this->get(AddEditImageFileListAction::class);

        $imageEvent = $this->fileListEvent($this->fileIdentifier());
        $listener($imageEvent);
        $action = $imageEvent->getAction('imageWorkbench', ActionGroup::secondary);
        self::assertInstanceOf(LinkButton::class, $action);
        self::assertSame('Edit image', $action->getTitle());
        self::assertStringContainsString('image-workbench/edit', $action->getHref());
        self::assertStringContainsString('returnUrl=', $action->getHref());

        $textEvent = $this->fileListEvent('1:/notes.txt');
        $listener($textEvent);
        self::assertFalse($textEvent->hasAction('imageWorkbench'));
    }

    private function loginAs(int $uid): void
    {
        $this->setUpBackendUser($uid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    /**
     * @param array<string, string> $body
     */
    private function save(array $body): ResponseInterface
    {
        return $this->get(SaveController::class)->save($this->request()->withParsedBody($body));
    }

    /**
     * @param array<string, string> $body
     */
    private function generate(array $body): ResponseInterface
    {
        return $this->get(AiImageController::class)->generate($this->request()->withParsedBody($body));
    }

    /**
     * @return array<string, mixed>
     */
    private function json(ResponseInterface $response): array
    {
        $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }

    private function fileListEvent(string $combinedIdentifier): ProcessFileListActionsEvent
    {
        $file = $this->get(ResourceFactory::class)->getFileObjectFromCombinedIdentifier($combinedIdentifier);
        self::assertInstanceOf(File::class, $file);

        return new ProcessFileListActionsEvent(
            new ComponentGroup('primary'),
            new ComponentGroup('secondary'),
            $file,
            new ServerRequest('https://example.com/typo3/module/file/list?id=1%3A%2F'),
        );
    }

    /**
     * @param array<string, string> $queryParams
     */
    private function request(array $queryParams = []): ServerRequest
    {
        $route = new Route('/image-workbench/edit', ['packageName' => 'webconsulting/image-workbench']);

        $serverParams = [
            'HTTP_HOST' => 'example.com',
            'HTTPS' => 'on',
            'SCRIPT_NAME' => '/typo3/index.php',
            'REQUEST_URI' => '/typo3/image-workbench/edit',
        ];

        return new ServerRequest('https://example.com/typo3/image-workbench/edit', 'POST', 'php://input', [], $serverParams)
            ->withAttribute('applicationType', 2 /* BE */)
            ->withAttribute('route', $route)
            ->withAttribute('normalizedParams', new NormalizedParams($serverParams, [], '', ''))
            ->withQueryParams($queryParams);
    }

    private function fileIdentifier(): string
    {
        return '1:/' . self::FILE_NAME;
    }

    private function dataUrl(string $binary): string
    {
        return 'data:image/png;base64,' . base64_encode($binary);
    }

    /**
     * @return list<string>
     */
    private function folderFileNames(): array
    {
        $folder = $this->get(StorageRepository::class)->getDefaultStorage()?->getRootLevelFolder();
        self::assertNotNull($folder);

        $names = [];
        foreach ($folder->getFiles() as $file) {
            $names[] = $file->getName();
        }
        sort($names);

        return $names;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function pngBinary(int $width = 8, int $height = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(\GdImage::class, $image);
        $color = imagecolorallocate($image, 12, 34, 56);
        self::assertIsInt($color);
        imagefill($image, 0, 0, $color);
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        self::assertIsString($binary);

        return $binary;
    }
}

<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Tests\Functional\Backend;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Routing\Route as SymfonyRoute;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Resource\Exception\InsufficientFileAccessPermissionsException;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\ImageWorkbench\Controller\AiImageController;
use Webconsulting\ImageWorkbench\Controller\EditorController;
use Webconsulting\ImageWorkbench\Controller\ImageController;
use Webconsulting\ImageWorkbench\Controller\SaveController;

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
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(\TYPO3\CMS\Core\Localization\LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        $fileadmin = Environment::getPublicPath() . '/fileadmin';
        GeneralUtility::mkdir_deep($fileadmin);
        GeneralUtility::writeFile($fileadmin . '/' . self::FILE_NAME, $this->pngBinary(), true);
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

        self::assertSame([
            'ajax_image_workbench_generate' => ['/ajax/image-workbench/generate', AiImageController::class . '::generate'],
            'ajax_image_workbench_save' => ['/ajax/image-workbench/save', SaveController::class . '::save'],
            'image_workbench_edit' => ['/image-workbench/edit', EditorController::class . '::edit'],
            'image_workbench_source' => ['/image-workbench/source', ImageController::class . '::source'],
        ], $this->sorted($targets));
    }

    #[Test]
    public function everyRouteTargetIsResolvableFromTheContainer(): void
    {
        foreach ([EditorController::class, ImageController::class, SaveController::class] as $class) {
            self::assertInstanceOf($class, $this->get($class), $class . ' must be a public service');
        }
    }

    #[Test]
    public function theEditorRouteRendersForAnEditableImage(): void
    {
        $response = $this->get(EditorController::class)->edit($this->request([
            'target' => $this->fileIdentifier(),
            'returnUrl' => '/typo3/module/file/list',
        ]));
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('id="image-workbench"', $body);
        self::assertStringContainsString('data-filename="' . self::FILE_NAME . '"', $body);
        self::assertStringContainsString('data-extension="png"', $body);
        self::assertStringContainsString('image-workbench/source', $body);
    }

    #[Test]
    public function theEditorRouteRefusesAFileTheEditorCannotOpen(): void
    {
        GeneralUtility::writeFile(Environment::getPublicPath() . '/fileadmin/notes.txt', 'plain text', true);
        $this->get(StorageRepository::class)->getDefaultStorage()?->getRootLevelFolder()->getFiles();

        $this->expectException(InsufficientFileAccessPermissionsException::class);
        $this->expectExceptionCode(1752910001);

        $this->get(EditorController::class)->edit($this->request(['target' => '1:/notes.txt']));
    }

    #[Test]
    public function theEditorRouteRefusesAFileThatDoesNotExist(): void
    {
        $this->expectException(ResourceDoesNotExistException::class);

        $this->get(EditorController::class)->edit($this->request(['target' => '1:/does-not-exist.png']));
    }

    #[Test]
    public function savingACopyWritesASecondFileNextToTheOriginal(): void
    {
        $before = $this->folderFileNames();

        $response = $this->get(SaveController::class)->save(
            $this->request()->withParsedBody([
                'target' => $this->fileIdentifier(),
                'mode' => 'copy',
                'filename' => 'edited-fixture',
                'image' => 'data:image/png;base64,' . base64_encode($this->pngBinary(16, 9)),
            ]),
        );

        $payload = json_decode((string)$response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($payload['success'], (string)($payload['message'] ?? ''));

        $after = $this->folderFileNames();
        self::assertCount(count($before) + 1, $after);
        self::assertContains(self::FILE_NAME, $after, 'The original must survive a copy');
        self::assertContains('edited-fixture.png', $after);
    }

    #[Test]
    public function invalidImageDataIsRejectedWithoutWriting(): void
    {
        $before = $this->folderFileNames();

        $response = $this->get(SaveController::class)->save(
            $this->request()->withParsedBody([
                'target' => $this->fileIdentifier(),
                'mode' => 'copy',
                'image' => 'not-a-data-url',
            ]),
        );

        self::assertSame(400, $response->getStatusCode());
        self::assertSame($before, $this->folderFileNames());
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

        return (new ServerRequest('https://example.com/typo3/image-workbench/edit', 'POST', 'php://input', [], $serverParams))
            ->withAttribute('applicationType', 2 /* BE */)
            ->withAttribute('route', $route)
            ->withAttribute('normalizedParams', new NormalizedParams($serverParams, [], '', ''))
            ->withQueryParams($queryParams);
    }

    private function fileIdentifier(): string
    {
        return '1:/' . self::FILE_NAME;
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
     * @param array<string, array{string, mixed}> $targets
     * @return array<string, array{string, mixed}>
     */
    private function sorted(array $targets): array
    {
        ksort($targets);

        return $targets;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function pngBinary(int $width = 8, int $height = 6): string
    {
        $image = imagecreatetruecolor($width, $height);
        self::assertInstanceOf(\GdImage::class, $image);
        imagefill($image, 0, 0, (int)imagecolorallocate($image, 12, 34, 56));
        ob_start();
        imagepng($image);

        return (string)ob_get_clean();
    }
}

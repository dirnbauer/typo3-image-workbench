<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\ImageWorkbench\Service\ImageFormatConverter;

/**
 * The editor's canvas and the AI provider both hand back PNG, whatever the
 * source was, so this conversion is what keeps a file's contents and its
 * extension honest. Every case here works on real binaries produced by GD —
 * a stubbed "image" would prove nothing about the encoders.
 */
final class ImageFormatConverterTest extends TestCase
{
    private ImageFormatConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ImageFormatConverter();
    }

    /**
     * @return iterable<string, array{string, string, string}> source format, target extension, expected MIME
     */
    public static function conversions(): iterable
    {
        yield 'png into a jpg' => ['png', 'jpg', 'image/jpeg'];
        yield 'png into a jpeg' => ['png', 'jpeg', 'image/jpeg'];
        yield 'jpeg into a png' => ['jpeg', 'png', 'image/png'];
        yield 'jpeg into a webp' => ['jpeg', 'webp', 'image/webp'];
        yield 'webp into a png' => ['webp', 'png', 'image/png'];
    }

    #[DataProvider('conversions')]
    #[Test]
    public function dataIsReEncodedIntoTheFormatTheExtensionPromises(
        string $sourceFormat,
        string $targetExtension,
        string $expectedMime,
    ): void {
        if (($sourceFormat === 'webp' || $targetExtension === 'webp') && !function_exists('imagewebp')) {
            self::markTestSkipped('GD was built without WebP support.');
        }

        $converted = $this->converter->toExtension($this->image($sourceFormat), $targetExtension);

        self::assertSame($expectedMime, $this->mimeOf($converted));
        self::assertNotFalse(@imagecreatefromstring($converted), 'The result must still decode as an image');
    }

    #[Test]
    public function anUppercaseExtensionIsRecognised(): void
    {
        $converted = $this->converter->toExtension($this->image('png'), 'JPG');

        self::assertSame('image/jpeg', $this->mimeOf($converted));
    }

    #[Test]
    public function matchingDataIsReturnedByteForByte(): void
    {
        $png = $this->image('png');

        self::assertSame($png, $this->converter->toExtension($png, 'png'));
    }

    #[Test]
    public function jpgAndJpegAreTheSameTarget(): void
    {
        $jpeg = $this->image('jpeg');

        self::assertSame($jpeg, $this->converter->toExtension($jpeg, 'jpg'));
        self::assertSame($jpeg, $this->converter->toExtension($jpeg, 'jpeg'));
    }

    #[Test]
    public function anUnknownTargetExtensionIsRefused(): void
    {
        // .gif and .svg never reach the editor; writing a PNG under either
        // name would be exactly the mismatch this class exists to prevent.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1752910106);

        $this->converter->toExtension($this->image('png'), 'svg');
    }

    #[Test]
    public function onlyTheFourEditableFormatsAreSupported(): void
    {
        foreach (['jpg', 'JPEG', 'png', 'WebP'] as $extension) {
            self::assertTrue($this->converter->supports($extension), $extension);
        }
        foreach (['gif', 'svg', 'html', 'php', ''] as $extension) {
            self::assertFalse($this->converter->supports($extension), $extension);
        }
    }

    #[Test]
    public function transparencyOfAPngSurvivesTheRoundTrip(): void
    {
        $source = imagecreatetruecolor(4, 4);
        self::assertInstanceOf(\GdImage::class, $source);
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $transparent = imagecolorallocatealpha($source, 255, 0, 0, 127);
        self::assertIsInt($transparent);
        imagefill($source, 0, 0, $transparent);
        ob_start();
        imagepng($source);
        $transparentPng = ob_get_clean();
        self::assertIsString($transparentPng);

        // A PNG that is already a PNG is returned untouched, so force the
        // decode/encode path by going through WebP and back.
        if (!function_exists('imagewebp')) {
            self::markTestSkipped('GD was built without WebP support.');
        }
        $roundTripped = $this->converter->toExtension(
            $this->converter->toExtension($transparentPng, 'webp'),
            'png',
        );

        $image = imagecreatefromstring($roundTripped);
        self::assertInstanceOf(\GdImage::class, $image);
        $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
        self::assertGreaterThan(0, $alpha, 'The alpha channel must not be flattened away');
    }

    #[Test]
    public function nonImageDataIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1752910103);

        $this->converter->toExtension('this is not an image', 'png');
    }

    #[Test]
    public function anEmptyPayloadIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1752910103);

        $this->converter->toExtension('', 'jpg');
    }

    #[Test]
    public function truncatedImageDataIsRejected(): void
    {
        $truncated = substr($this->image('png'), 0, 40);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionCode(1752910104);

        $this->converter->toExtension($truncated, 'jpg');
    }

    #[Test]
    public function everySupportedExtensionMapsToAnImageMimeType(): void
    {
        foreach (ImageFormatConverter::MIME_BY_EXTENSION as $extension => $mime) {
            self::assertMatchesRegularExpression('/^[a-z]+$/', $extension);
            self::assertStringStartsWith('image/', $mime);
        }
    }

    /**
     * A tiny but real image in the given format.
     */
    private function image(string $format): string
    {
        $image = imagecreatetruecolor(8, 6);
        self::assertInstanceOf(\GdImage::class, $image);
        $color = imagecolorallocate($image, 12, 34, 56);
        self::assertIsInt($color);
        imagefill($image, 0, 0, $color);

        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image, null, 90),
        };
        $binary = ob_get_clean();
        self::assertIsString($binary);
        self::assertNotSame('', $binary);

        return $binary;
    }

    private function mimeOf(string $binary): string
    {
        $mime = new \finfo(FILEINFO_MIME_TYPE)->buffer($binary);
        self::assertIsString($mime);

        return $mime;
    }
}

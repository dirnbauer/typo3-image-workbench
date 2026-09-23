<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Tests\Unit\Enum;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webconsulting\ImageWorkbench\Enum\SaveMode;

final class SaveModeTest extends TestCase
{
    /**
     * @return iterable<string, array{mixed, SaveMode}>
     */
    public static function requestValues(): iterable
    {
        yield 'overwrite' => ['overwrite', SaveMode::Overwrite];
        yield 'copy' => ['copy', SaveMode::Copy];
        yield 'missing' => [null, SaveMode::Copy];
        yield 'unknown' => ['replace', SaveMode::Copy];
        yield 'wrong case' => ['Overwrite', SaveMode::Copy];
        yield 'array' => [['overwrite'], SaveMode::Copy];
    }

    #[DataProvider('requestValues')]
    #[Test]
    public function onlyAnExplicitOverwriteOverwrites(mixed $value, SaveMode $expected): void
    {
        self::assertSame($expected, SaveMode::fromRequestValue($value));
    }
}

<?php

declare(strict_types=1);

namespace Webconsulting\ImageWorkbench\Enum;

/**
 * How an edited image is written back.
 *
 * Copy is the default: anything that is not explicitly an overwrite writes a
 * new file next to the original.
 */
enum SaveMode: string
{
    case Copy = 'copy';
    case Overwrite = 'overwrite';

    public static function fromRequestValue(mixed $value): self
    {
        return is_string($value) ? self::tryFrom($value) ?? self::Copy : self::Copy;
    }
}

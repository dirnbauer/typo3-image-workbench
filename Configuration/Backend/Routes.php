<?php

declare(strict_types=1);

use Webconsulting\ImageWorkbench\Controller\EditorController;
use Webconsulting\ImageWorkbench\Controller\ImageController;

return [
    'image_workbench_edit' => [
        'path' => '/image-workbench/edit',
        'packageName' => 'webconsulting/image-workbench',
        'inheritAccessFromModule' => 'media_management',
        'target' => EditorController::class . '::edit',
    ],
    'image_workbench_source' => [
        'path' => '/image-workbench/source',
        'packageName' => 'webconsulting/image-workbench',
        'inheritAccessFromModule' => 'media_management',
        'target' => ImageController::class . '::source',
    ],
];

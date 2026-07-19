<?php

declare(strict_types=1);

use Webconsulting\ImageWorkbench\Controller\AiImageController;
use Webconsulting\ImageWorkbench\Controller\SaveController;

return [
    'image_workbench_save' => [
        'path' => '/image-workbench/save',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
        'target' => SaveController::class . '::save',
    ],
    'image_workbench_generate' => [
        'path' => '/image-workbench/generate',
        'methods' => ['POST'],
        'inheritAccessFromModule' => 'media_management',
        'target' => AiImageController::class . '::generate',
    ],
];

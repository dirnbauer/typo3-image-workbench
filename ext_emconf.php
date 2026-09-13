<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Image Workbench',
    'description' => 'Edit images in the TYPO3 v14 backend and generate alternatives through nr-llm with central usage and cost tracking.',
    'category' => 'be',
    'author' => 'webconsulting.at',
    'author_email' => 'office@webconsulting.at',
    'state' => 'beta',
    'version' => '0.2.0',
    'constraints' => [
        'depends' => [
            'php' => '8.4.0-8.99.99',
            'typo3' => '14.3.7-14.99.99',
            'nr_llm' => '0.34.0-0.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Webconsulting\\ImageWorkbench\\' => 'Classes/',
        ],
    ],
];

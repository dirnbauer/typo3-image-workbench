<?php

declare(strict_types=1);

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config->getFinder()
    ->in(__DIR__)
    ->exclude(['.Build', 'Build/node_modules', 'var', 'vendor'])
    ->append([
        __DIR__ . '/ext_emconf.php',
    ]);

return $config;

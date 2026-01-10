<?php

declare(strict_types=1);

use Sirix\CsFixerConfig\ConfigBuilder;

return ConfigBuilder::create()
    ->inDir(__DIR__.'/src')
    ->inDir(__DIR__ . '/tests')
    ->setRules([
        '@PHP8x2Migration' => true,
        'php_unit_test_class_requires_covers' => false,
        'php_unit_internal_class' => false,
        'phpdoc_to_comment' => false,
        'Gordinskiy/line_length_limit' => ['max_length' => 140],
    ])
    ->getConfig()
    ->setCacheFile(__DIR__ . '/runtime/.php-cs-fixer.cache')
    ->setUnsupportedPhpVersionAllowed(true)
;

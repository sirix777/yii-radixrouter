<?php

declare(strict_types=1);

return [
    'sirix/yii-radixrouter' => [
        'enableCache' => false,
        'saveToPhpFile' => true,
        'phpCachePath' => 'runtime/routes-cache.php',

        /**
         * Yii Framework encodes URLs differently than previous versions. If you are
         * migrating a project from older versions, you can set this value to `false`
         * to keep URLs encoded the same way.
         * Default `true` is RFC3986 compliant
         */
        'encodeRaw' => true,
        'scheme' => null,
        'host' => null,
    ],
];

<?php

declare(strict_types=1);


use Sirix\Router\RadixRouter\UrlGenerator;
use Yiisoft\Router\UrlGeneratorInterface;

/** @var array $params */

return [
    UrlGeneratorInterface::class => [
        'class' => UrlGenerator::class,
        '__construct()' => [
            'scheme' => $params['sirix/yii-radixrouter']['scheme'],
            'host' => $params['sirix/yii-radixrouter']['host'],
        ],
        'setEncodeRaw()' => [$params['sirix/yii-radixrouter']['encodeRaw']],
        'reset' => function () {
            $this->defaultArguments = [];
        },
    ],
];

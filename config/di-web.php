<?php

declare(strict_types=1);

use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Injector\Injector;
use Yiisoft\Router\UrlMatcherInterface;

/** @var array $params */

return [
    UrlMatcherInterface::class => static function (Injector $injector) use ($params) {
        $config = $params['sirix/yii-radixrouter'] ?? [];
        $enableCache = $config['enableCache'] ?? true;
        $encodeRaw = $config['encodeRaw'] ?? true;

        $arguments = ['config' => $config];
        if ($enableCache === false) {
            $arguments['cache'] = null;
        }

        $matcher = $injector->make(UrlMatcher::class, $arguments);
        $matcher->setEncodeRaw($encodeRaw);

        return $matcher;
    },
];

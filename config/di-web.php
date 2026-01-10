<?php

declare(strict_types=1);

use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Injector\Injector;
use Yiisoft\Router\UrlMatcherInterface;

/** @var array $params */

return [
    UrlMatcherInterface::class => static function (Injector $injector) use ($params) {
        $enableCache = $params['sirix/yii-radixrouter']['enableCache'] ?? true;
        $encodeRaw = $params['sirix/yii-radixrouter']['encodeRaw'] ?? true;

        $arguments = [];
        if ($enableCache === false) {
            $arguments['cache'] = null;
        }

        $matcher = $injector->make(UrlMatcher::class, $arguments);
        $matcher->setEncodeRaw($encodeRaw);

        return $matcher;
    },
];

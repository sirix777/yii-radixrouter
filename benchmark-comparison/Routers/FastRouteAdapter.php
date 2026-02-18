<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\BenchmarkComparison;

use Nyholm\Psr7\ServerRequest;
use RuntimeException;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteCollection;

class FastRouteAdapter implements RouterInterface
{
    private ?RouteCollector $collector = null;
    private ?UrlMatcher $matcher = null;

    public function mount(string $tmpFile): void
    {
    }

    public function adapt(array $routes): array
    {
        return $routes;
    }

    public function register(array $adaptedRoutes): void
    {
        $this->collector = new RouteCollector();

        foreach ($adaptedRoutes as $pattern) {
            $this->collector->addRoute(
                Route::get($pattern)
                    ->name($pattern)
                    ->action(function () { return 'ok'; })
            );
        }

        $collection = new RouteCollection($this->collector);
        $this->matcher = new UrlMatcher($collection);
    }

    public function lookup(string $path): void
    {
        if ($this->matcher === null) {
            throw new RuntimeException('Router not initialized');
        }

        $request = new ServerRequest('GET', $path);
        $result = $this->matcher->match($request);
        if (!$result->isSuccess()) {
            throw new RuntimeException("Route not found: $path");
        }
    }

    public static function details(): array
    {
        return [
            'name' => 'YiiFastRoute',
            'description' => 'Yii3 FastRoute adapter using yiisoft/router-fastroute',
        ];
    }
}

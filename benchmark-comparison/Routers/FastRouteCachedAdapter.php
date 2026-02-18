<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\BenchmarkComparison;

use Nyholm\Psr7\ServerRequest;
use RuntimeException;
use Yiisoft\Cache\File\FileCache;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteCollection;

class FastRouteCachedAdapter implements RouterInterface
{
    private ?RouteCollector $collector = null;
    private ?UrlMatcher $matcher = null;
    private string $cacheFile = '';

    public function mount(string $tmpFile): void
    {
        $this->cacheFile = $tmpFile;
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

        $this->matcher = new UrlMatcher(
            $collection,
            new Filecache($this->cacheFile),
            [UrlMatcher::CONFIG_CACHE_KEY => 'routes-cache']
        );
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
            'name' => 'YiiFastRouteCached',
            'description' => 'Yii3 FastRoute adapter with file caching',
        ];
    }
}

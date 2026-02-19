<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\BenchmarkComparison;

use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteCollection;

class YiiRadixRouterPhpCacheAdapter implements RouterInterface
{
    private ?RouteCollector $collector = null;
    private ?UrlMatcher $matcher = null;
    private string $cacheFile = '';

    public function mount(string $tmpFile): void
    {
        $this->cacheFile = $tmpFile . '.php';
    }

    public function adapt(array $routes): array
    {
        return $routes;
    }

    /**
     * @throws InvalidArgumentException
     */
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
            null,
            [
                UrlMatcher::CONFIG_USE_PHP_CACHE => true,
                UrlMatcher::CONFIG_PHP_CACHE_PATH => $this->cacheFile,
            ]
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

    /**
     * @throws InvalidArgumentException
     */
    public function match(ServerRequestInterface $request): void
    {
        if ($this->matcher === null) {
            throw new RuntimeException('Router not initialized');
        }
        $result = $this->matcher->match($request);
        if (!$result->isSuccess()) {
            throw new RuntimeException('Route not found: ' . $request->getUri()->getPath());
        }
    }

    public static function details(): array
    {
        return [
            'name' => 'YiiRadixRouterPhpCache',
            'description' => 'Yii3 RadixRouter adapter using native PHP array cache (opcache-friendly)',
        ];
    }
}

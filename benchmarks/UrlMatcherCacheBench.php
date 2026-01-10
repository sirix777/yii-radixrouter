<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\Benchmarks;

use Nyholm\Psr7\ServerRequest;
use Psr\SimpleCache\InvalidArgumentException;
use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;

use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

/**
 * @Iterations(5)
 * @Revs(1000)
 */
class UrlMatcherCacheBench
{
    private ?UrlMatcher $matcherNoCache = null;
    private ?UrlMatcher $matcherWithCache = null;
    private ?ServerRequest $request = null;

    /**
     * @throws InvalidArgumentException
     */
    public function setUp(): void
    {
        $routes = [];
        for ($i = 0; $i < 100; $i++) {
            $routes[] = Route::get("/route-$i")->name("route-$i");
        }
        $routes[] = Route::get('/blog/{slug}')->name('blog/view');

        $collector = new RouteCollector();
        foreach ($routes as $route) {
            $collector->addRoute($route);
        }
        $routeCollection = new RouteCollection($collector);

        $this->request = new ServerRequest('GET', '/blog/hello-world');

        // No cache
        $this->matcherNoCache = new UrlMatcher($routeCollection);

        // With cache (pre-warmed)
        $cache = new MemorySimpleCache();

        $this->matcherWithCache = new UrlMatcher($routeCollection, $cache);
        // Warm up the cache
        $this->matcherWithCache->match($this->request);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @throws InvalidArgumentException
     */
    public function benchMatchNoCache(): void
    {
        $this->matcherNoCache->match($this->request);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @throws InvalidArgumentException
     */
    public function benchMatchWithCache(): void
    {
        $this->matcherWithCache->match($this->request);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Iterations(5)
     * @Revs(100)
     * @throws InvalidArgumentException
     */
    public function benchInitializationNoCache(): void
    {
        $routes = [];
        for ($i = 0; $i < 100; $i++) {
            $routes[] = Route::get("/route-$i")->name("route-$i");
        }
        $collector = new RouteCollector();
        foreach ($routes as $route) {
            $collector->addRoute($route);
        }
        $matcher = new UrlMatcher(new RouteCollection($collector));
        $matcher->match($this->request);
    }

    /**
     * @BeforeMethods({"setUp"})
     * @Iterations(5)
     * @Revs(100)
     * @throws InvalidArgumentException
     */
    public function benchInitializationWithCache(): void
    {
        $routes = [];
        for ($i = 0; $i < 100; $i++) {
            $routes[] = Route::get("/route-$i")->name("route-$i");
        }
        $collector = new RouteCollector();
        foreach ($routes as $route) {
            $collector->addRoute($route);
        }

        static $cache = null;
        if ($cache === null) {
            $cache = new MemorySimpleCache();
        }

        $matcher = new UrlMatcher(new RouteCollection($collector), $cache);
        $matcher->match($this->request);
    }
}

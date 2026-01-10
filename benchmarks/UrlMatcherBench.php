<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\Benchmarks;

use Nyholm\Psr7\ServerRequest;
use Psr\SimpleCache\InvalidArgumentException;
use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;

/**
 * @Iterations(5)
 * @Revs(1000)
 */
class UrlMatcherBench
{
    private ?UrlMatcher $matcher = null;
    private ?ServerRequest $request = null;

    /**
     * @throws InvalidArgumentException
     */
    public function setUp(): void
    {
        $routes = [
            Route::get('/')->name('home'),
            Route::get('/blog')->name('blog/index'),
            Route::get('/blog/{slug}')->name('blog/view'),
            Route::get('/user/{id:\d+}')->name('user/view'),
            Route::post('/user/{id:\d+}')->name('user/update'),
        ];

        $collector = new RouteCollector();
        foreach ($routes as $route) {
            $collector->addRoute($route);
        }

        $this->matcher = new UrlMatcher(new RouteCollection($collector));
        $this->request = new ServerRequest('GET', '/blog/hello-world');
    }

    /**
     * @BeforeMethods({"setUp"})
     */
    public function benchMatch(): void
    {
        $this->matcher->match($this->request);
    }
}

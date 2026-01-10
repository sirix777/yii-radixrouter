<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\Benchmarks;

use Sirix\Router\RadixRouter\UrlGenerator;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;

/**
 * @Iterations(5)
 * @Revs(1000)
 */
class UrlGeneratorBench
{
    private ?UrlGenerator $generator = null;

    public function setUp(): void
    {
        $routes = [
            Route::get('/')->name('home'),
            Route::get('/blog')->name('blog/index'),
            Route::get('/blog/{slug}')->name('blog/view'),
            Route::get('/user/{id:\d+}')->name('user/view'),
        ];

        $collector = new RouteCollector();
        foreach ($routes as $route) {
            $collector->addRoute($route);
        }

        $this->generator = new UrlGenerator(new RouteCollection($collector));
    }

    /**
     * @BeforeMethods({"setUp"})
     */
    public function benchGenerate(): void
    {
        $this->generator->generate('blog/view', ['slug' => 'hello-world']);
    }
}

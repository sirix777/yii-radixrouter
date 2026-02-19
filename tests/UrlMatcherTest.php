<?php

declare(strict_types=1);

namespace Sirix\Router\RadixRouter\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Sirix\Router\RadixRouter\UrlMatcher;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlMatcherInterface;

use function file_put_contents;
use function is_file;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;
use function var_export;

final class UrlMatcherTest extends TestCase
{
    /**
     * @throws InvalidArgumentException
     */
    public function testDefaultsAreInResult(): void
    {
        $routes = [
            Route::get('/:name?')
                ->action(fn () => 1)
                ->defaults(['name' => 'test']),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $arguments);
        $this->assertSame('test', $arguments['name']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRoute(): void
    {
        $routes = [
            Route::get('/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithDifferentMethods(): void
    {
        $routes = [
            Route::methods(['GET', 'POST'], '/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/index');
        $request2 = new ServerRequest('POST', '/site/index');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithParam(): void
    {
        $routes = [
            Route::get('/site/post/:id')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/23');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('id', $arguments);
        $this->assertSame('23', $arguments['id']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithUrlencodedParam(): void
    {
        $routes = [
            Route::get('/site/post/:name1/:name2')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/with%20space/also%20space');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name1', $arguments);
        $this->assertArrayHasKey('name2', $arguments);
        $this->assertSame('with space', $arguments['name1']);
        $this->assertSame('also space', $arguments['name2']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithUrlencodedParamNotRaw(): void
    {
        $routes = [
            Route::get('/site/post/:name1/:name2')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);
        if ($urlMatcher instanceof UrlMatcher) {
            $urlMatcher->setEncodeRaw(false);
        }

        $request = new ServerRequest('GET', '/site/post/with+space/also%20space');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name1', $arguments);
        $this->assertArrayHasKey('name2', $arguments);
        $this->assertSame('with space', $arguments['name1']);
        $this->assertSame('also space', $arguments['name2']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithHostSuccess(): void
    {
        $routes = [
            Route::get('/site/index')
                ->action(fn () => 1)
                ->hosts('yii.test', 'yii.dev'),
            Route::get('/site/index')
                ->action(fn () => 1)
                ->host('{user}.yiiframework.com'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request
            ->getUri()
            ->withHost('yii.test'));
        $request2 = $request->withUri($request
            ->getUri()
            ->withHost('rustamwin.yiiframework.com'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());

        $this->assertArrayHasKey('user', $result2->arguments());
        $this->assertSame('rustamwin', $result2->arguments()['user']);
        $this->assertTrue($result2->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithMultipleHostSuccess(): void
    {
        $routes = [
            Route::get('/site/index')
                ->action(fn () => 1)
                ->hosts('yii.test', 'yii.com', 'yii.ru'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);
        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request
            ->getUri()
            ->withHost('yii.test'));
        $request2 = $request->withUri($request
            ->getUri()
            ->withHost('yii.com'));
        $errorRequest = $request->withUri($request
            ->getUri()
            ->withHost('example.com'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $errorResult = $urlMatcher->match($errorRequest);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertFalse($errorResult->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testMultipleHostException(): void
    {
        $route = Route::get('/')
            ->action(fn () => 1)
            ->hosts(
                'https://yiiframework.com/',
                'yf.com',
                ':user.yii.com'
            )
        ;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Placeholders are not allowed with multiple host names.');

        $urlMatcher = $this->createUrlMatcher([$route]);
        $urlMatcher->match(new ServerRequest('GET', '/site/index'));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSimpleRouteWithHostFailed(): void
    {
        $routes = [
            Route::get('/site/index')
                ->action(fn () => 1)
                ->host('yii.test'),
            Route::get('/site/index')
                ->action(fn () => 1)
                ->host('yiiframework.{zone:ru|com}'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request
            ->getUri()
            ->withHost('yee.test'));
        $request2 = $request->withUri($request
            ->getUri()
            ->withHost('yiiframework.uz'));
        $request3 = $request->withUri($request
            ->getUri()
            ->withHost('yiiframework.com'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertFalse($result1->isSuccess());
        $this->assertFalse($result1->isMethodFailure());

        $this->assertFalse($result2->isSuccess());
        $this->assertFalse($result2->isMethodFailure());

        $this->assertTrue($result3->isSuccess());
        $this->assertSame('com', $result3->arguments()['zone']);
    }

    public function testSimpleRouteWithOptionalParam(): void
    {
        $routes = [
            Route::get('/site/post/:id?')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');

        $result1 = $urlMatcher->match($request1);
        $arguments1 = $result1->arguments();
        $result2 = $urlMatcher->match($request2);
        $arguments2 = $result2->arguments();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $arguments1);
        $this->assertSame('23', $arguments1['id']);

        $this->assertTrue($result2->isSuccess());
        $this->assertArrayNotHasKey('id', $arguments2);
    }

    /**
     * @param string[] $methods
     *
     * @throws InvalidArgumentException
     */
    #[DataProvider('disallowedMethodsProvider')]
    public function testDisallowedMethod(array $methods, string $disallowedMethod): void
    {
        $routes = [
            Route::methods($methods, '/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest($disallowedMethod, '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame($methods, $result->methods());
    }

    /**
     * @return array<int, array{0: string[], 1: string}>
     */
    public static function disallowedMethodsProvider(): array
    {
        return [
            [['GET', 'HEAD'], 'POST'],
            [['POST'], 'HEAD'],
            [['PATCH', 'PUT'], 'GET'],
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testAutoAllowedHEADMethod(): void
    {
        $routes = [
            Route::post('/site/post/view')->action(fn () => 1),
            Route::get('/site/index')->action(fn () => 1),
            Route::post('/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('HEAD', '/site/index');
        $result = $urlMatcher->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isMethodFailure());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testNoCache(): void
    {
        $routes = [
            Route::get('/')
                ->action(fn () => 1)
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->action(fn () => 1)
                ->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->method('has')
            ->willReturn(false)
        ;

        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testHasCache(): void
    {
        $routes = [
            Route::get('/')
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->name('site/contact'),
        ];

        $cacheArray = [
            'hosts' => [],
            'parameterized' => null,
            'default' => [
                'tree' => [],
                'static' => [
                    '' => [
                        'GET' => [
                            'code' => 200,
                            'handler' => 'site/index',
                            'pattern' => '/',
                            'params' => [],
                        ],
                    ],
                    '/contact' => [
                        'GET' => [
                            'code' => 200,
                            'handler' => 'site/contact',
                            'pattern' => '/contact',
                            'params' => [],
                        ],
                        'POST' => [
                            'code' => 200,
                            'handler' => 'site/contact',
                            'pattern' => '/contact',
                            'params' => [],
                        ],
                    ],
                ],
            ],
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->method('has')
            ->willReturn(true)
        ;
        $cache
            ->method('get')
            ->willReturn($cacheArray)
        ;
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testStaticRouteExcludeFromMatching(): void
    {
        $routes = [
            Route::get('/test')
                ->action(fn () => 1)
                ->name('test'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);
        $request = new ServerRequest('GET', '/');
        $result = $urlMatcher->match($request);

        $this->assertFalse($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testCacheError(): void
    {
        $routes = [
            Route::get('/')
                ->action(fn () => 1)
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->action(fn () => 1)
                ->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache
            ->method('get')
            ->will($this->throwException(new RuntimeException()))
        ;
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheNoFile(): void
    {
        $routes = [
            Route::get('/')
                ->action(fn () => 1)
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->action(fn () => 1)
                ->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $tempFile = sys_get_temp_dir() . '/radix-router-test-no-file-' . uniqid() . '.php';

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheHasFile(): void
    {
        $routes = [
            Route::get('/')
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->name('site/contact'),
        ];

        $cacheArray = [
            'hosts' => [],
            'parameterized' => null,
            'default' => [
                'tree' => [],
                'static' => [
                    '' => [
                        'GET' => [
                            'code' => 200,
                            'handler' => 'site/index',
                            'pattern' => '/',
                            'params' => [],
                        ],
                    ],
                    '/contact' => [
                        'GET' => [
                            'code' => 200,
                            'handler' => 'site/contact',
                            'pattern' => '/contact',
                            'params' => [],
                        ],
                        'POST' => [
                            'code' => 200,
                            'handler' => 'site/contact',
                            'pattern' => '/contact',
                            'params' => [],
                        ],
                    ],
                ],
            ],
            'hostPatterns' => [],
        ];

        $request = new ServerRequest('GET', '/contact');

        $tempFile = sys_get_temp_dir() . '/radix-router-test-has-file-' . uniqid() . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export($cacheArray, true) . ';');

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheFileIsCreated(): void
    {
        $routes = [
            Route::get('/')
                ->action(fn () => 1)
                ->name('site/index'),
            Route::get('/about')
                ->action(fn () => 1)
                ->name('site/about'),
        ];

        $tempFile = sys_get_temp_dir() . '/radix-router-test-created-' . uniqid() . '.php';

        if (is_file($tempFile)) {
            unlink($tempFile);
        }

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);

        $request = new ServerRequest('GET', '/about');
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertFileExists($tempFile);

        $cachedData = require $tempFile;
        $this->assertIsArray($cachedData);
        $this->assertArrayHasKey('default', $cachedData);
        $this->assertArrayHasKey('hostPatterns', $cachedData);

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheWithHostRoutes(): void
    {
        $routes = [
            Route::get('/index')
                ->action(fn () => 1)
                ->host('api.example.com')
                ->name('api/index'),
        ];

        $cacheArray = [
            'hosts' => [
                'api.example.com' => [
                    'tree' => [],
                    'static' => [
                        '/index' => [
                            'GET' => [
                                'code' => 200,
                                'handler' => 'api/index',
                                'pattern' => '/index',
                                'params' => [],
                            ],
                        ],
                    ],
                ],
            ],
            'parameterized' => null,
            'default' => null,
            'hostPatterns' => [],
        ];

        $tempFile = sys_get_temp_dir() . '/radix-router-test-host-' . uniqid() . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export($cacheArray, true) . ';');

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);

        $request = (new ServerRequest('GET', '/index'))
            ->withUri((new ServerRequest('GET', 'http://api.example.com/index'))->getUri())
        ;

        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheWithParameterizedHost(): void
    {
        $routes = [
            Route::get('/index')
                ->action(fn () => 1)
                ->host('{subdomain}.example.com')
                ->name('subdomain/index'),
        ];

        $cacheArray = [
            'hosts' => [],
            'parameterized' => [
                'tree' => [
                    '' => [
                        '/p' => [
                            'example' => [
                                'com' => [
                                    'index' => [
                                        '/r' => [
                                            'GET' => [
                                                'code' => 200,
                                                'handler' => 'subdomain/index',
                                                'params' => [
                                                    'subdomain' => 'subdomain',
                                                ],
                                                'pattern' => '/:subdomain/example/com/index',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'static' => [],
            ],
            'default' => null,
            'hostPatterns' => [
                'subdomain/index' => '{subdomain}.example.com',
            ],
        ];

        $tempFile = sys_get_temp_dir() . '/radix-router-test-param-host-' . uniqid() . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export($cacheArray, true) . ';');

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);

        $request = (new ServerRequest('GET', '/index'))
            ->withUri((new ServerRequest('GET', 'http://blog.example.com/index'))->getUri())
        ;

        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['subdomain' => 'blog'], $result->arguments());

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPhpCacheWithParameterizedRoute(): void
    {
        $routes = [
            Route::get('/post/:id')
                ->action(fn () => 1)
                ->name('post/view'),
        ];

        $cacheArray = [
            'hosts' => [],
            'parameterized' => null,
            'default' => [
                'tree' => [
                    '' => [
                        'post' => [
                            '/p' => [
                                '/r' => [
                                    'GET' => [
                                        'code' => 200,
                                        'handler' => 'post/view',
                                        'params' => [
                                            'id' => 'id',
                                        ],
                                        'pattern' => '/post/:id',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'static' => [],
            ],
            'hostPatterns' => [],
        ];

        $tempFile = sys_get_temp_dir() . '/radix-router-test-param-' . uniqid() . '.php';
        file_put_contents($tempFile, '<?php return ' . var_export($cacheArray, true) . ';');

        $matcher = $this->createUrlMatcherWithPhpCache($routes, $tempFile);

        $request = new ServerRequest('GET', '/post/42');
        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['id' => '42'], $result->arguments());

        if (is_file($tempFile)) {
            unlink($tempFile);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testPure(): void
    {
        $matcher = new UrlMatcher(
            new RouteCollection(
                new RouteCollector()
            )
        );

        $result = $matcher->match(new ServerRequest('GET', '/contact'));

        $this->assertFalse($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testStaticRoutes(): void
    {
        $matcher = $this->createUrlMatcher([
            Route::get('/i/file')->name('image'),
        ]);

        $result = $matcher->match(new ServerRequest('GET', '/i/face.jpg'));

        $this->assertFalse($result->isSuccess());
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testRequiredWildcard(): void
    {
        $routes = [
            Route::get('/assets/:resource+')->action(fn () => 1)->name('assets'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/assets');
        $request2 = new ServerRequest('GET', '/assets/logo.png');
        $request3 = new ServerRequest('GET', '/assets/img/banner.jpg');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertFalse($result1->isSuccess());

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('logo.png', $result2->arguments()['resource']);

        $this->assertTrue($result3->isSuccess());
        $this->assertSame('img/banner.jpg', $result3->arguments()['resource']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testOptionalWildcard(): void
    {
        $routes = [
            Route::get('/downloads/:file*')->action(fn () => 1)->name('downloads'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/downloads');
        $request2 = new ServerRequest('GET', '/downloads/report.pdf');
        $request3 = new ServerRequest('GET', '/downloads/docs/guide.md');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertTrue($result1->isSuccess());
        $this->assertSame('', $result1->arguments()['file'] ?? '');

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('report.pdf', $result2->arguments()['file']);

        $this->assertTrue($result3->isSuccess());
        $this->assertSame('docs/guide.md', $result3->arguments()['file']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testSingleOptionalParameter(): void
    {
        $routes = [
            Route::get('/blog/:slug?')->action(fn () => 1)->name('blog/view'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/blog');
        $request2 = new ServerRequest('GET', '/blog/hello');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayNotHasKey('slug', $result1->arguments());

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('hello', $result2->arguments()['slug']);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testChainedOptionalParameters(): void
    {
        $routes = [
            Route::get('/archive/:year?/:month?')->action(fn () => 1)->name('archive/list'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/archive');
        $request2 = new ServerRequest('GET', '/archive/2022');
        $request3 = new ServerRequest('GET', '/archive/2022/12');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayNotHasKey('year', $result1->arguments());
        $this->assertArrayNotHasKey('month', $result1->arguments());

        $this->assertTrue($result2->isSuccess());
        $this->assertSame('2022', $result2->arguments()['year']);
        $this->assertArrayNotHasKey('month', $result2->arguments());

        $this->assertTrue($result3->isSuccess());
        $this->assertSame('2022', $result3->arguments()['year']);
        $this->assertSame('12', $result3->arguments()['month']);
    }

    public function testLocaleWithRadixFormat(): void
    {
        $routes = [
            Route::get(':locale/home/index')->action(fn () => 1)->name('index'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $result = $matcher->match(new ServerRequest('GET', '/uz/home/index'));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['locale' => 'uz'], $result->arguments());
    }

    public function testStandardYiiFormat(): void
    {
        $routes = [
            Route::get('/post/{id}')->action(fn () => 1)->name('post/view'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $result = $matcher->match(new ServerRequest('GET', '/post/42'));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['id' => '42'], $result->arguments());
    }

    public function testStandardYiiOptionalFormat(): void
    {
        $routes = [
            Route::get('/post/[{id}]')->action(fn () => 1)->name('post/view'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $result = $matcher->match(new ServerRequest('GET', '/post/42'));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['id' => '42'], $result->arguments());
    }

    public function testYiiFormatWithRegex(): void
    {
        // RadixRouter doesn't support custom regex per parameter,
        // but UrlMatcher should strip it and still match.
        $routes = [
            Route::get('/user/{name:\w+}')->action(fn () => 1)->name('user/view'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $result = $matcher->match(new ServerRequest('GET', '/user/john_doe'));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['name' => 'john_doe'], $result->arguments());
    }

    public function testMixedFormats(): void
    {
        $routes = [
            Route::get('/mixed/{a}/:b/{c:\d+}')->action(fn () => 1)->name('mixed'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $result = $matcher->match(new ServerRequest('GET', '/mixed/val1/val2/123'));

        $this->assertTrue($result->isSuccess());
        $this->assertEquals([
            'a' => 'val1',
            'b' => 'val2',
            'c' => '123',
        ], $result->arguments());
    }

    public function testYiiFormatInHost(): void
    {
        $routes = [
            Route::get('/index')
                ->action(fn () => 1)
                ->host('{subdomain}.example.com')
                ->name('host_test'),
        ];

        $matcher = $this->createUrlMatcher($routes);
        $request = (new ServerRequest('GET', '/index'))
            ->withUri((new ServerRequest('GET', 'http://blog.example.com/index'))->getUri())
        ;

        $result = $matcher->match($request);

        $this->assertTrue($result->isSuccess());
        $this->assertEquals(['subdomain' => 'blog'], $result->arguments());
    }

    public function testYiiSimpleRouteWithOptionalParam(): void
    {
        $routes = [
            Route::get('/site/post[/{id}]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');

        $result1 = $urlMatcher->match($request1);
        $arguments1 = $result1->arguments();

        $result2 = $urlMatcher->match($request2);
        $arguments2 = $result2->arguments();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $arguments1);
        $this->assertSame('23', $arguments1['id']);

        $this->assertTrue($result2->isSuccess());
        $this->assertArrayNotHasKey('id', $arguments2);
    }

    public function testYiiSimpleRouteWithNestedOptionalParamsSuccess(): void
    {
        $routes = [
            Route::get('/site[/{name}[/{id}]]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');
        $request3 = new ServerRequest('GET', '/site');

        $result1 = $urlMatcher->match($request1);
        $arguments1 = $result1->arguments();

        $result2 = $urlMatcher->match($request2);
        $arguments2 = $result2->arguments();

        $result3 = $urlMatcher->match($request3);
        $arguments3 = $result3->arguments();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $arguments1);
        $this->assertArrayHasKey('name', $arguments1);
        $this->assertSame('23', $arguments1['id']);
        $this->assertSame('post', $arguments1['name']);

        $this->assertTrue($result2->isSuccess());
        $this->assertArrayHasKey('name', $arguments2);
        $this->assertSame('post', $arguments2['name']);

        $this->assertTrue($result3->isSuccess());
        $this->assertArrayNotHasKey('id', $arguments3);
        $this->assertArrayNotHasKey('name', $arguments3);
    }

    public function testYiiSimpleRouteWithNestedOptionalParts(): void
    {
        $routes = [
            Route::get('/site[/post[/view]]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/view');
        $request2 = new ServerRequest('GET', '/site/post');
        $request3 = new ServerRequest('GET', '/site');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertTrue($result3->isSuccess());
    }

    /**
     * @param array<int, Group|Route> $routes
     *
     * @throws InvalidArgumentException
     */
    private function createUrlMatcher(array $routes, ?CacheInterface $cache = null): UrlMatcherInterface
    {
        $rootGroup = Group::create()->routes(...$routes);
        $collector = new RouteCollector();
        $collector->addRoute($rootGroup);

        return new UrlMatcher(new RouteCollection($collector), $cache, ['cache_key' => 'route-cache']);
    }

    /**
     * @param array<int, Group|Route> $routes
     *
     * @throws InvalidArgumentException
     */
    private function createUrlMatcherWithPhpCache(array $routes, string $phpCachePath): UrlMatcherInterface
    {
        $rootGroup = Group::create()->routes(...$routes);
        $collector = new RouteCollector();
        $collector->addRoute($rootGroup);

        return new UrlMatcher(
            new RouteCollection($collector),
            null,
            [
                'cacheKey' => 'route-cache',
                'saveToPhpFile' => true,
                'phpCachePath' => $phpCachePath,
            ]
        );
    }
}

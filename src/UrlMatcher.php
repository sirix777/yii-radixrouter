<?php

declare(strict_types=1);

/**
 * This package is a Radix Tree based router adapter for Yii3.
 * It is based on the original yiisoft/router-fastroute implementation.
 *
 * @copyright Copyright (c) 2026 Sirix
 * @copyright Copyright (c) 2008 Yii Software
 * @license MIT
 */

namespace Sirix\Router\RadixRouter;

use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use RuntimeException;
use Wilaak\Http\RadixRouter;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Router\MatchingResult;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlMatcherInterface;

use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace_callback;
use function rawurldecode;
use function str_replace;
use function strlen;
use function substr;
use function urldecode;

final class UrlMatcher implements UrlMatcherInterface
{
    /**
     * Configuration key used to set the cache file path.
     */
    public const CONFIG_CACHE_KEY = 'cache_key';
    private const VARIABLE_REGEX = <<<'REGEX'
        (?|
            \{ \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s* (?: : \s* ( (?: [^{}]++ | \{(?-1)\} )* ) )? \}
            |
            :([a-zA-Z_][a-zA-Z0-9_]*)([?*+])?
        )
        REGEX;

    /**
     * Configuration key used to set the cache file path.
     */
    private string $cacheKey = 'routes-cache';

    /**
     * @var array<string, RadixRouter> map of host to RadixRouter instance
     */
    private array $hostRouters = [];

    /**
     * @var null|RadixRouter router for routes with parameterized hosts
     */
    private ?RadixRouter $parameterizedHostRouter = null;

    /**
     * @var null|RadixRouter router for routes that allow any host
     */
    private ?RadixRouter $defaultRouter = null;

    /**
     * @var array<string, mixed> cached data used by the routers
     */
    private array $dispatchData = [];

    /**
     * @var bool whether cache is enabled and valid dispatch data has been loaded from cache
     */
    private bool $hasCache = false;

    private bool $hasInjectedRoutes = false;

    /** @var array<string, string> */
    private array $hostPatterns = [];

    private bool $encodeRaw = true;

    /**
     * @param null|array<string, mixed> $config
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        private readonly RouteCollectionInterface $routeCollection,
        private readonly ?CacheInterface $cache = null,
        ?array $config = null,
    ) {
        $this->loadConfig($config);
        $this->loadDispatchData();
    }

    public function setEncodeRaw(bool $encodeRaw): void
    {
        $this->encodeRaw = $encodeRaw;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function match(ServerRequestInterface $request): MatchingResult
    {
        $this->initializeRoutersIfNeeded();

        $context = $this->createRequestContext($request);
        $allowedMethods = [];

        if (($result = $this->matchHostRouter($context, $allowedMethods)) instanceof MatchingResult) {
            return $result;
        }

        if (($result = $this->matchParameterizedHostRouter($context, $allowedMethods)) instanceof MatchingResult) {
            return $result;
        }

        if (($result = $this->matchDefaultRouter($context, $allowedMethods)) instanceof MatchingResult) {
            return $result;
        }

        return $this->buildFailureResult($allowedMethods);
    }

    /**
     * @return array<string, string>
     */
    private function createRequestContext(ServerRequestInterface $request): array
    {
        $path = $request->getUri()->getPath();

        return [
            'host' => $request->getUri()->getHost(),
            'path' => $this->encodeRaw ? rawurldecode($path) : urldecode($path),
            'method' => $request->getMethod(),
        ];
    }

    /**
     * @throws InvalidArgumentException
     */
    private function initializeRoutersIfNeeded(): void
    {
        if (! $this->hasCache && ! $this->hasInjectedRoutes) {
            $this->injectRoutes();

            return;
        }

        if ($this->hasCache && ! $this->hasInjectedRoutes) {
            $this->restoreRoutersFromCache();
        }
    }

    /**
     * @param array<string, string> $context
     * @param string[]              $allowedMethods
     */
    private function matchHostRouter(array $context, array &$allowedMethods): ?MatchingResult
    {
        if (! isset($this->hostRouters[$context['host']])) {
            return null;
        }

        return $this->lookupRouter(
            $this->hostRouters[$context['host']],
            $context['method'],
            $context['path'],
            $allowedMethods
        );
    }

    /**
     * @param array<string, string> $context
     * @param string[]              $allowedMethods
     */
    private function matchParameterizedHostRouter(array $context, array &$allowedMethods): ?MatchingResult
    {
        if (! $this->parameterizedHostRouter instanceof RadixRouter) {
            return null;
        }

        $hostAsPath = '/' . str_replace('.', '/', $context['host']);

        /** @var array{code: int, handler: string, params: array<string, string>, allowed_methods: string[]} $result */
        $result = $this->parameterizedHostRouter->lookup(
            $context['method'],
            $hostAsPath . $context['path']
        );

        if (Status::OK === $result['code'] && ! $this->validateHostPattern($result, $context['host'])) {
            return null;
        }

        return $this->handleLookupResult($result, $allowedMethods);
    }

    /**
     * @param array<string, string> $ctx
     * @param string[]              $allowedMethods
     */
    private function matchDefaultRouter(array $ctx, array &$allowedMethods): ?MatchingResult
    {
        if (! $this->defaultRouter instanceof RadixRouter) {
            return null;
        }

        return $this->lookupRouter(
            $this->defaultRouter,
            $ctx['method'],
            $ctx['path'],
            $allowedMethods
        );
    }

    /**
     * @param string[] $allowedMethods
     */
    private function lookupRouter(
        RadixRouter $router,
        string $method,
        string $path,
        array &$allowedMethods
    ): ?MatchingResult {
        /** @var array{code: int, handler: string, params: array<string, string>, allowed_methods: string[]} $result */
        $result = $router->lookup($method, $path);

        return $this->handleLookupResult($result, $allowedMethods);
    }

    /**
     * @param array{
     *     code: int,
     *     handler: string,
     *     params: array<string, string>,
     *     allowed_methods: string[]
     * } $result
     * @param string[] $allowedMethods
     */
    private function handleLookupResult(array $result, array &$allowedMethods): ?MatchingResult
    {
        if (Status::OK === $result['code']) {
            return $this->marshalMatchedRoute($result);
        }

        if (Status::METHOD_NOT_ALLOWED === $result['code']) {
            $allowedMethods = array_merge($allowedMethods, $result['allowed_methods']);
        }

        return null;
    }

    /**
     * @param array{code: int, handler: string, params: array<string, string>, allowed_methods: string[]} $result
     */
    private function validateHostPattern(array $result, string $host): bool
    {
        $routeName = $result['handler'];

        if (! isset($this->hostPatterns[$routeName])) {
            return true;
        }

        $regex = preg_replace_callback(
            '~' . self::VARIABLE_REGEX . '~x',
            static fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^.]+') . ')',
            str_replace('.', '\.', $this->hostPatterns[$routeName])
        );

        return (bool) preg_match('~^' . $regex . '$~D', $host);
    }

    /**
     * @param string[] $allowedMethods
     */
    private function buildFailureResult(array $allowedMethods): MatchingResult
    {
        if ([] !== $allowedMethods) {
            return MatchingResult::fromFailure(
                array_values(array_unique($allowedMethods))
            );
        }

        return MatchingResult::fromFailure(Method::ALL);
    }

    /**
     * @param array{code: int, handler: string, params: array<string, string>, allowed_methods: string[]} $result
     */
    private function marshalMatchedRoute(array $result): MatchingResult
    {
        /** @var string $routeName */
        $routeName = $result['handler'];
        $arguments = $result['params'];

        $route = $this->routeCollection->getRoute($routeName);
        $arguments = array_merge($route->getData('defaults'), $arguments);

        return MatchingResult::fromSuccess($route, $arguments);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function injectRoutes(): void
    {
        foreach ($this->routeCollection->getRoutes() as $route) {
            if (! $this->shouldProcessRoute($route)) {
                continue;
            }

            $methods = $this->normalizeHttpMethods($route->getData('methods'));
            $hosts = $route->getData('hosts');

            match (count($hosts)) {
                0 => $this->registerDefaultRoute($route, $methods),
                1 => $this->registerSingleHostRoute($route, $hosts[0], $methods),
                default => $this->registerMultipleHostRoute($route, $hosts, $methods),
            };
        }

        $this->finalizeRouteInjection();
    }

    private function shouldProcessRoute(mixed $route): bool
    {
        return (bool) $route->getData('hasMiddlewares');
    }

    /**
     * @param string[] $methods
     *
     * @return string[]
     */
    private function normalizeHttpMethods(array $methods): array
    {
        if (in_array(Method::GET, $methods, true) && ! in_array(Method::HEAD, $methods, true)) {
            $methods[] = Method::HEAD;
        }

        return $methods;
    }

    /**
     * @param string[] $methods
     */
    private function registerDefaultRoute(mixed $route, array $methods): void
    {
        $router = $this->getDefaultRouter();

        foreach ($this->expandOptional($route->getData('pattern')) as $pattern) {
            $router->add(
                $methods,
                $this->preparePattern($pattern),
                $route->getData('name')
            );
        }
    }

    /**
     * @param string[] $methods
     */
    private function registerSingleHostRoute(mixed $route, string $host, array $methods): void
    {
        if ($this->isParameterizedHost($host)) {
            $this->registerParameterizedHostRoute($route, $host, $methods);

            return;
        }

        $router = $this->getHostRouter($host);

        foreach ($this->expandOptional($route->getData('pattern')) as $pattern) {
            $router->add(
                $methods,
                $this->preparePattern($pattern),
                $route->getData('name')
            );
        }
    }

    /**
     * @param string[] $hosts
     * @param string[] $methods
     */
    private function registerMultipleHostRoute(mixed $route, array $hosts, array $methods): void
    {
        foreach ($hosts as $host) {
            if ($this->isParameterizedHost($host)) {
                throw new RuntimeException(
                    'Placeholders are not allowed with multiple host names.'
                );
            }

            $router = $this->getHostRouter($host);

            foreach ($this->expandOptional($route->getData('pattern')) as $pattern) {
                $router->add(
                    $methods,
                    $this->preparePattern($pattern),
                    $route->getData('name')
                );
            }
        }
    }

    /**
     * @param string[] $methods
     */
    private function registerParameterizedHostRoute(mixed $route, string $host, array $methods): void
    {
        $router = $this->getParameterizedHostRouter();

        $hostPattern = $this->buildParameterizedHostPattern($host);

        foreach ($this->expandOptional($route->getData('pattern')) as $pattern) {
            $router->add(
                $methods,
                $hostPattern . $this->preparePattern($pattern),
                $route->getData('name')
            );
        }

        $this->hostPatterns[$route->getData('name')] = $host;
    }

    /**
     * @return string[]
     */
    private function expandOptional(string $pattern): array
    {
        $level = 0;
        $start = -1;

        for ($i = 0, $len = strlen($pattern); $i < $len; ++$i) {
            $char = $pattern[$i];

            if ('[' === $char) {
                if (0 === $level) {
                    $start = $i;
                }
                ++$level;
            } elseif (']' === $char) {
                --$level;
                if (0 === $level) {
                    $optionalPart = substr($pattern, $start + 1, $i - $start - 1);
                    $before = substr($pattern, 0, $start);
                    $after = substr($pattern, $i + 1);

                    return array_merge(
                        $this->expandOptional($before . $after),
                        $this->expandOptional($before . $optionalPart . $after)
                    );
                }
            }
        }

        if (0 !== $level) {
            throw new RuntimeException("Unmatched square brackets in pattern: {$pattern}");
        }

        return [$pattern];
    }

    private function preparePattern(string $pattern): string
    {
        $result = preg_replace_callback(
            '~' . self::VARIABLE_REGEX . '~x',
            static function(array $matches) {
                $name = $matches[1];
                $captured = $matches[2] ?? '';

                return match ($captured) {
                    '?' => ':' . $name . '?',
                    '*' => ':' . $name . '*',
                    '+' => ':' . $name . '+',
                    default => ':' . $name,
                };
            },
            $pattern
        );

        return (string) $result;
    }

    private function isParameterizedHost(string $host): bool
    {
        return (bool) preg_match('~' . self::VARIABLE_REGEX . '~x', $host);
    }

    private function buildParameterizedHostPattern(string $host): string
    {
        preg_match_all('~' . self::VARIABLE_REGEX . '~x', $host, $matches);

        foreach ($matches[0] as $i => $fullMatch) {
            $host = str_replace(
                $fullMatch,
                '{' . $matches[1][$i] . '}',
                $host
            );
        }

        return '/' . str_replace(['{', '}', '.'], [':', '', '/'], $host);
    }

    private function getHostRouter(string $host): RadixRouter
    {
        return $this->hostRouters[$host] ??= new RadixRouter();
    }

    private function getParameterizedHostRouter(): RadixRouter
    {
        return $this->parameterizedHostRouter ??= new RadixRouter();
    }

    private function getDefaultRouter(): RadixRouter
    {
        return $this->defaultRouter ??= new RadixRouter();
    }

    /**
     * @throws InvalidArgumentException
     */
    private function finalizeRouteInjection(): void
    {
        if ($this->cache instanceof CacheInterface) {
            $this->cacheDispatchData($this->collectDispatchData());
        }

        $this->hasInjectedRoutes = true;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectDispatchData(): array
    {
        $data = [
            'hosts' => [],
            'parameterized' => null,
            'default' => null,
            'hostPatterns' => $this->hostPatterns,
        ];

        foreach ($this->hostRouters as $host => $router) {
            $data['hosts'][$host] = [
                'tree' => $router->tree,
                'static' => $router->static,
            ];
        }

        if ($this->parameterizedHostRouter instanceof RadixRouter) {
            $data['parameterized'] = [
                'tree' => $this->parameterizedHostRouter->tree,
                'static' => $this->parameterizedHostRouter->static,
            ];
        }

        if ($this->defaultRouter instanceof RadixRouter) {
            $data['default'] = [
                'tree' => $this->defaultRouter->tree,
                'static' => $this->defaultRouter->static,
            ];
        }

        return $data;
    }

    private function restoreRoutersFromCache(): void
    {
        $this->hostPatterns = $this->dispatchData['hostPatterns'] ?? [];

        foreach ($this->dispatchData['hosts'] as $host => $data) {
            $router = new RadixRouter();
            $router->tree = $data['tree'];
            $router->static = $data['static'];
            $this->hostRouters[$host] = $router;
        }

        if (! empty($this->dispatchData['parameterized'])) {
            $router = new RadixRouter();
            $router->tree = $this->dispatchData['parameterized']['tree'];
            $router->static = $this->dispatchData['parameterized']['static'];
            $this->parameterizedHostRouter = $router;
        }

        if (! empty($this->dispatchData['default'])) {
            $router = new RadixRouter();
            $router->tree = $this->dispatchData['default']['tree'];
            $router->static = $this->dispatchData['default']['static'];
            $this->defaultRouter = $router;
        }

        $this->hasInjectedRoutes = true;
    }

    /**
     * @param null|array<string, mixed> $config
     */
    private function loadConfig(?array $config): void
    {
        if (null === $config) {
            return;
        }

        if (isset($config[self::CONFIG_CACHE_KEY])) {
            $this->cacheKey = (string) $config[self::CONFIG_CACHE_KEY];
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    private function loadDispatchData(): void
    {
        if ($this->cache instanceof CacheInterface && $this->cache->has($this->cacheKey)) {
            /** @var array<string, mixed> $dispatchData */
            $dispatchData = $this->cache->get($this->cacheKey);

            $this->hasCache = true;
            $this->dispatchData = $dispatchData;

            return;
        }

        $this->hasCache = false;
    }

    /**
     * @param array<string, mixed> $dispatchData
     *
     * @throws InvalidArgumentException
     */
    private function cacheDispatchData(array $dispatchData): void
    {
        if ($this->cache instanceof CacheInterface) {
            $this->cache->set($this->cacheKey, $dispatchData);
        }
    }
}

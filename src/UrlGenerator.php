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

use Psr\Http\Message\UriInterface;
use RuntimeException;
use Stringable;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_reverse;
use function http_build_query;
use function implode;
use function is_array;
use function is_string;
use function parse_str;
use function preg_match;
use function preg_match_all;
use function rawurlencode;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strncmp;
use function strpos;
use function substr;
use function urlencode;

final class UrlGenerator implements UrlGeneratorInterface
{
    private const VARIABLE_REGEX = <<<'REGEX'
        (?|
            \{ \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s* (?: : \s* ( (?: [^{}]++ | \{(?-1)\} )* ) )? \}
            |
            :([a-zA-Z_][a-zA-Z0-9_]*)([?*+])?
        )
        REGEX;
    private string $uriPrefix = '';

    /** @var array<string, string> */
    private array $defaultArguments = [];
    private bool $encodeRaw = true;

    /** @var array<string, array<int, array<int, array<int, string>|string>>> */
    private array $parsedPatternsCache = [];

    public function __construct(
        private readonly RouteCollectionInterface $routeCollection,
        private readonly ?CurrentRoute $currentRoute = null,
        private readonly ?string $scheme = null,
        private readonly ?string $host = null,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $queryParameters
     */
    public function generate(
        string $name,
        array $arguments = [],
        array $queryParameters = [],
        ?string $hash = null,
    ): string {
        $arguments = array_merge(
            $this->defaultArguments,
            $this->prepareArguments($arguments),
        );

        try {
            $route = $this->routeCollection->getRoute($name);
        } catch (RouteNotFoundException $e) {
            throw new RouteNotFoundException(
                sprintf(
                    'Cannot generate URI for route "%s"; route not found',
                    $name
                ),
                0,
                $e
            );
        }

        $pattern = $route->getData('pattern');
        $parsedRoutes = $this->parsedPatternsCache[$pattern] ??= $this->parse($pattern);

        $missingArguments = [];

        foreach ($parsedRoutes as $parsedRouteParts) {
            $missingArguments = $this->getMissingArguments($parsedRouteParts, $arguments);

            if ([] === $missingArguments) {
                return $this->generatePath($arguments, $queryParameters, $parsedRouteParts, $hash);
            }
        }

        throw new RuntimeException(
            sprintf(
                'Route `%s` expects at least argument values for [%s], but received [%s]',
                $name,
                implode(',', $missingArguments),
                implode(',', array_keys($arguments))
            )
        );
    }

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $queryParameters
     */
    public function generateAbsolute(
        string $name,
        array $arguments = [],
        array $queryParameters = [],
        ?string $hash = null,
        ?string $scheme = null,
        ?string $host = null,
    ): string {
        $url = $this->generate($name, $arguments, $queryParameters, $hash);
        $route = $this->routeCollection->getRoute($name);
        $uri = $this->currentRoute?->getUri();

        $host ??= $route->getData('host') ?? $this->host;

        if (null !== $host) {
            $isRelativeHost = $this->isRelative($host);
            $scheme ??= $isRelativeHost ? ($this->scheme ?? $uri?->getScheme()) : null;

            if (null === $scheme && ! $isRelativeHost) {
                return rtrim($host, '/') . $url;
            }

            $host = ('' !== $host && $isRelativeHost) ? '//' . $host : $host;

            return $this->ensureScheme(rtrim($host, '/') . $url, $scheme);
        }

        return $uri instanceof UriInterface ? $this->generateAbsoluteFromLastMatchedRequest($url, $uri, $scheme) : $url;
    }

    /**
     * @param array<string, mixed> $replacedArguments
     * @param array<string, mixed> $queryParameters
     */
    public function generateFromCurrent(
        array $replacedArguments,
        array $queryParameters = [],
        ?string $hash = null,
        ?string $fallbackRouteName = null,
    ): string {
        if (! $this->currentRoute instanceof CurrentRoute || null === $this->currentRoute->getName()) {
            if (null !== $fallbackRouteName) {
                return $this->generate($fallbackRouteName, $replacedArguments, hash: $hash);
            }

            if ($this->currentRoute instanceof CurrentRoute && $this->currentRoute->getUri() instanceof UriInterface) {
                return $this->currentRoute->getUri()->getPath() . (null !== $hash ? '#' . $hash : '');
            }

            throw new RuntimeException('Current route is not detected.');
        }

        if ($this->currentRoute->getUri() instanceof UriInterface) {
            $currentQueryParameters = [];
            parse_str($this->currentRoute->getUri()->getQuery(), $currentQueryParameters);
            $queryParameters = array_merge($currentQueryParameters, $queryParameters);
        }

        /** @var array<string, mixed> $queryParameters */
        return $this->generate(
            $this->currentRoute->getName(),
            array_merge($this->currentRoute->getArguments(), $replacedArguments),
            $queryParameters,
            $hash,
        );
    }

    public function setDefaultArgument(string $name, bool|float|int|string|Stringable|null $value): void
    {
        if (null === $value) {
            return;
        }

        $this->defaultArguments[$name] = (string) $value;
    }

    public function getUriPrefix(): string
    {
        return $this->uriPrefix;
    }

    public function setEncodeRaw(bool $encodeRaw): void
    {
        $this->encodeRaw = $encodeRaw;
    }

    public function setUriPrefix(string $name): void
    {
        $this->uriPrefix = $name;
    }

    /**
     * @return array<int, mixed>
     */
    private function parse(string $pattern): array
    {
        return array_reverse($this->buildRoutes($this->parseOptional($pattern)));
    }

    /**
     * @return array<int, mixed>
     */
    private function parseOptional(string $pattern): array
    {
        $segments = [];
        $len = strlen($pattern);
        $lastPos = 0;
        $depth = 0;
        $start = 0;

        for ($i = 0; $i < $len; ++$i) {
            if ('[' === $pattern[$i]) {
                if (0 === $depth) {
                    if ($i > $lastPos) {
                        $segments = array_merge(
                            $segments,
                            $this->parseVariables(substr($pattern, $lastPos, $i - $lastPos))
                        );
                    }
                    $start = $i + 1;
                }
                ++$depth;
            } elseif (']' === $pattern[$i]) {
                --$depth;
                if (0 === $depth) {
                    $segments[] = ['optional' => $this->parseOptional(substr($pattern, $start, $i - $start))];
                    $lastPos = $i + 1;
                }
            }
        }

        if ($lastPos < $len) {
            return array_merge($segments, $this->parseVariables(substr($pattern, $lastPos)));
        }

        return $segments;
    }

    /**
     * @return array<int, mixed>
     */
    private function parseVariables(string $pattern): array
    {
        if (! preg_match_all('~' . self::VARIABLE_REGEX . '~x', $pattern, $matches, PREG_OFFSET_CAPTURE)) {
            return [$pattern];
        }

        $parts = [];
        $lastOffset = 0;

        foreach ($matches[0] as $index => $match) {
            $offset = $match[1];
            if ($offset > $lastOffset) {
                $parts[] = substr($pattern, $lastOffset, $offset - $lastOffset);
            }

            $name = $matches[1][$index][0];
            $captured = $matches[2][$index][0] ?? '';

            $parts[] = match ($captured) {
                '?' => ['optional' => [[$name, '[^/]+']]],
                '*', '+' => [$name, '.+'],
                '' => [$name, '[^/]+'],
                default => [$name, $captured],
            };

            $lastOffset = $offset + strlen($match[0]);
        }

        if ($lastOffset < strlen($pattern)) {
            $parts[] = substr($pattern, $lastOffset);
        }

        return $parts;
    }

    /**
     * @param array<int, mixed> $segments
     *
     * @return array<int, mixed>
     */
    private function buildRoutes(array $segments): array
    {
        $routes = [[]];

        foreach ($segments as $segment) {
            if (isset($segment['optional'])) {
                // Optional segment
                $newRoutes = [];
                $optionalRoutes = $this->buildRoutes($segment['optional']);
                foreach ($routes as $route) {
                    $newRoutes[] = $route; // Route without optional segment
                    foreach ($optionalRoutes as $optionalRoute) {
                        $newRoutes[] = array_merge($route, $optionalRoute);
                    }
                }
                $routes = $newRoutes;
            } else {
                // Mandatory segment (string or variable)
                foreach ($routes as &$route) {
                    $route[] = $segment;
                }
                unset($route);
            }
        }

        return $routes;
    }

    private function generateAbsoluteFromLastMatchedRequest(string $url, UriInterface $uri, ?string $scheme): string
    {
        $port = '';
        $uriPort = $uri->getPort();
        if (80 !== $uriPort && null !== $uriPort) {
            $port = ':' . $uriPort;
        }

        return $this->ensureScheme('://' . $uri->getHost() . $port . $url, $scheme ?? $uri->getScheme());
    }

    private function ensureScheme(string $url, ?string $scheme): string
    {
        if (null === $scheme || $this->isRelative($url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            return '' === $scheme ? $url : "{$scheme}:{$url}";
        }

        if (($pos = strpos($url, '://')) !== false) {
            return '' === $scheme
                ? substr($url, $pos + 1)
                : $scheme . substr($url, $pos);
        }

        return $url;
    }

    private function isRelative(string $url): bool
    {
        return strncmp($url, '//', 2) && ! str_contains($url, '://');
    }

    /**
     * @param array<int, mixed>     $parts
     * @param array<string, string> $substitutions
     *
     * @return string[]
     */
    private function getMissingArguments(array $parts, array $substitutions): array
    {
        $missingArguments = [];

        foreach ($parts as $part) {
            if (is_array($part)) {
                $missingArguments[] = $part[0];
            }
        }

        foreach ($missingArguments as $argument) {
            if (! array_key_exists($argument, $substitutions)) {
                return $missingArguments;
            }
        }

        return [];
    }

    /**
     * @param array<string, string> $arguments
     * @param array<string, mixed>  $queryParameters
     * @param array<int, mixed>     $parts
     */
    private function generatePath(array $arguments, array $queryParameters, array $parts, ?string $hash): string
    {
        $path = $this->uriPrefix;
        $notSubstitutedArguments = $arguments;

        foreach ($parts as $part) {
            if (is_string($part)) {
                $path .= $part;

                continue;
            }

            [$name, $regex] = $part;
            $value = $arguments[$name] ?? '';

            if ('' !== $value) {
                $pattern = str_replace('~', '\~', $regex);
                if (! preg_match('~^' . $pattern . '$~', (string) $value)) {
                    throw new RuntimeException(
                        sprintf('Argument value for [%s] did not match the regex `%s`', $name, $regex)
                    );
                }

                $path .= $this->encodeRaw ? rawurlencode((string) $value) : urlencode((string) $value);
            }
            unset($notSubstitutedArguments[$name]);
        }

        $path = str_replace('//', '/', $path);
        $path = '' === $path ? '/' : rtrim($path, '/');
        if ('' === $path) {
            $path = '/';
        }

        $queryParameters += $notSubstitutedArguments;
        $queryString = [] === $queryParameters ? '' : http_build_query($queryParameters);

        return $path
            . ('' !== $queryString ? '?' . $queryString : '')
            . (null !== $hash ? '#' . $hash : '');
    }

    /**
     * @param array<string, mixed> $arguments
     *
     * @return array<string, string>
     */
    private function prepareArguments(array $arguments): array
    {
        $result = [];
        foreach ($arguments as $name => $value) {
            if (null === $value) {
                continue;
            }
            $result[$name] = (string) $value;
        }

        return $result;
    }
}

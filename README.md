# Yii Radix Router Adapter

[![Latest Stable Version](http://poser.pugx.org/sirix/yii-radixrouter/v)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![Total Downloads](http://poser.pugx.org/sirix/yii-radixrouter/downloads)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![Latest Unstable Version](http://poser.pugx.org/sirix/yii-radixrouter/v/unstable)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![License](http://poser.pugx.org/sirix/yii-radixrouter/license)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![PHP Version Require](http://poser.pugx.org/sirix/yii-radixrouter/require/php)](https://packagist.org/packages/sirix/yii-radixrouter)

A high-performance Radix Tree based router implementation for the Yii Framework (Yii3), using the [wilaak/radix-router](https://github.com/wilaak/radix-router) under the hood.

This package is based on the [yiisoft/router-fastroute](https://github.com/yiisoft/router-fastroute) implementation.

## Installation

The package can be installed with Composer:

```bash
composer require sirix/yii-radixrouter
```

> [!IMPORTANT]
> This package provides its own DI configuration for `UrlMatcherInterface` and `UrlGeneratorInterface`.
> To avoid "Duplicate key" errors during configuration building, you **must not** have another router implementation (like `yiisoft/router-fastroute`) enabled at the same time.
>
> If you have `yiisoft/router-fastroute` installed, remove it:
>
> ```bash
> composer remove yiisoft/router-fastroute
> ```

## Route Formats

This adapter supports both the standard Yii-style route patterns and the native Radix Router format.

### Yii-style Support

You can use the familiar `{param}` syntax, including optional regex parts. The matcher automatically converts these into a format compatible with the underlying Radix Tree.

> [!WARNING]
> The underlying Radix Router does **not support regular expressions** for parameter matching.
> When using Yii-style syntax with regex (e.g., `{id:\d+}`), the regular expression part is **ignored and stripped** during conversion to Radix format.
> All parameters are treated as simple string segments.

| Yii Format | Internal Radix Format | Description |
|------------|-----------------------|-------------|
| `/post/{id}` | `/post/:id` | Standard parameter |
| `/user/{name:\w+}` | `/user/:name` | Parameter with regex (regex is ignored) |
| `/post/[{id}]` | `/post/:id?` | Optional parameter |
| `/site/post[/{id}]` | `/site/post/:id?` | Optional segment |
| `/site[/{name}[/{id}]]` | `/site/:name?/:id?` | Nested optional segments |

### Native Radix Router Formats

For advanced use cases, you can use native Radix Router parameters directly in your route definitions.

| Native Format | Description | Example |
|---------------|-------------|---------|
| `:param` | Required parameter | `/blog/:slug` |
| `:param?` | Optional parameter | `/archive/:year?` |
| `:param*` | Optional wildcard (0 or more segments) | `/downloads/:path*` |
| `:param+` | Required wildcard (1 or more segments) | `/assets/:file+` |

For more details on the native format, see the [Radix Router documentation](https://github.com/wilaak/radix-router).

## Usage

The package is designed to work seamlessly with Yii3's DI container.

### DI Configuration

If you are using `yiisoft/config`, the configuration will be applied automatically. Otherwise, you can manually configure the `UrlMatcherInterface` and `UrlGeneratorInterface`.

### Parameters

Configuration is available via `params.php`:

```php
return [
    'sirix/yii-radixrouter' => [
        'enableCache' => true,
        'saveToPhpFile' => true,
        'cacheKey' => 'routes-cache',
        'phpCachePath' => 'runtime/routes-cache.php',
        'encodeRaw' => true,
        'scheme' => null,
        'host' => null,
    ],
];
```

## Benchmarks

Performance benchmarks comparing Radix Router against FastRoute are available in the `benchmarks-comparison` directory. These benchmarks are adapted from the excellent work of [wilaak/radix-router](https://github.com/wilaak/radix-router) by [@wilaak](https://github.com/wilaak).

### Quick Start

```bash
cd benchmark-comparison
composer install
php bench.php --all
```

### Available Test Suites

| Suite | Routes | Description |
|-------|--------|-------------|
| `simple` | 33 | Basic static and parameterized routes |
| `avatax` | 256 | Real-world API routes from Avatax |
| `bitbucket` | 177 | Real-world API routes from Bitbucket |
| `huge` | 500 | Randomly generated complex routes |

### Available PHP Modes

| Mode | Description |
|------|-------------|
| `JIT=tracing` | PHP with JIT compiler (tracing mode) |
| `OPcache` | PHP with OPcache enabled |
| `No OPcache` | PHP without any optimizations |

### Run Commands

```bash
# Run all benchmarks
php bench.php --all

# Run specific suite
php bench.php --suite=simple

# Run specific mode
php bench.php --mode="JIT=tracing"

# Run specific routers
php bench.php --router=YiiRadixRouterAdapter,YiiRadixRouterCachedAdapter
```

### Benchmark Results

> [!NOTE]
> Benchmark results may vary depending on hardware, PHP version, OS configuration, and other environment factors.

#### simple (33 routes)

Performance on **8 × 11th Gen Intel® Core™ i7-1165G7 @ 2.80GHz** with **16Gb** memory:

| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       902,589 |      321.1 |           0.138 |
|    2 | **YiiRadixRouterCached**     | JIT=tracing        |       874,764 |      477.2 |           0.334 |
|    3 | **YiiRadixRouter**           | JIT=tracing        |       809,893 |      568.6 |           0.264 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       588,295 |       63.5 |           0.142 |
|    5 | **YiiRadixRouter**           | OPcache            |       562,243 |      127.7 |           0.319 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       561,496 |      193.1 |           0.342 |
|    7 | **YiiRadixRouterCached**     | No OPcache         |       510,513 |      774.3 |           2.908 |
|    8 | **YiiRadixRouter**           | No OPcache         |       506,440 |      653.7 |           2.402 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       495,544 |      672.7 |           2.417 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |       366,698 |      213.7 |           0.227 |
|   11 | **YiiFastRouteCached**       | OPcache            |       274,248 |       99.4 |           0.232 |
|   12 | **YiiFastRouteCached**       | No OPcache         |       264,178 |      659.4 |           2.842 |
|   13 | **YiiFastRoute**             | JIT=tracing        |        81,639 |      269.8 |           0.301 |
|   14 | **YiiFastRoute**             | OPcache            |        60,548 |      101.3 |           0.360 |
|   15 | **YiiFastRoute**             | No OPcache         |        59,739 |      610.8 |           2.537 |

For detailed methodology, full results for all suites (`avatax`, `bitbucket`, `huge`), and more information, see [benchmark-comparison/README.md](benchmark-comparison/README.md).


## License

The Yii Radix Router is free software. It is released under the terms of the MIT License. Please see [LICENSE](LICENSE) for more information.

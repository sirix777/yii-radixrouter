# Yii Radix Router Adapter

[![Latest Stable Version](http://poser.pugx.org/sirix/yii-radixrouter/v)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![Total Downloads](http://poser.pugx.org/sirix/yii-radixrouter/downloads)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![Latest Unstable Version](http://poser.pugx.org/sirix/yii-radixrouter/v/unstable)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![License](http://poser.pugx.org/sirix/yii-radixrouter/license)](https://packagist.org/packages/sirix/yii-radixrouter) 
[![PHP Version Require](http://poser.pugx.org/sirix/yii-radixrouter/require/php)](https://packagist.org/packages/sirix/yii-radixrouter)

A high-performance Radix Tree based router implementation for the Yii Framework (Yii3), using the [wilaak/radix-router](https://github.com/wilaak/php-radix-router) under the hood.

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
        'encodeRaw' => true,
        'scheme' => null,
        'host' => null,
    ],
];
```

## License

The Yii Radix Router is free software. It is released under the terms of the MIT License. Please see [LICENSE](LICENSE) for more information.

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - unreleased

### Added
- Added performance benchmarks and results for Intel Core i7-1165G7.
- Added support for PHP file-based cache (`var_export`) in `UrlMatcher` for maximum performance.
- Added `cacheKey` and `phpCachePath` configuration parameters.
- Added `saveToPhpFile` configuration parameter to toggle between PSR-16 and PHP file cache.
- Added detailed performance comparison documentation and methodology (including route shuffling) in `benchmark-comparison/README.md`.

### Changed
- Updated `UrlMatcher` to better leverage JIT and OPcache when using PHP file cache.
- Updated documentation to emphasize JIT and PHP Cache advantages.

## [1.0.0] - 2026-01-10

### Added
- Initial release of the Radix Tree based router adapter for Yii3.
- `UrlMatcher` implementation using `wilaak/radix-router` for efficient route matching.
- `UrlGenerator` for generating URLs from named routes, supporting optional segments and parameter constraints.
- Support for host-based routing (static and parameterized hosts).
- PSR-16 cache support for route dispatch data to improve performance.
- Support for both standard Yii3 route placeholders `{name:regex}` and short syntax `:name`.
- Configurable URL encoding (raw or standard).
- Full compatibility with `yiisoft/router` 4.0 interfaces.

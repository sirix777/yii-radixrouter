# Benchmark Comparison

This directory contains performance benchmarks comparing the Yii Radix Router adapter against Yii FastRoute adapter.

## Acknowledgments

These benchmarks are **heavily adapted** from the excellent work of **[wilaak/radix-router](https://github.com/wilaak/radix-router)** by [@wilaak](https://github.com/wilaak).

The original radix-router benchmarks provided the foundation for this Yii-specific comparison. Special thanks to [@wilaak](https://github.com/wilaak) for creating such a well-designed benchmark framework and of course the radix-router itself!

## What is Being Tested

This benchmark suite compares four router implementations:

| Router | Description |
|--------|-------------|
| `YiiRadixRouter` | Radix Tree-based router |
| `YiiRadixRouterCached` | Radix Tree-based router with file-based cache |
| `FastRoute` | Regular expression-based router |
| `FastRouteCached` | Regular expression-based router with file-based cache |

## Test Suites

The benchmark uses four different route collections:

| Suite | Routes | Description |
|-------|--------|-------------|
| `simple` | 33 | Basic static and parameterized routes |
| `avatax` | 256 | Real-world API routes from Avatax |
| `bitbucket` | 177 | Real-world API routes from Bitbucket |
| `huge` | 500 | Randomly generated complex routes |

## PHP Modes

Each router is tested under three different PHP configurations:

| Mode | Description |
|------|-------------|
| `JIT=tracing` | PHP with JIT compiler (tracing mode) |
| `OPcache` | PHP with OPcache enabled |
| `No OPcache` | PHP without any optimizations |

## How It Works

The benchmark starts multiple PHP built-in servers, each running with a different PHP configuration. For each combination:

1. **Routes are registered** - All routes from the test suite are added to the router
2. **Warmup** - Several warmup requests are made to stabilize JIT/OPcache
3. **Benchmark** - Millions of lookups are performed and measured
4. **Memory measurement** - Peak memory usage is recorded

## Running the Benchmarks

### Prerequisites

```bash
cd benchmark-comparison
composer install
```

### Run All Benchmarks

```bash
php bench.php --all
```

### Run Specific Suite

```bash
php bench.php --suite=simple
php bench.php --suite=avatax
php bench.php --suite=bitbucket
php bench.php --suite=huge
```

### Run Specific Mode

```bash
php bench.php --mode="JIT=tracing"
php bench.php --mode=OPcache
php bench.php --mode="No OPcache"
```

### Run Specific Router

```bash
php bench.php --router=YiiRadixRouterAdapter
php bench.php --router=YiiRadixRouterCachedAdapter
php bench.php --router=FastRouteAdapter
php bench.php --router=FastRouteCachedAdapter
```

### Combine Options

```bash
# Simple suite with JIT only
php bench.php --suite=simple --mode="JIT=tracing"

# All suites, radix routers only
php bench.php --suite=all --router=YiiRadixRouterAdapter,YiiRadixRouterCachedAdapter
```

## Output

The benchmark outputs a formatted table with the following columns:

| Column | Description |
|--------|-------------|
| `Rank` | Performance ranking |
| `Router` | Router implementation name |
| `Mode` | PHP configuration |
| `Lookups/sec` | Number of route lookups per second |
| `Mem (KB)` | Peak memory usage in kilobytes |
| `Register (ms)` | Time to register all routes in milliseconds |

## Example Output

```
#### simple (33 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 |  🏆 **YiiRadixRouterCached** | JIT=tracing        |       897,661 |      222.1 |           0.167 |
|    2 |  🥈 **YiiRadixRouter**     | JIT=tracing        |       865,760 |      412.9 |           0.232 |
|    3 |  🥉 **FastRouteCached**    | JIT=tracing        |       569,965 |      209.2 |           0.122 |
```

## Understanding Results

### Speed vs Memory Tradeoff

- **Radix Router** is typically **3-10x faster** in lookup speed
- **FastRoute** uses **less memory** (especially with OPcache)

This is a fundamental architectural difference: Radix Router uses a tree-based structure for fast lookups, while FastRoute relies on compiled regex patterns which are more memory-efficient.

### Cached vs Non-Cached

- **Cached versions** store pre-processed route data in a file
- **Non-cached versions** rebuild the routing tree on each request
- Cached versions typically have faster registration time

### JIT vs OPcache

- **JIT** provides the best raw performance for both routers
- **OPcache** significantly improves performance over no optimizations
- Results may vary based on PHP version and workload

## Notes

- Benchmarks run on your local hardware - results will vary
- PHP built-in server is used for testing (not php-fpm)
- For production environments with php-fpm, results may differ
- Use multiple runs to account for JIT variance

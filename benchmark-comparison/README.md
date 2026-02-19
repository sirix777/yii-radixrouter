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
| `YiiRadixRouterPhpCache` | Radix Tree-based router with native PHP array cache |
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
3. **Shuffle** - Routes are shuffled before the lookup benchmark to ensure a fair distribution
4. **Benchmark** - Millions of lookups are performed and measured
5. **Memory measurement** - Peak memory usage is recorded

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
php bench.php --router=YiiRadixRouterPhpCacheAdapter
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

### Metrics explained

- Lookups/sec
  - What it means: Throughput of successful route matches per second (higher is better). Reflects the router's steady‑state lookup speed.
  - How measured: During the Benchmark phase, the harness performs a large number of local HTTP requests against the PHP built‑in server. Each request performs a loop of lookups for a randomized (shuffled) subset of the suite's routes. It measures wall‑clock time for the benchmark window (after warmup) and divides the total number of successful matches by the elapsed time.
  - Scope: The handler does minimal work (creating a PSR-7 Request and calling `match`), but this overhead is present for all routers. Results are sensitive to CPU, JIT/OPcache state, and suite composition.

- Mem (KB)
  - What it means: Peak memory used by the PHP process including routing structures (lower is better).
  - How measured: Calculated as `memory_get_peak_usage(true)` minus a baseline measured before loading any router-specific code or routes. This provides a more accurate representation of the memory overhead introduced by the router and its route collection.
  - Scope: Includes the memory for the router object, internal tree/regex structures, and the route collection itself.

- Register (ms)
  - What it means: Time to prepare the router for the first request (cold start).
  - How measured: High‑resolution timing around the `register()` call and the very first `lookup()` call (since some routers use lazy initialization). To ensure fairness, PSR-7 classes are warmed up before the measurement starts. For cached routers, this primarily measures cache loading and hydration time.
  - Scope: Impacts the "Time to First Byte" on a cold start in environments like CGI or PHP-FPM without persistent processes.

## Benchmarks

### Environment

- **CPU**: 8 × 11th Gen Intel® Core™ i7-1165G7 @ 2.80GHz
- **Memory**: 16Gb

#### simple (33 routes)
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

#### avatax (256 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       680,523 |      426.6 |           0.471 |
|    2 | **YiiRadixRouter**           | JIT=tracing        |       658,349 |      976.5 |           1.945 |
|    3 | **YiiRadixRouterCached**     | JIT=tracing        |       652,730 |     1529.5 |           1.622 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       462,150 |      426.6 |           0.604 |
|    5 | **YiiRadixRouter**           | OPcache            |       447,056 |      976.5 |           2.466 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       438,801 |     1529.5 |           1.956 |
|    7 | **YiiRadixRouter**           | No OPcache         |       408,652 |     1523.2 |           4.822 |
|    8 | **YiiRadixRouterCached**     | No OPcache         |       402,932 |     2158.3 |           4.427 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       390,121 |     1691.4 |           4.795 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |        96,523 |      767.1 |           0.692 |
|   11 | **YiiFastRouteCached**       | OPcache            |        87,499 |      702.0 |           0.813 |
|   12 | **YiiFastRouteCached**       | No OPcache         |        85,862 |     1291.1 |           3.529 |
|   13 | **YiiFastRoute**             | JIT=tracing        |        12,519 |      725.3 |           1.435 |
|   14 | **YiiFastRoute**             | OPcache            |         9,504 |      725.2 |           2.049 |
|   15 | **YiiFastRoute**             | No OPcache         |         9,430 |     1255.4 |           4.230 |

#### bitbucket (177 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       584,470 |      308.7 |           0.357 |
|    2 | **YiiRadixRouter**           | JIT=tracing        |       572,162 |      762.7 |           1.645 |
|    3 | **YiiRadixRouterCached**     | JIT=tracing        |       561,039 |     1194.6 |           1.260 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       391,927 |      308.7 |           0.440 |
|    5 | **YiiRadixRouter**           | OPcache            |       385,441 |      762.7 |           2.194 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       379,597 |     1194.6 |           1.516 |
|    7 | **YiiRadixRouter**           | No OPcache         |       352,938 |     1306.8 |           4.420 |
|    8 | **YiiRadixRouterCached**     | No OPcache         |       347,895 |     1826.1 |           4.104 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       334,728 |     1441.8 |           4.272 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |       105,872 |      512.1 |           0.541 |
|   11 | **YiiFastRouteCached**       | OPcache            |        95,314 |      512.1 |           0.621 |
|   12 | **YiiFastRouteCached**       | No OPcache         |        92,279 |     1104.4 |           3.251 |
|   13 | **YiiFastRoute**             | JIT=tracing        |        16,812 |      526.8 |           1.200 |
|   14 | **YiiFastRoute**             | No OPcache         |        13,001 |     1054.3 |           3.817 |
|   15 | **YiiFastRoute**             | OPcache            |        12,857 |      526.6 |           1.642 |

#### huge (500 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       588,854 |      860.8 |           0.877 |
|    2 | **YiiRadixRouter**           | JIT=tracing        |       542,090 |     2599.6 |           4.112 |
|    3 | **YiiRadixRouterCached**     | JIT=tracing        |       533,735 |     4205.0 |           3.851 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       406,514 |      818.2 |           1.112 |
|    5 | **YiiRadixRouter**           | OPcache            |       374,910 |     2599.6 |           5.362 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       370,879 |     4205.0 |           4.577 |
|    7 | **YiiRadixRouter**           | No OPcache         |       343,669 |     3158.5 |           7.951 |
|    8 | **YiiRadixRouterCached**     | No OPcache         |       329,871 |     4893.9 |           6.965 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       327,534 |     3446.5 |           8.396 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |        58,181 |     1350.7 |           1.193 |
|   11 | **YiiFastRouteCached**       | OPcache            |        52,395 |     1350.7 |           1.449 |
|   12 | **YiiFastRouteCached**       | No OPcache         |        51,637 |     1967.1 |           4.205 |
|   13 | **YiiFastRoute**             | JIT=tracing        |         6,638 |     1462.6 |           2.886 |
|   14 | **YiiFastRoute**             | OPcache            |         4,991 |     1378.9 |           4.086 |
|   15 | **YiiFastRoute**             | No OPcache         |         4,978 |     1921.3 |           6.357 |


## Understanding Results

### Speed vs Memory Tradeoff

- **Radix Router** is typically **3-10x faster** in lookup speed
- **FastRoute** typically uses **less memory** 

### The JIT and PHP Cache Advantage

For the absolute best performance, the **PHP Array Cache** mode (`YiiRadixRouterPhpCache`) combined with **JIT=tracing** provides the highest throughput.

#### Why is it more efficient for Radix Trees?

Unlike traditional PSR-16 caching (which stores a serialized string), the PHP file cache leverages the full power of the PHP engine:

1.  **OPcache Shared Memory**: When the cache file is `require`d, OPcache stores the entire array structure in shared memory. This means the routing tree is not just "stored" but is already "live" and shared across all PHP-FPM processes, drastically reducing the memory footprint per request.
2.  **Zero Deserialization Overhead**: Standard caching requires `unserialize()` or JSON parsing, which consumes CPU cycles and creates many temporary objects. The PHP file cache avoids this entirely; the array is already "there" in memory, ready to be used.
3.  **Native String Interning**: During compilation, OPcache automatically interns all strings (route names, patterns, methods). In a Radix Tree, where many nodes share the same keys (like `GET` or `/api`), this means all nodes point to the **exact same memory address** for these strings. This drastically improves CPU L1/L2 cache locality and reduces memory usage compared to `unserialize()`, which often creates duplicate string objects.
4.  **Optimal Memory Locality**: Because the array is built by the PHP compiler into a contiguous memory block in OPcache, traversing the tree causes fewer "cache misses" than a tree reconstructed from a serialized string, where nodes might be scattered across the heap.
5.  **Maximum Speed**: This mode achieves a "perfect" balance: it matches the raw lookup speed of a live-built router (due to optimal memory layout) while having the near-instant registration time of a cached version. Combined with **JIT**, it reaches the absolute maximum performance.

In this benchmark, `YiiRadixRouterPhpCache` typically demonstrates the highest `Lookups/sec` while maintaining one of the lowest `Register (ms)` scores.

This is a fundamental architectural difference: Radix Router uses a tree-based structure for fast lookups, while FastRoute relies on compiled regex patterns which are more memory-efficient.

### Cached vs Non-Cached

- **Cached versions** store pre-processed route data in a file
- **Non-cached versions** rebuild the routing tree on each request
- **Cached versions** typically have faster registration time

### JIT vs OPcache

- **JIT** provides the best raw performance for both routers
- **OPcache** significantly improves performance over no optimizations
- Results may vary based on PHP version and workload

## Notes

> [!IMPORTANT]
> Benchmark results are highly dependent on the environment. Actual performance in your application may vary based on several factors:

- **Hardware**: CPU architecture, clock speed, and available cache significantly impact Radix Tree traversal and Regex execution.
- **PHP Version**: Different PHP versions have varying optimizations in OPcache and JIT compiler.
- **Environment**: These tests use the PHP built-in server for isolation. In production (e.g., PHP-FPM, RoadRunner, or Swoole), results may differ due to process management and communication overhead.
- **Route Complexity**: The "huge" suite uses randomized routes; real-world route patterns in your application might favor one algorithm over another.
- **Variance**: Always use multiple runs to account for JIT warmup and system background tasks.

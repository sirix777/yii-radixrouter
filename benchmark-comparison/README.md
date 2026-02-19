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

> [!NOTE]
> Benchmark results may vary depending on hardware, PHP version, OS configuration, and other environment factors.

> [!NOTE]
> These benchmarks were run inside a virtualization environment on a running machine. This cannot be considered a full test bench as resources are shared among all containers. Results may vary compared to dedicated hardware.

### Environment

- **CPU**: 8 × Intel(R) Core(TM) i7-6700 CPU @ 3.40GHz
- **Memory**: 64Gb
- **PHP**: 8.2.29

#### simple (33 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |     1,062,161 |       65.1 |           0.094 |
|    2 | **YiiRadixRouterCached**     | JIT=tracing        |     1,038,986 |      196.9 |           0.243 |
|    3 | **YiiRadixRouter**           | JIT=tracing        |     1,026,673 |      211.8 |           0.177 |
|    4 | **YiiRadixRouterCached**     | OPcache            |       661,535 |      193.3 |           0.295 |
|    5 | **YiiRadixRouterPhpCache**   | OPcache            |       661,076 |       63.5 |           0.144 |
|    6 | **YiiRadixRouter**           | OPcache            |       625,778 |      127.7 |           0.200 |
|    7 | **YiiRadixRouter**           | No OPcache         |       594,685 |      649.2 |           1.510 |
|    8 | **YiiRadixRouterCached**     | No OPcache         |       585,544 |      769.8 |           2.167 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       567,784 |      668.0 |           1.540 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |       546,878 |      102.4 |           0.224 |
|   11 | **YiiFastRouteCached**       | OPcache            |       429,623 |       99.1 |           0.191 |
|   12 | **YiiFastRouteCached**       | No OPcache         |       406,177 |      656.9 |           2.135 |
|   13 | **YiiFastRoute**             | JIT=tracing        |       116,380 |      181.2 |           0.221 |
|   14 | **YiiFastRoute**             | No OPcache         |        89,250 |      608.1 |           1.806 |
|   15 | **YiiFastRoute**             | OPcache            |        89,037 |      100.9 |           0.266 |

#### avatax (256 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       797,503 |      424.8 |           0.298 |
|    2 | **YiiRadixRouter**           | JIT=tracing        |       781,621 |      974.8 |           1.078 |
|    3 | **YiiRadixRouterCached**     | JIT=tracing        |       773,586 |     1527.9 |           1.045 |
|    4 | **YiiRadixRouterCached**     | OPcache            |       516,406 |     1527.9 |           1.246 |
|    5 | **YiiRadixRouter**           | OPcache            |       508,653 |      974.8 |           1.527 |
|    6 | **YiiRadixRouterPhpCache**   | OPcache            |       507,039 |      424.8 |           0.355 |
|    7 | **YiiRadixRouter**           | No OPcache         |       478,623 |     1518.7 |           3.001 |
|    8 | **YiiRadixRouterCached**     | No OPcache         |       447,831 |     2153.7 |           2.916 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       443,521 |     1686.7 |           3.021 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |       124,342 |      700.0 |           0.518 |
|   11 | **YiiFastRouteCached**       | OPcache            |       111,902 |      700.0 |           0.536 |
|   12 | **YiiFastRouteCached**       | No OPcache         |       111,628 |     1288.6 |           2.358 |
|   13 | **YiiFastRoute**             | JIT=tracing        |        16,302 |      723.2 |           0.944 |
|   14 | **YiiFastRoute**             | No OPcache         |        13,527 |     1252.7 |           3.102 |
|   15 | **YiiFastRoute**             | OPcache            |        13,250 |      723.1 |           1.244 |

#### bitbucket (177 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       740,269 |      307.6 |           0.232 |
|    2 | **YiiRadixRouter**           | JIT=tracing        |       709,342 |      761.6 |           1.065 |
|    3 | **YiiRadixRouterCached**     | JIT=tracing        |       685,482 |     1193.6 |           0.773 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       491,243 |      307.6 |           0.261 |
|    5 | **YiiRadixRouter**           | OPcache            |       459,702 |      761.6 |           1.542 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       449,863 |     1193.6 |           1.094 |
|    7 | **YiiRadixRouterCached**     | No OPcache         |       421,692 |     1822.5 |           2.622 |
|    8 | **YiiRadixRouter**           | No OPcache         |       412,991 |     1302.3 |           2.748 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       404,219 |     1437.2 |           2.566 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |       150,425 |      510.7 |           0.389 |
|   11 | **YiiFastRouteCached**       | OPcache            |       137,948 |      510.7 |           0.477 |
|   12 | **YiiFastRouteCached**       | No OPcache         |       125,556 |     1102.4 |           2.275 |
|   13 | **YiiFastRoute**             | JIT=tracing        |        22,436 |      525.3 |           0.727 |
|   14 | **YiiFastRoute**             | OPcache            |        18,712 |      525.1 |           1.121 |
|   15 | **YiiFastRoute**             | No OPcache         |        18,576 |     1051.6 |           2.432 |

#### huge (500 routes)
| Rank | Router                       | Mode               | Lookups/sec   | Mem (KB)   | Register (ms)   |
|------|------------------------------|--------------------|---------------|------------|-----------------|
|    1 | **YiiRadixRouterPhpCache**   | JIT=tracing        |       734,733 |      814.6 |           0.510 |
|    2 | **YiiRadixRouterCached**     | JIT=tracing        |       705,212 |     4201.5 |           2.540 |
|    3 | **YiiRadixRouter**           | JIT=tracing        |       701,293 |     2596.0 |           2.335 |
|    4 | **YiiRadixRouterPhpCache**   | OPcache            |       489,427 |      814.6 |           0.733 |
|    5 | **YiiRadixRouter**           | OPcache            |       470,546 |     2596.0 |           3.455 |
|    6 | **YiiRadixRouterCached**     | OPcache            |       450,440 |     4201.5 |           3.154 |
|    7 | **YiiRadixRouterCached**     | No OPcache         |       442,865 |     4889.5 |           4.219 |
|    8 | **YiiRadixRouter**           | No OPcache         |       441,664 |     3154.0 |           4.747 |
|    9 | **YiiRadixRouterPhpCache**   | No OPcache         |       414,326 |     3441.8 |           5.194 |
|   10 | **YiiFastRouteCached**       | JIT=tracing        |        77,606 |     1346.8 |           0.859 |
|   11 | **YiiFastRouteCached**       | OPcache            |        69,623 |     1346.7 |           0.971 |
|   12 | **YiiFastRouteCached**       | No OPcache         |        66,465 |     1964.6 |           2.777 |
|   13 | **YiiFastRoute**             | JIT=tracing        |         8,777 |     1375.1 |           1.846 |
|   14 | **YiiFastRoute**             | OPcache            |         7,537 |     1374.9 |           2.261 |
|   15 | **YiiFastRoute**             | No OPcache         |         7,037 |     1918.6 |           4.097 |


## Understanding Results

### Speed vs Memory Tradeoff

- **Radix Router** is typically **2-10x faster** in lookup speed
- **FastRoute** typically uses **less memory** 

### The JIT and PHP Cache Advantage

For the absolute best performance, the **PHP Array Cache** mode (`YiiRadixRouterPhpCache`) combined with **JIT=tracing** provides the highest throughput.

#### Why is it more efficient for Radix Trees?

Unlike traditional PSR-16 caching (which stores a serialized string), the PHP file cache leverages the full power of the PHP engine:

1.  **OPcache Shared Memory**: When the cache file is `required`, OPcache stores the entire array structure in shared memory. This means the routing tree is not just "stored" but is already "live" and shared across all PHP-FPM processes, drastically reducing the memory footprint per request.
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

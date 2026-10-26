# Zana benchmarks

Benchmarks for Zana 2: container resolution and the HTTP round trip. They
answer two questions:

- What does the framework cost per request?
- Does the compiled container pay off?

```
composer install
composer bench
```

`composer bench` runs PHP without `php.ini` (`-n`): no Xdebug, with OPcache
enabled for the CLI. **Xdebug slows the container down roughly ten times;**
never benchmark with it loaded.

Each figure is the median of 7 rounds after a warm-up.

## What is measured

**Container** (`bin/container.php`): a generated graph of 100 classes, shaped
like an application:

- 10 top services (controllers, handlers), each needing 4 mid services;
- 40 mid services (repositories), each needing 3 leaves;
- 50 leaves.

Resolving one top service creates 17 objects when everything is transient.

| Benchmark | What it measures |
|---|---|
| build | Register and build the container, with or without the compiled class |
| get singleton (cached) | `get()` of a service already created |
| get transient | Creating the 17 objects |
| first get, 17 singletons | Build, then the first `get()`: the cost of a cold request |
| scope | Create a scope, resolve 17 scoped objects, dispose it, as each HTTP request does |
| baseline | The same 17 objects written by hand with `new` |

**HTTP** (`bin/http.php`): an application with `HttpServiceProvider`,
`RoutingServiceProvider`, one middleware and 22 routes. Requests are handled
in-process by `HttpKernel::handle()`, with no network or web server.

| Benchmark | What it measures |
|---|---|
| round trip | One request through the kernel, its scope, the middleware, the router, a controller and the JSON response; and a 404 problem document |
| boot + one request | Boot the application, then one request: what PHP-FPM pays per request. With and without the container cache |
| baseline | Building one PSR-7 JSON response with Nyholm, the floor |

## Results — 2026-10-26, 2.4.0

Same machine and settings. The machine ran 8–20% slower than for the 2.3 run below (the hand-written
baselines are slower too), so compare the rows of one run with each other rather than across runs.
HTTP figures are the range of two runs.

| HTTP | µs per op |
|---|---:|
| round trip, 200 JSON | 77–81 |
| round trip, 404 problem document | 64–66 |
| boot + one request | 1,070–1,170 |
| boot + one request, container cache | 1,009–1,011 |
| boot + one request, container and configuration caches | 638–644 |

- **The configuration cache cuts boot by about 37%** in the same run: one cache file replaces
  scanning `config/` and loading each file. It removes more the more configuration files an
  application has: in a side-by-side measurement of the 2.3 and 2.4 code (median of 400 boots),
  boot plus one request went from 900 to 510 µs with one file, and from 1,530 to 516 µs with five.
- The container figures are within the machine's variation of the 2.3 run.

## Results — 2026-10-26, 2.3.0

Same machine and settings as below. The container figures are from one run; the HTTP figures are
the range of three runs, which vary by about ±10% on this machine.

| Container | µs per op | vs 2.0.0-rc.1 |
|---|---:|---:|
| build, 100 services | 804 | 926 |
| build, 100 services, compiled | 253 | 281 |
| get singleton (cached) | 0.57 | 0.62 |
| get transient, 17 objects | 44 | 45 |
| scope: create, get 17 scoped, dispose | 45 | 47 |
| first get, 17 singletons | 1,148 | 1,181 |
| first get, 17 singletons, compiled | 364 | 379 |
| baseline: 17 objects with `new` | 1.7 | 1.7 |

| HTTP | µs per op | vs 2.0.0-rc.1 |
|---|---:|---:|
| round trip, 200 JSON | 73–80 | 70 |
| round trip, 404 problem document | 60–66 | 57 |
| boot + one request | 913–1,055 | 867 |
| boot + one request, container cache | 956–1,132 | 862 |
| baseline: one PSR-7 response | 2.8 | 2.8 |

- **The container is unchanged or slightly faster.**
- **HTTP looked 5–20% slower than 2.0, but less of it was real.** Running the 2.0.0 and 2.3.0 code
  side by side (same harness, alternating, median of 400 boots) showed boot plus one request about
  3% slower without the container cache and 11% slower with it (0.03–0.09 ms): more services to
  register, and a few more classes to load. The rest was variation between runs.
- **Reading configuration was the real cost:** each configuration file added about 150 µs to boot on
  this machine. 2.4 adds a configuration cache for that (see above).
- **The container cache does not speed up boot here**, as in 2.0: on this small application, the
  saving is below the noise, and the cached runs came out slower. Worth profiling on Linux before
  drawing conclusions.

## Results — 2026-10-25, the code tagged 2.0.0-rc.1

Intel Xeon E-2276M (2.8 GHz), 32 GB, Windows 11; PHP 8.5.12 CLI, OPcache on,
JIT off, Xdebug off.

| Container | µs per op |
|---|---:|
| build, 100 services | 926 |
| build, 100 services, compiled | 281 |
| get singleton (cached) | 0.62 |
| get transient, 17 objects | 45 |
| scope: create, get 17 scoped, dispose | 47 |
| first get, 17 singletons | 1,181 |
| first get, 17 singletons, compiled | 379 |
| baseline: 17 objects with `new` | 1.7 |

| HTTP | µs per op | Requests per second |
|---|---:|---:|
| round trip, 200 JSON | 70 | 14,400 |
| round trip, 404 problem document | 57 | 17,400 |
| boot + one request | 867 | 1,150 |
| boot + one request, container cache | 862 | 1,160 |
| baseline: one PSR-7 response | 2.8 | 355,000 |

Raw output: `composer bench`. The compiled numbers are within noise of the
runtime ones once a service is resolved.

## Reading the results

- **Resolving costs about 2.7 µs per created object**, about 25 times a
  hand-written `new`. Each creation checks for cycles, applies decorators and
  interceptors, and wraps errors with the resolution chain. A request that
  creates 50 services spends about 0.15 ms in the container. Cached
  singletons cost 0.6 µs.
- **The compiled container makes building 3.3 times faster** (926 → 281 µs for
  100 services): it skips validation, reflection and scanning. Resolution
  speed is the same. The gain grows with the number of services. On the
  small HTTP application (a few dozen registrations), the cache saves less than the
  timing noise.
- **The HTTP round trip costs about 70 µs** in a booted application: about
  14,000 requests per second per core in a long-running process, before the
  application's own work.
- **Booting dominates under PHP-FPM.** On this machine, about 870 µs per
  request, much of it filesystem checks:
  - Windows takes about 30 µs per file check here, so looking for `.env`,
    `config/modules.php` and the configuration directories costs about
    250 µs.
  - Linux, with OPcache, typically takes a few microseconds per check, so
    expect boot to be noticeably cheaper there.
  - Measure on your production platform before drawing conclusions.

## Changes made because of these benchmarks

- **Container cache in `zana-core`.** Before rc.1, a cached boot read the
  whole compiled file as text, just to find its class name, before
  including it. On Windows that read cost more than the compiled container
  saved, and the cache made boot slower. The cache file now returns its
  class name, so loading it is a single include served by OPcache.
  Files written by earlier versions still load.

## Candidates for 2.x

- **Resolution fast path:** skip the interceptor closure chain and the
  decorator lookup when none are registered.

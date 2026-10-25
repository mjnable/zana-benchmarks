<?php

declare(strict_types=1);

namespace Bench;

/**
 * Times a callable: a warm-up, then several rounds; reports the median time
 * per call, so one slow round (a GC run, the OS) does not skew the result.
 */
final class Harness
{
    /** @var list<array{string, float}> */
    private array $results = [];

    public function __construct(private readonly int $rounds = 7)
    {
    }

    public function run(string $name, int $iterations, callable $operation): void
    {
        for ($i = 0; $i < max(1, intdiv($iterations, 10)); $i++) {
            $operation();
        }

        $times = [];
        for ($round = 0; $round < $this->rounds; $round++) {
            $start = hrtime(true);
            for ($i = 0; $i < $iterations; $i++) {
                $operation();
            }
            $times[] = (hrtime(true) - $start) / $iterations;
        }
        sort($times);

        $this->results[] = [$name, $times[intdiv(count($times), 2)] / 1000];
        fwrite(STDERR, '.');
    }

    public function report(string $title): string
    {
        $width = max(array_map(static fn (array $r): int => strlen($r[0]), $this->results));
        $lines = ["### {$title}", '', sprintf('| %s | µs per op | ops per second |', str_pad('Benchmark', $width)), sprintf('|%s|---:|---:|', str_repeat('-', $width + 2))];
        foreach ($this->results as [$name, $micros]) {
            $lines[] = sprintf('| %s | %s | %s |', str_pad($name, $width), number_format($micros, $micros < 10 ? 2 : 1), number_format(1_000_000 / $micros));
        }

        return implode("\n", $lines) . "\n";
    }

    public static function environment(): string
    {
        return sprintf(
            'PHP %s (%s), opcache %s, JIT %s, Xdebug %s, %s',
            PHP_VERSION,
            PHP_OS_FAMILY,
            function_exists('opcache_get_status') && (opcache_get_status(false)['opcache_enabled'] ?? false) ? 'on' : 'off',
            (string) ini_get('opcache.jit') ?: 'off',
            extension_loaded('xdebug') ? 'loaded' : 'off',
            date('Y-m-d'),
        );
    }
}

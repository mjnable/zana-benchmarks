<?php

declare(strict_types=1);

namespace Bench;

use Zana\Container\ServiceCollection;
use Zana\Container\ServiceLifetime;

/**
 * A generated service graph of 100 classes, shaped like an application:
 *
 * - 10 "top" services (controllers, handlers), each needing 4 mid services;
 * - 40 "mid" services (repositories, gateways), each needing 3 leaves;
 * - 50 leaves with no dependencies.
 *
 * Resolving one top service builds 17 objects when everything is transient.
 */
final class Graph
{
    public const int TOPS = 10;
    public const int MIDS = 40;
    public const int LEAVES = 50;
    public const string NS = 'Bench\\Generated';

    public static function load(string $directory): void
    {
        if (class_exists(self::NS . '\\Top0', false)) {
            return;
        }

        $file = "{$directory}/graph.php";
        if (!is_file($file)) {
            @mkdir($directory, 0777, true);
            file_put_contents($file, self::source());
        }
        require $file;
    }

    public static function register(ServiceCollection $services, ServiceLifetime $lifetime): ServiceCollection
    {
        foreach (self::classes() as $class) {
            match ($lifetime) {
                ServiceLifetime::Singleton => $services->singleton($class),
                ServiceLifetime::Scoped => $services->scoped($class),
                ServiceLifetime::Transient => $services->transient($class),
            };
        }

        return $services;
    }

    /**
     * @return list<class-string>
     */
    public static function classes(): array
    {
        $classes = [];
        foreach (['Leaf' => self::LEAVES, 'Mid' => self::MIDS, 'Top' => self::TOPS] as $kind => $count) {
            for ($i = 0; $i < $count; $i++) {
                /** @var class-string $class */
                $class = self::NS . "\\{$kind}{$i}";
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * @return class-string
     */
    public static function top(int $i = 0): string
    {
        /** @var class-string */
        return self::NS . "\\Top{$i}";
    }

    private static function source(): string
    {
        $php = "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . self::NS . ";\n\n";

        for ($i = 0; $i < self::LEAVES; $i++) {
            $php .= "final class Leaf{$i} {}\n";
        }
        for ($i = 0; $i < self::MIDS; $i++) {
            $params = [];
            foreach ([0, 1, 2] as $n) {
                $params[] = 'public Leaf' . (($i + $n) % self::LEAVES) . " \$l{$n}";
            }
            $php .= "final class Mid{$i} { public function __construct(" . implode(', ', $params) . ") {} }\n";
        }
        for ($i = 0; $i < self::TOPS; $i++) {
            $params = [];
            foreach ([0, 1, 2, 3] as $n) {
                $params[] = 'public Mid' . ($i * 4 + $n) . " \$m{$n}";
            }
            $php .= "final class Top{$i} { public function __construct(" . implode(', ', $params) . ") {} }\n";
        }

        // The same tree as Top0 written by hand: the floor for any container
        $php .= "\nfunction handWrittenTop0(): Top0\n{\n    return new Top0(\n";
        for ($m = 0; $m < 4; $m++) {
            $php .= "        new Mid{$m}(new Leaf{$m}(), new Leaf" . ($m + 1) . "(), new Leaf" . ($m + 2) . "()),\n";
        }
        $php .= "    );\n}\n";

        return $php;
    }
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Bench\Graph;
use Bench\Harness;
use Zana\Container\ContainerCompiler;
use Zana\Container\ServiceCollection;
use Zana\Container\ServiceLifetime;

$var = __DIR__ . '/../var';
Graph::load($var);

/**
 * The compiled container class for a lifetime, generated once per run.
 *
 * @return class-string
 */
$compiled = static function (ServiceLifetime $lifetime) use ($var): string {
    $class = 'Bench\\Compiled\\' . $lifetime->name . 'Container';
    if (!class_exists($class, false)) {
        $file = "{$var}/compiled-{$lifetime->name}.php";
        file_put_contents($file, (new ContainerCompiler())->compile(Graph::register(new ServiceCollection(), $lifetime), $class));
        require $file;
    }

    /** @var class-string */
    return $class;
};
$build = static fn (ServiceLifetime $lifetime, bool $compile) => Graph::register(new ServiceCollection(), $lifetime)
    ->build($compile ? $compiled($lifetime) : null);

$top = Graph::top();
$bench = new Harness();

// Building: what every PHP-FPM request pays at boot
$bench->run('build, 100 services', 200, static fn () => $build(ServiceLifetime::Transient, false));
$bench->run('build, 100 services, compiled', 200, static fn () => $build(ServiceLifetime::Transient, true));

foreach (['' => false, ', compiled' => true] as $label => $compile) {
    $singletons = $build(ServiceLifetime::Singleton, $compile);
    $transients = $build(ServiceLifetime::Transient, $compile);
    $scoped = $build(ServiceLifetime::Scoped, $compile);

    $bench->run("get singleton (cached){$label}", 200_000, static fn () => $singletons->get($top));
    $bench->run("get transient, 17 objects{$label}", 20_000, static fn () => $transients->get($top));
    $bench->run("first get, 17 singletons{$label}", 2_000, static fn () => $build(ServiceLifetime::Singleton, $compile)->get($top));
    $bench->run("scope: create, get 17 scoped, dispose{$label}", 20_000, static function () use ($scoped, $top): void {
        $scope = $scoped->createScope();
        $scope->get($top);
        $scope->dispose();
    });
}

$bench->run('baseline: the same 17 objects with new', 200_000, 'Bench\\Generated\\handWrittenTop0');

echo $bench->report('Container: ' . Harness::environment());

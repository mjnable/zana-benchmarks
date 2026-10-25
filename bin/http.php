<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Bench\Harness;
use Bench\Http\AppProvider;
use Bench\Http\PoweredBy;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Zana\Core\Application;
use Zana\Http\HttpKernel;
use Zana\Http\HttpServiceProvider;
use Zana\Routing\RoutingServiceProvider;

$base = __DIR__ . '/../var/http-app';
@mkdir("{$base}/config", 0777, true);
file_put_contents("{$base}/config/http.php", '<?php return ["middleware" => [' . var_export(PoweredBy::class, true) . ']];');
$cache = "{$base}/var/container.php";
@unlink($cache);

$boot = static function (bool $cached) use ($base, $cache): HttpKernel {
    $builder = Application::configure($base)
        ->withEnvironment('production')
        ->withProviders(HttpServiceProvider::class, RoutingServiceProvider::class, AppProvider::class);
    if ($cached) {
        $builder->withContainerCache($cache);
    }
    $kernel = $builder->build()->container()->get(HttpKernel::class);
    assert($kernel instanceof HttpKernel);

    return $kernel;
};

$show = new ServerRequest('GET', '/api/patients/42', ['Accept' => 'application/json']);
$missing = new ServerRequest('GET', '/api/nothing', ['Accept' => 'application/json']);

$kernel = $boot(false);
$response = $kernel->handle($show);
if ($response->getStatusCode() !== 200 || $response->getHeaderLine('X-Powered-By') !== 'Zana') {
    fwrite(STDERR, "Unexpected response: {$response->getStatusCode()} {$response->getBody()}\n");
    exit(1);
}
$boot(true); // writes the container cache

$bench = new Harness();

$bench->run('baseline: build a PSR-7 JSON response', 100_000, static fn () => new Response(200, ['Content-Type' => 'application/json'], json_encode(['id' => 42], JSON_THROW_ON_ERROR)));
$bench->run('round trip: GET /api/patients/42 (200)', 20_000, static fn () => $kernel->handle($show));
$bench->run('round trip: GET /api/nothing (404 problem)', 20_000, static fn () => $kernel->handle($missing));
$bench->run('boot + one request', 500, static fn () => $boot(false)->handle($show));
$bench->run('boot + one request, container cache', 500, static fn () => $boot(true)->handle($show));

echo $bench->report('HTTP: ' . Harness::environment());

<?php

declare(strict_types=1);

namespace Bench\Http;

use Zana\Container\Contracts\ContainerInterface;
use Zana\Container\Contracts\ServiceCollectionInterface;
use Zana\Core\ServiceProvider;
use Zana\Routing\Router;

/**
 * A small API: one attribute-routed controller among 20 other routes, and
 * one application middleware (config/http.php).
 */
final class AppProvider extends ServiceProvider
{
    public function register(ServiceCollectionInterface $services): void
    {
        $services
            ->transient(PatientController::class)
            ->scoped(PoweredBy::class);
    }

    public function boot(ContainerInterface $container): void
    {
        $router = $container->get(Router::class);
        assert($router instanceof Router);

        for ($i = 0; $i < 20; $i++) {
            $router->get("/api/resource{$i}/{id}", static fn (int $id): array => ['resource' => $i, 'id' => $id]);
        }
        $router->loadAttributes(PatientController::class);
    }
}

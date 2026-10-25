<?php

declare(strict_types=1);

namespace Bench\Http;

use Psr\Http\Message\ServerRequestInterface;
use Zana\Routing\Attribute\Route;

#[Route('/api/patients')]
final class PatientController
{
    /**
     * @return array<string, mixed>
     */
    #[Route('', methods: ['GET'], name: 'patients.index')]
    public function index(ServerRequestInterface $request): array
    {
        return ['page' => (int) ($request->getQueryParams()['page'] ?? 1), 'items' => []];
    }

    /**
     * @return array<string, mixed>
     */
    #[Route('/{id}', methods: ['GET'], name: 'patients.show')]
    public function show(int $id): array
    {
        return ['id' => $id, 'name' => 'Ada Lovelace', 'heightCm' => 170, 'weightKg' => 60.5];
    }
}

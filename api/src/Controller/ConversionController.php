<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConversionController extends AbstractController
{
    #[Route('/conversions', name: 'conversion_create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        return $this->json(
            ['message' => 'Not implemented.'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    #[Route('/conversions/{id}', name: 'conversion_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json(
            ['message' => 'Not implemented.'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }

    #[Route('/conversions/{id}/download', name: 'conversion_download', methods: ['GET'])]
    public function download(): JsonResponse
    {
        return $this->json(
            ['message' => 'Not implemented.'],
            Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}

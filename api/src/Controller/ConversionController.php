<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Model\BadRequest;
use App\Model\ConversionRequest;
use App\Service\AcceptConversion;
use App\Service\RequestResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class ConversionController extends AbstractController
{
    public function __construct(
        public SerializerInterface $serializer,
        private RequestResolver $requestResolver,
        private AcceptConversion $acceptConversion,
    ) {
    }

    #[Route('/conversions', name: 'conversion_create', methods: ['POST'])]
    public function accept(
        Request $httpRequest,
        #[CurrentUser] Customer $customer,
    ): Response {
        $id = new UuidV7();
        $ownerId = $customer->getId();

        $request = $this->convertRequest($httpRequest, $id, $ownerId);

        $responseMediaType = $this->resolveResponseMediaType($httpRequest);

        $entity = $this->acceptConversion->accept($request);

        $payload = [
            'id' => $entity->getId()->toRfc4122(),
            'status' => $entity->getStatus()->asString(),
        ];

        return $this->serializeResponse($payload, $responseMediaType, Response::HTTP_ACCEPTED);
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

    /** @param object|array<string, mixed> $data */
    private function serializeResponse(object|array $data, string $mediaType, int $statusCode): Response
    {
        $serializerFormat = 'application/json' === $mediaType ? 'json' : 'xml';
        $content = $this->serializer->serialize($data, $serializerFormat);

        return new Response($content, $statusCode, ['Content-Type' => $mediaType]);
    }

    /**
     * @throws BadRequest
     */
    private function resolveResponseMediaType(Request $httpRequest): string
    {
        $acceptHeader = $httpRequest->headers->get('Accept');

        return match ($acceptHeader) {
            'application/json' => 'application/json',
            'application/xml' => 'application/xml',
            default => throw new BadRequest(sprintf('Missing or invalid Accept header. Expected one of: [application/json, application/xml] but got [%s].', $acceptHeader ?? 'null')),
        };
    }

    /**
     * @throws BadRequest
     */
    private function convertRequest(Request $httpRequest, Uuid $id, Uuid $ownerId): ConversionRequest
    {
        return $this->requestResolver->convertRequest($httpRequest, $id, $ownerId);
    }
}

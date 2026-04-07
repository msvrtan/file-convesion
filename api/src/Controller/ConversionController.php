<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Customer;
use App\Model\BadRequest;
use App\Model\ConversionRequest;
use App\Model\ConversionStatus;
use App\Repository\ConversionRepository;
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
        private SerializerInterface $serializer,
        private RequestResolver $requestResolver,
        private AcceptConversion $acceptConversion,
        private ConversionRepository $conversionRepository,
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
    public function status(
        Request $httpRequest,
        Uuid $id,
        #[CurrentUser] Customer $customer,
    ): Response {
        $responseMediaType = $this->resolveResponseMediaType($httpRequest);

        $entity = $this->conversionRepository->load($id, $customer->getId());

        if (null === $entity) {
            $payload = [
                'message' => 'Conversion not found.',
            ];

            return $this->serializeResponse($payload, $responseMediaType, Response::HTTP_NOT_FOUND);
        }

        $payload = match ($entity->getStatus()) {
            ConversionStatus::Accepted => [
                'id' => $entity->getId()->toRfc4122(),
                'status' => $entity->getStatus()->asString(),
                'message' => 'Your conversion is accepted. We will try to start processing it as soon as possible.',
                'lastUpdate' => $entity->getCreatedAt()->format(\DateTimeInterface::RFC3339),
            ],
            ConversionStatus::InProgress => [
                'id' => $entity->getId()->toRfc4122(),
                'status' => $entity->getStatus()->asString(),
                'message' => 'Your conversion is being converted right now.',
                'lastUpdate' => $entity->getProcessingStartedAt()?->format(\DateTimeInterface::RFC3339),
            ],
            ConversionStatus::Failed => [
                'id' => $entity->getId()->toRfc4122(),
                'status' => $entity->getStatus()->asString(),
                'message' => sprintf('Conversion failed: %s', $entity->getMessage()),
                'lastUpdate' => $entity->getProcessingEndedAt()?->format(\DateTimeInterface::RFC3339),
            ],
            ConversionStatus::Completed => [
                'id' => $entity->getId()->toRfc4122(),
                'status' => $entity->getStatus()->asString(),
                'message' => 'Your conversion is completed.',
                'lastUpdate' => $entity->getProcessingEndedAt()?->format(\DateTimeInterface::RFC3339),
            ],
        };

        return $this->serializeResponse($payload, $responseMediaType, Response::HTTP_OK);
    }

    #[Route('/conversions/{id}/download', name: 'conversion_download', methods: ['GET'])]
    public function download(
        Request $httpRequest,
        Uuid $id,
        #[CurrentUser] Customer $customer,
    ): Response {
        $responseMediaType = $this->resolveResponseMediaType($httpRequest);

        $entity = $this->conversionRepository->load($id, $customer->getId());

        if (null === $entity || ConversionStatus::Completed !== $entity->getStatus()) {
            $payload = [
                'message' => 'Conversion not found.',
            ];

            return $this->serializeResponse($payload, $responseMediaType, Response::HTTP_NOT_FOUND);
        }

        // create file download response

    }

    /** @param object|array<string, mixed> $data */
    private function serializeResponse(object|array $data, string $mediaType, int $statusCode): Response
    {
        $serializerFormat = 'application/json' === $mediaType ? 'json' : 'xml';
        $content = $this->serializer->serialize($data, $serializerFormat);

        return new Response($content, $statusCode, ['Content-Type' => $mediaType]);
    }

    private function resolveResponseMediaType(Request $httpRequest): string
    {
        foreach ($httpRequest->getAcceptableContentTypes() as $acceptableContentType) {
            if ('application/xml' === $acceptableContentType) {
                return 'application/xml';
            }

            if ('application/json' === $acceptableContentType || '*/*' === $acceptableContentType) {
                return 'application/json';
            }
        }

        return 'application/json';
    }

    /**
     * @throws BadRequest
     */
    private function convertRequest(Request $httpRequest, Uuid $id, Uuid $ownerId): ConversionRequest
    {
        return $this->requestResolver->convertRequest($httpRequest, $id, $ownerId);
    }
}

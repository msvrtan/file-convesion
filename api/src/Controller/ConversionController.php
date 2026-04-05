<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Conversion;
use App\Entity\Customer;
use App\Model\ConversionRequest;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ConversionController extends AbstractController
{
    public function __construct(
        public ValidatorInterface $validator,
        public SerializerInterface $serializer,
        private FilesystemOperator $defaultStorage,
        private ConversionRepository $conversionRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/conversions', name: 'conversion_create', methods: ['POST'])]
    public function accept(
        Request $httpRequest,
        #[CurrentUser] Customer $customer,
    ): Response {
        $acceptHeader = $httpRequest->headers->get('Accept');

        if ('application/json' === $acceptHeader) {
            $responseMediaType = 'application/json';
        } elseif ('application/xml' === $acceptHeader) {
            $responseMediaType = 'application/xml';
        } else {
            $message = sprintf('Missing or invalid Accept header. Expected one of: [application/json, application/xml] but got [%s].', $acceptHeader ?? 'null');

            return $this->serializeResponse(
                ['message' => $message],
                'application/json',
                Response::HTTP_BAD_REQUEST,
            );
        }

        /** @var UploadedFile|null $file */
        $file = $httpRequest->files->get('file');
        $targetFormat = (string) $httpRequest->request->get('targetFormat');

        $request = new ConversionRequest($file, $targetFormat);

        $errors = $this->validator->validate($request);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->serializeResponse(
                ['errors' => $errorMessages],
                $responseMediaType,
                Response::HTTP_BAD_REQUEST,
            );
        }

        $id = new UuidV7();
        $ownerId = $customer->getId();

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file;
        $tempPath = $uploadedFile->getPathname();
        $sourcePath = \sprintf('uploads/%s/%s.%s', $ownerId, $id, $request->sourceFormat);
        $stream = fopen($tempPath, 'r');

        if (false === $stream) {
            throw new \RuntimeException('Unable to open uploaded file.');
        }

        $this->defaultStorage->writeStream($sourcePath, $stream);

        if (\is_resource($stream)) {
            fclose($stream);
        }

        $entity = $this->buildAndSaveConversion($id, $ownerId, $request);

        $this->publishConversion($entity);

        return $this->serializeResponse(
            [
                'id' => (string) $id,
                'status' => 'accepted',
            ],
            $responseMediaType,
            Response::HTTP_ACCEPTED,
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

    /** @param object|array<string, mixed> $data */
    private function serializeResponse(object|array $data, string $mediaType, int $statusCode): Response
    {
        $serializerFormat = 'application/json' === $mediaType ? 'json' : 'xml';
        $content = $this->serializer->serialize($data, $serializerFormat);

        return new Response($content, $statusCode, ['Content-Type' => $mediaType]);
    }

    private function buildAndSaveConversion(Uuid $id, Uuid $ownerId, ConversionRequest $request): Conversion
    {
        $entity = new Conversion(
            id: $id,
            ownerId: $ownerId,
            sourceFormat: $request->sourceFormat,
            targetFormat: $request->targetFormat,
        );

        $this->conversionRepository->save($entity);

        return $entity;
    }

    /**
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    private function publishConversion(Conversion $conversion): void
    {
        $message = new ConvertFile($conversion->getId(), $conversion->getOwnerId());
        $this->messageBus->dispatch($message);
    }
}

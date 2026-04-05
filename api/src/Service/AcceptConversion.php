<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversion;
use App\Model\ConversionRequest;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final class AcceptConversion
{
    public function __construct(
        private FilesystemOperator $defaultStorage,
        private ConversionRepository $conversionRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \League\Flysystem\FilesystemException
     * @throws \RuntimeException
     * @throws \Symfony\Component\Messenger\Exception\ExceptionInterface
     */
    public function accept(ConversionRequest $request): Conversion
    {
        $this->moveFileToUploadSection($request);

        $entity = $this->buildAndSaveConversion($request);

        $this->publishConversion($entity);

        return $entity;
    }

    /**
     * @throws \League\Flysystem\FilesystemException
     * @throws \RuntimeException
     */
    private function moveFileToUploadSection(ConversionRequest $request): void
    {
        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file;
        $tempPath = $uploadedFile->getPathname();
        $sourcePath = \sprintf('uploads/%s/%s.%s', $request->ownerId, $request->id, $request->sourceFormat);
        $stream = fopen($tempPath, 'r');

        if (false === $stream) {
            throw new \RuntimeException('Unable to open uploaded file.');
        }

        $this->defaultStorage->writeStream($sourcePath, $stream);

        if (\is_resource($stream)) {
            fclose($stream);
        }
    }

    /**
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    private function buildAndSaveConversion(ConversionRequest $request): Conversion
    {
        $entity = new Conversion(
            id: $request->id,
            ownerId: $request->ownerId,
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

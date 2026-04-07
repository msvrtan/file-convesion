<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Conversion;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use App\Service\FileConverter\FileConverter;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ConvertFileHandler
{
    public function __construct(
        private FilesystemOperator $defaultStorage,
        private ConversionRepository $conversionRepository,
        private FileConverter $fileConverter,
    ) {
    }

    /**
     * @throws FilesystemException
     */
    public function __invoke(ConvertFile $message): void
    {
        $entity = $this->loadConversion($message);
        $sourceContent = $this->loadSourceContent($entity);
        $convertedContent = $this->convertContent($entity, $sourceContent);

        $this->storeConvertedContent($entity, $convertedContent);
    }

    private function loadConversion(ConvertFile $message): Conversion
    {
        $conversion = $this->conversionRepository->load($message->getId(), $message->getOwnerId());

        if (null === $conversion) {
            throw new \RuntimeException('Conversion not found.');
        }

        return $conversion;
    }

    /**
     * @throws FilesystemException
     */
    private function loadSourceContent(Conversion $entity): string
    {
        return $this->defaultStorage->read($this->sourcePath($entity));
    }

    private function convertContent(Conversion $entity, string $sourceContent): string
    {
        return $this->fileConverter->convert(
            $sourceContent,
            $entity->getSourceFormat(),
            $entity->getTargetFormat(),
        );
    }

    /**
     * @throws FilesystemException
     */
    private function storeConvertedContent(Conversion $entity, string $convertedContent): void
    {
        $this->defaultStorage->write($this->convertedPath($entity), $convertedContent);
    }

    private function sourcePath(Conversion $conversion): string
    {
        return sprintf(
            'uploads/%s/%s.%s',
            $conversion->getOwnerId(),
            $conversion->getId(),
            $conversion->getSourceFormat(),
        );
    }

    private function convertedPath(Conversion $conversion): string
    {
        return sprintf(
            'converted/%s/%s.%s',
            $conversion->getOwnerId(),
            $conversion->getId(),
            $conversion->getTargetFormat(),
        );
    }
}

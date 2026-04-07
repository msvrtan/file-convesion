<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Conversion;
use App\MessageHandler\ConvertFileHandler;
use App\Model\ConversionStatus;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use App\Service\FileConverter\FileConverter;
use App\Service\PathResolver;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ConvertFileHandlerTest extends TestCase
{
    private FilesystemOperator&MockObject $defaultStorage;
    private ConversionRepository&MockObject $conversionRepository;
    private FileConverter&MockObject $fileConverter;
    private PathResolver $pathResolver;
    private ConvertFileHandler $handler;

    protected function setUp(): void
    {
        $this->defaultStorage = $this->createMock(FilesystemOperator::class);
        $this->conversionRepository = $this->getMockBuilder(ConversionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load', 'save'])
            ->getMock();
        $this->fileConverter = $this->createMock(FileConverter::class);
        $this->pathResolver = new PathResolver();

        $this->handler = new ConvertFileHandler(
            $this->defaultStorage,
            $this->conversionRepository,
            $this->fileConverter,
            $this->pathResolver,
        );
    }

    public function testItReadsSourceConvertsItAndStoresConvertedContent(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $saveCount = 0;

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(static function (Conversion $savedConversion) use ($conversion): bool {
                self::assertSame($conversion, $savedConversion);

                return true;
            }))
            ->willReturnCallback(function (Conversion $savedConversion) use (&$saveCount): void {
                ++$saveCount;

                if (1 === $saveCount) {
                    self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());
                    self::assertNotNull($savedConversion->getProcessingStartedAt());
                    self::assertNull($savedConversion->getProcessingEndedAt());
                    self::assertNull($savedConversion->getMessage());

                    return;
                }

                self::assertSame(ConversionStatus::Completed, $savedConversion->getStatus());
                self::assertNotNull($savedConversion->getProcessingStartedAt());
                self::assertNotNull($savedConversion->getProcessingEndedAt());
                self::assertNull($savedConversion->getMessage());
            });
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->with($this->pathResolver->uploadPath($message->getOwnerId(), $message->getId(), 'json'))
            ->willReturn('{"country":"Croatia"}');
        $this->fileConverter->expects(self::once())
            ->method('convert')
            ->with('{"country":"Croatia"}', 'json', 'xml')
            ->willReturn('<root><country>Croatia</country></root>');
        $this->defaultStorage->expects(self::once())
            ->method('write')
            ->with(
                $this->pathResolver->convertedPath($message->getOwnerId(), $message->getId(), 'xml'),
                '<root><country>Croatia</country></root>',
            );

        ($this->handler)($message);
    }

    public function testItThrowsWhenConversionCannotBeLoaded(): void
    {
        $id = new UuidV7();
        $ownerId = new UuidV7();
        $message = new ConvertFile($id, $ownerId);

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($id, $ownerId)
            ->willReturn(null);
        $this->conversionRepository->expects(self::never())->method('save');
        $this->defaultStorage->expects(self::never())->method('read');
        $this->defaultStorage->expects(self::never())->method('write');
        $this->fileConverter->expects(self::never())->method('convert');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Conversion not found.');

        ($this->handler)($message);
    }

    public function testItPropagatesFailureWhenProcessingStartCannotBeSaved(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $exception = new \RuntimeException('Unable to persist processing start.');

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Conversion $savedConversion) use ($conversion): bool {
                self::assertSame($conversion, $savedConversion);
                self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());
                self::assertNotNull($savedConversion->getProcessingStartedAt());
                self::assertNull($savedConversion->getProcessingEndedAt());
                self::assertNull($savedConversion->getMessage());

                return true;
            }))
            ->willThrowException($exception);
        $this->defaultStorage->expects(self::never())->method('read');
        $this->defaultStorage->expects(self::never())->method('write');
        $this->fileConverter->expects(self::never())->method('convert');

        $this->expectExceptionObject($exception);

        ($this->handler)($message);
    }

    public function testItMarksConversionAsFailedWhenReadingSourceFails(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $saveCount = 0;

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (Conversion $savedConversion) use ($conversion, &$saveCount): void {
                ++$saveCount;
                self::assertSame($conversion, $savedConversion);

                if (1 === $saveCount) {
                    self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());
                    self::assertNotNull($savedConversion->getProcessingStartedAt());
                    self::assertNull($savedConversion->getProcessingEndedAt());
                    self::assertNull($savedConversion->getMessage());

                    return;
                }

                self::assertSame(ConversionStatus::Failed, $savedConversion->getStatus());
                self::assertNotNull($savedConversion->getProcessingStartedAt());
                self::assertNotNull($savedConversion->getProcessingEndedAt());
                self::assertSame('Unable to read source file.', $savedConversion->getMessage());
            });
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->with($this->pathResolver->uploadPath($message->getOwnerId(), $message->getId(), 'json'))
            ->willThrowException(new \RuntimeException('Unable to read source file.'));
        $this->defaultStorage->expects(self::never())->method('write');
        $this->fileConverter->expects(self::never())->method('convert');

        ($this->handler)($message);
    }

    public function testItMarksConversionAsFailedWhenConversionFails(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $saveCount = 0;

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (Conversion $savedConversion) use ($conversion, &$saveCount): void {
                ++$saveCount;
                self::assertSame($conversion, $savedConversion);

                if (1 === $saveCount) {
                    self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());

                    return;
                }

                self::assertSame(ConversionStatus::Failed, $savedConversion->getStatus());
                self::assertSame('Converter crashed.', $savedConversion->getMessage());
                self::assertNotNull($savedConversion->getProcessingEndedAt());
            });
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->willReturn('{"country":"Croatia"}');
        $this->defaultStorage->expects(self::never())->method('write');
        $this->fileConverter->expects(self::once())
            ->method('convert')
            ->with('{"country":"Croatia"}', 'json', 'xml')
            ->willThrowException(new \RuntimeException('Converter crashed.'));

        ($this->handler)($message);
    }

    public function testItMarksConversionAsFailedWhenWritingConvertedContentFails(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $saveCount = 0;

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (Conversion $savedConversion) use ($conversion, &$saveCount): void {
                ++$saveCount;
                self::assertSame($conversion, $savedConversion);

                if (1 === $saveCount) {
                    self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());

                    return;
                }

                self::assertSame(ConversionStatus::Failed, $savedConversion->getStatus());
                self::assertSame('Unable to store converted file.', $savedConversion->getMessage());
                self::assertNotNull($savedConversion->getProcessingEndedAt());
            });
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->willReturn('{"country":"Croatia"}');
        $this->fileConverter->expects(self::once())
            ->method('convert')
            ->willReturn('<root><country>Croatia</country></root>');
        $this->defaultStorage->expects(self::once())
            ->method('write')
            ->with(
                $this->pathResolver->convertedPath($message->getOwnerId(), $message->getId(), 'xml'),
                '<root><country>Croatia</country></root>',
            )
            ->willThrowException(new \RuntimeException('Unable to store converted file.'));

        ($this->handler)($message);
    }

    public function testItPropagatesFailureWhenSavingFailedStateFails(): void
    {
        [$message, $conversion] = self::createMessageAndConversion();
        $saveCount = 0;
        $exception = new \RuntimeException('Unable to persist failed conversion.');

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($message->getId(), $message->getOwnerId())
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->willReturnCallback(function (Conversion $savedConversion) use ($conversion, &$saveCount, $exception): void {
                ++$saveCount;
                self::assertSame($conversion, $savedConversion);

                if (1 === $saveCount) {
                    self::assertSame(ConversionStatus::InProgress, $savedConversion->getStatus());

                    return;
                }

                self::assertSame(ConversionStatus::Failed, $savedConversion->getStatus());
                self::assertSame('Converter crashed.', $savedConversion->getMessage());

                throw $exception;
            });
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->willReturn('{"country":"Croatia"}');
        $this->defaultStorage->expects(self::never())->method('write');
        $this->fileConverter->expects(self::once())
            ->method('convert')
            ->willThrowException(new \RuntimeException('Converter crashed.'));

        $this->expectExceptionObject($exception);

        ($this->handler)($message);
    }

    /**
     * @return array{ConvertFile, Conversion}
     */
    private static function createMessageAndConversion(): array
    {
        $id = new UuidV7();
        $ownerId = new UuidV7();

        return [
            new ConvertFile($id, $ownerId),
            new Conversion($id, $ownerId, 'json', 'xml'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Conversion;
use App\Model\ConversionRequest;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use App\Service\AcceptConversion;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\UuidV7;

final class AcceptConversionTest extends TestCase
{
    private FilesystemOperator&MockObject $defaultStorage;
    private ConversionRepository&MockObject $conversionRepository;
    private MessageBusInterface&MockObject $messageBus;
    private AcceptConversion $acceptConversion;

    protected function setUp(): void
    {
        $this->defaultStorage = $this->createMock(FilesystemOperator::class);
        $this->conversionRepository = $this->getMockBuilder(ConversionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['save', 'delete'])
            ->getMock();
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->acceptConversion = new AcceptConversion(
            $this->defaultStorage,
            $this->conversionRepository,
            $this->messageBus,
        );
    }

    public function testItMovesFileSavesConversionAndDispatchesMessage(): void
    {
        $request = self::createConversionRequest();
        $expectedContent = file_get_contents(self::fixturePath('sample.json'));
        self::assertIsString($expectedContent);

        $this->defaultStorage->expects(self::once())
            ->method('writeStream')
            ->with(
                sprintf('uploads/%s/%s.json', $request->ownerId, $request->id),
                self::callback(static function (mixed $stream) use ($expectedContent): bool {
                    if (!is_resource($stream)) {
                        return false;
                    }

                    return $expectedContent === stream_get_contents($stream);
                }),
            );

        $this->conversionRepository->expects(self::once())
            ->method('save')
            ->with(self::callback(static function (Conversion $conversion) use ($request): bool {
                self::assertSame($request->id, $conversion->getId());
                self::assertSame($request->ownerId, $conversion->getOwnerId());
                self::assertSame($request->sourceFormat, $conversion->getSourceFormat());
                self::assertSame($request->targetFormat, $conversion->getTargetFormat());

                return true;
            }));
        $this->conversionRepository->expects(self::never())->method('delete');

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $message) use ($request): bool {
                self::assertInstanceOf(ConvertFile::class, $message);
                self::assertSame($request->id, $message->getId());
                self::assertSame($request->ownerId, $message->getOwnerId());

                return true;
            }))
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $conversion = $this->acceptConversion->accept($request);

        self::assertSame($request->id, $conversion->getId());
        self::assertSame($request->ownerId, $conversion->getOwnerId());
        self::assertSame('accepted', $conversion->getStatus()->asString());
    }

    public function testItPropagatesStorageWriteFailures(): void
    {
        $request = self::createConversionRequest();
        $exception = new class('Unable to write uploaded file.') extends \RuntimeException implements FilesystemException {
        };

        $this->defaultStorage->expects(self::once())
            ->method('writeStream')
            ->willThrowException($exception);
        $this->defaultStorage->expects(self::never())->method('delete');
        $this->conversionRepository->expects(self::never())->method('save');
        $this->conversionRepository->expects(self::never())->method('delete');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->expectExceptionObject($exception);

        $this->acceptConversion->accept($request);
    }

    public function testItThrowsRuntimeExceptionWhenUploadedFileCannotBeOpened(): void
    {
        $request = self::createConversionRequest();
        $uploadedFile = self::createStub(UploadedFile::class);
        $uploadedFile->method('getPathname')->willReturn('/definitely/missing/file.json');
        $request->file = $uploadedFile;

        $this->defaultStorage->expects(self::never())->method('writeStream');
        $this->defaultStorage->expects(self::never())->method('delete');
        $this->conversionRepository->expects(self::never())->method('save');
        $this->conversionRepository->expects(self::never())->method('delete');
        $this->messageBus->expects(self::never())->method('dispatch');

        set_error_handler(static fn (): true => true);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Unable to open uploaded file.');

            $this->acceptConversion->accept($request);
        } finally {
            restore_error_handler();
        }
    }

    public function testItPropagatesOrmExceptionsFromSave(): void
    {
        $request = self::createConversionRequest();
        $exception = new class('ORM failure.') extends \RuntimeException implements ORMException {
        };

        $sourcePath = sprintf('uploads/%s/%s.json', $request->ownerId, $request->id);

        $this->defaultStorage->expects(self::once())->method('writeStream');
        $this->defaultStorage->expects(self::once())
            ->method('delete')
            ->with($sourcePath);
        $this->conversionRepository->expects(self::once())
            ->method('save')
            ->willThrowException($exception);
        $this->conversionRepository->expects(self::never())->method('delete');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->expectExceptionObject($exception);

        $this->acceptConversion->accept($request);
    }

    public function testItPropagatesOptimisticLockExceptionsFromSave(): void
    {
        $request = self::createConversionRequest();
        $exception = new OptimisticLockException('Optimistic lock failure.', null);

        $sourcePath = sprintf('uploads/%s/%s.json', $request->ownerId, $request->id);

        $this->defaultStorage->expects(self::once())->method('writeStream');
        $this->defaultStorage->expects(self::once())
            ->method('delete')
            ->with($sourcePath);
        $this->conversionRepository->expects(self::once())
            ->method('save')
            ->willThrowException($exception);
        $this->conversionRepository->expects(self::never())->method('delete');
        $this->messageBus->expects(self::never())->method('dispatch');

        $this->expectExceptionObject($exception);

        $this->acceptConversion->accept($request);
    }

    public function testItPropagatesMessengerExceptionsFromDispatch(): void
    {
        $request = self::createConversionRequest();
        $exception = new class('Dispatch failure.') extends \RuntimeException implements ExceptionInterface {
        };

        $sourcePath = sprintf('uploads/%s/%s.json', $request->ownerId, $request->id);

        $this->defaultStorage->expects(self::once())->method('writeStream');
        $this->defaultStorage->expects(self::once())
            ->method('delete')
            ->with($sourcePath);
        $this->conversionRepository->expects(self::once())->method('save');
        $this->conversionRepository->expects(self::once())
            ->method('delete')
            ->with(self::callback(static function (Conversion $conversion) use ($request): bool {
                self::assertSame($request->id, $conversion->getId());
                self::assertSame($request->ownerId, $conversion->getOwnerId());

                return true;
            }));
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->acceptConversion->accept($request);
    }

    private static function createConversionRequest(): ConversionRequest
    {
        return new ConversionRequest(
            new UuidV7(),
            new UuidV7(),
            new UploadedFile(
                self::fixturePath('sample.json'),
                'sample.json',
                'application/json',
                test: true,
            ),
            'xml',
        );
    }

    private static function fixturePath(string $filename): string
    {
        return dirname(__DIR__, 2).'/Fixtures/'.$filename;
    }
}

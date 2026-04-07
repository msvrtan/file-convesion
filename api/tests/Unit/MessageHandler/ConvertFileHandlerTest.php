<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Entity\Conversion;
use App\MessageHandler\ConvertFileHandler;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use App\Service\FileConverter\FileConverter;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\UuidV7;

final class ConvertFileHandlerTest extends TestCase
{
    private FilesystemOperator&MockObject $defaultStorage;
    private ConversionRepository&MockObject $conversionRepository;
    private FileConverter&MockObject $fileConverter;
    private ConvertFileHandler $handler;

    protected function setUp(): void
    {
        $this->defaultStorage = $this->createMock(FilesystemOperator::class);
        $this->conversionRepository = $this->getMockBuilder(ConversionRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['load', 'save'])
            ->getMock();
        $this->fileConverter = $this->createMock(FileConverter::class);

        $this->handler = new ConvertFileHandler(
            $this->defaultStorage,
            $this->conversionRepository,
            $this->fileConverter,
        );
    }

    public function testItReadsSourceConvertsItAndStoresConvertedContent(): void
    {
        $id = new UuidV7();
        $ownerId = new UuidV7();
        $message = new ConvertFile($id, $ownerId);
        $conversion = new Conversion($id, $ownerId, 'json', 'xml');

        $this->conversionRepository->expects(self::once())
            ->method('load')
            ->with($id, $ownerId)
            ->willReturn($conversion);
        $this->conversionRepository->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(static function (Conversion $savedConversion) use ($conversion): bool {
                self::assertSame($conversion, $savedConversion);

                return true;
            }));
        $this->defaultStorage->expects(self::once())
            ->method('read')
            ->with(sprintf('uploads/%s/%s.json', $ownerId, $id))
            ->willReturn('{"country":"Croatia"}');
        $this->fileConverter->expects(self::once())
            ->method('convert')
            ->with('{"country":"Croatia"}', 'json', 'xml')
            ->willReturn('<root><country>Croatia</country></root>');
        $this->defaultStorage->expects(self::once())
            ->method('write')
            ->with(
                sprintf('converted/%s/%s.xml', $ownerId, $id),
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
}

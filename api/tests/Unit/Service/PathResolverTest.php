<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Conversion;
use App\Model\ConversionRequest;
use App\Service\PathResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\UuidV7;

final class PathResolverTest extends TestCase
{
    private PathResolver $pathResolver;

    protected function setUp(): void
    {
        $this->pathResolver = new PathResolver();
    }

    public function testItBuildsUploadPathFromScalars(): void
    {
        self::assertSame(
            'uploads/owner-id/conversion-id.json',
            $this->pathResolver->uploadPath('owner-id', 'conversion-id', 'json'),
        );
    }

    public function testItBuildsConvertedPathFromScalars(): void
    {
        self::assertSame(
            'converted/owner-id/conversion-id.xml',
            $this->pathResolver->convertedPath('owner-id', 'conversion-id', 'xml'),
        );
    }

    public function testItBuildsUploadPathFromRequest(): void
    {
        $request = new ConversionRequest(
            new UuidV7(),
            new UuidV7(),
            self::createStub(UploadedFile::class),
            'xml',
        );
        $request->sourceFormat = 'json';

        self::assertSame(
            sprintf('uploads/%s/%s.json', $request->ownerId, $request->id),
            $this->pathResolver->uploadPathForRequest($request),
        );
    }

    public function testItBuildsPathsFromConversion(): void
    {
        $conversion = new Conversion(new UuidV7(), new UuidV7(), 'json', 'xml');

        self::assertSame(
            sprintf('uploads/%s/%s.json', $conversion->getOwnerId(), $conversion->getId()),
            $this->pathResolver->uploadPathForConversion($conversion),
        );
        self::assertSame(
            sprintf('converted/%s/%s.xml', $conversion->getOwnerId(), $conversion->getId()),
            $this->pathResolver->convertedPathForConversion($conversion),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Model\BadRequest;
use App\Service\RequestResolver;
use App\Tests\UsesFixtureFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\UuidV7;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestResolverTest extends TestCase
{
    use UsesFixtureFiles;

    private RequestResolver $requestResolver;

    protected function setUp(): void
    {
        $this->requestResolver = new RequestResolver($this->createValidator());
    }

    public function testItBuildsConversionRequestFromValidHttpRequest(): void
    {
        $id = new UuidV7();
        $ownerId = new UuidV7();

        $request = new Request(
            request: ['targetFormat' => 'xml'],
            files: ['file' => self::createFixtureUpload()],
        );

        $conversionRequest = $this->requestResolver->convertRequest($request, $id, $ownerId);

        self::assertSame($id, $conversionRequest->id);
        self::assertSame($ownerId, $conversionRequest->ownerId);
        self::assertSame('json', $conversionRequest->sourceFormat);
        self::assertSame('xml', $conversionRequest->targetFormat);
        self::assertInstanceOf(UploadedFile::class, $conversionRequest->file);
        self::assertSame('sample.json', $conversionRequest->file->getClientOriginalName());
    }

    public function testItThrowsBadRequestForInvalidHttpRequest(): void
    {
        $request = new Request(request: ['targetFormat' => 'yaml']);

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('A file is required.');
        $this->expectExceptionMessage('Supported source formats are csv, json, xlsx, ods.');
        $this->expectExceptionMessage('Supported target formats are json, xml.');

        $this->requestResolver->convertRequest($request, new UuidV7(), new UuidV7());
    }

    public function testItAcceptsUppercaseFileExtensions(): void
    {
        $request = new Request(
            request: ['targetFormat' => 'xml'],
            files: ['file' => self::createFixtureUpload('sample.xlsx', 'sample.XLSX')],
        );

        $conversionRequest = $this->requestResolver->convertRequest($request, new UuidV7(), new UuidV7());

        self::assertSame('xlsx', $conversionRequest->sourceFormat);
        self::assertSame('sample.XLSX', $conversionRequest->file?->getClientOriginalName());
    }

    public function testItAcceptsMixedCaseFileExtensions(): void
    {
        $request = new Request(
            request: ['targetFormat' => 'json'],
            files: ['file' => self::createFixtureUpload('sample.ods', 'sample.oDs')],
        );

        $conversionRequest = $this->requestResolver->convertRequest($request, new UuidV7(), new UuidV7());

        self::assertSame('ods', $conversionRequest->sourceFormat);
        self::assertSame('sample.oDs', $conversionRequest->file?->getClientOriginalName());
    }

    public function testItThrowsBadRequestForArrayShapedFileInput(): void
    {
        $request = new Request(
            request: ['targetFormat' => 'xml'],
            files: [
                'file' => [
                    self::createFixtureUpload(),
                    self::createFixtureUpload('sample.csv'),
                ],
            ],
        );

        $this->expectException(BadRequest::class);
        $this->expectExceptionMessage('Only a single file upload is supported.');

        $this->requestResolver->convertRequest($request, new UuidV7(), new UuidV7());
    }

    private function createValidator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }
}

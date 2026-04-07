<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

final class ConversionSecurityTest extends WebTestCase
{
    private const ACME_CONVERSION_ID = '019d86b0-0000-7000-8000-000000000001';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testInvalidJwtCannotAccessCreateConversion(): void
    {
        $this->requestCreateConversion('Bearer wRoNgTokEn');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testInvalidJwtCannotAccessConversionStatus(): void
    {
        $this->requestAuthenticated('GET', '/conversions/019d58eb-2dc4-7b0f-8fec-6bb9804399f2', 'Bearer wRoNgTokEn');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testInvalidJwtCannotAccessConversionDownload(): void
    {
        $this->requestAuthenticated('GET', '/conversions/019d58eb-2dc4-7b0f-8fec-6bb9804399f2/download', 'Bearer wRoNgTokEn');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testCustomerWithValidJwtCanCreateConversion(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->requestCreateConversion(sprintf('Bearer %s', $token));

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');
    }

    public function testCustomerWithValidJwtCanViewConversionStatus(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->requestAuthenticated(
            'GET',
            sprintf('/conversions/%s', self::ACME_CONVERSION_ID),
            sprintf('Bearer %s', $token),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testCustomerWithValidJwtCanDownloadConversion(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->requestAuthenticated(
            'GET',
            '/conversions/019d58eb-2dc4-7b0f-8fec-6bb9804399f2/download',
            sprintf('Bearer %s', $token),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    private function createJwtToken(string $username): string
    {
        $this->client->request(
            'POST',
            '/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => $username,
                'password' => AppFixtures::DEFAULT_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{token?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertNotSame('', $payload['token']);

        return $payload['token'];
    }

    private function requestCreateConversion(string $authorization): void
    {
        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'json'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_AUTHORIZATION' => $authorization,
            ],
        );
    }

    private function requestAuthenticated(string $method, string $uri, string $authorization): void
    {
        $this->client->request(
            $method,
            $uri,
            server: [
                'HTTP_AUTHORIZATION' => $authorization,
            ],
        );
    }

    private static function createFixtureUpload(): UploadedFile
    {
        return new UploadedFile(
            self::fixturePath('sample.csv'),
            'sample.csv',
            'text/csv',
            test: true,
        );
    }

    private static function fixturePath(string $filename): string
    {
        return dirname(__DIR__).'/Fixtures/'.$filename;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ConversionAcceptTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testCustomerCanSubmitConversionRequest(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{id?: mixed, status?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('id', $payload);
        self::assertIsString($payload['id']);
        self::assertTrue(Uuid::isValid($payload['id']));
        self::assertSame('accepted', $payload['status'] ?? null);
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

    private static function createFixtureUpload(): UploadedFile
    {
        return new UploadedFile(
            self::fixturePath('sample.json'),
            'sample.json',
            'application/json',
            test: true,
        );
    }

    private static function fixturePath(string $filename): string
    {
        return dirname(__DIR__).'/Fixtures/'.$filename;
    }
}

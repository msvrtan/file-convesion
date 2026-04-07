<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionStatusTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    #[DataProvider('statusProvider')]
    public function testItReturnsStatusFromFixtures(
        string $username,
        string $conversionId,
        string $expectedStatus,
        string $expectedMessage,
    ): void {
        $token = $this->createJwtToken($username);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s', $conversionId),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{id?: mixed, status?: mixed, message?: mixed, lastUpdate?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame($conversionId, $payload['id'] ?? null);
        self::assertSame($expectedStatus, $payload['status'] ?? null);
        self::assertSame($expectedMessage, $payload['message'] ?? null);
        self::assertIsString($payload['lastUpdate'] ?? null);
        self::assertNotSame('', $payload['lastUpdate']);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function statusProvider(): iterable
    {
        yield 'accepted' => [
            AppFixtures::ACME_USERNAME,
            '019d86b0-0000-7000-8000-000000000001',
            'accepted',
            'Your conversion is accepted. We will try to start processing it as soon as possible.',
        ];

        yield 'in progress' => [
            AppFixtures::GLOBEX_USERNAME,
            '019d86b0-0000-7000-8000-000000000002',
            'inprogress',
            'Your conversion is being converted right now.',
        ];

        yield 'failed' => [
            AppFixtures::INITECH_USERNAME,
            '019d86b0-0000-7000-8000-000000000003',
            'failed',
            'Conversion failed: Source payload could not be normalized.',
        ];

        yield 'completed' => [
            AppFixtures::UMBRELLA_USERNAME,
            '019d86b0-0000-7000-8000-000000000004',
            'completed',
            'Your conversion is completed.',
        ];
    }

    public function testNonExistentConversionReturns404(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            '/conversions/019d86b0-0000-7000-8000-999999999999',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{message?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Conversion not found.', $payload['message'] ?? null);
    }

    public function testAnotherCustomerConversionReturns404(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            '/conversions/019d86b0-0000-7000-8000-000000000004',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{message?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Conversion not found.', $payload['message'] ?? null);
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

        self::assertIsString($payload['token'] ?? null);

        return $payload['token'];
    }
}

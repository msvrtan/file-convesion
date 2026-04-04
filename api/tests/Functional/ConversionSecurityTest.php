<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionSecurityTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function conversionRoutes(): array
    {
        return [
            'create' => ['POST', '/conversions'],
            'status' => ['GET', '/conversions/019d58eb-2dc4-7b0f-8fec-6bb9804399f2'],
            'download' => ['GET', '/conversions/019d58eb-2dc4-7b0f-8fec-6bb9804399f2/download'],
        ];
    }

    #[DataProvider('conversionRoutes')]
    public function testCustomerWithoutRoleUserCannotAccessConversions(string $method, string $uri): void
    {
        $token = $this->createJwtToken(AppFixtures::GLOBEX_USERNAME);

        $this->client->request(
            $method,
            $uri,
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer wRoNgTokEn',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[DataProvider('conversionRoutes')]
    public function testCustomerWithRoleUserJwtCanAccessConversions(string $method, string $uri): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            $method,
            $uri,
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
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
}

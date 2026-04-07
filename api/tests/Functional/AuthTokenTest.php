<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthTokenTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    public function testCustomerCanLogInAndReceiveJwtToken(): void
    {
        $this->requestAuthToken([
            'username' => AppFixtures::ACME_USERNAME,
            'password' => AppFixtures::DEFAULT_PASSWORD,
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_OK);

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{token?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('token', $payload);
        self::assertIsString($payload['token']);
        self::assertNotSame('', $payload['token']);
    }

    #[DataProvider('invalidCredentialsProvider')]
    public function testInvalidCredentialsAreRejected(array $payload): void
    {
        $this->requestAuthToken($payload);

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{code?: mixed, message?: mixed} $responsePayload */
        $responsePayload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $responsePayload['code'] ?? null);
        self::assertSame('Invalid credentials.', $responsePayload['message'] ?? null);
    }

    #[DataProvider('missingCredentialsProvider')]
    public function testMissingCredentialsAreRejected(array $payload, string $expectedDetail): void
    {
        $this->requestAuthToken($payload);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{status?: mixed, detail?: mixed} $responsePayload */
        $responsePayload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(Response::HTTP_BAD_REQUEST, $responsePayload['status'] ?? null);
        self::assertSame($expectedDetail, $responsePayload['detail'] ?? null);
    }

    /**
     * @return iterable<string, array{array{username: string, password: string}}>
     */
    public static function invalidCredentialsProvider(): iterable
    {
        yield 'wrong password' => [[
            'username' => AppFixtures::ACME_USERNAME,
            'password' => 'wrong-password',
        ]];

        yield 'wrong username' => [[
            'username' => 'does-not-exist',
            'password' => AppFixtures::DEFAULT_PASSWORD,
        ]];
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function missingCredentialsProvider(): iterable
    {
        yield 'missing password' => [
            ['username' => AppFixtures::ACME_USERNAME],
            'The key "password" must be provided.',
        ];

        yield 'missing username' => [
            ['password' => AppFixtures::DEFAULT_PASSWORD],
            'The key "username" must be provided.',
        ];
    }

    /** @param array<string, string> $payload */
    private function requestAuthToken(array $payload): void
    {
        $this->client->request(
            'POST',
            '/auth/token',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}

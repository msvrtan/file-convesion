<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ConversionRoutingTest extends WebTestCase
{
    use AuthenticatesCustomer;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    protected function browser(): KernelBrowser
    {
        return $this->client;
    }

    public function testListEndpointRejectsGetMethod(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            '/conversions',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('allow', 'POST');
    }

    public function testStatusEndpointRejectsPostMethod(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions/019d86b0-0000-7000-8000-000000000001',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
        self::assertResponseHeaderSame('allow', 'GET');
    }

    public function testStatusEndpointRejectsInvalidUuid(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            '/conversions/not-a-uuid',
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}

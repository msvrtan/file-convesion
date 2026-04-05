<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
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

        /** @var ConversionRepository $conversionRepository */
        $conversionRepository = self::getContainer()->get(ConversionRepository::class);
        $conversion = $conversionRepository->load(
            Uuid::fromString($payload['id']),
            Uuid::fromString(AppFixtures::ACME_ID),
        );

        self::assertNotNull($conversion);
        self::assertSame($payload['id'], (string) $conversion->getId());
        self::assertSame(AppFixtures::ACME_ID, (string) $conversion->getOwnerId());

        $storedFilePath = self::storagePath(
            sprintf('uploads/%s/%s.json', AppFixtures::ACME_ID, $payload['id']),
        );
        self::assertFileExists($storedFilePath);
        self::assertSame(
            file_get_contents(self::fixturePath('sample.json')),
            file_get_contents($storedFilePath),
        );

        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);
        $queuedMessage = $connection->fetchAssociative(
            'SELECT body, headers FROM messenger_messages WHERE queue_name = :queueName ORDER BY id DESC LIMIT 1',
            ['queueName' => 'async'],
        );

        self::assertIsArray($queuedMessage);
        self::assertArrayHasKey('body', $queuedMessage);
        self::assertArrayHasKey('headers', $queuedMessage);
        self::assertIsString($queuedMessage['body']);
        self::assertIsString($queuedMessage['headers']);

        $headers = json_decode($queuedMessage['headers'], true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($headers);

        foreach ($headers as $key => $value) {
            self::assertIsString($key);
            self::assertIsString($value);
        }

        $serializer = new PhpSerializer();
        /** @var array<string, string> $headers */
        $envelope = $serializer->decode([
            'body' => $queuedMessage['body'],
            'headers' => $headers,
        ]);

        $message = $envelope->getMessage();

        self::assertInstanceOf(ConvertFile::class, $message);
        self::assertSame($payload['id'], (string) $message->getId());
        self::assertSame(AppFixtures::ACME_ID, (string) $message->getOwnerId());
    }

    public function testHappyPathDefaultsToJsonWhenAcceptHeaderIsMissing(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{id?: mixed, status?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($payload['id'] ?? null);
        self::assertSame('accepted', $payload['status'] ?? null);
    }

    public function testHappyPathDefaultsToJsonWhenAcceptHeaderIsWildcard(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => '*/*',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{id?: mixed, status?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($payload['id'] ?? null);
        self::assertSame('accepted', $payload['status'] ?? null);
    }

    public function testHappyPathDefaultsToJsonWhenAcceptHeaderContainsMultipleValues(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/json, */*',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{id?: mixed, status?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsString($payload['id'] ?? null);
        self::assertSame('accepted', $payload['status'] ?? null);
    }

    public function testBadRequestUsesXmlWhenAcceptHeaderRequestsXml(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'yaml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/xml',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('content-type', 'application/xml');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $payload = simplexml_load_string($content);
        self::assertInstanceOf(\SimpleXMLElement::class, $payload);
        self::assertSame(
            'Supported target formats are json, xml.',
            (string) $payload->message,
        );
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

    private static function storagePath(string $filename): string
    {
        return dirname(__DIR__, 2).'/var/storage/default/'.$filename;
    }
}

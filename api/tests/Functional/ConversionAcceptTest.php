<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Model\ConvertFile;
use App\Repository\ConversionRepository;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

final class ConversionAcceptTest extends WebTestCase
{
    use AuthenticatesCustomer;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->asyncTransport()->reset();
    }

    protected function browser(): KernelBrowser
    {
        return $this->client;
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

        $storedFilePath = sprintf('uploads/%s/%s.json', AppFixtures::ACME_ID, $payload['id']);
        self::assertTrue($this->defaultStorage()->fileExists($storedFilePath));
        self::assertSame(
            file_get_contents(self::fixturePath('sample.json')),
            $this->defaultStorage()->read($storedFilePath),
        );

        $sentEnvelopes = $this->asyncTransport()->getSent();
        self::assertCount(1, $sentEnvelopes);

        $envelope = $sentEnvelopes[0];

        $message = $envelope->getMessage();

        self::assertInstanceOf(ConvertFile::class, $message);
        self::assertSame($payload['id'], (string) $message->getId());
        self::assertSame(AppFixtures::ACME_ID, (string) $message->getOwnerId());
    }

    public function testCustomerCanSubmitCsvConversionRequest(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload('sample.csv', 'sample.csv', 'text/csv')],
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

        self::assertIsString($payload['id'] ?? null);
        self::assertSame('accepted', $payload['status'] ?? null);

        /** @var ConversionRepository $conversionRepository */
        $conversionRepository = self::getContainer()->get(ConversionRepository::class);
        $conversion = $conversionRepository->load(
            Uuid::fromString($payload['id']),
            Uuid::fromString(AppFixtures::ACME_ID),
        );

        self::assertNotNull($conversion);
        self::assertSame('csv', $conversion->getSourceFormat());

        $storedFilePath = sprintf('uploads/%s/%s.csv', AppFixtures::ACME_ID, $payload['id']);
        self::assertTrue($this->defaultStorage()->fileExists($storedFilePath));
        self::assertSame(
            file_get_contents(self::fixturePath('sample.csv')),
            $this->defaultStorage()->read($storedFilePath),
        );
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

    public function testHappyPathUsesXmlWhenAcceptHeaderRequestsXml(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/xml',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_ACCEPTED);
        self::assertResponseHeaderSame('content-type', 'application/xml');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        $payload = simplexml_load_string($content);
        self::assertInstanceOf(\SimpleXMLElement::class, $payload);
        self::assertTrue(Uuid::isValid((string) $payload->id));
        self::assertSame('accepted', (string) $payload->status);
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
            self::normalizeXmlText((string) $payload->message),
        );
    }

    public function testBadRequestUsesXmlWhenAcceptHeaderContainsMultipleValues(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'yaml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/xml, */*',
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
            self::normalizeXmlText((string) $payload->message),
        );
    }

    public function testBadRequestUsesXmlWhenAcceptHeaderContainsWeightedXml(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'yaml'],
            ['file' => self::createFixtureUpload()],
            server: [
                'HTTP_ACCEPT' => 'application/xml;q=0.9,application/json;q=0.8',
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
            self::normalizeXmlText((string) $payload->message),
        );
    }

    public function testBadRequestIsReturnedForArrayShapedFileInput(): void
    {
        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'POST',
            '/conversions',
            ['targetFormat' => 'xml'],
            [
                'file' => [
                    self::createFixtureUpload(),
                    self::createFixtureUpload('sample.csv', 'sample.csv', 'text/csv'),
                ],
            ],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{message?: mixed} $payload */
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('Only a single file upload is supported.', $payload['message'] ?? null);
    }

    private static function createFixtureUpload(
        string $fixtureName = 'sample.json',
        ?string $clientName = null,
        string $mimeType = 'application/json',
    ): UploadedFile {
        return new UploadedFile(
            self::fixturePath($fixtureName),
            $clientName ?? $fixtureName,
            $mimeType,
            test: true,
        );
    }

    private static function fixturePath(string $filename): string
    {
        return dirname(__DIR__).'/Fixtures/'.$filename;
    }

    private function asyncTransport(): InMemoryTransport
    {
        /** @var InMemoryTransport $transport */
        $transport = self::getContainer()->get('messenger.transport.async');

        return $transport;
    }

    private function defaultStorage(): FilesystemOperator
    {
        /** @var Filesystem $defaultStorage */
        $defaultStorage = self::getContainer()->get('League\\Flysystem\\FilesystemOperator $defaultStorage');

        return $defaultStorage;
    }

    private static function normalizeXmlText(string $value): string
    {
        return trim(str_replace(["\r", "\n", '\r', '\n'], '', $value));
    }
}

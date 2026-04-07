<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\DataFixtures\AppFixtures;
use App\Entity\Conversion;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ConversionDownloadTest extends WebTestCase
{
    /** @var list<string> */
    private array $createdConversionIds = [];

    /** @var list<string> */
    private array $seededStoragePaths = [];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
    }

    protected function tearDown(): void
    {
        foreach ($this->seededStoragePaths as $path) {
            if ($this->defaultStorage()->fileExists($path)) {
                $this->defaultStorage()->delete($path);
            }
        }

        if ([] !== $this->createdConversionIds) {
            $entityManager = $this->entityManager();

            foreach ($this->createdConversionIds as $conversionId) {
                $conversion = $entityManager->find(Conversion::class, Uuid::fromString($conversionId));

                if ($conversion instanceof Conversion) {
                    $entityManager->remove($conversion);
                }
            }

            $entityManager->flush();
        }

        $this->seededStoragePaths = [];
        $this->createdConversionIds = [];

        parent::tearDown();
    }

    public function testCompletedConversionCanBeDownloaded(): void
    {
        $conversionId = '019d86b0-0000-7000-8000-000000000004';
        $ownerId = AppFixtures::UMBRELLA_ID;
        $targetFormat = 'xml';
        $fileContent = '<root><item>test content</item></root>';

        $this->seedConvertedFile($ownerId, $conversionId, $targetFormat, $fileContent);

        $token = $this->createJwtToken(AppFixtures::UMBRELLA_USERNAME);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/xml');
        self::assertResponseHeaderSame(
            'content-disposition',
            sprintf('attachment; filename="%s.%s"', $conversionId, $targetFormat),
        );

        $content = $this->client->getInternalResponse()->getContent();
        self::assertSame($fileContent, $content);
    }

    public function testSecondCompletedConversionCanBeDownloaded(): void
    {
        $conversionId = '019d86b0-0000-7000-8000-000000000008';
        $ownerId = AppFixtures::GLOBEX_ID;
        $targetFormat = 'xml';
        $fileContent = '<data><row>globex export</row></data>';

        $this->seedConvertedFile($ownerId, $conversionId, $targetFormat, $fileContent);

        $token = $this->createJwtToken(AppFixtures::GLOBEX_USERNAME);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/xml');

        $content = $this->client->getInternalResponse()->getContent();
        self::assertSame($fileContent, $content);
    }

    public function testCompletedJsonConversionCanBeDownloaded(): void
    {
        $conversionId = '019d86b0-0000-7000-8000-000000000009';
        $ownerId = AppFixtures::ACME_ID;
        $targetFormat = 'json';
        $fileContent = '{"items":[{"name":"test content"}]}';

        $this->createCompletedConversion(
            ownerId: $ownerId,
            conversionId: $conversionId,
            sourceFormat: 'csv',
            targetFormat: $targetFormat,
        );
        $this->seedConvertedFile($ownerId, $conversionId, $targetFormat, $fileContent);

        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
            server: [
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertResponseHeaderSame(
            'content-disposition',
            sprintf('attachment; filename="%s.%s"', $conversionId, $targetFormat),
        );

        $content = $this->client->getInternalResponse()->getContent();
        self::assertSame($fileContent, $content);
    }

    #[DataProvider('nonDownloadableConversionProvider')]
    public function testNonCompletedConversionReturns404(
        string $username,
        string $conversionId,
    ): void {
        $token = $this->createJwtToken($username);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function nonDownloadableConversionProvider(): iterable
    {
        yield 'accepted conversion' => [
            AppFixtures::ACME_USERNAME,
            '019d86b0-0000-7000-8000-000000000001',
        ];

        yield 'in progress conversion' => [
            AppFixtures::GLOBEX_USERNAME,
            '019d86b0-0000-7000-8000-000000000002',
        ];

        yield 'failed conversion' => [
            AppFixtures::INITECH_USERNAME,
            '019d86b0-0000-7000-8000-000000000003',
        ];

        yield 'non-existent conversion' => [
            AppFixtures::ACME_USERNAME,
            '019d86b0-0000-7000-8000-999999999999',
        ];
    }

    public function testDownloadingAnotherCustomerConversionReturns404(): void
    {
        $conversionId = '019d86b0-0000-7000-8000-000000000004';

        $token = $this->createJwtToken(AppFixtures::ACME_USERNAME);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testCompletedConversionWithoutStoredFileReturns404(): void
    {
        $conversionId = '019d86b0-0000-7000-8000-000000000008';
        $this->removeConvertedFile(AppFixtures::GLOBEX_ID, $conversionId, 'xml');

        $token = $this->createJwtToken(AppFixtures::GLOBEX_USERNAME);

        $this->client->request(
            'GET',
            sprintf('/conversions/%s/download', $conversionId),
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

    private function seedConvertedFile(
        string $ownerId,
        string $conversionId,
        string $targetFormat,
        string $content,
    ): void {
        $path = sprintf('converted/%s/%s.%s', $ownerId, $conversionId, $targetFormat);

        $this->defaultStorage()->write($path, $content);
        $this->seededStoragePaths[] = $path;
    }

    private function removeConvertedFile(string $ownerId, string $conversionId, string $targetFormat): void
    {
        $path = sprintf('converted/%s/%s.%s', $ownerId, $conversionId, $targetFormat);

        if ($this->defaultStorage()->fileExists($path)) {
            $this->defaultStorage()->delete($path);
        }
    }

    private function createCompletedConversion(
        string $ownerId,
        string $conversionId,
        string $sourceFormat,
        string $targetFormat,
    ): void {
        $conversion = new Conversion(
            Uuid::fromString($conversionId),
            Uuid::fromString($ownerId),
            $sourceFormat,
            $targetFormat,
        );
        $conversion->markAsProcessingStarted();
        $conversion->markAsCompleted();

        $entityManager = $this->entityManager();
        $entityManager->persist($conversion);
        $entityManager->flush();

        $this->createdConversionIds[] = $conversionId;
    }

    private function defaultStorage(): FilesystemOperator
    {
        /** @var FilesystemOperator $defaultStorage */
        $defaultStorage = self::getContainer()->get('League\\Flysystem\\FilesystemOperator $defaultStorage');

        return $defaultStorage;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}

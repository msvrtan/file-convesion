<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\DataFixtures\AppFixtures;
use App\Entity\Conversion;
use App\Repository\ConversionRepository;
use App\Service\PathResolver;
use App\Tests\Functional\AuthenticatesCustomer;
use App\Tests\UsesFixtureFiles;
use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class ConversionFlowTest extends WebTestCase
{
    use AuthenticatesCustomer;
    use UsesFixtureFiles;

    private const DOCTRINE_TRANSPORT_DSN = 'doctrine://default?queue_name=async&auto_setup=0';
    private const DOCTRINE_FAILED_TRANSPORT_DSN = 'doctrine://default?queue_name=failed&auto_setup=0';

    private KernelBrowser $client;

    private ?string $createdConversionId = null;

    protected function tearDown(): void
    {
        if (null !== $this->createdConversionId) {
            $this->deleteCreatedArtifacts($this->createdConversionId);
        }

        parent::tearDown();
    }

    protected function browser(): KernelBrowser
    {
        return $this->client;
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCustomerCanCompleteConversionThroughAsyncWorkerAndDownloadResult(): void
    {
        /** @var string|null $originalTransportDsn */
        $originalTransportDsn = $_SERVER['MESSENGER_TRANSPORT_DSN'] ?? null;
        /** @var string|null $originalFailedTransportDsn */
        $originalFailedTransportDsn = $_SERVER['MESSENGER_FAILED_TRANSPORT_DSN'] ?? null;

        $this->overrideMessengerTransportEnvironment();
        self::ensureKernelShutdown();
        $this->client = self::createClient();

        try {
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

            $conversionId = $payload['id'];
            $this->createdConversionId = $conversionId;

            self::assertSame(1, $this->queuedMessageCount());

            $this->client->request(
                'GET',
                sprintf('/conversions/%s', $conversionId),
                server: [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                ],
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);

            $statusContent = $this->client->getResponse()->getContent();
            self::assertIsString($statusContent);

            /** @var array{status?: mixed} $statusPayload */
            $statusPayload = json_decode($statusContent, true, 512, JSON_THROW_ON_ERROR);

            self::assertSame('accepted', $statusPayload['status'] ?? null);

            $this->consumeAsyncMessage();

            self::assertSame(0, $this->queuedMessageCount());

            $this->client->request(
                'GET',
                sprintf('/conversions/%s', $conversionId),
                server: [
                    'HTTP_ACCEPT' => 'application/json',
                    'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                ],
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);

            $completedStatusContent = $this->client->getResponse()->getContent();
            self::assertIsString($completedStatusContent);

            /** @var array{status?: mixed, message?: mixed} $completedStatusPayload */
            $completedStatusPayload = json_decode($completedStatusContent, true, 512, JSON_THROW_ON_ERROR);

            self::assertSame('completed', $completedStatusPayload['status'] ?? null);
            self::assertSame('Your conversion is completed.', $completedStatusPayload['message'] ?? null);

            $this->client->request(
                'GET',
                sprintf('/conversions/%s/download', $conversionId),
                server: [
                    'HTTP_AUTHORIZATION' => sprintf('Bearer %s', $token),
                ],
            );

            self::assertResponseStatusCodeSame(Response::HTTP_OK);
            self::assertResponseHeaderSame('content-type', 'application/xml');

            $downloadContent = $this->client->getInternalResponse()->getContent();
            self::assertSame(file_get_contents(self::fixturePath('sample.xml')), $downloadContent);
        } finally {
            $this->restoreMessengerTransportEnvironment($originalTransportDsn, $originalFailedTransportDsn);
            self::ensureKernelShutdown();
        }
    }

    private function overrideMessengerTransportEnvironment(): void
    {
        $_SERVER['MESSENGER_TRANSPORT_DSN'] = self::DOCTRINE_TRANSPORT_DSN;
        $_ENV['MESSENGER_TRANSPORT_DSN'] = self::DOCTRINE_TRANSPORT_DSN;
        putenv(sprintf('MESSENGER_TRANSPORT_DSN=%s', self::DOCTRINE_TRANSPORT_DSN));

        $_SERVER['MESSENGER_FAILED_TRANSPORT_DSN'] = self::DOCTRINE_FAILED_TRANSPORT_DSN;
        $_ENV['MESSENGER_FAILED_TRANSPORT_DSN'] = self::DOCTRINE_FAILED_TRANSPORT_DSN;
        putenv(sprintf('MESSENGER_FAILED_TRANSPORT_DSN=%s', self::DOCTRINE_FAILED_TRANSPORT_DSN));
    }

    private function restoreMessengerTransportEnvironment(?string $transportDsn, ?string $failedTransportDsn): void
    {
        $this->restoreEnvironmentValue('MESSENGER_TRANSPORT_DSN', $transportDsn);
        $this->restoreEnvironmentValue('MESSENGER_FAILED_TRANSPORT_DSN', $failedTransportDsn);
    }

    private function restoreEnvironmentValue(string $name, ?string $value): void
    {
        if (null === $value) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $value;
        $_ENV[$name] = $value;
        putenv(sprintf('%s=%s', $name, $value));
    }

    private function consumeAsyncMessage(): void
    {
        self::assertNotNull(self::$kernel);

        $application = new Application(self::$kernel);
        $application->setAutoExit(false);

        $command = $application->find('messenger:consume');
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([
            'receivers' => ['async'],
            '--limit' => 1,
            '--time-limit' => 5,
        ]);

        self::assertSame(0, $exitCode, $tester->getDisplay(true));
    }

    private function queuedMessageCount(): int
    {
        /** @var int|numeric-string $count */
        $count = $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM messenger_messages WHERE queue_name = :queueName',
            ['queueName' => 'async'],
        );

        return (int) $count;
    }

    private function deleteCreatedArtifacts(string $conversionId): void
    {
        $repository = $this->conversionRepository();
        $conversion = $repository->load(
            Uuid::fromString($conversionId),
            Uuid::fromString(AppFixtures::ACME_ID),
        );

        if ($conversion instanceof Conversion) {
            $this->deleteStorageFiles($conversion);
            $repository->delete($conversion);
        }

        $this->createdConversionId = null;
    }

    private function deleteStorageFiles(Conversion $conversion): void
    {
        $uploadPath = $this->pathResolver()->uploadPathForConversion($conversion);
        $convertedPath = $this->pathResolver()->convertedPathForConversion($conversion);

        if ($this->defaultStorage()->fileExists($uploadPath)) {
            $this->defaultStorage()->delete($uploadPath);
        }

        if ($this->defaultStorage()->fileExists($convertedPath)) {
            $this->defaultStorage()->delete($convertedPath);
        }
    }

    private function conversionRepository(): ConversionRepository
    {
        return self::getContainer()->get(ConversionRepository::class);
    }

    private function pathResolver(): PathResolver
    {
        return new PathResolver();
    }

    private function connection(): Connection
    {
        return self::getContainer()->get(Connection::class);
    }

    private function defaultStorage(): FilesystemOperator
    {
        /** @var Filesystem $defaultStorage */
        $defaultStorage = self::getContainer()->get('League\\Flysystem\\FilesystemOperator $defaultStorage');

        return $defaultStorage;
    }
}

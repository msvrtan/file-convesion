<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Conversion;
use App\Model\ConversionStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

final class ConversionFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * @var list<array{
     *     id: string,
     *     ownerId: string,
     *     sourceFormat: 'csv'|'json'|'xlsx'|'ods',
     *     targetFormat: 'json'|'xml',
     *     status: ConversionStatus,
     *     message: ?string,
     *     createdAt: string,
     *     processingStartedAt: ?string,
     *     processingEndedAt: ?string
     * }>
     */
    private const CONVERSIONS = [
        [
            'id' => '019d86b0-0000-7000-8000-000000000001',
            'ownerId' => AppFixtures::ACME_ID,
            'sourceFormat' => 'csv',
            'targetFormat' => 'json',
            'status' => ConversionStatus::Accepted,
            'message' => null,
            'createdAt' => '2026-04-01 09:00:00',
            'processingStartedAt' => null,
            'processingEndedAt' => null,
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000002',
            'ownerId' => AppFixtures::GLOBEX_ID,
            'sourceFormat' => 'csv',
            'targetFormat' => 'xml',
            'status' => ConversionStatus::InProgress,
            'message' => null,
            'createdAt' => '2026-04-01 09:05:00',
            'processingStartedAt' => '2026-04-01 09:06:00',
            'processingEndedAt' => null,
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000003',
            'ownerId' => AppFixtures::INITECH_ID,
            'sourceFormat' => 'json',
            'targetFormat' => 'json',
            'status' => ConversionStatus::Failed,
            'message' => 'Source payload could not be normalized.',
            'createdAt' => '2026-04-01 09:10:00',
            'processingStartedAt' => '2026-04-01 09:11:00',
            'processingEndedAt' => '2026-04-01 09:12:00',
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000004',
            'ownerId' => AppFixtures::UMBRELLA_ID,
            'sourceFormat' => 'json',
            'targetFormat' => 'xml',
            'status' => ConversionStatus::Completed,
            'message' => null,
            'createdAt' => '2026-04-01 09:15:00',
            'processingStartedAt' => '2026-04-01 09:16:00',
            'processingEndedAt' => '2026-04-01 09:17:00',
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000005',
            'ownerId' => AppFixtures::WAYNE_ID,
            'sourceFormat' => 'xlsx',
            'targetFormat' => 'json',
            'status' => ConversionStatus::Accepted,
            'message' => null,
            'createdAt' => '2026-04-01 09:20:00',
            'processingStartedAt' => null,
            'processingEndedAt' => null,
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000006',
            'ownerId' => AppFixtures::STARK_ID,
            'sourceFormat' => 'xlsx',
            'targetFormat' => 'xml',
            'status' => ConversionStatus::InProgress,
            'message' => null,
            'createdAt' => '2026-04-01 09:25:00',
            'processingStartedAt' => '2026-04-01 09:26:00',
            'processingEndedAt' => null,
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000007',
            'ownerId' => AppFixtures::ACME_ID,
            'sourceFormat' => 'ods',
            'targetFormat' => 'json',
            'status' => ConversionStatus::Failed,
            'message' => 'Spreadsheet parser crashed.',
            'createdAt' => '2026-04-01 09:30:00',
            'processingStartedAt' => '2026-04-01 09:31:00',
            'processingEndedAt' => '2026-04-01 09:32:00',
        ],
        [
            'id' => '019d86b0-0000-7000-8000-000000000008',
            'ownerId' => AppFixtures::GLOBEX_ID,
            'sourceFormat' => 'ods',
            'targetFormat' => 'xml',
            'status' => ConversionStatus::Completed,
            'message' => null,
            'createdAt' => '2026-04-01 09:35:00',
            'processingStartedAt' => '2026-04-01 09:36:00',
            'processingEndedAt' => '2026-04-01 09:37:00',
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::CONVERSIONS as $fixtureConversion) {
            $conversion = new Conversion(
                Uuid::fromString($fixtureConversion['id']),
                Uuid::fromString($fixtureConversion['ownerId']),
                $fixtureConversion['sourceFormat'],
                $fixtureConversion['targetFormat'],
            );

            $this->setProperty($conversion, 'status', $fixtureConversion['status']);
            $this->setProperty($conversion, 'message', $fixtureConversion['message']);
            $this->setProperty($conversion, 'createdAt', new \DateTime($fixtureConversion['createdAt']));
            $this->setProperty(
                $conversion,
                'processingStartedAt',
                null === $fixtureConversion['processingStartedAt']
                    ? null
                    : new \DateTime($fixtureConversion['processingStartedAt']),
            );
            $this->setProperty(
                $conversion,
                'processingEndedAt',
                null === $fixtureConversion['processingEndedAt']
                    ? null
                    : new \DateTime($fixtureConversion['processingEndedAt']),
            );

            $manager->persist($conversion);
        }

        $manager->flush();
    }

    /** @return array<class-string<FixtureInterface>> */
    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    private function setProperty(Conversion $conversion, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($conversion, $property);
        $reflection->setValue($conversion, $value);
    }
}

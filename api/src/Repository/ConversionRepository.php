<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Conversion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Conversion>
 */
class ConversionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversion::class);
    }

    public function load(Uuid $id, Uuid $ownerId): ?Conversion
    {
        /** @var Conversion|null $conversion */
        $conversion = $this->findOneBy([
            'id' => $id,
            'ownerId' => $ownerId,
        ]);

        return $conversion;
    }

    public function save(Conversion $conversion): void
    {
        $entityManager = $this->getEntityManager();
        $entityManager->persist($conversion);
        $entityManager->flush();
    }
}

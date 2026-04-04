<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\UuidV7;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    public function findOneById(UuidV7 $id): ?Customer
    {
        /** @var Customer|null $customer */
        $customer = $this->find($id);

        return $customer;
    }

    public function findOneByUsername(string $username): ?Customer
    {
        /** @var Customer|null $customer */
        $customer = $this->findOneBy(['username' => $username]);

        return $customer;
    }
}

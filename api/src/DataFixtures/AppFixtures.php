<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Customer;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\UuidV7;

class AppFixtures extends Fixture
{
    public const ACME_ID = '019d58eb-2dc4-7b0f-8fec-6bb9804399f2';
    public const ACME_USERNAME = 'acme-corp';
    public const GLOBEX_ID = '019d58eb-2dc4-7b5b-8fec-6bb980dba0fb';
    public const GLOBEX_USERNAME = 'globex-corp';
    public const INITECH_ID = '019d58eb-2dc4-7b97-8fec-6bb98199aa0c';
    public const INITECH_USERNAME = 'initech';
    public const UMBRELLA_ID = '019d58eb-2dc4-7ba7-8fec-6bb9824369d1';
    public const UMBRELLA_USERNAME = 'umbrella-corp';
    public const WAYNE_ID = '019d58eb-2dc4-7bb7-8fec-6bb9827a0007';
    public const WAYNE_USERNAME = 'wayne-enterprises';
    public const STARK_ID = '019d58eb-2dc4-7bc3-8fec-6bb982c80e76';
    public const STARK_USERNAME = 'stark-industries';
    public const DEFAULT_PASSWORD = 'customer-password';

    private const CUSTOMERS = [
        ['id' => self::ACME_ID, 'username' => self::ACME_USERNAME],
        ['id' => self::GLOBEX_ID, 'username' => self::GLOBEX_USERNAME],
        ['id' => self::INITECH_ID, 'username' => self::INITECH_USERNAME],
        ['id' => self::UMBRELLA_ID, 'username' => self::UMBRELLA_USERNAME],
        ['id' => self::WAYNE_ID, 'username' => self::WAYNE_USERNAME],
        ['id' => self::STARK_ID, 'username' => self::STARK_USERNAME],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        foreach (self::CUSTOMERS as $fixtureCustomer) {
            $customer = new Customer(
                UuidV7::fromString($fixtureCustomer['id']),
                $fixtureCustomer['username'],
            );

            $customer->setPassword(
                $this->userPasswordHasher->hashPassword($customer, self::DEFAULT_PASSWORD),
            );

            $manager->persist($customer);
        }

        $manager->flush();
    }
}

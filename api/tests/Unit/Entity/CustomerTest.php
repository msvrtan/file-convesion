<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Customer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

final class CustomerTest extends TestCase
{
    public function testItRequiresUuidV7Ids(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer IDs must be UUIDv7 values.');

        new Customer(Uuid::v4(), 'acme-corp');
    }

    public function testItRequiresNonEmptyUsernames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer username cannot be empty.');

        new Customer(new UuidV7(), '');
    }

    public function testItBuildsCustomerFromValidInput(): void
    {
        $id = new UuidV7();
        $customer = new Customer($id, 'acme-corp');

        self::assertSame($id, $customer->getId());
        self::assertSame('acme-corp', $customer->getUsername());
        self::assertSame('acme-corp', $customer->getUserIdentifier());
        self::assertSame(['ROLE_CUSTOMER'], $customer->getRoles());
    }
}

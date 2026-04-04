<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CustomerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\Table(name: 'customer')]
class Customer implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    private string $username;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $password = '';

    public function __construct(Uuid $id, string $username)
    {
        if (!$id instanceof UuidV7) {
            throw new \InvalidArgumentException('Customer IDs must be UUIDv7 values.');
        }

        if ('' === $username) {
            throw new \InvalidArgumentException('Customer username cannot be empty.');
        }

        $this->id = $id;
        $this->username = $username;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getUsername(): string
    {
        if ('' === $this->username) {
            throw new \LogicException('Customer username cannot be empty.');
        }

        return $this->username;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        return $this->getUsername();
    }

    public function getRoles(): array
    {
        return ['ROLE_CUSTOMER'];
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}

<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConversionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ConversionRepository::class)]
#[ORM\Table(name: 'conversion')]
class Conversion
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\Column(name: 'owner_id', type: UuidType::NAME)]
    private Uuid $ownerId;

    #[ORM\Column(name: 'source_format', type: Types::STRING, length: 32)]
    private string $sourceFormat;

    #[ORM\Column(name: 'target_format', type: Types::STRING, length: 32)]
    private string $targetFormat;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'processing_started_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $processingStartedAt = null;

    #[ORM\Column(name: 'processing_ended_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $processingEndedAt = null;

    public function __construct(Uuid $id, Uuid $ownerId, string $sourceFormat, string $targetFormat)
    {
        if ('' === $sourceFormat) {
            throw new \InvalidArgumentException('Source format cannot be empty.');
        }

        if ('' === $targetFormat) {
            throw new \InvalidArgumentException('Target format cannot be empty.');
        }

        $this->id = $id;
        $this->ownerId = $ownerId;
        $this->sourceFormat = $sourceFormat;
        $this->targetFormat = $targetFormat;
        $this->createdAt = new \DateTime();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwnerId(): Uuid
    {
        return $this->ownerId;
    }

    public function setOwnerId(Uuid $ownerId): self
    {
        $this->ownerId = $ownerId;

        return $this;
    }

    public function getSourceFormat(): string
    {
        return $this->sourceFormat;
    }

    public function setSourceFormat(string $sourceFormat): self
    {
        if ('' === $sourceFormat) {
            throw new \InvalidArgumentException('Source format cannot be empty.');
        }

        $this->sourceFormat = $sourceFormat;

        return $this;
    }

    public function getTargetFormat(): string
    {
        return $this->targetFormat;
    }

    public function setTargetFormat(string $targetFormat): self
    {
        if ('' === $targetFormat) {
            throw new \InvalidArgumentException('Target format cannot be empty.');
        }

        $this->targetFormat = $targetFormat;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getProcessingStartedAt(): ?\DateTime
    {
        return $this->processingStartedAt;
    }

    public function setProcessingStartedAt(?\DateTime $processingStartedAt): self
    {
        $this->processingStartedAt = $processingStartedAt;

        return $this;
    }

    public function getProcessingEndedAt(): ?\DateTime
    {
        return $this->processingEndedAt;
    }

    public function setProcessingEndedAt(?\DateTime $processingEndedAt): self
    {
        $this->processingEndedAt = $processingEndedAt;

        return $this;
    }
}

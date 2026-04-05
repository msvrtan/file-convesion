<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\Uid\Uuid;

final class ConvertFile
{
    public function __construct(
        private Uuid $id,
        private Uuid $ownerId,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getOwnerId(): Uuid
    {
        return $this->ownerId;
    }
}

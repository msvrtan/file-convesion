<?php

declare(strict_types=1);

namespace App\Service\FileConverter;

interface FileConverter
{
    public function convert(string $content, string $sourceFormat, string $targetFormat): string;
}

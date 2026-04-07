<?php

declare(strict_types=1);

namespace App\Service\FileConverter;

class TestConverter implements FileConverter
{
    public function convert(string $content, string $sourceFormat, string $targetFormat): string
    {
        $path = dirname(__DIR__, 3).'/tests/Fixtures/sample.'.$targetFormat;
        $convertedContent = file_get_contents($path);

        if (false === $convertedContent) {
            throw new \RuntimeException(sprintf('Unable to load test fixture for target format [%s].', $targetFormat));
        }

        return $convertedContent;
    }
}

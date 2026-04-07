<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

trait UsesFixtureFiles
{
    protected static function fixturePath(string $filename): string
    {
        return __DIR__.'/Fixtures/'.$filename;
    }

    protected static function createFixtureUpload(
        string $fixtureName = 'sample.json',
        ?string $clientName = null,
        string $mimeType = 'application/json',
    ): UploadedFile {
        return new UploadedFile(
            self::fixturePath($fixtureName),
            $clientName ?? $fixtureName,
            $mimeType,
            test: true,
        );
    }
}

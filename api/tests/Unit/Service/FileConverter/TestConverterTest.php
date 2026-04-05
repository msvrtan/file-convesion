<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\FileConverter;

use App\Service\FileConverter\TestConverter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestConverterTest extends TestCase
{
    private TestConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new TestConverter();
    }

    #[DataProvider('supportedTargetFormats')]
    public function testItLoadsSampleFixtureForTargetFormat(string $targetFormat): void
    {
        $convertedContent = $this->converter->convert('ignored', 'json', $targetFormat);

        self::assertSame(
            file_get_contents(self::fixturePath('sample.'.$targetFormat)),
            $convertedContent,
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function supportedTargetFormats(): array
    {
        return [
            ['csv'],
            ['json'],
            ['xml'],
            ['xlsx'],
            ['ods'],
        ];
    }

    private static function fixturePath(string $filename): string
    {
        return dirname(__DIR__, 3).'/Fixtures/'.$filename;
    }
}

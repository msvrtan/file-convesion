<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\FileConverter;

use App\Service\FileConverter\TestConverter;
use App\Tests\UsesFixtureFiles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TestConverterTest extends TestCase
{
    use UsesFixtureFiles;

    private TestConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new TestConverter();
    }

    #[DataProvider('supportedFormats')]
    public function testItLoadsSampleFiles(string $targetFormat): void
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
    public static function supportedFormats(): array
    {
        return [
            ['csv'],
            ['json'],
            ['xml'],
            ['xlsx'],
            ['ods'],
        ];
    }

}

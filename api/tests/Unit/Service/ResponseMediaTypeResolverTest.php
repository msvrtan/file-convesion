<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ResponseMediaTypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ResponseMediaTypeResolverTest extends TestCase
{
    private ResponseMediaTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ResponseMediaTypeResolver();
    }

    #[DataProvider('requestProvider')]
    public function testItResolvesPreferredResponseMediaType(
        ?string $acceptHeader,
        string $expectedMediaType,
    ): void {
        $request = new Request();

        if (null !== $acceptHeader) {
            $request->headers->set('Accept', $acceptHeader);
        }

        self::assertSame($expectedMediaType, $this->resolver->resolve($request));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function requestProvider(): iterable
    {
        yield 'missing accept header defaults to json' => [
            null,
            'application/json',
        ];

        yield 'wildcard defaults to json' => [
            '*/*',
            'application/json',
        ];

        yield 'xml is preferred when requested' => [
            'application/xml',
            'application/xml',
        ];

        yield 'xml is preferred over wildcard' => [
            'application/xml, */*',
            'application/xml',
        ];

        yield 'json is selected from multiple values' => [
            'application/json, */*',
            'application/json',
        ];

        yield 'unsupported types default to json' => [
            'text/plain',
            'application/json',
        ];
    }
}

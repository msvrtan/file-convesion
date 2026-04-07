<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class OpenApiDocumentationTest extends WebTestCase
{
    public function testGeneratedSpecDocumentsAuthenticationAndConversionEndpoints(): void
    {
        $client = self::createClient();
        $client->request('GET', '/doc.json');

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        self::assertResponseHeaderSame('content-type', 'application/json');

        $content = $client->getResponse()->getContent();
        self::assertIsString($content);

        /** @var array{
         *     openapi?: mixed,
         *     info?: array{title?: mixed},
         *     components?: array{securitySchemes?: array<string, mixed>},
         *     paths?: array<string, mixed>
         * } $spec
         */
        $spec = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('3.0.3', $spec['openapi'] ?? null);
        self::assertSame('AI conversion API', $spec['info']['title'] ?? null);
        self::assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes'] ?? []);

        /** @var array<string, array<string, mixed>> $paths */
        $paths = $spec['paths'] ?? [];

        self::assertArrayHasKey('/auth/token', $paths);
        self::assertArrayHasKey('/conversions', $paths);
        self::assertArrayHasKey('/conversions/{id}', $paths);
        self::assertArrayHasKey('/conversions/{id}/download', $paths);

        $authTokenPath = $this->requireArrayKey($paths, '/auth/token');
        $authTokenPost = $this->requireArrayKey($authTokenPath, 'post');
        $authTokenRequestBody = $this->requireArrayKey($authTokenPost, 'requestBody');
        $authTokenContent = $this->requireArrayKey($authTokenRequestBody, 'content');
        self::assertArrayHasKey('application/json', $authTokenContent);

        $conversionCreatePath = $this->requireArrayKey($paths, '/conversions');
        $conversionCreatePost = $this->requireArrayKey($conversionCreatePath, 'post');
        $conversionCreateRequestBody = $this->requireArrayKey($conversionCreatePost, 'requestBody');
        $conversionCreateContent = $this->requireArrayKey($conversionCreateRequestBody, 'content');
        self::assertArrayHasKey('multipart/form-data', $conversionCreateContent);

        $conversionStatusPath = $this->requireArrayKey($paths, '/conversions/{id}');
        $conversionStatusGet = $this->requireArrayKey($conversionStatusPath, 'get');
        $conversionStatusResponses = $this->requireArrayKey($conversionStatusGet, 'responses');
        $conversionStatusSuccess = $this->requireArrayKey($conversionStatusResponses, '200');
        $conversionStatusContent = $this->requireArrayKey($conversionStatusSuccess, 'content');
        self::assertArrayHasKey('application/json', $conversionStatusContent);
        self::assertArrayHasKey('application/xml', $conversionStatusContent);

        $conversionDownloadPath = $this->requireArrayKey($paths, '/conversions/{id}/download');
        $conversionDownloadGet = $this->requireArrayKey($conversionDownloadPath, 'get');
        $conversionDownloadResponses = $this->requireArrayKey($conversionDownloadGet, 'responses');
        $conversionDownloadSuccess = $this->requireArrayKey($conversionDownloadResponses, '200');
        $conversionDownloadContent = $this->requireArrayKey($conversionDownloadSuccess, 'content');
        self::assertArrayHasKey('application/json', $conversionDownloadContent);
        self::assertArrayHasKey('application/xml', $conversionDownloadContent);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function requireArrayKey(array $data, string $key): array
    {
        self::assertArrayHasKey($key, $data);
        self::assertIsArray($data[$key]);

        /** @var array<string, mixed> $value */
        $value = $data[$key];

        return $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class ResponseMediaTypeResolver
{
    public function resolve(Request $request): string
    {
        foreach ($request->getAcceptableContentTypes() as $acceptableContentType) {
            if ('application/xml' === $acceptableContentType) {
                return 'application/xml';
            }

            if ('application/json' === $acceptableContentType || '*/*' === $acceptableContentType) {
                return 'application/json';
            }
        }

        return 'application/json';
    }
}

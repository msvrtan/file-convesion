<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Conversion;
use App\Model\ConversionRequest;

final class PathResolver
{
    public function uploadPath(string|\Stringable $ownerId, string|\Stringable $conversionId, string $sourceFormat): string
    {
        return sprintf('uploads/%s/%s.%s', $ownerId, $conversionId, $sourceFormat);
    }

    public function convertedPath(string|\Stringable $ownerId, string|\Stringable $conversionId, string $targetFormat): string
    {
        return sprintf('converted/%s/%s.%s', $ownerId, $conversionId, $targetFormat);
    }

    public function uploadPathForRequest(ConversionRequest $request): string
    {
        return $this->uploadPath($request->ownerId, $request->id, $request->sourceFormat);
    }

    public function uploadPathForConversion(Conversion $conversion): string
    {
        return $this->uploadPath(
            $conversion->getOwnerId(),
            $conversion->getId(),
            $conversion->getSourceFormat(),
        );
    }

    public function convertedPathForConversion(Conversion $conversion): string
    {
        return $this->convertedPath(
            $conversion->getOwnerId(),
            $conversion->getId(),
            $conversion->getTargetFormat(),
        );
    }
}

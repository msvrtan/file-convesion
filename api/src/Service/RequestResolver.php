<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\BadRequest;
use App\Model\ConversionRequest;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RequestResolver
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @throws BadRequest
     */
    public function convertRequest(Request $httpRequest, Uuid $id, Uuid $ownerId): ConversionRequest
    {
        /** @var UploadedFile|array<mixed,mixed>|null $file */
        $file = $httpRequest->files->get('file');

        if (is_array($file)) {
            throw new BadRequest('Only a single file upload is supported.');
        }

        $targetFormat = (string) $httpRequest->request->get('targetFormat');

        $request = new ConversionRequest($id, $ownerId, $file, $targetFormat);

        $errors = $this->validator->validate($request);

        if (count($errors) > 0) {
            $errorMessage = '';
            foreach ($errors as $error) {
                $errorMessage .= (string) $error->getMessage();
            }

            throw new BadRequest($errorMessage);
        }

        return $request;
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

class ConversionRequest
{
    private const INPUT_FORMATS = ['csv', 'json', 'xlsx', 'ods'];
    private const OUTPUT_FORMATS = ['json', 'xml'];

    #[Assert\NotNull(message: 'A file is required.')]
    #[Assert\File(
        extensions: self::INPUT_FORMATS,
        extensionsMessage: 'Supported file extensions are csv, json, xlsx, ods.',
    )]
    public ?UploadedFile $file;

    #[Assert\Choice(
        choices: self::INPUT_FORMATS,
        message: 'Supported source formats are csv, json, xlsx, ods.',
    )]
    public string $sourceFormat;

    #[Assert\Choice(
        choices: self::OUTPUT_FORMATS,
        message: 'Supported target formats are json, xml.',
    )]
    public string $targetFormat;

    public function __construct(
        ?UploadedFile $file,
        string $targetFormat,
    ) {
        $this->file = $file;
        $this->sourceFormat = $this->file?->getClientOriginalExtension() ?? '';
        $this->targetFormat = $targetFormat;
    }
}

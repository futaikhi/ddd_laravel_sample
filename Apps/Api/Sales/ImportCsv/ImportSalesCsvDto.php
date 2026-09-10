<?php

declare(strict_types=1);

namespace Apps\Api\Sales\ImportCsv;

use Illuminate\Http\UploadedFile;

final readonly class ImportSalesCsvDto
{
    public function __construct(
        public UploadedFile $file,
    ) {
    }
}

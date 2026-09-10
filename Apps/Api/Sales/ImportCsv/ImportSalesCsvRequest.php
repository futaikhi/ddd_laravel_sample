<?php

declare(strict_types=1);

namespace Apps\Api\Sales\ImportCsv;

use Apps\Shared\Http\AbstractFormRequest;
use Illuminate\Http\UploadedFile;

final class ImportSalesCsvRequest extends AbstractFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function getUploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }
}

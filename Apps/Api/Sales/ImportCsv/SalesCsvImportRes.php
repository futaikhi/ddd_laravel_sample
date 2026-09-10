<?php

declare(strict_types=1);

namespace Apps\Api\Sales\ImportCsv;

use Apps\Shared\Http\BaseRes;

final readonly class SalesCsvImportRes extends BaseRes
{
    /**
     * @param list<array{import_ref: string, sale_id: string, invoice_number: string}> $sales
     * @param list<array{row: int, import_ref: ?string, field: ?string, message: string}> $errors
     */
    public function __construct(
        public int $total_rows,
        public int $created_sales,
        public int $failed_rows,
        public array $sales,
        public array $errors,
    ) {
    }
}

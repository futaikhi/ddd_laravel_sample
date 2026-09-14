<?php

declare(strict_types=1);

namespace Apps\Api\Sales\ImportCsv;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use RuntimeException;
use Src\Sales\Application\Commands\Create\CreateSaleCommand;
use Src\Sales\Application\Commands\Create\CreateSaleLineItem;
use Src\Sales\Domain\Ports\InvoiceNumberGeneratorInterface;
use Src\Sales\Domain\ValueObjects\CustomerId;
use Src\Sales\Domain\ValueObjects\SaleId;
use Src\Shared\Framework\Infrastructure\Bus\CommandBus\CommandBusInterface;

/**
 * Parses an uploaded CSV file, groups line-item rows by `import_ref`, and
 * dispatches one create-sale command per group. Uses backend-generated
 * `SaleId` and `invoice_number` for each accepted group.
 *
 * Failure strategy: fail the entire import if any row is invalid. No sales
 * are dispatched when validation errors are collected.
 */
final readonly class ImportSalesCsvAction
{
    /** @var list<string> */
    private const REQUIRED_HEADERS = ['import_ref', 'customer_id', 'product_id', 'quantity'];

    public function __construct(
        private CommandBusInterface $commandBus,
        private InvoiceNumberGeneratorInterface $invoiceNumbers,
    ) {
    }

    public function __invoke(ImportSalesCsvDto $dto): SalesCsvImportRes
    {
        [$rows, $parseErrors, $totalRows] = $this->parseCsv($dto->file);

        // If parsing produced errors, fail the entire import up-front.
        if ($parseErrors !== []) {
            return new SalesCsvImportRes(
                total_rows: $totalRows,
                created_sales: 0,
                failed_rows: $this->countFailedRows($parseErrors),
                sales: [],
                errors: $parseErrors,
            );
        }

        // Group parsed rows by import_ref while validating that each group
        // uses a consistent customer_id.
        $groups = [];
        $groupErrors = [];

        foreach ($rows as $row) {
            $ref = $row->importRef;

            if (! isset($groups[$ref])) {
                $groups[$ref] = [
                    'customer_id' => $row->customerId,
                    'first_row' => $row->rowNumber,
                    'rows' => [],
                    'products' => [],
                ];
            }

            if ($groups[$ref]['customer_id'] !== $row->customerId) {
                $groupErrors[] = [
                    'row' => $row->rowNumber,
                    'import_ref' => $ref,
                    'field' => 'customer_id',
                    'message' => 'All rows sharing the same import_ref must use the same customer_id.',
                ];
                continue;
            }

            if (isset($groups[$ref]['products'][$row->productId])) {
                $groupErrors[] = [
                    'row' => $row->rowNumber,
                    'import_ref' => $ref,
                    'field' => 'product_id',
                    'message' => 'Duplicate product_id within the same import_ref is not allowed.',
                ];
                continue;
            }

            $groups[$ref]['products'][$row->productId] = true;
            $groups[$ref]['rows'][] = $row;
        }

        if ($groupErrors !== []) {
            return new SalesCsvImportRes(
                total_rows: $totalRows,
                created_sales: 0,
                failed_rows: $this->countFailedRows($groupErrors),
                sales: [],
                errors: $groupErrors,
            );
        }

        // All rows are structurally valid: build and dispatch commands.
        $sales = [];
        $dispatchErrors = [];

        foreach ($groups as $ref => $group) {
            try {
                $customerId = CustomerId::fromString($group['customer_id']);
            } catch (InvalidArgumentException $e) {
                $dispatchErrors[] = [
                    'row' => $group['first_row'],
                    'import_ref' => $ref,
                    'field' => 'customer_id',
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            $items = [];
            $itemErrors = [];

            foreach ($group['rows'] as $row) {
                try {
                    $items[] = new CreateSaleLineItem(
                        productId: $row->productId,
                        quantity: $row->quantity,
                    );
                } catch (InvalidArgumentException $e) {
                    $itemErrors[] = [
                        'row' => $row->rowNumber,
                        'import_ref' => $ref,
                        'field' => 'product_id',
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if ($itemErrors !== []) {
                $dispatchErrors = array_merge($dispatchErrors, $itemErrors);
                continue;
            }

            $saleId = SaleId::random();
            $invoiceNumber = $this->invoiceNumbers->next();

            try {
                $this->commandBus->dispatch(new CreateSaleCommand(
                    id: $saleId,
                    customerId: $customerId,
                    items: $items,
                    invoiceNumber: $invoiceNumber,
                ));
            } catch (\Throwable $e) {
                $dispatchErrors[] = [
                    'row' => $group['first_row'],
                    'import_ref' => $ref,
                    'field' => null,
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            $sales[] = [
                'import_ref' => (string) $ref,
                'sale_id' => $saleId->getValue(),
                'invoice_number' => $invoiceNumber->getValue(),
            ];
        }

        return new SalesCsvImportRes(
            total_rows: $totalRows,
            created_sales: count($sales),
            failed_rows: $this->countFailedRows($dispatchErrors),
            sales: $sales,
            errors: $dispatchErrors,
        );
    }

    /**
     * @return array{0: list<SalesCsvRowDto>, 1: list<array{row: int, import_ref: ?string, field: ?string, message: string}>, 2: int}
     */
    private function parseCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Uploaded CSV file could not be read.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Failed to open uploaded CSV file.');
        }

        try {
            $header = fgetcsv($handle);
            if ($header === false || $header === null) {
                return [[], [[
                    'row' => 0,
                    'import_ref' => null,
                    'field' => null,
                    'message' => 'CSV file is empty or missing a header row.',
                ]], 0];
            }

            $header = array_map(
                static fn ($value): string => is_string($value) ? strtolower(trim($value)) : '',
                $header,
            );

            foreach (self::REQUIRED_HEADERS as $required) {
                if (! in_array($required, $header, true)) {
                    return [[], [[
                        'row' => 1,
                        'import_ref' => null,
                        'field' => $required,
                        'message' => sprintf('Missing required CSV column: %s.', $required),
                    ]], 0];
                }
            }

            $columnIndex = [];
            foreach (self::REQUIRED_HEADERS as $required) {
                $columnIndex[$required] = array_search($required, $header, true);
            }

            /** @var list<SalesCsvRowDto> $rows */
            $rows = [];
            /** @var list<array{row: int, import_ref: ?string, field: ?string, message: string}> $errors */
            $errors = [];
            $totalRows = 0;

            $rowNumber = 1; // header is row 1
            while (($record = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Skip fully blank lines.
                if ($record === [null] || $record === false) {
                    continue;
                }
                $nonEmpty = array_filter(
                    $record,
                    static fn ($value): bool => is_string($value) && trim($value) !== '',
                );
                if ($nonEmpty === []) {
                    continue;
                }

                $totalRows++;
                $importRef = $this->readColumn($record, $columnIndex['import_ref']);
                $customerId = $this->readColumn($record, $columnIndex['customer_id']);
                $productId = $this->readColumn($record, $columnIndex['product_id']);
                $quantityRaw = $this->readColumn($record, $columnIndex['quantity']);

                $rowErrors = $this->validateRow(
                    $rowNumber,
                    $importRef,
                    $customerId,
                    $productId,
                    $quantityRaw,
                );

                if ($rowErrors !== []) {
                    $errors = array_merge($errors, $rowErrors);
                    continue;
                }

                $rows[] = new SalesCsvRowDto(
                    rowNumber: $rowNumber,
                    importRef: $importRef,
                    customerId: $customerId,
                    productId: $productId,
                    quantity: (int) $quantityRaw,
                );
            }

            return [$rows, $errors, $totalRows];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<array{row: int, import_ref: ?string, field: ?string, message: string}> $errors
     */
    private function countFailedRows(array $errors): int
    {
        return count(array_unique(array_column($errors, 'row')));
    }

    /**
     * @param array<int, mixed> $record
     */
    private function readColumn(array $record, int|false $index): string
    {
        if ($index === false) {
            return '';
        }

        $value = $record[$index] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @return list<array{row: int, import_ref: ?string, field: ?string, message: string}>
     */
    private function validateRow(
        int $rowNumber,
        string $importRef,
        string $customerId,
        string $productId,
        string $quantityRaw,
    ): array {
        $errors = [];
        $refForReport = $importRef !== '' ? $importRef : null;

        if ($importRef === '') {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => null,
                'field' => 'import_ref',
                'message' => 'import_ref is required.',
            ];
        }

        if ($customerId === '') {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'customer_id',
                'message' => 'customer_id is required.',
            ];
        } elseif (! $this->isUuid($customerId)) {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'customer_id',
                'message' => 'customer_id must be a valid UUID.',
            ];
        }

        if ($productId === '') {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'product_id',
                'message' => 'product_id is required.',
            ];
        } elseif (! $this->isUuid($productId)) {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'product_id',
                'message' => 'product_id must be a valid UUID.',
            ];
        }

        if ($quantityRaw === '') {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'quantity',
                'message' => 'quantity is required.',
            ];
        } elseif (! ctype_digit($quantityRaw) || (int) $quantityRaw <= 0) {
            $errors[] = [
                'row' => $rowNumber,
                'import_ref' => $refForReport,
                'field' => 'quantity',
                'message' => 'quantity must be an integer greater than zero.',
            ];
        }

        return $errors;
    }

    private function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value,
        ) === 1;
    }
}

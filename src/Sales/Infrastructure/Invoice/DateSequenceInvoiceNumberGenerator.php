<?php

declare(strict_types=1);

namespace Src\Sales\Infrastructure\Invoice;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Src\Sales\Domain\Ports\InvoiceNumberGeneratorInterface;
use Src\Sales\Domain\ValueObjects\InvoiceNumber;

/**
 * Generates invoice numbers of the form INV-YYYYMMDD-NNNN using a
 * dedicated sequence table keyed by date. The sequence row is locked
 * for update to guarantee uniqueness across concurrent requests.
 */
final class DateSequenceInvoiceNumberGenerator implements InvoiceNumberGeneratorInterface
{
    private const TABLE = 'sale_invoice_sequences';

    public function next(?DateTimeImmutable $referenceDate = null): InvoiceNumber
    {
        $date = $referenceDate ?? new DateTimeImmutable('now');
        $sequenceDate = $date->format('Y-m-d');
        $datePart = $date->format('Ymd');

        $number = DB::transaction(function () use ($sequenceDate): int {
            $existing = DB::table(self::TABLE)
                ->where('sequence_date', $sequenceDate)
                ->lockForUpdate()
                ->first();

            $now = now();

            if ($existing === null) {
                DB::table(self::TABLE)->insert([
                    'sequence_date' => $sequenceDate,
                    'last_number' => 1,
                    'updated_at' => $now,
                ]);

                return 1;
            }

            $next = (int) $existing->last_number + 1;

            DB::table(self::TABLE)
                ->where('sequence_date', $sequenceDate)
                ->update([
                    'last_number' => $next,
                    'updated_at' => $now,
                ]);

            return $next;
        });

        if ($number <= 0) {
            throw new RuntimeException('Failed to allocate invoice sequence number.');
        }

        $value = sprintf('INV-%s-%04d', $datePart, $number);

        return InvoiceNumber::fromString($value);
    }
}

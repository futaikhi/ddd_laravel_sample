<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\DomainEventJob;

$e = new SaleConfirmedEvent('sale-1', '2026-09-02 10:00:00');
$j = new DomainEventJob($e);
$s = serialize($j);

echo "Serialized length: " . strlen($s) . "\n";

$u = unserialize($s);

echo "OK: " . $u->event::class . "\n";
echo "tries: " . $u->tries . "\n";
echo "backoff: " . print_r($u->backoff, true) . "\n";
echo "maxExceptions: " . $u->maxExceptions . "\n";

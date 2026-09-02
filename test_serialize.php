<?php

require __DIR__ . '/vendor/autoload.php';

use Src\Sales\Domain\Events\SaleConfirmedEvent;
use Src\Shared\Framework\Infrastructure\Bus\EventBus\DomainEventJob;

$e = new SaleConfirmedEvent('sale-1', '2026-09-02 10:00:00');
$j = new DomainEventJob($e);
$s = serialize($j);
$u = unserialize($s);

echo 'OK: ' . $u->event::class . PHP_EOL;

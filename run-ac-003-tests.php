<?php

declare(strict_types=1);

$rootPath = __DIR__;
$phpunitPath = $rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'phpunit.bat' : 'phpunit');

$testFiles = [
    'Tests/Feature/Sales/ReadModel/SalesReadModelListPerformanceTest.php',
    'Tests/Feature/Sales/Projections/SalesReadModelProjectionsTest.php',
    'Tests/Unit/Sales/Handlers/GetSaleByIdHandlerTest.php',
    'Tests/Unit/Sales/Handlers/ListSalesHandlerTest.php',
    'Tests/Unit/Sales/Handlers/GetSalesReportHandlerTest.php',
    'Tests/Unit/Sales/Handlers/GetCommissionSummaryHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CompleteSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CancelSaleHandlerTest.php',
];

if (! file_exists($phpunitPath)) {
    fwrite(STDERR, "PHPUnit executable was not found at: {$phpunitPath}" . PHP_EOL);
    fwrite(STDERR, 'Run composer install first, then retry: php run-ac-003-tests.php' . PHP_EOL);
    exit(1);
}

$missingTests = array_values(array_filter(
    $testFiles,
    static fn (string $testFile): bool => ! file_exists($rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $testFile)),
));

if ($missingTests !== []) {
    fwrite(STDERR, 'Missing AC-003 test file(s):' . PHP_EOL);

    foreach ($missingTests as $missingTest) {
        fwrite(STDERR, "- {$missingTest}" . PHP_EOL);
    }

    exit(1);
}

$commandParts = [escapeshellarg($phpunitPath)];

foreach ($testFiles as $testFile) {
    $commandParts[] = escapeshellarg($testFile);
}

$commandParts[] = '--testdox';
$command = implode(' ', $commandParts);

fwrite(STDOUT, 'Running AC-003 Sales CQRS test suite...' . PHP_EOL);
fwrite(STDOUT, $command . PHP_EOL . PHP_EOL);

passthru($command, $exitCode);

exit($exitCode);

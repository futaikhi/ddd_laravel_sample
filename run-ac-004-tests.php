<?php

declare(strict_types=1);

/**
 * AC-004 Domain Events Audit Trail test runner.
 *
 * This file provides a single entry point for all test cases relevant to
 * Task.txt AC-004:
 * - Domain events are recorded by the Sales aggregate.
 * - Events are published from the repository persistence boundary.
 * - Subscribers remain decoupled from command handlers.
 * - Audit trail rows are persisted in domain_events.
 * - Audit trail rows contain complete required metadata and JSON payloads.
 */

$rootPath = __DIR__;
$phpunitPath = $rootPath
    . DIRECTORY_SEPARATOR . 'vendor'
    . DIRECTORY_SEPARATOR . 'bin'
    . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'phpunit.bat' : 'phpunit');

$testFiles = [
    // Command handlers: events are recorded on aggregate and persisted through repository publishing.
    'Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/ConfirmSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CompleteSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CancelSaleHandlerTest.php',

    // API/domain-events feature coverage: audit trail and subscriber side effects.
    'Tests/Feature/Api/Sales/SalesDomainEventsTest.php',
    'Tests/Feature/Api/Sales/SalesAuditTrailFieldsTest.php',

    // Projection subscribers: read model, metrics, and commission projections react to domain events.
    'Tests/Feature/Sales/Projections/SalesReadModelProjectionsTest.php',
];

$staticChecks = [
    'CreateSaleHandler must not depend directly on EventBusInterface' => [
        'file' => 'Src/Sales/Application/Commands/Create/CreateSaleHandler.php',
        'forbiddenPattern' => '/EventBusInterface|publishEvents\s*\(/',
    ],
    'ConfirmSaleHandler must not depend directly on EventBusInterface' => [
        'file' => 'Src/Sales/Application/Commands/Confirm/ConfirmSaleHandler.php',
        'forbiddenPattern' => '/EventBusInterface|publishEvents\s*\(/',
    ],
    'CompleteSaleHandler must not depend directly on EventBusInterface' => [
        'file' => 'Src/Sales/Application/Commands/Complete/CompleteSaleHandler.php',
        'forbiddenPattern' => '/EventBusInterface|publishEvents\s*\(/',
    ],
    'CancelSaleHandler must not depend directly on EventBusInterface' => [
        'file' => 'Src/Sales/Application/Commands/Cancel/CancelSaleHandler.php',
        'forbiddenPattern' => '/EventBusInterface|publishEvents\s*\(/',
    ],
    'SaleRepository must publish aggregate domain events after persistence' => [
        'file' => 'Src/Sales/Infrastructure/Persistence/SaleRepository.php',
        'requiredPattern' => '/publishEvents\s*\(\s*\$sale->releaseEvents\s*\(\s*\)\s*\)/',
    ],
    'Event store must persist complete AC-004 audit columns' => [
        'file' => 'Src/Shared/Framework/Infrastructure/Events/EloquentDomainEventStore.php',
        'requiredPattern' => '/aggregate_id[\s\S]*event_type[\s\S]*event_data[\s\S]*occurred_at[\s\S]*recorded_at/',
    ],
];

function println(string $message = ''): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $exitCode = 1): void
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function normalize_path(string $rootPath, string $relativePath): string
{
    return $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function assert_file_exists(string $rootPath, string $relativePath, string $label): void
{
    if (! file_exists(normalize_path($rootPath, $relativePath))) {
        fail("Missing {$label}: {$relativePath}");
    }
}

/**
 * @param array{file: string, forbiddenPattern?: string, requiredPattern?: string} $check
 */
function run_static_check(string $rootPath, string $title, array $check): void
{
    $filePath = normalize_path($rootPath, $check['file']);

    if (! file_exists($filePath)) {
        fail("Static check file not found for {$title}: {$check['file']}");
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        fail("Unable to read file during static check for {$title}: {$check['file']}");
    }

    if (isset($check['forbiddenPattern']) && preg_match($check['forbiddenPattern'], $content) === 1) {
        fail("FAIL: {$title}");
    }

    if (isset($check['requiredPattern']) && preg_match($check['requiredPattern'], $content) !== 1) {
        fail("FAIL: {$title}");
    }

    println("OK: {$title}");
}

println('AC-004 Domain Events Audit Trail test suite');
println(str_repeat('=', 56));
println('');

if (! file_exists($phpunitPath)) {
    fail("PHPUnit executable not found at: {$phpunitPath}" . PHP_EOL . 'Run composer install first, then retry: php Run-ac-004-tests.php');
}

foreach ($testFiles as $testFile) {
    assert_file_exists($rootPath, $testFile, 'AC-004 test file');
}

println('Static AC-004 architecture checks:');
foreach ($staticChecks as $title => $check) {
    run_static_check($rootPath, $title, $check);
}

println('');
println('PHPUnit files included:');
foreach ($testFiles as $testFile) {
    println("  - {$testFile}");
}

$commandParts = [escapeshellarg($phpunitPath)];
foreach ($testFiles as $testFile) {
    $commandParts[] = escapeshellarg($testFile);
}
$commandParts[] = '--testdox';

$command = implode(' ', $commandParts);

println('');
println('Running AC-004 PHPUnit suite...');
println($command);
println('');

passthru($command, $exitCode);

if ($exitCode === 0) {
    println('');
    println('AC-004 PASSED: domain events, subscribers, and audit trail tests completed successfully.');
}

exit($exitCode);

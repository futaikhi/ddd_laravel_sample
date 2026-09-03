<?php

declare(strict_types=1);

/**
 * AC-001 Sale Domain Model test runner.
 *
 * This runner groups PHPUnit tests and lightweight static checks relevant to
 * Task.txt AC-001: Sale aggregate, value objects, state transitions, protected
 * business rules, and domain tests that can run without database/HTTP setup.
 */

$rootPath = __DIR__;
$phpunitPath = $rootPath
    . DIRECTORY_SEPARATOR . 'vendor'
    . DIRECTORY_SEPARATOR . 'bin'
    . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'phpunit.bat' : 'phpunit');

$testFiles = [
    // Sale aggregate behavior: creation, minimum amount, line item validation,
    // state transitions, domain events, and query methods.
    'Tests/Unit/Sales/SaleTest.php',

    // Supporting value object behavior used by the Sale aggregate.
    'Tests/Unit/Sales/ValueObjects/CommissionTest.php',
];

$requiredFiles = [
    'Sale aggregate' => 'Src/Sales/Domain/Entities/Sale.php',
    'SaleId value object' => 'Src/Sales/Domain/ValueObjects/SaleId.php',
    'CustomerId value object' => 'Src/Sales/Domain/ValueObjects/CustomerId.php',
    'ProductId value object' => 'Src/Sales/Domain/ValueObjects/ProductId.php',
    'LineItem value object' => 'Src/Sales/Domain/ValueObjects/LineItem.php',
    'Money value object' => 'Src/Sales/Domain/ValueObjects/Money.php',
    'OrderStatus enum' => 'Src/Sales/Domain/Enums/OrderStatus.php',
    'SaleCreatedEvent' => 'Src/Sales/Domain/Events/SaleCreatedEvent.php',
    'SaleConfirmedEvent' => 'Src/Sales/Domain/Events/SaleConfirmedEvent.php',
    'SaleCompletedEvent' => 'Src/Sales/Domain/Events/SaleCompletedEvent.php',
    'SaleCancelledEvent' => 'Src/Sales/Domain/Events/SaleCancelledEvent.php',
];

$staticChecks = [
    'Sale domain model must not import Laravel/Illuminate directly' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'forbiddenPattern' => '/^\s*use\s+Illuminate\\\\/m',
    ],
    'Sale aggregate must expose AC-001 factory method' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'requiredPattern' => '/public\s+static\s+function\s+create\s*\(/m',
    ],
    'Sale aggregate must expose confirm transition' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'requiredPattern' => '/public\s+function\s+confirm\s*\(/m',
    ],
    'Sale aggregate must expose complete transition' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'requiredPattern' => '/public\s+function\s+complete\s*\(/m',
    ],
    'Sale aggregate must expose cancel transition' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'requiredPattern' => '/public\s+function\s+cancel\s*\(/m',
    ],
    'Sale aggregate must keep core properties private' => [
        'file' => 'Src/Sales/Domain/Entities/Sale.php',
        'requiredStrings' => [
            'private readonly SaleId $id',
            'private readonly CustomerId $customerId',
            'private readonly array $lineItems',
            'private Money $totalAmount',
            'private OrderStatus $status',
            'private readonly DateTimeImmutable $createdAt',
        ],
    ],
    'OrderStatus enum must contain all AC-001 statuses' => [
        'file' => 'Src/Sales/Domain/Enums/OrderStatus.php',
        'requiredPattern' => '/case\s+PENDING[\s\S]*case\s+CONFIRMED[\s\S]*case\s+COMPLETED[\s\S]*case\s+CANCELLED/m',
    ],
    'Money must support arithmetic operations' => [
        'file' => 'Src/Sales/Domain/ValueObjects/Money.php',
        'requiredPattern' => '/function\s+add\s*\([\s\S]*function\s+multiply\s*\(/m',
    ],
    'LineItem must validate quantity and price' => [
        'file' => 'Src/Sales/Domain/ValueObjects/LineItem.php',
        'requiredPattern' => '/\$this->quantity\s*<=\s*0[\s\S]*\$this->unitPrice->getValue\(\)\s*<=?\s*0/m',
    ],
];

function println(string $message = ''): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message, int $exitCode = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($exitCode);
}

function normalize_path(string $rootPath, string $relativePath): string
{
    return $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function run_static_check(string $rootPath, string $title, array $check): void
{
    $filePath = normalize_path($rootPath, $check['file']);

    if (! file_exists($filePath)) {
        fail("Static check file not found for {$title}: {$filePath}");
    }

    $content = file_get_contents($filePath);

    if ($content === false) {
        fail("Unable to read file during static check: {$filePath}");
    }

    if (isset($check['forbiddenPattern']) && preg_match($check['forbiddenPattern'], $content) === 1) {
        fail("FAIL: {$title}");
    }

    if (isset($check['requiredPattern']) && preg_match($check['requiredPattern'], $content) !== 1) {
        fail("FAIL: {$title}");
    }

    if (isset($check['requiredStrings'])) {
        foreach ($check['requiredStrings'] as $requiredString) {
            if (! str_contains($content, $requiredString)) {
                fail("FAIL: {$title} missing expected string: {$requiredString}");
            }
        }
    }

    println("OK: {$title}");
}

println('AC-001 Sale Domain Model test summary');
println(str_repeat('=', 56));
println('');

if (! file_exists($phpunitPath)) {
    fail("PHPUnit executable not found at: {$phpunitPath}\nRun composer install first, then retry: php Run-ac-001-tests.php");
}

$missingRequiredFiles = [];
foreach ($requiredFiles as $label => $requiredFile) {
    if (! file_exists(normalize_path($rootPath, $requiredFile))) {
        $missingRequiredFiles[] = "{$label}: {$requiredFile}";
    }
}

if ($missingRequiredFiles !== []) {
    println('Missing AC-001 required file(s):');
    foreach ($missingRequiredFiles as $missingRequiredFile) {
        println("  - {$missingRequiredFile}");
    }

    fail('AC-001 test runner cannot continue because one or more required files are missing.');
}

$missingTests = [];
foreach ($testFiles as $testFile) {
    if (! file_exists(normalize_path($rootPath, $testFile))) {
        $missingTests[] = $testFile;
    }
}

if ($missingTests !== []) {
    println('Missing AC-001 test file(s):');
    foreach ($missingTests as $missingTest) {
        println("  - {$missingTest}");
    }

    fail('AC-001 test runner cannot continue because one or more test files are missing.');
}

println('Required domain files:');
foreach ($requiredFiles as $label => $requiredFile) {
    println("  - {$label}: {$requiredFile}");
}

println('');
println('Static AC-001 checks:');
foreach ($staticChecks as $title => $check) {
    run_static_check($rootPath, $title, $check);
}

println('');
println('PHPUnit files included:');
foreach ($testFiles as $testFile) {
    println("  - {$testFile}");
}

println('');

$commandParts = [escapeshellarg($phpunitPath)];
foreach ($testFiles as $testFile) {
    $commandParts[] = escapeshellarg($testFile);
}
$commandParts[] = '--testdox';

$command = implode(' ', $commandParts);
println('Running AC-001 PHPUnit suite...');
println($command);
println('');

passthru($command, $exitCode);

if ($exitCode === 0) {
    println('');
    println('AC-001 PASSED: Sale aggregate tests and static domain model checks completed successfully.');
}

exit($exitCode);


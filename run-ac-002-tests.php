<?php

declare(strict_types=1);

/**
 * AC-002 Hexagonal Ports & Adapters test runner.
 *
 * This file groups the test coverage relevant to Task.txt AC-002:
 * - Domain stays isolated from Laravel/Eloquent infrastructure.
 * - Domain dependencies are expressed as ports/interfaces.
 * - Infrastructure adapters implement those ports.
 * - Application handlers depend on ports, not concrete adapters.
 * - Mock adapters can be swapped in tests without database/HTTP concerns.
 */

$rootPath = __DIR__;
$phpunitPath = $rootPath
    . DIRECTORY_SEPARATOR . 'vendor'
    . DIRECTORY_SEPARATOR . 'bin'
    . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'phpunit.bat' : 'phpunit');

$testFiles = [
    // Ports/adapters and dependency inversion checks.
    'Tests/Unit/DependencyInjection/SalesAdaptersBindingTest.php',
    'Tests/Unit/Sales/Integration/PortsAndAdaptersIntegrationTest.php',

    // Infrastructure adapter tests for AC-002 service ports.
    'Tests/Unit/Sales/Adapters/MockPaymentGatewayAdapterTest.php',
    'Tests/Unit/Sales/Adapters/MockCommissionServiceTest.php',

    // Application handler tests proving handlers use repository/payment/commission ports.
    'Tests/Unit/Sales/Handlers/CreateSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/ConfirmSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CompleteSaleHandlerTest.php',
    'Tests/Unit/Sales/Handlers/CancelSaleHandlerTest.php',
];

$staticChecks = [
    'Domain layer must not import Laravel/Illuminate' => [
        'directory' => 'Src/Sales/Domain',
        'forbiddenPattern' => '/^\s*use\s+Illuminate\\\\/m',
    ],
    'Sale aggregate must not import Eloquent model' => [
        'directory' => 'Src/Sales/Domain/Entities',
        'forbiddenPattern' => '/SaleModel|Eloquent|Model;/m',
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

function path_join(string ...$parts): string
{
    return implode(DIRECTORY_SEPARATOR, $parts);
}

function normalize_test_path(string $rootPath, string $relativePath): string
{
    return $rootPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
}

function run_static_check(string $rootPath, string $title, array $check): void
{
    $directory = path_join($rootPath, str_replace('/', DIRECTORY_SEPARATOR, $check['directory']));

    if (! is_dir($directory)) {
        fail("Static check directory not found for {$title}: {$directory}");
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];

    foreach ($iterator as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            fail('Unable to read file during static check: ' . $file->getPathname());
        }

        if (preg_match($check['forbiddenPattern'], $content) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    if ($violations !== []) {
        println("FAIL: {$title}");
        foreach ($violations as $violation) {
            println("  - {$violation}");
        }
        fail('AC-002 static check failed.');
    }

    println("OK: {$title}");
}

println('AC-002 Hexagonal Ports & Adapters test summary');
println(str_repeat('=', 56));
println('');

if (! file_exists($phpunitPath)) {
    fail("PHPUnit executable not found at: {$phpunitPath}\nRun composer install first, then retry: php Run-ac-002-tests.php");
}

$missingTests = [];
foreach ($testFiles as $testFile) {
    $fullPath = normalize_test_path($rootPath, $testFile);
    if (! file_exists($fullPath)) {
        $missingTests[] = $testFile;
    }
}

if ($missingTests !== []) {
    println('Missing AC-002 test file(s):');
    foreach ($missingTests as $missingTest) {
        println("  - {$missingTest}");
    }
    fail('AC-002 test runner cannot continue because one or more test files are missing.');
}

println('Static architecture checks:');
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
println('Running AC-002 PHPUnit suite...');
println($command);
println('');

passthru($command, $exitCode);

if ($exitCode === 0) {
    println('');
    println('AC-002 PASSED: ports/adapters tests and static isolation checks completed successfully.');
}

exit($exitCode);

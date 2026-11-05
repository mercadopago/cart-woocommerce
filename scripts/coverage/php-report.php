<?php
// Development-only export of a locally generated PHPUnit coverage artifact, PHP 7.4 compatible.
require $argv[1] . '/vendor/autoload.php';
require_once __DIR__ . '/php-shapes.php';
function coverageTestIds($filename)
{
    $ids = [];
    foreach (simplexml_load_file($filename)->testCaseClass as $class) {
        foreach ($class->testCaseMethod as $method) {
            $id = (string) $class['name'] . '::' . (string) $method['name'];
            if (isset($method['dataSet'])) {
                $id .= ' with data set ' . (string) $method['dataSet'];
            }
            $ids[] = $id;
        }
    }
    return $ids;
}
if ($argv[2] === '--inventory') {
    echo json_encode(coverageTestIds($argv[3]), JSON_THROW_ON_ERROR);
    exit(0);
}
$coverage = require $argv[2];
if (!$coverage instanceof \SebastianBergmann\CodeCoverage\CodeCoverage) {
    throw new RuntimeException('Invalid PHPUnit coverage artifact');
}
$knownGraphs = [];
coverageAssertCompatible($knownGraphs, $coverage, $argv[2]);
foreach (array_slice($argv, 4) as $artifact) {
    $part = require $artifact;
    if (!$part instanceof \SebastianBergmann\CodeCoverage\CodeCoverage) {
        throw new RuntimeException('Invalid PHPUnit partition artifact');
    }
    coverageAssertCompatible($knownGraphs, $part, $artifact);
    $coverage->merge($part);
    unset($part);
}
$contextFile = getenv('COVERAGE_BOOTSTRAP_CONTEXT');
if (!$contextFile) {
    throw new RuntimeException('PHP coverage bootstrap profile receipt is required');
}
$context = json_decode(file_get_contents($contextFile), true, 512, JSON_THROW_ON_ERROR);
$recipe = json_decode(file_get_contents(__DIR__ . '/php-bootstrap.json'), true, 512, JSON_THROW_ON_ERROR);
if ($context['recipe'] !== $recipe || $context['recipeSha256'] !== hash_file('sha256', __DIR__ . '/php-bootstrap.json')) {
    throw new RuntimeException('PHP coverage bootstrap recipe identity changed');
}
$bootstrapSummary = ['schemaVersion' => 1, 'profile' => $context['profile'], 'recipeSha256' => $context['recipeSha256'], 'allowlist' => [], 'contextId' => null, 'receipts' => []];
if ($context['profile'] === 'woo') {
    $declarationFile = $context['directory'] . '/bootstrap-receipts/declarations.json';
    $declaration = json_decode(file_get_contents($declarationFile), true, 512, JSON_THROW_ON_ERROR);
    $raw = $context['directory'] . '/bootstrap-declarations.php';
    if ($declaration['rawSha256'] !== hash_file('sha256', $raw) || $declaration['functionalHits'] !== 0 || $declaration['guardHits'] !== count(array_filter(array_merge($declaration['dependencies'], $declaration['closure']), function ($item) { return $item['measured'] && $item['role'] === 'guarded'; })) || $declaration['uncoveredExits'] !== $declaration['guardHits'] || $declaration['captureWhitelist'] !== $context['captureWhitelist']) {
        throw new RuntimeException('PHP native declaration receipt does not match its raw artifact');
    }
    foreach (array_merge($declaration['dependencies'], $declaration['closure'], $declaration['interfaces'], array_filter($declaration['parents'], function ($item) { return empty($item['internal']); })) as $item) {
        if ($item['sha256'] !== hash_file('sha256', $context['root'] . '/' . $item['path'])) {
            throw new RuntimeException('PHP bootstrap source changed during collection: ' . $item['path']);
        }
    }
    $bootstrapSummary['allowlist'] = $context['captureWhitelist'];
    $bootstrapSummary['declarationAllowlist'] = array_column(array_merge($recipe['dependencies'], $recipe['closure']), 'path');
    $bootstrapSummary['outsideMetrics'] = array_column(array_filter(array_merge($declaration['dependencies'], $declaration['interfaces']), function ($item) { return !$item['measured']; }), 'path');
    $bootstrapSummary['contextId'] = $recipe['contextId'];
    $bootstrapSummary['declaration'] = $declaration;
    foreach (glob($context['directory'] . '/bootstrap-receipts/*.json') as $receiptFile) {
        $receipt = json_decode(file_get_contents($receiptFile), true, 512, JSON_THROW_ON_ERROR);
        if ($receipt['profile'] !== 'woo' || $receipt['recipeSha256'] !== $context['recipeSha256'] || $receipt['coverageBefore'] || $receipt['coverageAfter'] || $receipt['dependencies'] !== $declaration['dependencies'] || $receipt['closure'] !== $declaration['closure'] || $receipt['interfaces'] !== $declaration['interfaces'] || $receipt['parents'] !== $declaration['parents'] || $receipt['captureWhitelist'] !== $context['captureWhitelist'] || $receipt['reservedAbsent'] !== $recipe['reservedClasses']) {
            throw new RuntimeException('PHP bootstrap worker receipt differs from the snapshot declaration');
        }
        $bootstrapSummary['receipts'][] = ['capture' => $receipt['capture'], 'sha256' => hash_file('sha256', $receiptFile)];
    }
    if (count($bootstrapSummary['receipts']) < 2) {
        throw new RuntimeException('PHP bootstrap worker receipts are missing');
    }
    $tests = $coverage->getTests();
    if (!isset($tests[$recipe['contextId']]) || $tests[$recipe['contextId']]['fromTestcase'] !== false) {
        throw new RuntimeException('PHP bootstrap context was lost or misrepresented as a PHPUnit testcase');
    }
} elseif ($context['profile'] !== 'generic') {
    throw new RuntimeException('Unknown PHP coverage bootstrap profile');
}
$inventory = getenv('COVERAGE_TEST_INVENTORY');
if ($inventory) {
    $expected = coverageTestIds($inventory);
    $actual = [];
    foreach (json_decode(getenv('COVERAGE_JUNIT_FILES'), true, 512, JSON_THROW_ON_ERROR) as $junit) {
        foreach (simplexml_load_file($junit)->xpath('//testcase') as $test) {
            $actual[] = (string) $test['class'] . '::' . (string) $test['name'];
        }
    }
    sort($expected);
    sort($actual);
    if ($expected !== $actual) {
        throw new RuntimeException('Partition test inventory differs from the complete PHPUnit suite');
    }
    file_put_contents(dirname($argv[3]) . '/executed-tests.json', json_encode($actual, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
}
(new \SebastianBergmann\CodeCoverage\Report\Clover())->process($coverage, dirname($argv[3]) . '/clover.xml');
$files = [];
foreach ($coverage->getReport() as $node) {
    if (!$node instanceof \SebastianBergmann\CodeCoverage\Node\File) {
        continue;
    }
    $lines = [];
    foreach ($node->lineCoverageData() as $line => $tests) {
        if (is_array($tests)) {
            $lines[$line] = count($tests);
        }
    }
    $files[] = [
        'path' => $node->pathAsString(),
        'lines' => (object) $lines,
        'counts' => [
            'lines' => [$node->numberOfExecutedLines(), $node->numberOfExecutableLines()],
            // PHPUnit's statement statistic is executable-line based, unlike Istanbul statements.
            'statements' => [$node->numberOfExecutedLines(), $node->numberOfExecutableLines()],
            'functions' => [$node->numberOfTestedFunctionsAndMethods(), $node->numberOfFunctionsAndMethods()],
            'branches' => [$node->numberOfExecutedBranches(), $node->numberOfExecutableBranches()],
        ],
    ];
}
file_put_contents($argv[3], json_encode($files, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
file_put_contents(dirname($argv[3]) . '/php-bootstrap-summary.json', json_encode($bootstrapSummary, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

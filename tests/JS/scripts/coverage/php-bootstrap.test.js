const fs = require('fs');
const path = require('path');
const os = require('os');
const { spawnSync } = require('child_process');
const { phpProfile } = require('../../../../scripts/coverage/php-profile');
const recipe = require('../../../../scripts/coverage/php-bootstrap.json');
const ROOT = path.resolve(__dirname, '../../../..');
beforeAll(() => fs.mkdirSync(path.join(ROOT, 'coverage'), { recursive: true }));

async function nativeCompilationProof(root, output, runtime) {
  const bootstrapHelper = path.join(ROOT, 'scripts/coverage/php-bootstrap.php');
  const graphHelper = path.join(ROOT, 'scripts/coverage/php-shapes.php');
  const context = path.join(output, 'php/bootstrap-context.json');
  const program = path.join(output, 'native-compilation-proof.php');
  fs.writeFileSync(program, String.raw`<?php
require $argv[1] . '/vendor/autoload.php';
require $argv[2];
require $argv[3];
$context = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
$mode = $argv[5]; $order = $argv[6]; $raw = $argv[7];
$roleNegatives = [];
if ($mode === 'reserved-before-loader') {
    Mockery::mock('alias:' . $context['recipe']['reservedClasses'][0]);
    try { coverageBootstrapLoad($context, false); }
    catch (RuntimeException $failure) {
        if (strpos($failure->getMessage(), 'reserved Mockery') === false) throw $failure;
        echo json_encode(['reservedRejection' => $failure->getMessage()], JSON_THROW_ON_ERROR);
        exit(0);
    }
    throw new RuntimeException('Loader accepted a reserved alias already loaded');
}
foreach ([['src/Gateways/AbstractGateway.php', 'guarded'], ['src/Helpers/Numbers.php', 'class-only']] as $negative) {
    $item = ['class' => $negative[0] === 'src/Helpers/Numbers.php' ? 'MercadoPago\\Woocommerce\\Helpers\\Numbers' : 'MercadoPago\\Woocommerce\\Gateways\\AbstractGateway', 'path' => $negative[0], 'role' => $negative[1]];
    try { coverageBootstrapDeclaration($context, $item); }
    catch (RuntimeException $failure) { $roleNegatives[] = $failure->getMessage(); continue; }
    throw new RuntimeException('Invalid source declaration role was accepted');
}
if ($mode === 'historical-five') {
    // Reproduce the valid worker state of the historical loader, not a schema rejection.
    require $argv[1] . '/tests/bootstrap.php';
    foreach (array_slice($context['recipe']['dependencies'], 0, 5) as $item) {
        if (!class_exists($item['class'], true)) throw new RuntimeException('Historical dependency absent');
    }
} else {
    coverageBootstrapLoad($context, false);
}
$status = 'MercadoPago\\Woocommerce\\Order\\OrderStatus';
$metadata = 'MercadoPago\\Woocommerce\\Order\\OrderMetadata';
$core = 'MercadoPago\\Woocommerce\\Notification\\CoreNotification';
$payment = 'MercadoPago\\Woocommerce\\Helpers\\PaymentMetadata';
if (class_exists($status, false)) throw new RuntimeException('Consumer unexpectedly loaded');
$metadataKnown = class_exists($metadata, false);
if (($mode === 'current') !== $metadataKnown) throw new RuntimeException('Wrong initial compilation context');
if ($mode === 'current' && (!class_exists($core, false) || class_exists($payment, false))) throw new RuntimeException('Reserved constant consumer compiled in wrong context');
$filter = new SebastianBergmann\CodeCoverage\Filter;
$file = $argv[1] . '/src/Order/OrderStatus.php';
$filter->includeFile($file);
$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage((new SebastianBergmann\CodeCoverage\Driver\Selector)->forLineAndPathCoverage($filter), $filter);
$coverage->start('compile-context:' . $mode . ':' . $order);
foreach ($order === 'consumer-first' ? [$status, $metadata] : [$metadata, $status] as $class) {
    if (!class_exists($class, true)) throw new RuntimeException('Real declaration absent');
}
$coverage->stop();
(new SebastianBergmann\CodeCoverage\Report\PHP)->process($coverage, $raw);
$aliases = [];
if ($mode === 'current') foreach ($context['recipe']['reservedClasses'] as $class) {
    if (class_exists($class, false)) throw new RuntimeException('Reserved alias was loaded');
    Mockery::mock('alias:' . $class);
    $aliases[] = $class;
}
Mockery::close();
$wanted = ['getUnprocessedRefundAmount', 'refundedFlow', 'calculateTotalRefunded'];
$shapes = [];
foreach (coverageGraphShapes($coverage) as $key => $shape) foreach ($wanted as $method) {
    if (substr($key, -strlen('->' . $method)) === '->' . $method) $shapes[$method] = $shape;
}
if (count($shapes) !== 3) throw new RuntimeException('Three real consumer graphs missing');
echo json_encode(['shapes' => $shapes, 'rawSha256' => hash_file('sha256', $raw), 'metadataKnown' => $metadataKnown, 'reservedAliasesCreated' => $aliases, 'coverageAfter' => xdebug_code_coverage_started(), 'roleNegatives' => $roleNegatives], JSON_THROW_ON_ERROR);
`);
  await runtime.stage([bootstrapHelper, graphHelper, context, program]);
  const reservedNegative = JSON.parse(await runtime.execute([program, root, bootstrapHelper, graphHelper, context, 'reserved-before-loader', 'none', 'none'], {}));
  expect(reservedNegative.reservedRejection).toContain('reserved Mockery');
  const observations = {};
  for (const mode of ['historical-five', 'current']) {
    observations[mode] = {};
    for (const order of ['consumer-first', 'dependency-first']) {
      const raw = path.join(output, `${mode}-${order}.php`);
      observations[mode][order] = JSON.parse(await runtime.execute([program, root, bootstrapHelper, graphHelper, context, mode, order, raw], {}));
      await runtime.retrieve(raw);
      expect(observations[mode][order].coverageAfter).toBe(false);
      expect(observations[mode][order].roleNegatives).toHaveLength(2);
      expect(observations[mode][order].roleNegatives[0]).toContain('strict top-level ABSPATH/exit');
      expect(observations[mode][order].roleNegatives[1]).toContain('declaration-only top level');
    }
  }
  const historical = observations['historical-five'];
  const current = observations.current;
  for (const method of ['getUnprocessedRefundAmount', 'refundedFlow', 'calculateTotalRefunded']) {
    expect(historical['consumer-first'].shapes[method]).not.toEqual(historical['dependency-first'].shapes[method]);
    expect(current['consumer-first'].shapes[method]).toEqual(current['dependency-first'].shapes[method]);
    expect(current['consumer-first'].shapes[method]).toEqual(historical['dependency-first'].shapes[method]);
  }
  for (const observation of Object.values(current)) {
    expect(observation.metadataKnown).toBe(true);
    expect(observation.reservedAliasesCreated).toEqual(recipe.reservedClasses);
  }
  const evidence = process.env.COVERAGE_PROOF_OUTPUT_DIR;
  if (evidence) {
    fs.mkdirSync(evidence, { recursive: true });
    for (const mode of Object.keys(observations)) for (const order of Object.keys(observations[mode])) {
      fs.copyFileSync(path.join(output, `${mode}-${order}.php`), path.join(evidence, `${mode}-${order}.php`));
    }
    fs.writeFileSync(path.join(evidence, 'native-compilation-observations.json'), JSON.stringify({ observations, reservedNegative }, null, 2));
  }
  console.info('Real compilation context proof:', JSON.stringify({ historicalMismatch: true, currentCompatible: true, methods: Object.keys(current['consumer-first'].shapes), reservedAliases: recipe.reservedClasses, rawHashes: Object.values(observations).flatMap(group => Object.values(group).map(item => item.rawSha256)) }));
}

async function nativePathProof(root, output) {
  const { DockerPhp } = require('../../../../scripts/coverage/docker-php');
  const { run } = require('../../../../scripts/coverage/process');
  const execute = async (command, args, cwd, env = {}) => (await run(command, args, { cwd, env, timeout: 30000 })).stdout.trim();
  const runtime = new DockerPhp(process.env.COVERAGE_PHP_DOCKER_IMAGE, root, path.join(output, 'graph-runtime'), execute);
  const helper = path.join(ROOT, 'scripts/coverage/php-shapes.php');
  const native = path.join(output, 'php/bootstrap-declarations.php');
  const tests = path.join(output, 'php/partition-0.php');
  const declarationReceipt = path.join(output, 'php/bootstrap-receipts/declarations.json');
  const program = path.join(output, 'native-graph-proof.php');
  const globalSource = path.join(output, 'named-global.php');
  fs.writeFileSync(globalSource, '<?php namespace CoverageDiagnostic; function main($flag) { if ($flag) { return "accepted"; } return "rejected"; }');
  const originalBytes = [native, tests].map(file => fs.readFileSync(file));
  fs.writeFileSync(program, String.raw`<?php
require $argv[1] . '/vendor/autoload.php';
require $argv[2];
function copyCoverage($coverage) { return unserialize(serialize($coverage)); }
function fileNode($coverage, $file) {
    $analyser = new SebastianBergmann\CodeCoverage\StaticAnalysis\ParsingFileAnalyser(true, false);
    $data = $coverage->getData(true);
    return new SebastianBergmann\CodeCoverage\Node\File(basename($file), new SebastianBergmann\CodeCoverage\Node\Directory('fixture'), $data->lineCoverage()[$file], $data->functionCoverage()[$file], $coverage->getTests(), $analyser->classesIn($file), $analyser->traitsIn($file), $analyser->functionsIn($file), $analyser->linesOfCodeFor($file));
}
function counts($node) {
    return ['lines' => [$node->numberOfExecutedLines(), $node->numberOfExecutableLines()], 'branches' => [$node->numberOfExecutedBranches(), $node->numberOfExecutableBranches()], 'paths' => [$node->numberOfExecutedPaths(), $node->numberOfExecutablePaths()], 'functions' => [$node->numberOfTestedFunctionsAndMethods(), $node->numberOfFunctionsAndMethods()]];
}
function allFileCounts($coverage) {
    $result = [];
    foreach ($coverage->getData(true)->lineCoverage() as $file => $lines) $result[$file] = counts(fileNode($coverage, $file));
    return $result;
}
function sortedLineHits($coverage) {
    $lines = $coverage->getData(true)->lineCoverage();
    foreach ($lines as &$file) foreach ($file as &$hits) if (is_array($hits)) sort($hits);
    unset($file, $hits);
    return $lines;
}
function rejectFunctionalMutation($reference, $file, $name, $kind) {
    $copy = copyCoverage($reference);
    $functions = $copy->getData(true)->functionCoverage();
    $function = &$functions[$file][$name];
    if ($kind === 'node') {
        $key = array_key_first($function['branches']);
        ++$function['branches'][$key]['op_end'];
    } else {
        $keys = array_keys($function['paths']);
        if (count($keys) < 2) throw new RuntimeException('No actual multi-path target');
        $temporary = $function['paths'][$keys[0]];
        $function['paths'][$keys[0]] = $function['paths'][$keys[1]];
        $function['paths'][$keys[1]] = $temporary;
    }
    unset($function);
    $copy->getData(true)->setFunctionCoverage($functions);
    $known = [];
    coverageAssertCompatible($known, $reference, 'native-original');
    try {
        coverageAssertCompatible($known, $copy, $name . '-' . $kind);
    } catch (RuntimeException $failure) {
        return $failure->getMessage();
    }
    throw new LogicException('Functional graph mutation was accepted: ' . $name . '-' . $kind);
}
$original = require $argv[3];
$tested = require $argv[4];
$known = [];
coverageAssertCompatible($known, $original, 'real-native-declarations');
coverageAssertCompatible($known, $tested, 'real-native-tests');
$original->merge($tested);
$realHits = false;
$numbers = $argv[1] . '/src/Helpers/Numbers.php';
foreach ($original->getData(true)->functionCoverage()[$numbers] as $name => $function) {
    if (strpos($name, 'Numbers->format') !== false) foreach ($function['branches'] as $branch) {
        if (count($branch['hit']) === 2) $realHits = true;
    }
}
if (!$realHits) throw new RuntimeException('Native merge lost distinct real testcase hits');
$mainVariant = copyCoverage($original);
$functions = $mainVariant->getData(true)->functionCoverage();
if (!isset($functions[$numbers]['{main}'])) throw new RuntimeException('Actual native file context missing');
$key = array_key_first($functions[$numbers]['{main}']['branches']);
++$functions[$numbers]['{main}']['branches'][$key]['op_end'];
$mainVariant->getData(true)->setFunctionCoverage($functions);
$known = [];
coverageAssertCompatible($known, $original, 'original-main');
coverageAssertCompatible($known, $mainVariant, 'different-file-context');
foreach (array_keys($known) as $key) if (substr($key, -7) === '|{main}') throw new RuntimeException('Synthetic context included in functional projection');
$forward = copyCoverage($original); $forward->merge($mainVariant);
$reverse = copyCoverage($mainVariant); $reverse->merge($original);
$metrics = counts(fileNode($original, $numbers));
if (allFileCounts($forward) !== allFileCounts($original) || allFileCounts($reverse) !== allFileCounts($original) || sortedLineHits($original) !== sortedLineHits($forward) || sortedLineHits($original) !== sortedLineHits($reverse)) throw new RuntimeException('Native merge changed functional metrics or real line hits');
$receipt = json_decode(file_get_contents(dirname($argv[3]) . '/bootstrap-receipts/declarations.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($receipt['dependencies'] as $identity) if ($identity['path'] === 'src/Helpers/Numbers.php') $guards = $identity;
$lines = $forward->getData(true)->lineCoverage()[$numbers];
if (count($lines[$guards['guardLine']]) !== 1 || $lines[$guards['exitLine']] !== []) throw new RuntimeException('Real declaration guard or uncovered exit lost');
$negative = [];
$negative['method-node'] = rejectFunctionalMutation($original, $numbers, 'MercadoPago\Woocommerce\Helpers\Numbers->format', 'node');
$negative['method-path-id'] = rejectFunctionalMutation($original, $numbers, 'MercadoPago\Woocommerce\Helpers\Numbers->calculateByCurrency', 'path-id');
// A real named main function is counted by File and remains strictly protected.
$source = $argv[5];
$filter = new SebastianBergmann\CodeCoverage\Filter;
$filter->includeFile($source);
$global = new SebastianBergmann\CodeCoverage\CodeCoverage((new SebastianBergmann\CodeCoverage\Driver\Selector)->forLineAndPathCoverage($filter), $filter);
$global->start('native-global:true'); require $source;
if (CoverageDiagnostic\main(true) !== 'accepted') throw new RuntimeException('Real true branch assertion failed');
$global->stop();
$global->start('native-global:false');
if (CoverageDiagnostic\main(false) !== 'rejected') throw new RuntimeException('Real false branch assertion failed');
$global->stop();
$node = fileNode($global, $source);
$globalMetrics = counts($node);
if (array_keys($node->functions()) !== ['CoverageDiagnostic\main'] || $globalMetrics['functions'] !== [1, 1] || $globalMetrics['branches'] !== [3, 3] || $globalMetrics['paths'] !== [2, 2]) throw new RuntimeException('Real named main function was not measured');
$negative['global-node'] = rejectFunctionalMutation($global, $source, 'CoverageDiagnostic\main', 'node');
$negative['global-path-id'] = rejectFunctionalMutation($global, $source, 'CoverageDiagnostic\main', 'path-id');
echo json_encode(['realHitUnion' => $realHits, 'syntheticMainAccepted' => true, 'metricsBothOrders' => $metrics, 'lineHitsBothOrders' => true, 'guardHits' => count($lines[$guards['guardLine']]), 'exitHits' => count($lines[$guards['exitLine']]), 'namedGlobal' => ['name' => array_keys($node->functions())[0], 'metrics' => $globalMetrics], 'negatives' => $negative], JSON_THROW_ON_ERROR);
`);
  try {
    await runtime.start();
    await runtime.stage([program, helper, native, tests, globalSource, declarationReceipt]);
    await nativeCompilationProof(root, output, runtime);
    const proof = JSON.parse(await runtime.execute(['-d', 'memory_limit=768M', program, root, helper, native, tests, globalSource], {}));
    expect(proof.realHitUnion).toBe(true);
    expect(proof.syntheticMainAccepted).toBe(true);
    expect(proof.lineHitsBothOrders).toBe(true);
    expect(proof.guardHits).toBe(1);
    expect(proof.exitHits).toBe(0);
    expect(proof.namedGlobal.name).toBe('CoverageDiagnostic\\main');
    expect(proof.namedGlobal.metrics.functions).toEqual([1, 1]);
    expect(proof.namedGlobal.metrics.branches).toEqual([3, 3]);
    expect(proof.namedGlobal.metrics.paths).toEqual([2, 2]);
    expect(Object.keys(proof.negatives)).toEqual(['method-node', 'method-path-id', 'global-node', 'global-path-id']);
    for (const message of Object.values(proof.negatives)) expect(message).toContain('graph mismatch');
    for (const [index, file] of [native, tests].entries()) expect(fs.readFileSync(file)).toEqual(originalBytes[index]);
    console.info('Native graph scope proof:', JSON.stringify(proof));
  } finally {
    await runtime.close();
  }
}

test('generic profile requires explicit absence of Woo identity, namespace and partial bootstrap sources', () => {
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'php-profile-'));
  try {
    fs.mkdirSync(path.join(root, 'src/Helpers'), { recursive: true });
    const manifest = value => fs.writeFileSync(path.join(root, 'composer.json'), JSON.stringify(value));
    manifest({ name: 'coverage-fixture/generic' });
    expect(phpProfile(root, 'generic', recipe, []).profile).toBe('generic');
    expect(() => phpProfile(root, 'woo', recipe, [])).toThrow('Composer identity');
    manifest({ name: recipe.composerName });
    expect(() => phpProfile(root, 'generic', recipe, [])).toThrow('rejects WooCommerce');
    manifest({ name: 'coverage-fixture/generic', autoload: { 'psr-4': { [recipe.namespace]: 'src' } } });
    expect(() => phpProfile(root, 'generic', recipe, [])).toThrow('rejects WooCommerce');
    manifest({ name: 'coverage-fixture/generic' });
    fs.writeFileSync(path.join(root, 'src/Helpers/Numbers.php'), '<?php');
    expect(() => phpProfile(root, 'generic', recipe, [])).toThrow('partial sources');
    fs.unlinkSync(path.join(root, 'src/Helpers/Numbers.php'));
    fs.writeFileSync(path.join(root, 'src/Helpers/Other.php'), '<?php namespace MercadoPago\\Woocommerce\\Helpers;');
    expect(() => phpProfile(root, 'generic', recipe, ['src/Helpers/Other.php'])).toThrow('partial sources');
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
  }
});

const phpTest = process.env.COVERAGE_PHP_DOCKER_IMAGE ? test : test.skip;
phpTest('declaration syntax accepts only strict types and empty exit forms without executing untrusted statements', async () => {
  const { DockerPhp } = require('../../../../scripts/coverage/docker-php');
  const { run } = require('../../../../scripts/coverage/process');
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'php-declaration-syntax-'));
  const root = path.join(directory, 'source');
  const output = path.join(directory, 'output');
  fs.mkdirSync(path.join(root, 'src/Helpers'), { recursive: true });
  fs.symlinkSync(path.join(ROOT, 'vendor'), path.join(root, 'vendor'), 'dir');
  const commands = [];
  const execute = async (command, args, cwd, env = {}) => {
    const result = await run(command, args, { cwd, env, timeout: 30000 });
    commands.push({ command, args, durationMs: result.durationMs });
    return result.stdout.trim();
  };
  const runtime = new DockerPhp(process.env.COVERAGE_PHP_DOCKER_IMAGE, root, output, execute);
  const helper = path.join(ROOT, 'scripts/coverage/php-bootstrap.php');
  const program = path.join(directory, 'declaration-syntax.php');
  fs.writeFileSync(program, String.raw`<?php
require $argv[1] . '/vendor/autoload.php';
require $argv[2];
$cases = [
    'bare-exit' => ['', 'exit;'],
    'parenthesized-exit' => ['', 'exit();'],
    'strict-types' => ['declare(strict_types=1);', 'exit;'],
    'strict-types-parenthesized-exit' => ['declare(strict_types=1);', 'exit();'],
    'exit-payload' => ['', 'exit(1);'],
    'exit-null-payload' => ['', 'exit(null);'],
    'extra-guard-statement' => ['', 'file_put_contents(__DIR__ . "/executed", "unsafe"); exit;'],
    'top-level-execution' => ['', 'exit;', 'file_put_contents(__DIR__ . "/executed", "unsafe");'],
    'malformed-syntax' => ['declare(strict_types=1;', 'exit;'],
    'weak-types' => ['declare(strict_types=0);', 'exit;'],
    'ticks' => ['declare(ticks=1);', 'exit;'],
    'declare-body' => ['declare(strict_types=1) {}', 'exit;'],
    'multiple-directives' => ['declare(strict_types=1, ticks=1);', 'exit;'],
];
$results = [];
foreach ($cases as $name => [$header, $exit]) {
    $relative = 'src/Helpers/' . $name . '.php';
    $file = $argv[1] . '/' . $relative;
    if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0700, true)) throw new RuntimeException('Cannot create private fixture directory');
    $source = "<?php\n" . $header . "\nnamespace SyntaxFixture;" . ($cases[$name][2] ?? '') . "\nif (!defined('ABSPATH')) {\n    " . $exit . "\n}\nclass Sample {}\n";
    file_put_contents($file, $source);
    $before = hash_file('sha256', $file);
    try {
        $guard = coverageBootstrapDeclaration(['root' => $argv[1]], ['path' => $relative, 'class' => 'SyntaxFixture\\Sample', 'role' => 'guarded']);
        $results[$name] = ['accepted' => true, 'guard' => $guard];
    } catch (Throwable $failure) {
        $results[$name] = ['accepted' => false, 'error' => $failure->getMessage()];
    }
    if ($before !== hash_file('sha256', $file)) throw new RuntimeException('Validator changed source bytes');
}
if (file_exists(dirname($file) . '/executed')) throw new RuntimeException('Validator executed untrusted source');
echo json_encode(['php' => PHP_VERSION, 'xdebug' => phpversion('xdebug'), 'results' => $results], JSON_THROW_ON_ERROR);
`);
  let observation;
  try {
    await runtime.start();
    await runtime.stage([helper, program]);
    observation = JSON.parse(await execute('docker', ['exec', runtime.cid, 'php', program, root, helper], root));
    expect(observation.php).toBe('7.4.33');
    expect(observation.xdebug).toBe('3.1.6');
    const accepted = Object.entries(observation.results).filter(([, value]) => value.accepted).map(([name]) => name);
    expect(accepted).toEqual(['bare-exit', 'parenthesized-exit', 'strict-types', 'strict-types-parenthesized-exit']);
    expect(observation.results['top-level-execution'].error).toContain('Stmt_Expression at line 3');
    expect(observation.results['malformed-syntax'].error).toMatch(/cannot parse declaration source: src\/Helpers\/malformed-syntax.php at line [1-9][0-9]*/);
    for (const result of Object.values(observation.results).filter(value => value.accepted)) {
      expect(result.guard).toEqual({ guardLine: 4, exitLine: 5 });
    }
    for (const [name, result] of Object.entries(observation.results).filter(([, value]) => !value.accepted)) {
      expect(result.error).toContain(`src/Helpers/${name}.php`);
      expect(result.error).toMatch(/inspect|review/i);
      expect(result.error).not.toContain(root);
    }
  } finally {
    await runtime.close();
    if (process.env.COVERAGE_PROOF_OUTPUT_DIR) {
      const proof = path.join(process.env.COVERAGE_PROOF_OUTPUT_DIR, 'declaration-syntax');
      fs.mkdirSync(proof, { recursive: true });
      fs.copyFileSync(program, path.join(proof, path.basename(program)));
      fs.writeFileSync(path.join(proof, 'result.json'), JSON.stringify({ observation, commands, cid: runtime.cid }, null, 2));
    }
    fs.rmSync(directory, { recursive: true, force: true });
  }
}, 120000);

phpTest('native Woo declarations preserve guards and subprocess coverage, tolerate header comments, and reject a real functional mutation', async () => {
  const root = fs.mkdtempSync(path.join(ROOT, 'coverage/php-bootstrap-'));
  const output = fs.mkdtempSync(path.join(os.tmpdir(), 'php-bootstrap-output-'));
  const policy = require('../../../../coverage-policy.json');
  try {
    for (const group of Object.values(policy.php)) if (group.path !== './') fs.mkdirSync(path.join(root, group.path), { recursive: true });
    for (const name of ['composer.json', 'composer.lock', 'package-lock.json']) fs.copyFileSync(path.join(ROOT, name), path.join(root, name));
    fs.symlinkSync(path.join(ROOT, 'vendor'), path.join(root, 'vendor'), 'dir');
    for (const name of [...recipe.dependencies, ...recipe.closure, ...recipe.interfaces, ...recipe.parents.filter(item => !item.internal), { path: 'src/Order/OrderStatus.php' }].map(item => item.path)) {
      fs.mkdirSync(path.dirname(path.join(root, name)), { recursive: true });
      fs.copyFileSync(path.join(ROOT, name), path.join(root, name));
    }
    fs.writeFileSync(path.join(root, 'tests/bootstrap.php'), '<?php\ndefine("ABSPATH", __DIR__);\n$loader = require __DIR__ . "/../vendor/autoload.php";\n$loader->setPsr4("MercadoPago\\\\Woocommerce\\\\", __DIR__ . "/../src");\nrequire __DIR__ . "/Mocks/WoocommerceBlocksStub.php";\nrequire __DIR__ . "/Mocks/WoocommerceGatewayStub.php";\n');
    fs.writeFileSync(path.join(root, 'tests/NativeDeclarationTest.php'), '<?php\nclass NativeDeclarationTest extends \\PHPUnit\\Framework\\TestCase {\n public function testOriginalFormat() { $this->assertSame(1.3, \\MercadoPago\\Woocommerce\\Helpers\\Numbers::format(1.26, 1)); }\n /**\n  * @runInSeparateProcess\n  * @preserveGlobalState disabled\n  */\n public function testRealChildFormat() { $this->assertSame(2.5, \\MercadoPago\\Woocommerce\\Helpers\\Numbers::format(2.46, 1)); }\n}\n');
    const reportFile = path.join(output, 'report.json');
    const execute = () => spawnSync(process.execPath, [path.join(ROOT, 'scripts/coverage/run.js'), '--root', root, '--language', 'php', '--collect-only', '--output-dir', output, '--report-json', reportFile, '--php-docker-image', process.env.COVERAGE_PHP_DOCKER_IMAGE], { env: { ...process.env, COVERAGE_PHP_PROFILE: 'woo' }, encoding: 'utf8', timeout: 120000 });
    let result = execute();
    expect(result.stderr).toBe('');
    expect(result.status).toBe(0);
    const first = JSON.parse(fs.readFileSync(reportFile));
    const bootstrap = first.results[0].phpBootstrap;
    expect(first.runtimes.phpProfile).toBe('woo');
    const expectedCapture = [...recipe.dependencies, ...recipe.closure].map(item => item.path).filter(file => !file.startsWith('src/Endpoints/')).sort();
    expect(bootstrap.allowlist).toEqual(expectedCapture);
    expect(expectedCapture).toHaveLength(15);
    expect(bootstrap.declarationAllowlist).toHaveLength(16);
    expect(bootstrap.outsideMetrics).toEqual(['src/Endpoints/CheckoutValidation.php', ...recipe.interfaces.map(item => item.path)]);
    expect(first.results[0].files.map(item => item.path)).not.toContain('src/Endpoints/CheckoutValidation.php');
    const classOnly = bootstrap.declaration.closure.find(item => item.path === 'src/Gateways/AbstractGateway.php');
    expect(classOnly).toMatchObject({ measured: true, role: 'class-only' });
    expect(classOnly).not.toHaveProperty('guardLine');
    expect(classOnly).not.toHaveProperty('exitLine');
    expect(bootstrap.declaration.guardHits).toBe(14);
    expect(bootstrap.declaration.uncoveredExits).toBe(14);
    expect(bootstrap.declaration.functionalHits).toBe(0);
    expect(bootstrap.receipts.filter(item => !item.capture).length).toBeGreaterThanOrEqual(3);
    const numbers = first.results[0].files.find(item => item.path === 'src/Helpers/Numbers.php');
    const identity = bootstrap.declaration.dependencies.find(item => item.path === numbers.path);
    expect(numbers.lines[identity.guardLine]).toBeGreaterThan(0);
    expect(numbers.lines[identity.exitLine]).toBe(0);
    if (process.env.COVERAGE_PROOF_OUTPUT_DIR) {
      const evidence = process.env.COVERAGE_PROOF_OUTPUT_DIR;
      for (const name of ['bootstrap-declarations.php', 'bootstrap-context.json', 'php-bootstrap-summary.json', 'partition-0.php', 'partition-0.xml']) fs.copyFileSync(path.join(output, 'php', name), path.join(evidence, name));
      fs.copyFileSync(reportFile, path.join(evidence, 'normalized-report.json'));
    }
    await nativePathProof(root, output);
    const file = path.join(root, numbers.path);
    const original = fs.readFileSync(file, 'utf8');
    fs.writeFileSync(file, original.replace('<?php', '<?php\ndeclare(strict_types=1);').replace('    exit;', '    exit();'));
    result = execute();
    expect(result.stderr).toBe('');
    expect(result.status).toBe(0);
    const moved = JSON.parse(fs.readFileSync(reportFile));
    const movedIdentity = moved.results[0].phpBootstrap.declaration.dependencies.find(item => item.path === numbers.path);
    expect(movedIdentity.guardLine).toBe(identity.guardLine + 1);
    expect(movedIdentity.exitLine).toBe(identity.exitLine + 1);
    expect(moved.results[0].files.find(item => item.path === numbers.path).metrics).toEqual(numbers.metrics);
    expect(moved.fingerprints.configuration).toBe(first.fingerprints.configuration);
    fs.writeFileSync(file, original.replace("return (float) number_format($value, $decimals, $separator, '');", 'return 999.0;'));
    result = execute();
    expect(result.status).not.toBe(0);
    expect(result.stderr).toContain('Failed asserting');
    expect(fs.existsSync(reportFile)).toBe(false);
  } finally {
    fs.rmSync(root, { recursive: true, force: true });
    fs.rmSync(output, { recursive: true, force: true });
  }
}, 420000);

(process.env.COVERAGE_PHP_DOCKER_IMAGE ? test : test.skip)('Credits copy compilation preserves native graphs and real declaration guards across both orders', async () => {
  const { DockerPhp } = require('../../../../scripts/coverage/docker-php');
  const { run } = require('../../../../scripts/coverage/process');
  const { sourceInventory } = require('../../../../scripts/coverage/report');
  const { createHash } = require('crypto');
  const directory = fs.mkdtempSync(path.join(os.tmpdir(), 'php-credits-context-'));
  const root = path.join(directory, 'source');
  const output = path.join(directory, 'output');
  const bootstrapHelper = path.join(ROOT, 'scripts/coverage/php-bootstrap.php');
  const graphHelper = path.join(ROOT, 'scripts/coverage/php-shapes.php');
  const policy = require('../../../../coverage-policy.json');
  const targetPaths = ['src/Gateways/CreditsGateway.php', 'src/Helpers/CreditsPresentation.php'];
  const observations = {};
  const commands = [];
  const cleanup = [];
  const sha = value => createHash('sha256').update(value).digest('hex');
  const execute = async (command, args, cwd, env = {}) => {
    const result = await run(command, args, { cwd, env, timeout: 30000 });
    commands.push({ command, args, durationMs: result.durationMs });
    return result.stdout.trim();
  };
  const runtime = new DockerPhp(process.env.COVERAGE_PHP_DOCKER_IMAGE, root, output, execute);
  const program = path.join(directory, 'credits-native-proof.php');
  const contextFiles = {};
  const sources = [...recipe.dependencies, ...recipe.closure, ...recipe.interfaces, ...recipe.parents.filter(item => !item.internal)].map(item => item.path);
  const sourceFiles = [...new Set([...sources, ...targetPaths, 'src/WoocommerceMercadoPago.php'])];
  const sourceHashes = Object.fromEntries(sourceFiles.map(file => [file, sha(fs.readFileSync(path.join(ROOT, file)))]));
  try {
    fs.mkdirSync(path.join(root, 'tests'), { recursive: true });
    fs.mkdirSync(output);
    for (const group of Object.values(policy.php)) if (group.path !== './') fs.mkdirSync(path.join(root, group.path), { recursive: true });
    for (const file of [...sourceFiles, 'composer.json', 'composer.lock', 'tests/Mocks/WoocommerceGatewayStub.php', 'tests/Mocks/WoocommerceBlocksStub.php']) {
      fs.mkdirSync(path.dirname(path.join(root, file)), { recursive: true });
      fs.copyFileSync(path.join(ROOT, file), path.join(root, file));
    }
    fs.symlinkSync(path.join(ROOT, 'vendor'), path.join(root, 'vendor'), 'dir');
    fs.copyFileSync(path.join(ROOT, 'tests/bootstrap.php'), path.join(root, 'tests/original-bootstrap.php'));
    fs.writeFileSync(path.join(root, 'tests/bootstrap.php'), '<?php $loader = require __DIR__ . "/../vendor/autoload.php"; $loader->setPsr4("MercadoPago\\\\Woocommerce\\\\", __DIR__ . "/../src"); require __DIR__ . "/original-bootstrap.php";');
    for (const mode of ['historical-thirteen', 'current']) {
      const selected = mode === 'current' ? recipe : { ...recipe, dependencies: recipe.dependencies.filter(item => item.path !== targetPaths[1]) };
      const owned = path.join(output, mode);
      fs.mkdirSync(owned);
      const recipeFile = path.join(directory, `${mode}-recipe.json`);
      const recipeBytes = JSON.stringify(selected, null, 2);
      fs.writeFileSync(recipeFile, recipeBytes);
      const inventory = sourceInventory(root, 'php', policy.php);
      const declarations = [...selected.dependencies, ...selected.closure].map(item => item.path);
      contextFiles[mode] = path.join(directory, `${mode}-context.json`);
      fs.writeFileSync(contextFiles[mode], JSON.stringify({ profile: 'woo', root, directory: owned, recipe: selected, recipeSha256: sha(recipeBytes), sourceInventory: inventory, captureWhitelist: inventory.filter(file => declarations.includes(file)) }));
    }
    fs.writeFileSync(program, String.raw`<?php
require $argv[1] . '/vendor/autoload.php'; require $argv[2]; require $argv[3];
if (PHP_VERSION !== '7.4.33' || phpversion('xdebug') !== '3.1.6') throw new RuntimeException('Expected the real approved PHP/Xdebug runtime');
$context = json_decode(file_get_contents($argv[4]), true, 512, JSON_THROW_ON_ERROR);
$operation = $argv[5]; $order = $argv[6]; $raw = $argv[7];
if ($operation === 'capture') { echo json_encode(coverageBootstrapLoad($context, true), JSON_THROW_ON_ERROR); exit(0); }
if ($operation === 'compare') {
    $known = []; coverageAssertCompatible($known, require $raw, $raw);
    try { coverageAssertCompatible($known, require $argv[8], $argv[8]); $error = null; }
    catch (RuntimeException $failure) { if (strpos($failure->getMessage(), 'graph mismatch') === false) throw $failure; $error = $failure->getMessage(); }
    echo json_encode(['error' => $error], JSON_THROW_ON_ERROR); exit(0);
}
$worker = coverageBootstrapLoad($context, false);
$gateway = 'MercadoPago\\Woocommerce\\Gateways\\CreditsGateway';
$presentation = 'MercadoPago\\Woocommerce\\Helpers\\CreditsPresentation';
if (class_exists($gateway, false)) throw new RuntimeException('Consumer unexpectedly loaded');
$presentationKnown = class_exists($presentation, false);
$filter = new SebastianBergmann\CodeCoverage\Filter;
foreach (['src/Gateways/CreditsGateway.php', 'src/Helpers/CreditsPresentation.php'] as $file) $filter->includeFile($context['root'] . '/' . $file);
$coverage = new SebastianBergmann\CodeCoverage\CodeCoverage((new SebastianBergmann\CodeCoverage\Driver\Selector)->forLineAndPathCoverage($filter), $filter);
$id = 'credits-native-order:' . $order;
$coverage->start($id);
foreach ($order === 'consumer-first' ? [$gateway, $presentation] : [$presentation, $gateway] as $class) if (!class_exists($class, true)) throw new RuntimeException('Declaration missing');
$coverage->stop();
$sidecar = require $context['directory'] . '/bootstrap-declarations.php';
$known = []; coverageAssertCompatible($known, $sidecar, 'declaration'); coverageAssertCompatible($known, $coverage, 'worker');
$sidecar->merge($coverage);
$functionalHits = 0;
foreach ($sidecar->getData(true)->functionCoverage() as $functions) foreach ($functions as $name => $function) {
    if ($name === '{main}') continue;
    foreach ($function['branches'] as $branch) $functionalHits += count($branch['hit']);
    foreach ($function['paths'] as $path) $functionalHits += count($path['hit']);
}
if ($functionalHits !== 0 || xdebug_code_coverage_started()) throw new RuntimeException('Unexpected functional execution');
$guardHits = [];
foreach (array_keys($sidecar->getData(true)->lineCoverage()) as $file) {
    if (substr($file, -strlen('/src/Gateways/AbstractGateway.php')) === '/src/Gateways/AbstractGateway.php') continue;
    $position = coverageBootstrapGuard($file); $lines = $sidecar->getData(true)->lineCoverage()[$file];
    if (!is_array($lines[$position['guardLine']]) || !$lines[$position['guardLine']] || $lines[$position['exitLine']] !== []) throw new RuntimeException('Real guards/exits were lost');
    $guardHits[$file] = $position + ['tests' => $lines[$position['guardLine']], 'exitHits' => 0];
}
foreach ($sidecar->getTests() as $test) if ($test['fromTestcase'] !== false) throw new RuntimeException('Declaration is not a PHPUnit testcase');
(new SebastianBergmann\CodeCoverage\Report\PHP)->process($sidecar, $raw);
$key = $context['root'] . '/src/Gateways/CreditsGateway.php|' . $gateway . '->prepareCreditsCopy';
echo json_encode(['shape' => coverageGraphShapes($sidecar)[$key], 'functionalHits' => $functionalHits, 'guards' => $guardHits, 'presentationKnown' => $presentationKnown, 'worker' => $worker, 'tests' => $sidecar->getTests(), 'rawSha256' => hash_file('sha256', $raw)], JSON_THROW_ON_ERROR);
`);
    await runtime.start();
    await runtime.stage([bootstrapHelper, graphHelper, program, ...Object.values(contextFiles)]);
    for (const mode of Object.keys(contextFiles)) {
      const owned = path.join(output, mode);
      await execute('docker', ['exec', runtime.cid, 'mkdir', '-p', owned], root);
      observations[mode] = { declaration: JSON.parse(await runtime.execute([program, root, bootstrapHelper, graphHelper, contextFiles[mode], 'capture', 'none', 'none'], { COVERAGE_SOURCE_ROOT: root })) };
      await runtime.retrieve(path.join(owned, 'bootstrap-declarations.php'));
      for (const order of ['consumer-first', 'dependency-first']) {
        const raw = path.join(owned, `${order}.php`);
        observations[mode][order] = JSON.parse(await runtime.execute([program, root, bootstrapHelper, graphHelper, contextFiles[mode], 'worker', order, raw], { COVERAGE_SOURCE_ROOT: root }));
        await runtime.retrieve(raw);
      }
      observations[mode].comparison = JSON.parse(await runtime.execute([program, root, bootstrapHelper, graphHelper, contextFiles[mode], 'compare', 'none', path.join(owned, 'consumer-first.php'), path.join(owned, 'dependency-first.php')], { COVERAGE_SOURCE_ROOT: root }));
    }
    const historical = observations['historical-thirteen'];
    const current = observations.current;
    expect(historical.comparison.error).toContain('CreditsGateway->prepareCreditsCopy');
    expect(historical['consumer-first'].shape).not.toEqual(historical['dependency-first'].shape);
    expect(current.comparison.error).toBeNull();
    expect(current['consumer-first'].shape).toEqual(current['dependency-first'].shape);
    expect(current['consumer-first'].shape).toEqual(historical['dependency-first'].shape);
    expect(current.declaration.guardHits).toBe(14);
    expect(current.declaration.uncoveredExits).toBe(14);
    for (const [mode, group] of Object.entries(observations)) {
      expect(group.declaration.functionalHits).toBe(0);
      for (const order of ['consumer-first', 'dependency-first']) {
        expect(group[order].functionalHits).toBe(0);
        expect(Object.keys(group[order].guards)).toHaveLength(15);
        expect(group[order].presentationKnown).toBe(mode === 'current');
        expect(group[order].worker.reservedAbsent).toEqual(recipe.reservedClasses);
      }
    }
    for (const [file, hash] of Object.entries(sourceHashes)) expect(sha(fs.readFileSync(path.join(ROOT, file)))).toBe(hash);
  } finally {
    await runtime.close();
    let absent;
    if (runtime.cid) {
      absent = spawnSync('docker', ['inspect', runtime.cid], { encoding: 'utf8', timeout: 15000 });
      cleanup.push({ cid: runtime.cid, status: absent.status, stderr: absent.stderr });
    }
    if (process.env.COVERAGE_PROOF_OUTPUT_DIR) {
      const evidence = process.env.COVERAGE_PROOF_OUTPUT_DIR;
      fs.mkdirSync(evidence, { recursive: true });
      fs.cpSync(output, path.join(evidence, 'native'), { recursive: true });
      for (const file of [program, ...Object.values(contextFiles), ...Object.keys(contextFiles).map(mode => path.join(directory, `${mode}-recipe.json`))]) fs.copyFileSync(file, path.join(evidence, path.basename(file)));
      fs.writeFileSync(path.join(evidence, 'credits-compilation-observations.json'), JSON.stringify({ observations, commands, sourceHashes, cleanup }, null, 2));
    }
    if (absent) {
      expect(absent.status).not.toBe(0);
      expect(absent.stderr).toMatch(/No such (container|object)/i);
    }
    fs.rmSync(directory, { recursive: true, force: true });
  }
}, 180000);

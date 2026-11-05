<?php
// Development-only deterministic declaration context. PHPUnit owns test coverage.
function coverageBootstrapGuard(string $file, ?string $relative = null): array
{
    $tokens = array_values(array_filter(token_get_all(file_get_contents($file)), function ($token) {
        return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    }));
    $found = [];
    $depth = 0;
    foreach ($tokens as $index => $token) {
        if (is_array($token) && $token[0] === T_CLASS && $depth === 0) {
            break;
        }
        if (is_array($token) && $token[0] === T_IF && $depth === 0) {
            $candidate = array_slice($tokens, $index, 14);
            $text = array_map(function ($item) { return is_array($item) ? $item[1] : $item; }, $candidate);
            if (count($candidate) >= 12 && $text[0] === 'if' && $text[1] === '(' && $text[2] === '!' && $text[3] === 'defined' && $text[4] === '(' && in_array($text[5], ["'ABSPATH'", '"ABSPATH"'], true) && (array_slice($text, 6, 6) === [')', ')', '{', 'exit', ';', '}'] || array_slice($text, 6, 8) === [')', ')', '{', 'exit', '(', ')', ';', '}']) && is_array($candidate[9]) && $candidate[9][0] === T_EXIT) {
                $found[] = ['guardLine' => $token[2], 'exitLine' => $candidate[9][2]];
            }
        }
        if ($token === '{') {
            ++$depth;
        } elseif ($token === '}') {
            --$depth;
        }
    }
    if (count($found) !== 1) {
        throw new RuntimeException('PHP coverage requires one strict top-level ABSPATH/exit declaration guard: ' . ($relative ?? basename($file)) . ' (expected Stmt_If with an empty Expr_Exit). Inspect the original guard; only exit; and exit() without a payload are supported.');
    }
    return $found[0];
}

function coverageBootstrapIdentity(array $context, string $class, string $relative): array
{
    $reflection = new ReflectionClass($class);
    $file = realpath($reflection->getFileName());
    if ($file !== realpath($context['root'] . '/' . $relative)) {
        throw new RuntimeException('PHP coverage bootstrap class outside snapshot identity: ' . $class);
    }
    return ['class' => $class, 'path' => $relative, 'sha256' => hash_file('sha256', $file)];
}

function coverageBootstrapDeclaration(array $context, array $item): array
{
    $file = $context['root'] . '/' . $item['path'];
    $guard = $item['role'] === 'guarded' ? coverageBootstrapGuard($file, $item['path']) : null;
    if (!in_array($item['role'], ['guarded', 'class-only'], true)) {
        throw new RuntimeException('Unknown PHP declaration role: ' . $item['path'] . '. Review the explicit declaration recipe and source identity; do not extend it automatically.');
    }
    try {
        $ast = (new PhpParser\ParserFactory)->create(PhpParser\ParserFactory::PREFER_PHP7)->parse(file_get_contents($file));
    } catch (PhpParser\Error $failure) {
        throw new RuntimeException('PHP bootstrap cannot parse declaration source: ' . $item['path'] . ' at line ' . $failure->getStartLine() . '. Inspect the original PHP syntax before collecting coverage.');
    }
    $statements = [];
    foreach ($ast as $node) {
        if ($node instanceof PhpParser\Node\Stmt\Declare_ && $node->stmts === null && count($node->declares) === 1 && $node->declares[0]->key->toString() === 'strict_types' && $node->declares[0]->value instanceof PhpParser\Node\Scalar\LNumber && $node->declares[0]->value->value === 1) {
            // The original autoloaded file retains this directive and its semantics.
            continue;
        }
        if ($node instanceof PhpParser\Node\Stmt\Namespace_) {
            $statements = array_merge($statements, $node->stmts);
        } else {
            $statements[] = $node;
        }
    }
    $declarations = 0;
    $guards = 0;
    foreach ($statements as $node) {
        if ($node instanceof PhpParser\Node\Stmt\Use_ || $node instanceof PhpParser\Node\Stmt\GroupUse || $node instanceof PhpParser\Node\Stmt\Nop) {
            continue;
        }
        if ($node instanceof PhpParser\Node\Stmt\ClassLike && $node->name && substr($item['class'], strrpos($item['class'], '\\') + 1) === $node->name->toString()) {
            ++$declarations;
        } elseif ($guard && $node instanceof PhpParser\Node\Stmt\If_ && $node->getStartLine() === $guard['guardLine'] && !$node->else && !$node->elseifs) {
            ++$guards;
        } else {
            throw new RuntimeException('PHP bootstrap requires declaration-only top level: ' . $item['path'] . ' (' . $node->getType() . ' at line ' . $node->getStartLine() . '). Inspect the original statement; only standalone declare(strict_types=1), imports, the validated guard and the named declaration are supported.');
        }
    }
    if ($declarations !== 1 || $guards !== ($guard ? 1 : 0)) {
        throw new RuntimeException('PHP bootstrap declaration role differs from source: ' . $item['path'] . '. Review the named declaration and validated guard against the explicit recipe; do not extend it automatically.');
    }
    return $guard ?? [];
}

function coverageBootstrapReserved(array $recipe): void
{
    foreach ($recipe['reservedClasses'] as $class) {
        if (class_exists($class, false)) {
            throw new RuntimeException('PHP bootstrap loaded a reserved Mockery alias/overload class: ' . $class);
        }
    }
}

function coverageBootstrapLoad(array $context, bool $capture): array
{
    $root = $context['root'];
    if (xdebug_code_coverage_started()) {
        throw new RuntimeException('PHP coverage bootstrap started inside active test coverage');
    }
    require_once $root . '/vendor/autoload.php';
    require $root . '/tests/bootstrap.php';
    if (xdebug_code_coverage_started()) {
        throw new RuntimeException('Original PHP bootstrap unexpectedly activated coverage');
    }
    $recipe = $context['recipe'];
    coverageBootstrapReserved($recipe);
    $declarations = array_merge($recipe['dependencies'], $recipe['closure']);
    $expectedPaths = array_column($declarations, 'path');
    $whitelist = array_values(array_intersect($context['sourceInventory'], $expectedPaths));
    if ($whitelist !== $context['captureWhitelist'] || !$whitelist) {
        throw new RuntimeException('PHP native declaration capture whitelist differs from measured inventory');
    }
    $record = ['schemaVersion' => 1, 'profile' => $context['profile'], 'recipeSha256' => $context['recipeSha256'], 'pid' => getmypid(), 'capture' => $capture, 'dependencies' => [], 'closure' => [], 'interfaces' => [], 'parents' => [], 'captureWhitelist' => $whitelist, 'reservedAbsent' => $recipe['reservedClasses'], 'coverageBefore' => false];
    $guards = [];
    foreach (array_merge($declarations, $recipe['interfaces']) as $item) {
        $guards[$item['path']] = coverageBootstrapDeclaration($context, $item);
        if ($capture && (class_exists($item['class'], false) || interface_exists($item['class'], false))) {
            throw new RuntimeException('PHP coverage declaration was already loaded: ' . $item['class']);
        }
    }
    $classesBefore = get_declared_classes();
    $interfacesBefore = get_declared_interfaces();
    if ($capture) {
        $filter = new SebastianBergmann\CodeCoverage\Filter;
        foreach ($whitelist as $relative) {
            $filter->includeFile($root . '/' . $relative);
        }
        $coverage = new SebastianBergmann\CodeCoverage\CodeCoverage((new SebastianBergmann\CodeCoverage\Driver\Selector)->forLineAndPathCoverage($filter), $filter);
        $coverage->start($recipe['contextId']);
    }
    foreach ($recipe['dependencies'] as $item) {
        if (!class_exists($item['class'], true)) {
            throw new RuntimeException('PHP coverage bootstrap class absent: ' . $item['class']);
        }
    }
    if ($capture) {
        $coverage->stop();
    }
    $record['coverageAfter'] = xdebug_code_coverage_started();
    if ($record['coverageAfter']) {
        throw new RuntimeException('PHP coverage bootstrap left active coverage');
    }
    coverageBootstrapReserved($recipe);
    foreach (['dependencies', 'closure', 'interfaces'] as $kind) {
        foreach ($recipe[$kind] as $item) {
            if (!class_exists($item['class'], false) && !interface_exists($item['class'], false)) {
                throw new RuntimeException('PHP declaration closure was not loaded: ' . $item['class']);
            }
            $record[$kind][] = array_merge(coverageBootstrapIdentity($context, $item['class'], $item['path']), ['role' => $item['role'], 'measured' => in_array($item['path'], $whitelist, true)], $guards[$item['path']]);
        }
    }
    $expected = array_column($declarations, 'class');
    $added = array_filter(array_diff(get_declared_classes(), $classesBefore), function ($name) use ($recipe) {
        return strpos($name, $recipe['namespace']) === 0;
    });
    if (array_diff($added, $expected)) {
        throw new RuntimeException('PHP coverage bootstrap loaded an unexpected application class');
    }
    $allowedInterfaces = array_column($recipe['interfaces'], 'class');
    foreach (array_diff(get_declared_interfaces(), $interfacesBefore) as $name) {
        if (strpos($name, $recipe['namespace']) === 0 && !in_array($name, $allowedInterfaces, true)) {
            throw new RuntimeException('PHP coverage bootstrap loaded an unexpected interface');
        }
    }
    foreach ($recipe['parents'] as $item) {
        if (!class_exists($item['class'], false)) {
            throw new RuntimeException('PHP bootstrap parent is absent: ' . $item['class']);
        }
        if (!empty($item['internal'])) {
            if (!(new ReflectionClass($item['class']))->isInternal()) {
                throw new RuntimeException('PHP bootstrap internal parent identity changed');
            }
            $record['parents'][] = ['class' => $item['class'], 'internal' => true];
        } else {
            $record['parents'][] = coverageBootstrapIdentity($context, $item['class'], $item['path']);
        }
    }
    // Preserve the original receipt fields while providing the complete closure.
    $record['interface'] = coverageBootstrapIdentity($context, $recipe['interface']['class'], $recipe['interface']['path']);
    $record['parent'] = coverageBootstrapIdentity($context, $recipe['parent']['class'], $recipe['parent']['path']);
    if ($capture) {
        $data = $coverage->getData(true);
        $lines = $data->lineCoverage();
        $actualPaths = array_keys($lines);
        sort($actualPaths);
        $expectedFiles = array_map(function ($relative) use ($root) { return realpath($root . '/' . $relative); }, $whitelist);
        sort($expectedFiles);
        if ($actualPaths !== $expectedFiles || $coverage->filter()->files() !== $expectedFiles) {
            throw new RuntimeException('PHP declaration capture changed its measured source universe');
        }
        $guardHits = 0;
        foreach ($declarations as $item) {
            if (!in_array($item['path'], $whitelist, true)) {
                continue; // Identity outside the measured filter is not a native hit claim.
            }
            $file = realpath($root . '/' . $item['path']);
            $actual = $lines[$file];
            $guard = $guards[$item['path']];
            if ($guard) {
                if (($actual[$guard['guardLine']] ?? null) !== [$recipe['contextId']] || !array_key_exists($guard['exitLine'], $actual) || $actual[$guard['exitLine']] !== []) {
                    throw new RuntimeException('PHP declaration capture lost its real guard or uncovered exit: ' . $item['path']);
                }
                ++$guardHits;
            }
            foreach ($actual as $line => $hits) {
                if (($guard['guardLine'] ?? null) !== $line && $hits) {
                    throw new RuntimeException('PHP declaration capture executed functional code: ' . $item['path']);
                }
            }
            foreach ($data->functionCoverage()[$file] ?? [] as $name => $function) {
                if ($name === '{main}') {
                    continue;
                }
                foreach (['branches', 'paths'] as $kind) {
                    foreach ($function[$kind] as $node) {
                        if ($node['hit']) {
                            throw new RuntimeException('PHP declaration capture executed a method graph: ' . $name);
                        }
                    }
                }
            }
        }
        $tests = $coverage->getTests();
        if (array_keys($tests) !== [$recipe['contextId']] || $tests[$recipe['contextId']]['fromTestcase'] !== false) {
            throw new RuntimeException('PHP declaration context was represented as a PHPUnit testcase');
        }
        $raw = $context['directory'] . '/bootstrap-declarations.php';
        (new SebastianBergmann\CodeCoverage\Report\PHP)->process($coverage, $raw);
        $record['rawSha256'] = hash_file('sha256', $raw);
        $record['guardHits'] = $guardHits;
        $record['uncoveredExits'] = $guardHits;
        $record['functionalHits'] = 0; // Only captureWhitelist; outside identities are not measured.
    }
    $target = $context['directory'] . '/bootstrap-receipts/' . ($capture ? 'declarations' : getmypid()) . '.json';
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0777, true);
    }
    file_put_contents($target, json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    return $record;
}

if (isset($argv[1]) && $argv[1] === '--capture') {
    $context = json_decode(file_get_contents($argv[2]), true, 512, JSON_THROW_ON_ERROR);
    coverageBootstrapLoad($context, true);
}

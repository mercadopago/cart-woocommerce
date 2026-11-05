<?php
// Compare native identities before merging. No approximate matching or counter rewriting.
function coverageCanonicalGraph($value)
{
    if (!is_array($value)) {
        return $value;
    }
    if ($value && array_keys($value) !== range(0, count($value) - 1)) {
        ksort($value, SORT_STRING);
    }
    foreach ($value as &$item) {
        $item = coverageCanonicalGraph($item);
    }
    return $value;
}

function coverageGraphShapes($coverage): array
{
    $shapes = [];
    foreach ($coverage->getData(true)->functionCoverage() as $file => $functions) {
        foreach ($functions as $name => $function) {
            // File/Builder counts named functions and methods, not Xdebug's file
            // context. Keep that raw context and all line hits in the native merge.
            if ($name === '{main}') {
                continue;
            }
            foreach ($function['branches'] as &$branch) {
                unset($branch['hit'], $branch['out_hit']);
            }
            unset($branch);
            foreach ($function['paths'] as &$path) {
                unset($path['hit']);
            }
            unset($path);
            $shapes[$file . '|' . $name] = coverageCanonicalGraph($function);
        }
    }
    return $shapes;
}

function coverageAssertCompatible(array &$known, $coverage, string $input): void
{
    foreach (coverageGraphShapes($coverage) as $key => $shape) {
        // Associative-key order does not change a graph. Node/path identities and
        // ordered edge/path sequences must match exactly, including opcode bounds.
        if (isset($known[$key]) && $known[$key] !== $shape) {
            [$file, $function] = explode('|', $key, 2);
            $source = strrpos($file, '/src/');
            $display = ($source === false ? basename($file) : substr($file, $source + 1)) . '|' . $function;
            throw new RuntimeException('PHP coverage graph mismatch before native merge: ' . $display . ' in ' . basename($input) . '. Inspect the retained native graphs and declaration receipts for compilation-context differences; do not normalize IDs or extend the preload recipe automatically.');
        }
        $known[$key] = $shape;
    }
}

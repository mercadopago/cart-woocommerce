<?php
// CLI test-runtime preload: exclude PHPUnit/vendor instrumentation before compilation.
// PHPUnit still owns the final include/exclude set and report denominator.
if (PHP_SAPI === 'cli' && function_exists('xdebug_set_filter')) {
    $root = getenv('COVERAGE_SOURCE_ROOT') ?: getcwd();
    xdebug_set_filter(XDEBUG_FILTER_CODE_COVERAGE, XDEBUG_PATH_INCLUDE, [$root . '/src/']);
}
